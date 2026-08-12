<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fail-closed File 03 outbox worker.
 *
 * Event delivery is intentionally at-least-once, but database uncertainty must
 * never be reported as successful progress. Every lease/read/result persistence
 * failure is preserved as operational evidence for File 24.
 */
final class SPD_Outbox_Dispatcher {
	const MAX_ATTEMPTS = 8;

	public static function replace_legacy_hook() {
		// This cron hook is owned by File 03. Replace the legacy worker as one unit
		// so two dispatchers can never race the same lease set.
		remove_all_actions( 'spd_dispatch_outbox' );
		add_action( 'spd_dispatch_outbox', array( __CLASS__, 'dispatch' ) );
	}

	private static function record_error( $code ) {
		$record = array( 'code' => sanitize_key( $code ), 'at' => SPD_Helpers::now() );
		update_option( 'spd_last_outbox_error', $record, false );
		try {
			do_action( 'sabri_file24_outbox_failure', array_merge( array( 'owner' => 'file03' ), $record ) );
		} catch ( Throwable $e ) {
			// File 24 is an assurance consumer, never a dependency of native File 03 delivery/recovery.
		}
	}

	public static function dispatch() {
		global $wpdb;
		if ( ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::base_ready() ) {
			self::record_error( 'outbox_schema_unavailable' );
			return;
		}
		$table = SPD_DB::table( 'events' );
		$had_error = false;

		$wpdb->last_error = '';
		$reset = $wpdb->query( "UPDATE {$table} SET status='retry',lease_token='',lease_expires=NULL,available_at=UTC_TIMESTAMP(),last_error_code='lease_expired' WHERE status='processing' AND lease_expires<UTC_TIMESTAMP()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $reset || $wpdb->last_error ) { self::record_error( 'outbox_lease_reset_failed' ); return; }

		$wpdb->last_error = '';
		$ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $ids ) ) { self::record_error( 'outbox_queue_read_failed' ); return; }

		foreach ( $ids as $id ) {
			$id = absint( $id );
			$token = hash( 'sha256', SPD_Helpers::trace_id() . ':' . $id );
			$lease = gmdate( 'Y-m-d H:i:s', time() + 300 );
			$wpdb->last_error = '';
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='processing',lease_token=%s,lease_expires=%s WHERE id=%d AND status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP()", $token, $lease, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $claimed || $wpdb->last_error ) { self::record_error( 'outbox_claim_failed' ); return; }
			if ( 1 !== $claimed ) { continue; }

			$wpdb->last_error = '';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND lease_token=%s LIMIT 1", $id, $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { self::record_error( 'outbox_claim_read_failed' ); return; }
			if ( ! $row ) { $had_error = true; self::record_error( 'outbox_claim_lost' ); continue; }

			$payload = json_decode( (string) $row['payload'], true );
			$attempts = absint( $row['attempts'] ) + 1;
			if ( ! is_array( $payload ) ) {
				$had_error = true;
				if ( ! self::fail_claim( $row, $token, $attempts, 'invalid_payload' ) ) { return; }
				self::record_error( 'outbox_invalid_payload' );
				continue;
			}

			try {
				do_action( 'spd_outbox_event_v1', $row['event_name'], $payload, $row );
				do_action( 'sabri_platform_event', $row['event_name'], $payload, array( 'owner' => 'file03', 'event_uuid' => $row['event_uuid'] ) );
				$wpdb->last_error = '';
				$saved = $wpdb->update(
					$table,
					array( 'status' => 'delivered', 'attempts' => $attempts, 'delivered_at' => SPD_Helpers::now(), 'lease_token' => '', 'lease_expires' => null, 'last_error_code' => '' ),
					array( 'id' => $id, 'lease_token' => $token )
				);
				if ( false === $saved || $wpdb->last_error ) { self::record_error( 'outbox_delivery_persist_failed' ); return; }
				if ( 1 !== $saved ) { $had_error = true; self::record_error( 'outbox_delivery_lease_lost' ); continue; }
			} catch ( Throwable $exception ) {
				$had_error = true;
				$error_code = sanitize_key( get_class( $exception ) );
				if ( ! self::fail_claim( $row, $token, $attempts, $error_code ) ) { return; }
				self::record_error( 'outbox_delivery_failed' );
			}
		}

		if ( ! $had_error ) { delete_option( 'spd_last_outbox_error' ); }
		update_option( 'spd_last_outbox_run', SPD_Helpers::now(), false );
	}

	private static function fail_claim( array $row, $token, $attempts, $error_code ) {
		global $wpdb;
		$status = $attempts >= self::MAX_ATTEMPTS ? 'dead' : 'retry';
		$delay = min( HOUR_IN_SECONDS, 30 * ( 2 ** min( $attempts, 6 ) ) );
		$wpdb->last_error = '';
		$saved = $wpdb->update(
			SPD_DB::table( 'events' ),
			array( 'status' => $status, 'attempts' => $attempts, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ), 'lease_token' => '', 'lease_expires' => null, 'last_error_code' => sanitize_key( $error_code ) ),
			array( 'id' => absint( $row['id'] ), 'lease_token' => (string) $token )
		);
		if ( false === $saved || $wpdb->last_error ) { self::record_error( 'outbox_failure_persist_failed' ); return false; }
		if ( 1 !== $saved ) { self::record_error( 'outbox_failure_lease_lost' ); return false; }
		return true;
	}
}
