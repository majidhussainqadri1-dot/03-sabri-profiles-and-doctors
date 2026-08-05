<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Privacy {
	const PAGE_SIZE = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profile-domain'] = array(
			'exporter_friendly_name' => __( 'Sabri profile domain', 'sabri-profiles-doctors' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-profile-domain'] = array(
			'eraser_friendly_name' => __( 'Sabri profile domain', 'sabri-profiles-doctors' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$page = max( 1, absint( $page ) );
		$data = array();
		if ( 1 === $page ) {
			$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user->ID, false );
			if ( $profile ) {
				$profile_data = array(
					array( 'name' => 'Public profile ID', 'value' => $profile['public_id'] ),
					array( 'name' => 'Canonical slug', 'value' => $profile['slug'] ),
					array( 'name' => 'Profile type', 'value' => $profile['profile_type'] ),
					array( 'name' => 'Profile state', 'value' => $profile['state'] ),
					array( 'name' => 'Version', 'value' => (string) $profile['version'] ),
					array( 'name' => 'Biography', 'value' => $profile['bio'] ),
					array( 'name' => 'Country', 'value' => $profile['country'] ),
					array( 'name' => 'City', 'value' => $profile['city'] ),
					array( 'name' => 'Languages', 'value' => $profile['languages'] ),
					array( 'name' => 'Classical books studied', 'value' => $profile['studied_books'] ),
					array( 'name' => 'Avatar attachment ID', 'value' => (string) $profile['avatar_id'] ),
					array( 'name' => 'Cover attachment ID', 'value' => (string) $profile['cover_id'] ),
				);
				foreach ( $profile['fields'] as $key => $field ) {
					$profile_data[] = array( 'name' => 'Audience: ' . $key, 'value' => $field['audience'] );
				}
				$data[] = array(
					'group_id'    => 'sabri-profile-domain',
					'group_label' => __( 'Sabri Profile', 'sabri-profiles-doctors' ),
					'item_id'     => 'profile-' . $profile['public_id'],
					'data'        => $profile_data,
				);
			}
		}

		$offset  = ( $page - 1 ) * self::PAGE_SIZE;
		$reports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT report_uuid,reason,details,status,severity,version,created_at,updated_at FROM " . SPD_DB::table( 'reports' ) . " WHERE reporter_user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d",
				absint( $user->ID ), self::PAGE_SIZE + 1, $offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$professional = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT submission_uuid,payload_json,status,version,created_at,updated_at FROM " . SPD_DB::table( 'professional_submissions' ) . " WHERE submitted_by=%d ORDER BY id ASC LIMIT %d OFFSET %d",
				absint( $user->ID ), self::PAGE_SIZE + 1, $offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_more = count( $reports ) > self::PAGE_SIZE || count( $professional ) > self::PAGE_SIZE;
		foreach ( array_slice( $reports, 0, self::PAGE_SIZE ) as $report ) {
			$data[] = array(
				'group_id'    => 'sabri-profile-reports',
				'group_label' => __( 'Profile reports submitted', 'sabri-profiles-doctors' ),
				'item_id'     => 'profile-report-' . $report['report_uuid'],
				'data'        => array(
					array( 'name' => 'Report ID', 'value' => $report['report_uuid'] ),
					array( 'name' => 'Reason', 'value' => $report['reason'] ),
					array( 'name' => 'Details', 'value' => $report['details'] ),
					array( 'name' => 'Status', 'value' => $report['status'] ),
					array( 'name' => 'Severity', 'value' => $report['severity'] ),
					array( 'name' => 'Version', 'value' => (string) $report['version'] ),
					array( 'name' => 'Created', 'value' => $report['created_at'] ),
					array( 'name' => 'Updated', 'value' => $report['updated_at'] ),
				),
			);
		}
		foreach ( array_slice( $professional, 0, self::PAGE_SIZE ) as $submission ) {
			$fields = json_decode( $submission['payload_json'], true );
			$data[] = array(
				'group_id'    => 'sabri-professional-profile-submissions',
				'group_label' => __( 'Professional profile submissions', 'sabri-profiles-doctors' ),
				'item_id'     => 'professional-submission-' . $submission['submission_uuid'],
				'data'        => array(
					array( 'name' => 'Submission ID', 'value' => $submission['submission_uuid'] ),
					array( 'name' => 'Submitted fields', 'value' => is_array( $fields ) ? wp_json_encode( $fields ) : '' ),
					array( 'name' => 'Status', 'value' => $submission['status'] ),
					array( 'name' => 'Version', 'value' => (string) $submission['version'] ),
					array( 'name' => 'Created', 'value' => $submission['created_at'] ),
					array( 'name' => 'Updated', 'value' => $submission['updated_at'] ),
				),
			);
		}
		return array( 'data' => $data, 'done' => ! $has_more );
	}

	public function erase( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user || absint( $page ) > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$repo     = SPD_Profile_Repository::instance();
		$result   = $repo->erase_profile( $user->ID );
		$removed  = ! empty( $result['removed'] );
		$retained = ! empty( $result['retained'] );
		$retry    = ! empty( $result['retry'] );
		$messages = (array) ( $result['messages'] ?? array() );

		if ( apply_filters( 'spd_profile_report_legal_hold', false, absint( $user->ID ) ) ) {
			$retained   = true;
			$messages[] = __( 'Profile-report evidence is retained under an active legal, safety, or governance hold.', 'sabri-profiles-doctors' );
		} else {
			$table   = SPD_DB::table( 'reports' );
			$reports = $wpdb->get_results( $wpdb->prepare( "SELECT id,report_uuid FROM {$table} WHERE reporter_user_id=%d", absint( $user->ID ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$erased = SPD_DB::transaction(
				function () use ( $wpdb, $table, $reports, $repo, $user ) {
					foreach ( $reports as $report ) {
						$changed = $wpdb->update(
							$table,
							array( 'reporter_user_id' => 0, 'details' => '', 'decision_note' => '', 'dedupe_hash' => hash( 'sha256', $report['report_uuid'] . ':erased' ), 'updated_at' => SPD_Helpers::now() ),
							array( 'id' => absint( $report['id'] ), 'reporter_user_id' => absint( $user->ID ) )
						);
						if ( false === $changed ) { return new WP_Error( 'spd_report_erasure_failed', __( 'Profile-report data could not be erased.', 'sabri-profiles-doctors' ) ); }
					}
					if ( $reports ) {
						$event = $repo->event( 'ProfileReporterErased.v1', 'user', (string) absint( $user->ID ), array( 'report_count' => count( $reports ) ) );
						if ( is_wp_error( $event ) ) { return $event; }
					}
					return true;
				}
			);
			if ( is_wp_error( $erased ) ) {
				$retry      = true;
				$retained   = true;
				$messages[] = __( 'Some profile-report data could not yet be erased and requires a retry.', 'sabri-profiles-doctors' );
			} elseif ( $reports ) {
				$removed    = true;
				$retained   = true;
				$messages[] = __( 'Report text and reporter identity were erased; minimal non-identifying workflow records are retained for safety and audit integrity.', 'sabri-profiles-doctors' );
			}
		}

		if ( apply_filters( 'spd_professional_submission_legal_hold', false, absint( $user->ID ) ) ) {
			$retained   = true;
			$messages[] = __( 'Professional-profile proposal data is retained under an active legal or governance hold.', 'sabri-profiles-doctors' );
		} else {
			$table = SPD_DB::table( 'professional_submissions' );
			$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_by=%d", absint( $user->ID ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$erased = SPD_DB::transaction(
				function () use ( $wpdb, $table, $count, $repo, $user ) {
					$deleted = $wpdb->delete( $table, array( 'submitted_by' => absint( $user->ID ) ) );
					if ( false === $deleted ) { return new WP_Error( 'spd_professional_erasure_failed', __( 'Professional-profile proposals could not be erased.', 'sabri-profiles-doctors' ) ); }
					if ( $count ) {
						$event = $repo->event( 'ProfileProfessionalSubmissionsErased.v1', 'user', (string) absint( $user->ID ), array( 'submission_count' => $count ) );
						if ( is_wp_error( $event ) ) { return $event; }
					}
					return true;
				}
			);
			if ( is_wp_error( $erased ) ) {
				$retry      = true;
				$retained   = true;
				$messages[] = __( 'Professional-profile proposal data could not yet be erased and requires a retry.', 'sabri-profiles-doctors' );
			} elseif ( $count ) {
				$removed    = true;
				$messages[] = __( 'File 03 professional-profile proposals were erased. File 09 retains only its independently governed verification record.', 'sabri-profiles-doctors' );
			}
		}
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => ! $retry );
	}

}
