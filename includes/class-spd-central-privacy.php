<?php
defined( 'ABSPATH' ) || exit;

/**
 * Privacy coverage for File 03 native central-plan tables.
 * Delegations and report appeals contain user-linked data and therefore must
 * not be omitted from WordPress export/erasure merely because they live
 * outside the base profile tables.
 */
final class SPD_Central_Privacy {
	const PAGE_SIZE = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profile-central-domain'] = array(
			'exporter_friendly_name' => __( 'Sabri profile delegation and appeal data', 'sabri-profiles-doctors' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-profile-central-domain'] = array(
			'eraser_friendly_name' => __( 'Sabri profile delegation and appeal data', 'sabri-profiles-doctors' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	private function schema_guard() {
		return SPD_Schema_Guard::central_ready()
			? true
			: new WP_Error( 'spd_central_privacy_schema_unavailable', __( 'Profile delegation and appeal storage is temporarily unavailable.', 'sabri-profiles-doctors' ) );
	}

	public function export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$guard = $this->schema_guard();
		if ( is_wp_error( $guard ) ) { return $guard; }
		$page = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * self::PAGE_SIZE;
		$user_id = absint( $user->ID );

		$delegations_table = SPD_Central_Profile::delegation_table();
		$appeals_table = SPD_Central_Profile::appeals_table();

		$wpdb->last_error = '';
		$delegations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,profile_id,owner_user_id,delegate_user_id,scopes,status,expires_at,version,created_at,updated_at FROM {$delegations_table} WHERE owner_user_id=%d OR delegate_user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id, $user_id, self::PAGE_SIZE + 1, $offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_central_privacy_export_failed', __( 'Profile delegation data could not be read for export.', 'sabri-profiles-doctors' ) ); }

		$wpdb->last_error = '';
		$appeals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,appeal_uuid,report_id,requested_by,reason,status,reviewer_id,decision_note,version,created_at,updated_at FROM {$appeals_table} WHERE requested_by=%d OR reviewer_id=%d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id, $user_id, self::PAGE_SIZE + 1, $offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_central_privacy_export_failed', __( 'Profile appeal data could not be read for export.', 'sabri-profiles-doctors' ) ); }

		$data = array();
		foreach ( array_slice( (array) $delegations, 0, self::PAGE_SIZE ) as $row ) {
			$role = $user_id === absint( $row['owner_user_id'] ) ? 'owner' : 'delegate';
			$data[] = array(
				'group_id'    => 'sabri-profile-delegations',
				'group_label' => __( 'Profile management delegations', 'sabri-profiles-doctors' ),
				'item_id'     => 'profile-delegation-' . absint( $row['id'] ),
				'data'        => array(
					array( 'name' => 'Relationship role', 'value' => $role ),
					array( 'name' => 'Scopes', 'value' => (string) $row['scopes'] ),
					array( 'name' => 'Status', 'value' => (string) $row['status'] ),
					array( 'name' => 'Expires', 'value' => (string) $row['expires_at'] ),
					array( 'name' => 'Version', 'value' => (string) absint( $row['version'] ) ),
					array( 'name' => 'Created', 'value' => (string) $row['created_at'] ),
					array( 'name' => 'Updated', 'value' => (string) $row['updated_at'] ),
				),
			);
		}
		foreach ( array_slice( (array) $appeals, 0, self::PAGE_SIZE ) as $row ) {
			$is_requester = $user_id === absint( $row['requested_by'] );
			$is_reviewer = $user_id === absint( $row['reviewer_id'] );
			$role = $is_requester && $is_reviewer ? 'requester_and_reviewer' : ( $is_requester ? 'requester' : 'reviewer' );
			$appeal_data = array(
				array( 'name' => 'Appeal ID', 'value' => (string) $row['appeal_uuid'] ),
				array( 'name' => 'Report ID', 'value' => (string) absint( $row['report_id'] ) ),
				array( 'name' => 'Relationship role', 'value' => $role ),
				array( 'name' => 'Status', 'value' => (string) $row['status'] ),
				array( 'name' => 'Version', 'value' => (string) absint( $row['version'] ) ),
				array( 'name' => 'Created', 'value' => (string) $row['created_at'] ),
				array( 'name' => 'Updated', 'value' => (string) $row['updated_at'] ),
			);
			if ( $is_requester ) { $appeal_data[] = array( 'name' => 'Reason', 'value' => (string) $row['reason'] ); }
			if ( ( $is_requester || $is_reviewer ) && '' !== trim( (string) $row['decision_note'] ) ) { $appeal_data[] = array( 'name' => 'Decision note', 'value' => (string) $row['decision_note'] ); }
			$data[] = array(
				'group_id'    => 'sabri-profile-appeals',
				'group_label' => __( 'Profile report appeals', 'sabri-profiles-doctors' ),
				'item_id'     => 'profile-appeal-' . sanitize_text_field( (string) $row['appeal_uuid'] ),
				'data'        => $appeal_data,
			);
		}

		$has_more = count( (array) $delegations ) > self::PAGE_SIZE || count( (array) $appeals ) > self::PAGE_SIZE;
		return array( 'data' => $data, 'done' => ! $has_more );
	}

	public function erase( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$guard = $this->schema_guard();
		if ( is_wp_error( $guard ) ) { return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( $guard->get_error_message() ), 'done' => false ); }

		$user_id = absint( $user->ID );
		$removed = false;
		$retained = false;
		$retry = false;
		$messages = array();

		if ( apply_filters( 'spd_profile_delegation_legal_hold', false, $user_id ) ) {
			$retained = true;
			$messages[] = __( 'Profile delegation records are retained under an active legal or governance hold.', 'sabri-profiles-doctors' );
		} else {
			$table = SPD_Central_Profile::delegation_table();
			$wpdb->last_error = '';
			$count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE owner_user_id=%d OR delegate_user_id=%d", $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) {
				$retry = true; $retained = true;
				$messages[] = __( 'Profile delegation data could not be read safely for erasure and requires a retry.', 'sabri-profiles-doctors' );
			} else {
				$count = absint( $count_raw );
				if ( $count ) {
					$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE owner_user_id=%d OR delegate_user_id=%d", $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( false === $deleted ) { $retry = true; $retained = true; $messages[] = __( 'Profile delegation data could not yet be erased and requires a retry.', 'sabri-profiles-doctors' ); }
					else { $removed = true; }
				}
			}
		}

