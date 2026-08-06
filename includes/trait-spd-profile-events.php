<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Events {
	public function event( $event_name, $aggregate_type, $aggregate_id, array $payload ) {
		global $wpdb;
		$table = SPD_DB::table( 'events' );
		$now   = SPD_Helpers::now();
		$uuid  = SPD_Helpers::public_id();
		$ok = $wpdb->insert(
			$table,
			array(
				'event_uuid'     => $uuid,
				'event_name'     => sanitize_text_field( $event_name ),
				'aggregate_type' => sanitize_key( $aggregate_type ),
				'aggregate_id'   => sanitize_text_field( (string) $aggregate_id ),
				'payload'        => SPD_Helpers::json_encode( $payload ),
				'status'         => 'pending',
				'attempts'       => 0,
				'available_at'   => $now,
				'lease_token'    => '',
				'created_at'     => $now,
			)
		);
		return $ok ? $uuid : new WP_Error( 'spd_event_persist_failed', __( 'The audit event could not be persisted.', 'sabri-profiles-doctors' ) );
	}

	private function audit_diff( array $profile, $actor_id, $before, $after, $reason ) {
		$payload = array(
			'actor_id'    => absint( $actor_id ),
			'public_id'   => $profile['public_id'],
			'reason'      => sanitize_key( $reason ),
			'before_hash' => hash( 'sha256', SPD_Helpers::json_encode( $before ) ),
			'after_hash'  => hash( 'sha256', SPD_Helpers::json_encode( $after ) ),
			'trace_id'    => SPD_Helpers::trace_id(),
		);
		if ( class_exists( 'SMC_Security' ) && is_callable( array( 'SMC_Security', 'audit' ) ) ) {
			SMC_Security::audit( 'spd_profile_changed', absint( $profile['user_id'] ), $payload );
		}
		do_action( 'spd_profile_audit', $payload );
	}

	private function idempotency_begin( $actor_id, $command, $key, $request_hash, $required = true ) {
		global $wpdb;
		$key = trim( (string) $key );
		if ( strlen( $key ) > 200 ) { return new WP_Error( 'spd_idempotency_invalid', __( 'The Idempotency-Key is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( '' === $key ) {
			return $required ? new WP_Error( 'spd_idempotency_required', __( 'An Idempotency-Key is required for this mutation.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ) : true;
		}
		$actor_id = absint( $actor_id );
		$command  = sanitize_key( $command );
		$key_hash = hash( 'sha256', $key );
		$table    = SPD_DB::table( 'idempotency' );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_id=%d AND command=%s AND idempotency_key=%s LIMIT 1", $actor_id, $command, $key_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row && strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
			$wpdb->delete( $table, array( 'id' => absint( $row['id'] ) ) );
			$row = array();
		}
		if ( $row ) {
			if ( ! hash_equals( (string) $row['request_hash'], (string) $request_hash ) ) {
				return new WP_Error( 'spd_idempotency_mismatch', __( 'This idempotency key was already used for a different request.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
			}
			if ( 'completed' === $row['status'] ) {
				$response = json_decode( (string) $row['response_json'], true );
				return is_array( $response ) ? array( 'replay' => true, 'response' => $response ) : new WP_Error( 'spd_idempotency_response_invalid', __( 'The stored replay result is invalid and requires operator review.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
			}
			return new WP_Error( 'spd_idempotency_in_progress', __( 'This request is already being processed.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$now = SPD_Helpers::now();
		$insert = $wpdb->insert(
			$table,
			array(
				'idempotency_key' => $key_hash,
				'actor_id'         => $actor_id,
				'command'          => $command,
				'request_hash'     => $request_hash,
				'status'           => 'started',
				'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);
		if ( $insert ) { return true; }
		// A simultaneous request may have won the unique-key race. Re-read and
		// return the deterministic state instead of producing a false database error.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_id=%d AND command=%s AND idempotency_key=%s LIMIT 1", $actor_id, $command, $key_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row && hash_equals( (string) $row['request_hash'], (string) $request_hash ) ) {
			if ( 'completed' === $row['status'] ) {
				$response = json_decode( (string) $row['response_json'], true );
				if ( is_array( $response ) ) { return array( 'replay' => true, 'response' => $response ); }
			}
			return new WP_Error( 'spd_idempotency_in_progress', __( 'This request is already being processed.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		return new WP_Error( 'spd_idempotency_store_failed', __( 'The replay-protection record could not be created.', 'sabri-profiles-doctors' ) );
	}

	private function idempotency_complete( $actor_id, $command, $key, array $response ) {
		global $wpdb;
		if ( '' === trim( (string) $key ) ) { return false; }
		$json = SPD_Helpers::json_encode( $response );
		if ( 'null' === $json ) { return false; }
		$updated = $wpdb->update(
			SPD_DB::table( 'idempotency' ),
			array( 'status' => 'completed', 'response_json' => $json, 'updated_at' => SPD_Helpers::now() ),
			array( 'actor_id' => absint( $actor_id ), 'command' => sanitize_key( $command ), 'idempotency_key' => hash( 'sha256', $key ), 'status' => 'started' )
		);
		return 1 === $updated;
	}

	private function idempotency_fail( $actor_id, $command, $key ) {
		global $wpdb;
		if ( '' === trim( (string) $key ) ) { return; }
		$wpdb->delete(
			SPD_DB::table( 'idempotency' ),
			array( 'actor_id' => absint( $actor_id ), 'command' => sanitize_key( $command ), 'idempotency_key' => hash( 'sha256', $key ), 'status' => 'started' )
		);
	}
}
