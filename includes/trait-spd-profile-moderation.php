<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Moderation {
	public function moderate_profile( $public_id, $actor_id, $new_state, $expected_version, $reason, $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$guard = SPD_Authorization::moderation_guard( $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$profile = $this->find_by_public_id_strict( $public_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( 'founder' === $profile['profile_type'] ) { return new WP_Error( 'spd_founder_invariant', __( 'The official Founder state cannot be altered through generic profile moderation.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$new_state = sanitize_key( $new_state );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 1000 );
		$expected_version = absint( $expected_version );
		if ( ! $reason ) { return new WP_Error( 'spd_reason_required', __( 'A moderation reason is required.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( ! $expected_version ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }

		$prior = $this->completed_idempotency_response( $actor_id, 'moderate_profile', $idempotency_key );
		if ( is_wp_error( $prior ) ) { return $prior; }
		if ( is_array( $prior ) && (string) ( $prior['public_id'] ?? '' ) === (string) $public_id && sanitize_key( (string) ( $prior['state'] ?? '' ) ) === $new_state && absint( $prior['version'] ?? 0 ) === $expected_version + 1 ) { return $prior; }
		if ( ! SPD_Helpers::state_transition_allowed( $profile['state'], $new_state, 'profile' ) ) { return new WP_Error( 'spd_invalid_transition', __( 'The requested profile-state transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }

		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $public_id, $new_state, $expected_version, $reason ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'moderate_profile', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'public_id' => $public_id, 'state' => $new_state, 'version' => $expected_version + 1 );
		$table = SPD_DB::table( 'profiles' );
		$media_table = SPD_DB::table( 'media' );
		$remove_media = in_array( $new_state, array( 'suspended', 'archived', 'tombstoned' ), true );
		$old_media = array( 'avatar' => absint( $profile['avatar_id'] ), 'cover' => absint( $profile['cover_id'] ) );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $table, $media_table, $profile, $new_state, $expected_version, $actor_id, $reason, $response, $idempotency_key, $remove_media, $old_media ) {
				$sql = $remove_media ? "UPDATE {$table} SET state=%s,avatar_id=0,cover_id=0,version=version+1,updated_at=%s WHERE id=%d AND version=%d" : "UPDATE {$table} SET state=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d";
				$wpdb->last_error = '';
				$updated = $wpdb->query( $wpdb->prepare( $sql, $new_state, SPD_Helpers::now(), $profile['id'], $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $wpdb->last_error || false === $updated ) { return new WP_Error( 'spd_profile_store_unavailable', __( 'Profile moderation is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
				if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				if ( $remove_media ) {
					$wpdb->last_error = '';
					$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$media_table} WHERE profile_id=%d", $profile['id'] ) );
					if ( $wpdb->last_error || false === $deleted ) { return new WP_Error( 'spd_media_moderation_store_unavailable', __( 'Profile media references could not be revoked safely during moderation.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
					foreach ( $old_media as $purpose => $attachment_id ) {
						if ( ! $attachment_id ) { continue; }
						$queued = SPD_Media::queue_owned_deletion( $attachment_id, $profile['user_id'], $purpose );
						if ( is_wp_error( $queued ) ) { return $queued; }
						$media_event = $this->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'purpose' => $purpose, 'attachment_id' => $attachment_id, 'state' => 'removed_for_moderation', 'version' => $expected_version + 1 ) );
						if ( is_wp_error( $media_event ) ) { return $media_event; }
					}
				}
				$event = $this->event( 'ProfileModerated.v1', 'profile', $profile['public_id'], array( 'actor_id' => $actor_id, 'from' => $profile['state'], 'to' => $new_state, 'reason_hash' => hash( 'sha256', $reason ), 'version' => $expected_version + 1 ) );
				if ( is_wp_error( $event ) ) { return $event; }
				if ( ! $this->idempotency_complete( $actor_id, 'moderate_profile', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The moderation replay result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return true;
			}
		);
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'moderate_profile', $idempotency_key ); return $result; }
		$this->audit_diff( $profile, $actor_id, array( 'state' => $profile['state'] ), array( 'state' => $new_state ), 'profile_moderation' );
		try { $this->purge_profile_cache( $profile ); } catch ( Throwable $ignored ) {}
		return $response;
	}

	public static function report_transition_targets( $from ) {
		$map = array(
			'submitted' => array( 'triaged', 'rejected' ),
			'triaged'   => array( 'in_review', 'rejected', 'closed' ),
			'in_review' => array( 'actioned', 'rejected', 'closed' ),
			'actioned'  => array( 'closed', 'in_review' ),
			'rejected'  => array( 'closed' ),
			'closed'    => array(),
		);
		$from = sanitize_key( $from );
		return isset( $map[ $from ] ) ? $map[ $from ] : array();
	}

	private function report_transition_allowed( $from, $to ) {
		return in_array( sanitize_key( $to ), self::report_transition_targets( $from ), true );
	}

	public function moderate_report( $report_uuid, $actor_id, $new_status, $expected_version, $note = '', $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$guard = SPD_Authorization::moderation_guard( $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$table = SPD_DB::table( 'reports' );
		$report_uuid = sanitize_text_field( $report_uuid );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_report_store_unavailable', __( 'The profile-report store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( ! $row ) { return new WP_Error( 'spd_report_unavailable', __( 'The profile report is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$new_status = sanitize_key( $new_status );
		$expected_version = absint( $expected_version );
		if ( ! $expected_version ) { return new WP_Error( 'spd_version_required', __( 'A current report version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		$note = SPD_Helpers::sanitize_multiline( $note, 1000 );
		if ( in_array( $new_status, array( 'actioned', 'rejected', 'closed' ), true ) && ! $note ) { return new WP_Error( 'spd_report_note_required', __( 'A review note is required for this report decision.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }

		$prior = $this->completed_idempotency_response( $actor_id, 'moderate_report', $idempotency_key );
		if ( is_wp_error( $prior ) ) { return $prior; }
		if ( is_array( $prior ) && (string) ( $prior['report_uuid'] ?? '' ) === $report_uuid && sanitize_key( (string) ( $prior['status'] ?? '' ) ) === $new_status && absint( $prior['version'] ?? 0 ) === $expected_version + 1 ) { return $prior; }
		if ( ! $this->report_transition_allowed( $row['status'], $new_status ) ) { return new WP_Error( 'spd_invalid_report_transition', __( 'The requested report transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }

		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $report_uuid, $new_status, $expected_version, $note ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'moderate_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'report_uuid' => $report_uuid, 'status' => $new_status, 'version' => $expected_version + 1 );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $table, $row, $new_status, $expected_version, $actor_id, $note, $report_uuid, $response, $idempotency_key ) {
				$wpdb->last_error = '';
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,assigned_to=%d,decision_note=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d", $new_status, $actor_id, $note, SPD_Helpers::now(), absint( $row['id'] ), $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $wpdb->last_error || false === $updated ) { return new WP_Error( 'spd_report_store_unavailable', __( 'Report moderation is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
				if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This report changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				$event = $this->event( 'ProfileReportReviewed.v1', 'report', $report_uuid, array( 'actor_id' => $actor_id, 'from' => $row['status'], 'to' => $new_status, 'note_hash' => hash( 'sha256', $note ), 'version' => $expected_version + 1 ) );
				if ( is_wp_error( $event ) ) { return $event; }
				if ( ! $this->idempotency_complete( $actor_id, 'moderate_report', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The report-review replay result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return true;
			}
		);
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'moderate_report', $idempotency_key ); return $result; }
		return $response;
	}

	/** Compatibility route delegates to the single strict safety-report command. */
	public function create_report( $public_id, $reporter_user_id, $reason, $details, $idempotency_key = '' ) {
		return $this->create_safety_report_strict( $public_id, $reporter_user_id, $reason, $details, $idempotency_key );
	}
}
