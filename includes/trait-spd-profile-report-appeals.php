<?php
defined( 'ABSPATH' ) || exit;

/**
 * Strict safety-report and report-appeal workflow.
 *
 * This trait is composed into SPD_Profile_Repository through the lifecycle trait.
 * It keeps report/appeal truth inside File 03, uses the repository's shared
 * transaction/idempotency/event primitives, and fails closed on DB uncertainty.
 */
trait SPD_Profile_Report_Appeals {
	public function create_safety_report_strict( $public_id, $reporter_user_id, $reason, $details, $idempotency_key = '' ) {
		global $wpdb;
		$reporter_user_id = absint( $reporter_user_id );
		if ( ! $reporter_user_id ) {
			return new WP_Error( 'spd_login_required', __( 'An eligible signed-in account is required to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) );
		}
		$health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $health['status'] ?? '' ) ) {
			return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$claims = SPD_Membership_Adapter::claims( $reporter_user_id );
		if ( ! $claims ) {
			return new WP_Error( 'spd_membership_claim_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( empty( $claims['eligible'] ) || ! empty( $claims['suspended'] ) ) {
			return new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		if ( SPD_Observability::safe_mode() ) {
			return new WP_Error( 'spd_safe_mode', __( 'Profile reporting is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$profile = $this->find_by_public_id_strict( (string) $public_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $reporter_user_id ) ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$reason = sanitize_key( $reason );
		$details = SPD_Helpers::sanitize_multiline( $details, 3000 );
		if ( ! in_array( $reason, SPD_Central_Profile::report_reasons(), true ) ) {
			return new WP_Error( 'spd_invalid_report_reason', __( 'Choose a valid report reason.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		if ( SPD_Helpers::text_length( $details ) < 10 ) {
			return new WP_Error( 'spd_report_details_required', __( 'Provide enough detail for a fair review.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $profile['public_id'], $reason, $details ) ) );
		$idem = $this->idempotency_begin( $reporter_user_id, 'create_safety_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }

		$reports = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE reporter_user_id=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)", $reporter_user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			$this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key );
			return new WP_Error( 'spd_report_store_unavailable', __( 'Profile reporting is temporarily unavailable because report-rate evidence could not be read safely.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$count = absint( $count_raw );
		if ( $count >= 5 || ! SPD_Helpers::consume_rate_limit( 'profile_report_' . $reporter_user_id, 5, DAY_IN_SECONDS ) ) {
			$this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key );
			return new WP_Error( 'spd_report_rate_limited', __( 'Too many reports were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) );
		}
		$uuid = SPD_Helpers::public_id();
		$now = SPD_Helpers::now();
		$critical = in_array( $reason, array( 'harm', 'child_safety', 'impersonation', 'privacy', 'privacy_breach', 'scam' ), true );
		$severity = $critical ? 'high' : 'normal';
		$dedupe = hash( 'sha256', gmdate( 'Y-m-d' ) . ':' . $profile['id'] . ':' . $reason . ':' . hash( 'sha256', $details ) );
		$response = array( 'report_uuid' => $uuid, 'status' => 'submitted' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $reports, $uuid, $profile, $reporter_user_id, $reason, $details, $severity, $now, $dedupe, $response, $idempotency_key ) {
			$ok = $wpdb->insert( $reports, array(
				'report_uuid' => $uuid,
				'profile_id' => $profile['id'],
				'reporter_user_id' => $reporter_user_id,
				'reason' => $reason,
				'details' => $details,
				'dedupe_hash' => $dedupe,
				'status' => 'submitted',
				'severity' => $severity,
				'version' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			) );
			if ( ! $ok ) { return new WP_Error( 'spd_report_duplicate_or_failed', __( 'This report could not be recorded or may already exist.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileReported.v1', 'report', $uuid, array( 'profile_public_id' => $profile['public_id'], 'reason' => $reason, 'severity' => $severity ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $reporter_user_id, 'create_safety_report', $idempotency_key, $response ) ) {
				return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) );
			}
			return true;
		} );
		if ( is_wp_error( $result ) ) {
			$this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key );
			return $result;
		}
		return $response;
	}

	public function request_report_appeal_strict( $report_uuid, $requester_id, $reason, $idempotency_key = '' ) {
		global $wpdb;
		$requester_id = absint( $requester_id );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 2000 );
		if ( ! $requester_id || ! SPD_Membership_Adapter::is_member_eligible( $requester_id ) ) {
			return new WP_Error( 'spd_account_ineligible', __( 'An eligible account is required to appeal a report.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		if ( SPD_Helpers::text_length( $reason ) < 10 ) {
			return new WP_Error( 'spd_appeal_reason_required', __( 'Provide a clear reason for the appeal.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		$reports = SPD_DB::table( 'reports' );
		$report_uuid = sanitize_text_field( $report_uuid );
		$wpdb->last_error = '';
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id,reporter_user_id,status FROM {$reports} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			return new WP_Error( 'spd_report_store_unavailable', __( 'The profile-report store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( ! $report || absint( $report['reporter_user_id'] ) !== $requester_id || ! in_array( $report['status'], array( 'rejected', 'closed', 'actioned' ), true ) ) {
			return new WP_Error( 'spd_appeal_unavailable', __( 'This report is not eligible for appeal by this account.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $report_uuid, $requester_id, $reason ) ) );
		$idem = $this->idempotency_begin( $requester_id, 'request_report_appeal', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$table = SPD_Central_Profile::appeals_table();
		$uuid = SPD_Helpers::public_id();
		$now = SPD_Helpers::now();
		$response = array( 'appeal_uuid' => $uuid, 'status' => 'submitted' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $report, $report_uuid, $requester_id, $reason, $uuid, $now, $response, $idempotency_key ) {
			$ok = $wpdb->insert( $table, array(
				'appeal_uuid' => $uuid,
				'report_id' => absint( $report['id'] ),
				'requested_by' => $requester_id,
				'reason' => $reason,
				'status' => 'submitted',
				'version' => 1,
				'created_at' => $now,
				'updated_at' => $now,
			) );
			if ( ! $ok ) { return new WP_Error( 'spd_appeal_duplicate_or_failed', __( 'An appeal already exists or could not be recorded.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileReportAppealed.v1', 'report', $report_uuid, array( 'appeal_uuid' => $uuid, 'requested_by' => $requester_id ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $requester_id, 'request_report_appeal', $idempotency_key, $response ) ) {
				return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) );
			}
			return true;
		} );
		if ( is_wp_error( $result ) ) {
			$this->idempotency_fail( $requester_id, 'request_report_appeal', $idempotency_key );
			return $result;
		}
		return $response;
	}

	public static function report_appeal_transition_targets( $from ) {
		$map = array(
			'submitted' => array( 'in_review' ),
			'in_review' => array( 'upheld', 'rejected' ),
			'upheld' => array(),
			'rejected' => array(),
		);
		$from = sanitize_key( $from );
		return isset( $map[ $from ] ) ? $map[ $from ] : array();
	}

	public function report_appeal_review_queue( $actor_id, $limit = 100 ) {
		global $wpdb;
		$guard = SPD_Authorization::moderation_guard( absint( $actor_id ) );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$appeals = SPD_Central_Profile::appeals_table();
		$reports = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.appeal_uuid,a.reason,a.status,a.reviewer_id,a.version,a.created_at,r.report_uuid,r.status AS report_status FROM {$appeals} a INNER JOIN {$reports} r ON r.id=a.report_id WHERE a.status IN ('submitted','in_review') ORDER BY a.created_at ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $rows ) ) {
			return new WP_Error( 'spd_appeal_store_unavailable', __( 'The report-appeal queue is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return array_map( static function( $row ) {
			return array(
				'appeal_uuid' => sanitize_text_field( (string) $row['appeal_uuid'] ),
				'reason' => SPD_Helpers::sanitize_multiline( (string) $row['reason'], 2000 ),
				'status' => sanitize_key( (string) $row['status'] ),
				'reviewer_id' => absint( $row['reviewer_id'] ),
				'version' => absint( $row['version'] ),
				'created_at' => sanitize_text_field( (string) $row['created_at'] ),
				'report_uuid' => sanitize_text_field( (string) $row['report_uuid'] ),
				'report_status' => sanitize_key( (string) $row['report_status'] ),
			);
		}, $rows );
	}

	public function moderate_report_appeal( $appeal_uuid, $actor_id, $new_status, $expected_version, $note = '', $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$guard = SPD_Authorization::moderation_guard( $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$appeal_uuid = sanitize_text_field( $appeal_uuid );
		$new_status = sanitize_key( $new_status );
		$expected_version = absint( $expected_version );
		$note = SPD_Helpers::sanitize_multiline( $note, 2000 );
		if ( ! $expected_version ) { return new WP_Error( 'spd_version_required', __( 'A current appeal version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		$appeals = SPD_Central_Profile::appeals_table();
		$reports = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT a.*,r.report_uuid,r.status AS report_status,r.version AS report_version FROM {$appeals} a INNER JOIN {$reports} r ON r.id=a.report_id WHERE a.appeal_uuid=%s LIMIT 1", $appeal_uuid ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_appeal_store_unavailable', __( 'The report-appeal store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( ! $row ) { return new WP_Error( 'spd_appeal_unavailable', __( 'This report appeal is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( ! in_array( $new_status, self::report_appeal_transition_targets( $row['status'] ), true ) ) {
			return new WP_Error( 'spd_invalid_appeal_transition', __( 'The requested appeal transition is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		if ( in_array( $new_status, array( 'upheld', 'rejected' ), true ) && SPD_Helpers::text_length( $note ) < 3 ) {
			return new WP_Error( 'spd_appeal_decision_note_required', __( 'A decision note is required for the appeal outcome.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $appeal_uuid, $new_status, $expected_version, $note ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'moderate_report_appeal', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$new_version = $expected_version + 1;
		$response = array( 'appeal_uuid' => $appeal_uuid, 'status' => $new_status, 'version' => $new_version, 'report_uuid' => sanitize_text_field( (string) $row['report_uuid'] ) );
		$result = SPD_DB::transaction( function() use ( $wpdb, $appeals, $reports, $row, $appeal_uuid, $actor_id, $new_status, $expected_version, $new_version, $note, $response, $idempotency_key ) {
			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE {$appeals} SET status=%s,reviewer_id=%d,decision_note=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d",
				$new_status,
				$actor_id,
				$note,
				SPD_Helpers::now(),
				absint( $row['id'] ),
				$expected_version
			) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This appeal changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( 'upheld' === $new_status ) {
				$report_updated = $wpdb->query( $wpdb->prepare(
					"UPDATE {$reports} SET status='in_review',assigned_to=%d,version=version+1,updated_at=%s WHERE id=%d AND version=%d AND status IN ('rejected','closed','actioned')",
					$actor_id,
					SPD_Helpers::now(),
					absint( $row['report_id'] ),
					absint( $row['report_version'] )
				) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $report_updated ) { return new WP_Error( 'spd_report_reopen_conflict', __( 'The underlying report changed while its appeal was being upheld.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				$reopen_event = $this->event( 'ProfileReportReopenedByAppeal.v1', 'report', $row['report_uuid'], array( 'appeal_uuid' => $appeal_uuid, 'actor_id' => $actor_id, 'from' => $row['report_status'], 'to' => 'in_review', 'version' => absint( $row['report_version'] ) + 1 ) );
				if ( is_wp_error( $reopen_event ) ) { return $reopen_event; }
			}
			$event = $this->event( 'ProfileReportAppealReviewed.v1', 'report', $row['report_uuid'], array( 'appeal_uuid' => $appeal_uuid, 'actor_id' => $actor_id, 'from' => $row['status'], 'to' => $new_status, 'decision_note_hash' => hash( 'sha256', $note ), 'version' => $new_version ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $actor_id, 'moderate_report_appeal', $idempotency_key, $response ) ) {
				return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The appeal-review replay result could not be committed.', 'sabri-profiles-doctors' ) );
			}
			return true;
		} );
		if ( is_wp_error( $result ) ) {
			$this->idempotency_fail( $actor_id, 'moderate_report_appeal', $idempotency_key );
			return $result;
		}
		return $response;
	}
}

/** REST overrides for the strict report/appeal path and the reviewer workflow. */
function spd_file03_report_rest_response( $result, $success = 200 ) {
	$trace = SPD_Helpers::trace_id();
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
		$response = new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'trace_id' => $trace ), $status );
	} else {
		$response = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $success );
	}
	$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );
	$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
	$response->header( 'X-SPD-Trace-ID', $trace );
	$response->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION );
	return $response;
}

function spd_file03_report_rest_eligible() {
	if ( ! is_user_logged_in() ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
	$claims = SPD_Membership_Adapter::claims( get_current_user_id() );
	return $claims && ! empty( $claims['eligible'] ) && empty( $claims['suspended'] )
		? true
		: new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
}

function spd_file03_appeal_review_permission() {
	if ( ! is_user_logged_in() ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
	return SPD_Authorization::moderation_guard( get_current_user_id() );
}

function spd_file03_register_strict_report_routes() {
	$uuid = array( 'validate_callback' => static function( $value ) { return SPD_Helpers::valid_uuid( (string) $value ); } );
	register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/safety-reports', array(
		'methods' => WP_REST_Server::CREATABLE,
		'callback' => static function( WP_REST_Request $request ) {
			$payload = (array) $request->get_json_params();
			if ( array_diff( array_keys( $payload ), array( 'reason', 'details' ) ) ) { return spd_file03_report_rest_response( new WP_Error( 'spd_unknown_safety_report_field', __( 'One or more submitted fields are not supported for this operation.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) ); }
			$result = SPD_Profile_Repository::instance()->create_safety_report_strict( $request['public_id'], get_current_user_id(), $payload['reason'] ?? '', $payload['details'] ?? '', sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) ) );
			return spd_file03_report_rest_response( $result, 201 );
		},
		'permission_callback' => 'spd_file03_report_rest_eligible',
		'args' => array( 'public_id' => $uuid ),
	), true );
	register_rest_route( 'sabri-profiles/v1', '/reports/(?P<report_uuid>[0-9a-fA-F-]{36})/appeal', array(
		'methods' => WP_REST_Server::CREATABLE,
		'callback' => static function( WP_REST_Request $request ) {
			$payload = (array) $request->get_json_params();
			if ( array_diff( array_keys( $payload ), array( 'reason' ) ) ) { return spd_file03_report_rest_response( new WP_Error( 'spd_unknown_appeal_field', __( 'One or more submitted fields are not supported for this operation.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) ); }
			$result = SPD_Profile_Repository::instance()->request_report_appeal_strict( $request['report_uuid'], get_current_user_id(), $payload['reason'] ?? '', sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) ) );
			return spd_file03_report_rest_response( $result, 201 );
		},
		'permission_callback' => 'spd_file03_report_rest_eligible',
		'args' => array( 'report_uuid' => $uuid ),
	), true );
	register_rest_route( 'sabri-profiles/v1', '/appeals/review-queue', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => static function( WP_REST_Request $request ) { return spd_file03_report_rest_response( SPD_Profile_Repository::instance()->report_appeal_review_queue( get_current_user_id(), absint( $request->get_param( 'limit' ) ?: 100 ) ) ); },
		'permission_callback' => 'spd_file03_appeal_review_permission',
	), true );
	register_rest_route( 'sabri-profiles/v1', '/appeals/(?P<appeal_uuid>[0-9a-fA-F-]{36})/review', array(
		'methods' => WP_REST_Server::EDITABLE,
		'callback' => static function( WP_REST_Request $request ) {
			$payload = (array) $request->get_json_params();
			if ( array_diff( array_keys( $payload ), array( 'status', 'version', 'note' ) ) ) { return spd_file03_report_rest_response( new WP_Error( 'spd_unknown_appeal_review_field', __( 'One or more submitted fields are not supported for this operation.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) ); }
			$result = SPD_Profile_Repository::instance()->moderate_report_appeal( $request['appeal_uuid'], get_current_user_id(), $payload['status'] ?? '', absint( $payload['version'] ?? 0 ), $payload['note'] ?? '', sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) ) );
			return spd_file03_report_rest_response( $result );
		},
		'permission_callback' => 'spd_file03_appeal_review_permission',
		'args' => array( 'appeal_uuid' => $uuid ),
	), true );
}
add_action( 'rest_api_init', 'spd_file03_register_strict_report_routes', 20 );
