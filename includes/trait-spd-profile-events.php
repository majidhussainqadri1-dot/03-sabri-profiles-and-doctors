<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Events {
	public function event( $event_name, $aggregate_type, $aggregate_id, array $payload ) {
		global $wpdb;
		$table = SPD_DB::table( 'events' );
		$now = SPD_Helpers::now();
		$uuid = SPD_Helpers::public_id();
		$wpdb->insert(
			$table,
			array(
				'event_uuid'    => $uuid,
				'event_name'    => sanitize_text_field( $event_name ),
				'aggregate_type'=> sanitize_key( $aggregate_type ),
				'aggregate_id'  => sanitize_text_field( (string) $aggregate_id ),
				'payload'       => SPD_Helpers::json_encode( $payload ),
				'status'        => 'pending',
				'attempts'      => 0,
				'available_at'  => $now,
				'created_at'    => $now,
			)
		);
		return $uuid;
	}

	private function audit_diff( array $profile, $actor_id, $before, $after, $reason ) {
		$payload = array(
			'actor_id'   => absint( $actor_id ),
			'public_id'  => $profile['public_id'],
			'reason'     => sanitize_key( $reason ),
			'before_hash'=> hash( 'sha256', SPD_Helpers::json_encode( $before ) ),
			'after_hash' => hash( 'sha256', SPD_Helpers::json_encode( $after ) ),
			'trace_id'   => SPD_Helpers::trace_id(),
		);
		if ( class_exists( 'SMC_Security' ) && is_callable( array( 'SMC_Security', 'audit' ) ) ) {
			SMC_Security::audit( 'spd_profile_changed', $profile['user_id'], 'profile', $profile['id'], $payload );
		}
		do_action( 'spd_profile_audit', $payload );
	}

	private function idempotency_begin( $actor_id, $command, $key, $request_hash ) {
		global $wpdb;
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return true;
		}
		$key_hash = hash( 'sha256', $key );
		$table = SPD_DB::table( 'idempotency' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_id=%d AND command=%s AND idempotency_key=%s LIMIT 1", absint( $actor_id ), sanitize_key( $command ), $key_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) {
			if ( ! hash_equals( $row['request_hash'], $request_hash ) ) {
				return new WP_Error( 'spd_idempotency_mismatch', __( 'This idempotency key was already used for a different request.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
			}
			if ( 'completed' === $row['status'] ) {
				return array( 'replay' => true, 'response' => json_decode( $row['response_json'], true ) );
			}
			return new WP_Error( 'spd_idempotency_in_progress', __( 'This profile update is already being processed.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$wpdb->insert(
			$table,
			array(
				'idempotency_key' => $key_hash,
				'actor_id'       => absint( $actor_id ),
				'command'        => sanitize_key( $command ),
				'request_hash'   => $request_hash,
				'status'         => 'started',
				'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'created_at'     => SPD_Helpers::now(),
			)
		);
		return true;
	}

	private function idempotency_complete( $actor_id, $command, $key, array $response ) {
		global $wpdb;
		if ( '' === trim( (string) $key ) ) {
			return;
		}
		$wpdb->update(
			SPD_DB::table( 'idempotency' ),
			array( 'status' => 'completed', 'response_json' => SPD_Helpers::json_encode( $response ) ),
			array( 'actor_id' => absint( $actor_id ), 'command' => sanitize_key( $command ), 'idempotency_key' => hash( 'sha256', $key ) )
		);
	}

	private function idempotency_fail( $actor_id, $command, $key ) {
		global $wpdb;
		if ( '' === trim( (string) $key ) ) {
			return;
		}
		$wpdb->delete( SPD_DB::table( 'idempotency' ), array( 'actor_id' => absint( $actor_id ), 'command' => sanitize_key( $command ), 'idempotency_key' => hash( 'sha256', $key ) ) );
	}

}
