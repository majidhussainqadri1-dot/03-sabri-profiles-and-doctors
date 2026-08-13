<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Appeals {
	private static function eligible_claims( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $health['status'] ?? '' ) ) { return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$claims = SPD_Membership_Adapter::claims( $user_id );
		if ( ! $claims ) { return new WP_Error( 'spd_membership_claim_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( empty( $claims['eligible'] ) || ! empty( $claims['suspended'] ) ) { return new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible for this appeal action.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		return $claims;
	}

	private static function emit( $name, $aggregate_id, array $payload ) {
		global $wpdb;
		$json = wp_json_encode( $payload );
		if ( false === $json ) { return false; }
		return (bool) $wpdb->insert( SPD_DB::table( 'events' ), array(
			'event_uuid' => wp_generate_uuid4(), 'event_name' => sanitize_text_field( $name ),
			'aggregate_type' => 'report_appeal', 'aggregate_id' => sanitize_text_field( $aggregate_id ),
			'payload' => $json, 'status' => 'pending', 'attempts' => 0,
			'available_at' => SPD_Helpers::now(), 'created_at' => SPD_Helpers::now(),
		) );
	}

	public static function request( $report_uuid, $requester_id, $reason ) {
		global $wpdb;
		$requester_id = absint( $requester_id );
		$claims = self::eligible_claims( $requester_id );
		if ( is_wp_error( $claims ) ) { return $claims; }
		$reason = SPD_Helpers::sanitize_multiline( $reason, 2000 );
		if ( SPD_Helpers::text_length( $reason ) < 10 ) { return new WP_Error( 'spd_appeal_reason_required', __( 'Provide a clear reason for the appeal.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$report_uuid = sanitize_text_field( $report_uuid );
		$reports = SPD_DB::table( 'reports' );
		$profiles = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id,profile_id,reporter_user_id,status,assigned_to FROM {$reports} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_report_store_unavailable', __( 'The profile-report store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( ! $report ) { return new WP_Error( 'spd_appeal_unavailable', __( 'This report is not eligible for appeal by this account.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$wpdb->last_error = '';
		$subject_user_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$profiles} WHERE id=%d LIMIT 1", absint( $report['profile_id'] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! $subject_user_id ) { return new WP_Error( 'spd_profile_store_unavailable', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$status = sanitize_key( $report['status'] );
		$is_reporter = absint( $report['reporter_user_id'] ) === $requester_id;
		$is_subject = $subject_user_id === $requester_id;
		$appeal_role = '';
		if ( $is_reporter && in_array( $status, array( 'rejected', 'closed' ), true ) ) { $appeal_role = 'reporter'; }
		elseif ( $is_subject && in_array( $status, array( 'actioned', 'closed' ), true ) ) { $appeal_role = 'profile_subject'; }
		if ( ! $appeal_role ) { return new WP_Error( 'spd_appeal_unavailable', __( 'This report is not eligible for appeal by this account.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$table = SPD_Central_Profile::appeals_table();
		$uuid = SPD_Helpers::public_id(); $now = SPD_Helpers::now();
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $report, $requester_id, $reason, $uuid, $now, $appeal_role, $report_uuid ) {
			$ok = $wpdb->insert( $table, array( 'appeal_uuid' => $uuid, 'report_id' => absint( $report['id'] ), 'requested_by' => $requester_id, 'reason' => $reason, 'status' => 'submitted', 'version' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			if ( ! $ok ) { return new WP_Error( 'spd_appeal_duplicate_or_failed', __( 'An appeal already exists or could not be recorded.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( ! self::emit( 'ProfileReportAppealed.v2', $report_uuid, array( 'appeal_uuid' => $uuid, 'requested_by' => $requester_id, 'appeal_role' => $appeal_role, 'report_status' => sanitize_key( $report['status'] ) ) ) ) { return new WP_Error( 'spd_appeal_event_failed', __( 'The appeal could not be committed safely.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'appeal_uuid' => $uuid, 'status' => 'submitted', 'appeal_role' => $appeal_role, 'version' => 1 );
	}

	public static function review( $appeal_uuid, $reviewer_id, $outcome, $note, $expected_version ) {
		global $wpdb;
		$reviewer_id = absint( $reviewer_id );
		$guard = SPD_Authorization::moderation_guard( $reviewer_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$appeal_uuid = sanitize_text_field( $appeal_uuid );
		$outcome = sanitize_key( $outcome );
		if ( ! in_array( $outcome, array( 'upheld', 'overturned', 'modified' ), true ) ) { return new WP_Error( 'spd_appeal_outcome_invalid', __( 'Choose a valid appeal outcome.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$note = SPD_Helpers::sanitize_multiline( $note, 2000 );
		if ( SPD_Helpers::text_length( $note ) < 10 ) { return new WP_Error( 'spd_appeal_note_required', __( 'Provide a clear independent-review note.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$expected_version = absint( $expected_version );
		if ( $expected_version < 1 ) { return new WP_Error( 'spd_version_required', __( 'A current appeal version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		$table = SPD_Central_Profile::appeals_table(); $reports = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT a.id,a.report_id,a.requested_by,a.status,a.version,r.report_uuid,r.assigned_to FROM {$table} a INNER JOIN {$reports} r ON r.id=a.report_id WHERE a.appeal_uuid=%s LIMIT 1", $appeal_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_appeal_store_unavailable', __( 'The appeal store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( ! $row ) { return new WP_Error( 'spd_appeal_not_found', __( 'The appeal is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( 'submitted' !== sanitize_key( $row['status'] ) ) { return new WP_Error( 'spd_appeal_already_decided', __( 'This appeal has already been decided.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		if ( absint( $row['version'] ) !== $expected_version ) { return new WP_Error( 'spd_version_conflict', __( 'This appeal changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		if ( $reviewer_id === absint( $row['requested_by'] ) || ( absint( $row['assigned_to'] ) && $reviewer_id === absint( $row['assigned_to'] ) ) ) { return new WP_Error( 'spd_appeal_independent_reviewer_required', __( 'An appeal requires an independent reviewer.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$now = SPD_Helpers::now(); $new_version = $expected_version + 1;
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $row, $appeal_uuid, $reviewer_id, $outcome, $note, $expected_version, $new_version, $now ) {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,reviewer_id=%d,decision_note=%s,version=%d,updated_at=%s WHERE id=%d AND version=%d AND status='submitted'", $outcome, $reviewer_id, $note, $new_version, $now, absint( $row['id'] ), $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This appeal changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( ! self::emit( 'ProfileReportAppealReviewed.v1', $row['report_uuid'], array( 'appeal_uuid' => $appeal_uuid, 'reviewer_id' => $reviewer_id, 'outcome' => $outcome, 'note_hash' => hash( 'sha256', $note ), 'version' => $new_version ) ) ) { return new WP_Error( 'spd_appeal_event_failed', __( 'The appeal decision could not be committed safely.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'appeal_uuid' => $appeal_uuid, 'status' => $outcome, 'version' => $new_version );
	}
}
