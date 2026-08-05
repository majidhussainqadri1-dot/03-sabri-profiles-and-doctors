<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Moderation {
	public function moderate_profile( $public_id, $actor_id, $new_state, $expected_version, $reason, $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$guard = SPD_Authorization::moderation_guard( $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$profile = $this->find_by_public_id( $public_id );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( 'founder' === $profile['profile_type'] ) { return new WP_Error( 'spd_founder_invariant', __( 'The official Founder state cannot be altered through generic profile moderation.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$new_state = sanitize_key( $new_state );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 1000 );
		$expected_version = absint( $expected_version );
		if ( ! $reason ) { return new WP_Error( 'spd_reason_required', __( 'A moderation reason is required.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( ! $expected_version ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		if ( ! SPD_Helpers::state_transition_allowed( $profile['state'], $new_state, 'profile' ) ) { return new WP_Error( 'spd_invalid_transition', __( 'The requested profile-state transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $public_id, $new_state, $expected_version, $reason ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'moderate_profile', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'public_id' => $public_id, 'state' => $new_state, 'version' => $expected_version + 1 );
		$table = SPD_DB::table( 'profiles' );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $table, $profile, $new_state, $expected_version, $actor_id, $reason, $response, $idempotency_key ) {
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d", $new_state, SPD_Helpers::now(), $profile['id'], $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				$event = $this->event( 'ProfileModerated.v1', 'profile', $profile['public_id'], array( 'actor_id' => $actor_id, 'from' => $profile['state'], 'to' => $new_state, 'reason_hash' => hash( 'sha256', $reason ), 'version' => $expected_version + 1 ) );
				if ( is_wp_error( $event ) ) { return $event; }
				if ( ! $this->idempotency_complete( $actor_id, 'moderate_profile', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The moderation replay result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return true;
			}
		);
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'moderate_profile', $idempotency_key ); return $result; }
		$this->audit_diff( $profile, $actor_id, array( 'state' => $profile['state'] ), array( 'state' => $new_state ), 'profile_moderation' );
		$updated_profile = $this->find_by_public_id( $public_id );
		$this->purge_profile_cache( $updated_profile );
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
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) { return new WP_Error( 'spd_report_unavailable', __( 'The profile report is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$new_status = sanitize_key( $new_status );
		$expected_version = absint( $expected_version );
		if ( ! $expected_version ) { return new WP_Error( 'spd_version_required', __( 'A current report version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		if ( ! $this->report_transition_allowed( $row['status'], $new_status ) ) { return new WP_Error( 'spd_invalid_report_transition', __( 'The requested report transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		$note = SPD_Helpers::sanitize_multiline( $note, 1000 );
		if ( in_array( $new_status, array( 'actioned', 'rejected', 'closed' ), true ) && ! $note ) { return new WP_Error( 'spd_report_note_required', __( 'A review note is required for this report decision.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $report_uuid, $new_status, $expected_version, $note ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'moderate_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'report_uuid' => $report_uuid, 'status' => $new_status, 'version' => $expected_version + 1 );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $table, $row, $new_status, $expected_version, $actor_id, $note, $report_uuid, $response, $idempotency_key ) {
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,assigned_to=%d,decision_note=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d", $new_status, $actor_id, $note, SPD_Helpers::now(), absint( $row['id'] ), $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

	public function create_report( $public_id, $reporter_user_id, $reason, $details, $idempotency_key = '' ) {
		global $wpdb;
		$reporter_user_id = absint( $reporter_user_id );
		if ( ! $reporter_user_id || ! SPD_Membership_Adapter::is_member_eligible( $reporter_user_id ) ) { return new WP_Error( 'spd_login_required', __( 'An eligible signed-in account is required to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		if ( SPD_Observability::safe_mode() ) { return new WP_Error( 'spd_safe_mode', __( 'Profile reporting is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$profile = $this->find_by_public_id( $public_id );
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $reporter_user_id ) ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$allowed = array( 'impersonation', 'harassment', 'false_qualification', 'unsafe_media', 'privacy_breach', 'other' );
		$reason = sanitize_key( $reason );
		$details = SPD_Helpers::sanitize_multiline( $details, 3000 );
		if ( ! in_array( $reason, $allowed, true ) ) { return new WP_Error( 'spd_invalid_report_reason', __( 'Choose a valid report reason.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( strlen( $details ) < 10 ) { return new WP_Error( 'spd_report_details_required', __( 'Provide enough detail for a fair review.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $public_id, $reason, $details ) ) );
		$idem = $this->idempotency_begin( $reporter_user_id, 'create_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$reports = SPD_DB::table( 'reports' );
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE reporter_user_id=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)", $reporter_user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count >= 5 ) { $this->idempotency_fail( $reporter_user_id, 'create_report', $idempotency_key ); return new WP_Error( 'spd_report_rate_limited', __( 'Too many reports were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) ); }
		$uuid = SPD_Helpers::public_id();
		$severity = in_array( $reason, array( 'impersonation', 'privacy_breach' ), true ) ? 'high' : 'normal';
		$now = SPD_Helpers::now();
		$dedupe = hash( 'sha256', gmdate( 'Y-m-d' ) . ':' . $profile['id'] . ':' . $reason . ':' . hash( 'sha256', $details ) );
		$response = array( 'report_uuid' => $uuid, 'status' => 'submitted', 'trace_id' => SPD_Helpers::trace_id() );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $reports, $uuid, $profile, $reporter_user_id, $reason, $details, $severity, $now, $dedupe, $response, $idempotency_key ) {
				$ok = $wpdb->insert( $reports, array( 'report_uuid' => $uuid, 'profile_id' => $profile['id'], 'reporter_user_id' => $reporter_user_id, 'reason' => $reason, 'details' => $details, 'dedupe_hash' => $dedupe, 'status' => 'submitted', 'severity' => $severity, 'created_at' => $now, 'updated_at' => $now ) );
				if ( ! $ok ) { return new WP_Error( 'spd_report_duplicate_or_failed', __( 'This report is already recorded or could not be submitted.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				$payload = array( 'report_uuid' => $uuid, 'profile_public_id' => $profile['public_id'], 'reporter_user_id' => $reporter_user_id, 'reason' => $reason, 'severity' => $severity );
				$event = $this->event( 'ProfileReported.v1', 'profile', $profile['public_id'], $payload );
				if ( is_wp_error( $event ) ) { return $event; }
				if ( ! $this->idempotency_complete( $reporter_user_id, 'create_report', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The report replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return $payload;
			}
		);
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $reporter_user_id, 'create_report', $idempotency_key ); return $result; }
		do_action( 'sabri_file24_profile_reported', $result );
		do_action( 'sabri_support_profile_reported', $result );
		return $response;
	}
}
