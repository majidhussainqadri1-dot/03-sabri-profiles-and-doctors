<?php
defined( 'ABSPATH' ) || exit;

final class SPD_REST {
	public function hooks() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		$uuid_args = array( 'validate_callback' => array( $this, 'valid_uuid_arg' ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_profile' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid_args ) ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_profile' ), 'permission_callback' => array( $this, 'eligible_member' ), 'args' => array( 'public_id' => $uuid_args ) ),
		) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/timeline', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_timeline' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'public_id' => $uuid_args,
				'limit' => array( 'sanitize_callback' => 'absint', 'default' => 20, 'validate_callback' => static function ( $value ) { return absint( $value ) >= 1 && absint( $value ) <= 50; } ),
				'cursor' => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static function ( $value ) { return strlen( (string) $value ) <= 512; } ),
				'provider' => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => static function ( $value ) { return strlen( (string) $value ) <= 40; } ),
			),
		) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/reports', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_report' ), 'permission_callback' => array( $this, 'eligible_member' ), 'args' => array( 'public_id' => $uuid_args ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/moderation', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'moderate_profile' ), 'permission_callback' => array( $this, 'can_moderate' ), 'args' => array( 'public_id' => $uuid_args ) ) );
		register_rest_route( 'sabri-profiles/v1', '/reports/(?P<report_uuid>[0-9a-fA-F-]{36})', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'moderate_report' ), 'permission_callback' => array( $this, 'can_moderate' ), 'args' => array( 'report_uuid' => $uuid_args ) ) );
		register_rest_route( 'sabri-profiles/v1', '/me/edit-model', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'edit_model' ), 'permission_callback' => array( $this, 'eligible_member' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/me/professional', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'submit_professional' ), 'permission_callback' => array( $this, 'eligible_member' ) ) );
	}

	public function valid_uuid_arg( $value ) { return SPD_Helpers::valid_uuid( (string) $value ); }
	public function eligible_member() {
		if ( ! is_user_logged_in() ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		return SPD_Membership_Adapter::is_member_eligible( get_current_user_id() ) ? true : new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible for protected profile actions.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
	}
	public function can_moderate() { return SPD_Authorization::moderation_guard( get_current_user_id() ); }
	public function get_profile( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); return $this->response( SPD_Profile_Repository::instance()->public_dto( $r['public_id'], get_current_user_id() ), $t ); }
	public function edit_model( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); return $this->response( SPD_Profile_Repository::instance()->edit_model( get_current_user_id(), absint( $r->get_param( 'target_user_id' ) ) ), $t ); }
	public function submit_professional( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); $p = (array) $r->get_json_params(); $fields = isset( $p['fields'] ) && is_array( $p['fields'] ) ? $p['fields'] : array(); $idem = $this->idempotency_key( $r ); return $this->response( SPD_Profile_Repository::instance()->submit_professional_fields( get_current_user_id(), $fields, $this->expected_version( $r ), $idem, empty( $p['save_draft'] ) ), $t ); }
	public function update_profile( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); $repo = SPD_Profile_Repository::instance(); $profile = $repo->find_by_public_id( $r['public_id'] ); if ( ! $profile || ! SPD_Authorization::can_edit_profile( $profile, get_current_user_id() ) ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ), $t ); } $params = (array) $r->get_json_params(); $params['target_user_id'] = absint( $profile['user_id'] ); return $this->response( $repo->update_profile( get_current_user_id(), $params, $this->expected_version( $r ), $this->idempotency_key( $r ) ), $t ); }
	public function get_timeline( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); return $this->response( SPD_Timeline::query( $r['public_id'], array( 'limit' => $r->get_param( 'limit' ), 'cursor' => $r->get_param( 'cursor' ), 'provider' => $r->get_param( 'provider' ) ), get_current_user_id() ), $t ); }
	public function create_report( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); $p = (array) $r->get_json_params(); return $this->response( SPD_Profile_Repository::instance()->create_report( $r['public_id'], get_current_user_id(), $p['reason'] ?? '', $p['details'] ?? '', $this->idempotency_key( $r ) ), $t, 201 ); }
	public function moderate_profile( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); $p = (array) $r->get_json_params(); return $this->response( SPD_Profile_Repository::instance()->moderate_profile( $r['public_id'], get_current_user_id(), $p['state'] ?? '', $this->expected_version( $r ), $p['reason'] ?? '', $this->idempotency_key( $r ) ), $t ); }
	public function moderate_report( WP_REST_Request $r ) { $t = SPD_Helpers::trace_id(); $p = (array) $r->get_json_params(); return $this->response( SPD_Profile_Repository::instance()->moderate_report( $r['report_uuid'], get_current_user_id(), $p['status'] ?? '', $this->expected_version( $r ), $p['note'] ?? '', $this->idempotency_key( $r ) ), $t ); }

	private function idempotency_key( WP_REST_Request $r ) {
		return trim( sanitize_text_field( (string) $r->get_header( 'Idempotency-Key' ) ) );
	}

	private function expected_version( WP_REST_Request $r ) {
		$raw = trim( (string) $r->get_header( 'If-Match' ) );
		if ( '' !== $raw ) {
			if ( 1 !== preg_match( '/^"?([1-9][0-9]*)"?$/', $raw, $matches ) ) { return 0; }
			return absint( $matches[1] );
		}
		return absint( $r->get_param( 'version' ) );
	}

	private function response( $result, $trace, $success = 200 ) {
		$error = is_wp_error( $result );
		if ( $error ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
			$response = new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'trace_id' => $trace ), $status );
		} else {
			$response = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $success );
		}
		$response->header( 'X-SPD-Trace-ID', $trace );
		$response->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION );
		$response->header( 'Cache-Control', 'private, no-store' );
		if ( $error || is_user_logged_in() ) { $response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' ); }
		return $response;
	}
}
