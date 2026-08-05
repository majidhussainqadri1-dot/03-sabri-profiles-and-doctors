<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Moderation {
	public function moderate_profile( $public_id, $actor_id, $new_state, $expected_version, $reason ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		if ( ! SPD_Membership_Adapter::can_moderate_profiles( $actor_id ) ) {
			return new WP_Error( 'spd_forbidden', __( 'You are not authorized to moderate profiles.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$profile = $this->find_by_public_id( $public_id );
		if ( ! $profile ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		if ( 'founder' === $profile['profile_type'] ) {
			return new WP_Error( 'spd_founder_invariant', __( 'The official Founder state cannot be altered through generic profile moderation.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$new_state = sanitize_key( $new_state );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 1000 );
		if ( ! $reason ) {
			return new WP_Error( 'spd_reason_required', __( 'A moderation reason is required.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		if ( ! SPD_Helpers::state_transition_allowed( $profile['state'], $new_state, 'profile' ) ) {
			return new WP_Error( 'spd_invalid_transition', __( 'The requested profile-state transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$table = SPD_DB::table( 'profiles' );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET state=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d", $new_state, SPD_Helpers::now(), $profile['id'], absint( $expected_version ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 !== $updated ) {
			return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$this->event( 'ProfileModerated.v1', 'profile', $profile['public_id'], array( 'actor_id' => $actor_id, 'from' => $profile['state'], 'to' => $new_state, 'reason' => $reason, 'version' => $profile['version'] + 1 ) );
		$this->audit_diff( $profile, $actor_id, array( 'state' => $profile['state'] ), array( 'state' => $new_state ), 'profile_moderation' );
		$updated_profile = $this->find_by_public_id( $public_id );
		$this->purge_profile_cache( $updated_profile );
		return array( 'public_id' => $public_id, 'state' => $new_state, 'version' => $updated_profile['version'] );
	}

	public function moderate_report( $report_uuid, $actor_id, $new_status, $expected_version, $note = '' ) {
		global $wpdb;
		if ( ! SPD_Membership_Adapter::can_moderate_profiles( $actor_id ) ) {
			return new WP_Error( 'spd_forbidden', __( 'You are not authorized to moderate profile reports.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$allowed = array( 'triaged', 'in_review', 'actioned', 'rejected', 'closed' );
		$new_status = sanitize_key( $new_status );
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new WP_Error( 'spd_invalid_report_status', __( 'The report status is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		$table = SPD_DB::table( 'reports' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_uuid=%s LIMIT 1", sanitize_text_field( $report_uuid ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return new WP_Error( 'spd_report_unavailable', __( 'The profile report is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,assigned_to=%d,version=version+1,updated_at=%s WHERE id=%d AND version=%d", $new_status, absint( $actor_id ), SPD_Helpers::now(), absint( $row['id'] ), absint( $expected_version ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 !== $updated ) {
			return new WP_Error( 'spd_version_conflict', __( 'This report changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$this->event( 'ProfileReportReviewed.v1', 'report', $report_uuid, array( 'actor_id' => absint( $actor_id ), 'from' => $row['status'], 'to' => $new_status, 'note' => SPD_Helpers::sanitize_multiline( $note, 1000 ), 'version' => absint( $row['version'] ) + 1 ) );
		return array( 'report_uuid' => $report_uuid, 'status' => $new_status, 'version' => absint( $row['version'] ) + 1 );
	}

	public function create_report( $public_id, $reporter_user_id, $reason, $details ) {
		global $wpdb;
		$reporter_user_id = absint( $reporter_user_id );
		if ( ! $reporter_user_id ) {
			return new WP_Error( 'spd_login_required', __( 'Log in to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) );
		}
		$profile = $this->find_by_public_id( $public_id );
		if ( ! $profile ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$allowed = array( 'impersonation', 'harassment', 'false_qualification', 'unsafe_media', 'privacy_breach', 'other' );
		$reason = sanitize_key( $reason );
		if ( ! in_array( $reason, $allowed, true ) ) {
			return new WP_Error( 'spd_invalid_report_reason', __( 'Choose a valid report reason.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		$reports = SPD_DB::table( 'reports' );
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE reporter_user_id=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)", $reporter_user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count >= 5 ) {
			return new WP_Error( 'spd_report_rate_limited', __( 'Too many reports were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) );
		}
		$uuid = SPD_Helpers::public_id();
		$severity = in_array( $reason, array( 'impersonation', 'privacy_breach' ), true ) ? 'high' : 'normal';
		$now = SPD_Helpers::now();
		$wpdb->insert(
			$reports,
			array(
				'report_uuid'     => $uuid,
				'profile_id'      => $profile['id'],
				'reporter_user_id'=> $reporter_user_id,
				'reason'          => $reason,
				'details'         => SPD_Helpers::sanitize_multiline( $details, 3000 ),
				'status'          => 'submitted',
				'severity'        => $severity,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'spd_report_failed', __( 'The report could not be submitted.', 'sabri-profiles-doctors' ) );
		}
		$payload = array( 'report_uuid' => $uuid, 'profile_public_id' => $profile['public_id'], 'reporter_user_id' => $reporter_user_id, 'reason' => $reason, 'severity' => $severity );
		$this->event( 'ProfileReported.v1', 'profile', $profile['public_id'], $payload );
		do_action( 'sabri_file24_profile_reported', $payload );
		do_action( 'sabri_support_profile_reported', $payload );
		return array( 'report_uuid' => $uuid, 'status' => 'submitted', 'trace_id' => SPD_Helpers::trace_id() );
	}

}
