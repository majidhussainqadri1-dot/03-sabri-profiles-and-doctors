<?php
defined( 'ABSPATH' ) || exit;

final class SPD_REST {
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'sabri-profiles/v1',
			'/profiles/(?P<public_id>[0-9a-fA-F-]{36})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'logged_in' ),
				),
			)
		);
		register_rest_route(
			'sabri-profiles/v1',
			'/profiles/(?P<public_id>[0-9a-fA-F-]{36})/timeline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_timeline' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit'    => array( 'sanitize_callback' => 'absint', 'default' => 20 ),
					'cursor'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'provider' => array( 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);
		register_rest_route(
			'sabri-profiles/v1',
			'/profiles/(?P<public_id>[0-9a-fA-F-]{36})/reports',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_report' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);
		register_rest_route(
			'sabri-profiles/v1',
			'/profiles/(?P<public_id>[0-9a-fA-F-]{36})/moderation',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'moderate_profile' ),
				'permission_callback' => array( $this, 'can_moderate' ),
			)
		);
		register_rest_route(
			'sabri-profiles/v1',
			'/reports/(?P<report_uuid>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'moderate_report' ),
				'permission_callback' => array( $this, 'can_moderate' ),
			)
		);
		register_rest_route(
			'sabri-profiles/v1',
			'/me/edit-model',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'edit_model' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);
	}

	public function logged_in() {
		return is_user_logged_in() ? true : new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) );
	}

	public function can_moderate() {
		return SPD_Membership_Adapter::can_moderate_profiles( get_current_user_id() ) ? true : new WP_Error( 'spd_forbidden', __( 'Profile moderation permission is required.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
	}

	public function get_profile( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$result = SPD_Profile_Repository::instance()->public_dto( $request['public_id'], get_current_user_id() );
		return $this->response( $result, $trace );
	}

	public function edit_model() {
		$trace = SPD_Helpers::trace_id();
		$result = SPD_Profile_Repository::instance()->edit_model( get_current_user_id() );
		return $this->response( $result, $trace );
	}

	public function update_profile( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$repo = SPD_Profile_Repository::instance();
		$profile = $repo->find_by_public_id( $request['public_id'] );
		if ( ! $profile || absint( $profile['user_id'] ) !== get_current_user_id() ) {
			return $this->response( new WP_Error( 'spd_forbidden', __( 'You are not authorized to change this profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ), $trace );
		}
		$expected = absint( $request->get_header( 'If-Match' ) );
		if ( ! $expected ) {
			$expected = absint( $request->get_param( 'version' ) );
		}
		$idempotency = sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) );
		$result = $repo->update_profile( get_current_user_id(), (array) $request->get_json_params(), $expected, $idempotency );
		return $this->response( $result, $trace );
	}

	public function get_timeline( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$result = SPD_Timeline::query(
			$request['public_id'],
			array( 'limit' => $request->get_param( 'limit' ), 'cursor' => $request->get_param( 'cursor' ), 'provider' => $request->get_param( 'provider' ) ),
			get_current_user_id()
		);
		return $this->response( $result, $trace );
	}

	public function create_report( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$params = (array) $request->get_json_params();
		$result = SPD_Profile_Repository::instance()->create_report(
			$request['public_id'],
			get_current_user_id(),
			$params['reason'] ?? '',
			$params['details'] ?? ''
		);
		return $this->response( $result, $trace, 201 );
	}

	public function moderate_profile( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$params = (array) $request->get_json_params();
		$result = SPD_Profile_Repository::instance()->moderate_profile( $request['public_id'], get_current_user_id(), $params['state'] ?? '', absint( $params['version'] ?? 0 ), $params['reason'] ?? '' );
		return $this->response( $result, $trace );
	}

	public function moderate_report( WP_REST_Request $request ) {
		$trace = SPD_Helpers::trace_id();
		$params = (array) $request->get_json_params();
		$result = SPD_Profile_Repository::instance()->moderate_report( $request['report_uuid'], get_current_user_id(), $params['status'] ?? '', absint( $params['version'] ?? 0 ), $params['note'] ?? '' );
		return $this->response( $result, $trace );
	}

	private function response( $result, $trace, $success_status = 200 ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
			$response = new WP_REST_Response(
				array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'trace_id' => $trace ),
				$status
			);
		} else {
			$response = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $success_status );
		}
		$response->header( 'X-SPD-Trace-ID', $trace );
		$response->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION );
		if ( is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'private, no-store' );
		}
		return $response;
	}
}
