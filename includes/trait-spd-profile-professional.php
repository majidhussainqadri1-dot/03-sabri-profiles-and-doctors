<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Professional {
	public function latest_professional_submission( $profile_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . SPD_DB::table( 'professional_submissions' ) . " WHERE profile_id=%d ORDER BY id DESC LIMIT 1", absint( $profile_id ) ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) { return array(); }
		$row['id'] = absint( $row['id'] );
		$row['profile_id'] = absint( $row['profile_id'] );
		$row['submitted_by'] = absint( $row['submitted_by'] );
		$row['version'] = absint( $row['version'] );
		$row['fields'] = json_decode( $row['payload_json'], true );
		if ( ! is_array( $row['fields'] ) ) { $row['fields'] = array(); }
		unset( $row['payload_json'] );
		return $row;
	}

	public function submit_professional_fields( $actor_id, array $input, $expected_profile_version, $idempotency_key, $submit = true ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$profile = $this->find_by_user_id( $actor_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( 'doctor' !== ( $profile['profile_type'] ?? '' ) || ! SPD_Membership_Adapter::is_doctor( $actor_id ) ) {
			return new WP_Error( 'spd_professional_profile_not_allowed', __( 'Only a currently eligible doctor account may submit professional profile claims.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$expected_profile_version = absint( $expected_profile_version );
		if ( $expected_profile_version < 1 ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		$unknown = array_diff( array_keys( $input ), self::professional_fields() );
		if ( $unknown ) { return new WP_Error( 'spd_unknown_professional_field', __( 'One or more professional fields are not supported.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$clean = array();
		foreach ( self::professional_fields() as $key ) {
			$value = isset( $input[ $key ] ) ? SPD_Helpers::sanitize_multiline( $input[ $key ], 3000 ) : '';
			if ( '' !== trim( $value ) ) { $clean[ $key ] = $value; }
		}
		if ( ! $clean ) { return new WP_Error( 'spd_professional_fields_required', __( 'Enter at least one professional field.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $clean, $expected_profile_version, (bool) $submit ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'submit_professional_fields', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }

		$table = SPD_DB::table( 'professional_submissions' );
		$profiles = SPD_DB::table( 'profiles' );
		$uuid = SPD_Helpers::public_id();
		$status = $submit ? 'pending_review' : 'draft';
		$now = SPD_Helpers::now();
		$response = array( 'submission_uuid' => $uuid, 'status' => $status, 'profile_version' => $expected_profile_version + 1 );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $table, $profiles, $profile, $actor_id, $expected_profile_version, $uuid, $clean, $request_hash, $status, $now, $response, $idempotency_key ) {
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$profiles} SET version=version+1,updated_at=%s WHERE id=%d AND version=%d", $now, $profile['id'], $expected_profile_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				// A new draft/submission explicitly supersedes the previous local proposal;
				// approved public truth remains solely in File 09.
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='superseded',version=version+1,updated_at=%s WHERE profile_id=%d AND status IN ('draft','pending_review','rejected')", $now, $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$inserted = $wpdb->insert(
					$table,
					array(
						'submission_uuid' => $uuid,
						'profile_id'      => $profile['id'],
						'submitted_by'    => $actor_id,
						'payload_json'    => SPD_Helpers::json_encode( $clean ),
						'payload_hash'    => $request_hash,
						'status'          => $status,
						'created_at'      => $now,
						'updated_at'      => $now,
					)
				);
				if ( ! $inserted ) { return new WP_Error( 'spd_professional_submission_failed', __( 'The professional profile submission could not be stored.', 'sabri-profiles-doctors' ) ); }
				$event = $this->event(
					$submit ? 'ProfileProfessionalFieldsSubmitted.v1' : 'ProfileProfessionalDraftSaved.v1',
					'professional_submission',
					$uuid,
					array( 'profile_public_id' => $profile['public_id'], 'doctor_user_id' => $actor_id, 'field_keys' => array_keys( $clean ), 'payload_hash' => $request_hash, 'profile_version' => $expected_profile_version + 1 )
				);
				if ( is_wp_error( $event ) ) { return $event; }
				if ( ! $this->idempotency_complete( $actor_id, 'submit_professional_fields', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The professional submission replay result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return true;
			}
		);
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'submit_professional_fields', $idempotency_key ); return $result; }
		$updated_profile = $this->find_by_id( $profile['id'] );
		$this->purge_profile_cache( $updated_profile );
		return $response;
	}
}