		if ( apply_filters( 'spd_profile_appeal_legal_hold', false, $user_id ) ) {
			$retained = true;
			$messages[] = __( 'Profile appeal records are retained under an active legal, safety, or governance hold.', 'sabri-profiles-doctors' );
		} else {
			$table = SPD_Central_Profile::appeals_table();
			$wpdb->last_error = '';
			$requester_count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE requested_by=%d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$requester_count_error = (string) $wpdb->last_error;
			$wpdb->last_error = '';
			$reviewer_count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE reviewer_id=%d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$reviewer_count_error = (string) $wpdb->last_error;
			if ( '' !== $requester_count_error || '' !== $reviewer_count_error ) {
				$retry = true; $retained = true;
				$messages[] = __( 'Profile appeal data could not be read safely for erasure and requires a retry.', 'sabri-profiles-doctors' );
			} else {
				$requester_count = absint( $requester_count_raw );
				$reviewer_count = absint( $reviewer_count_raw );
				$result = SPD_DB::transaction( function() use ( $wpdb, $table, $user_id, $requester_count, $reviewer_count ) {
					if ( $requester_count ) {
						$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET requested_by=0,reason='',updated_at=%s WHERE requested_by=%d", SPD_Helpers::now(), $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						if ( false === $changed ) { return new WP_Error( 'spd_appeal_requester_erasure_failed', __( 'Profile appeal requester data could not be erased.', 'sabri-profiles-doctors' ) ); }
					}
					if ( $reviewer_count ) {
						$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET reviewer_id=0,decision_note='',updated_at=%s WHERE reviewer_id=%d", SPD_Helpers::now(), $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						if ( false === $changed ) { return new WP_Error( 'spd_appeal_reviewer_erasure_failed', __( 'Profile appeal reviewer identity and authored decision note could not be erased.', 'sabri-profiles-doctors' ) ); }
					}
					return true;
				} );
				if ( is_wp_error( $result ) ) { $retry = true; $retained = true; $messages[] = $result->get_error_message(); }
				elseif ( $requester_count || $reviewer_count ) {
					$removed = true;
					$retained = true;
					$messages[] = __( 'Appeal requester text, reviewer-authored decision text and user identifiers were erased; the minimal non-identifying appeal workflow record is retained for safety and audit integrity.', 'sabri-profiles-doctors' );
				}
			}
		}

		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => ! $retry );
	}
}
