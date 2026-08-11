<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Central_REST {
	public function hooks() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		$uuid = array( 'validate_callback' => static function ( $v ) { return SPD_Helpers::valid_uuid( (string) $v ); } );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/personal-site', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'personal_site' ),
			'permission_callback' => '__return_true',
			'args' => array( 'public_id' => $uuid ),
		) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/search-projection', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'search_projection' ),
			'permission_callback' => '__return_true',
			'args' => array( 'public_id' => $uuid ),
		) );
		register_rest_route( 'sabri-profiles/v1', '/me/personal-site', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'edit_model' ), 'permission_callback' => array( $this, 'eligible' ) ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_personal_site' ), 'permission_callback' => array( $this, 'eligible' ) ),
		) );
		register_rest_route( 'sabri-profiles/v1', '/me/share-link/rotate', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'rotate_share' ), 'permission_callback' => array( $this, 'eligible' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/me/delegates', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'delegates' ), 'permission_callback' => array( $this, 'eligible' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'grant_delegate' ), 'permission_callback' => array( $this, 'eligible' ) ),
		) );
		register_rest_route( 'sabri-profiles/v1', '/me/delegates/(?P<delegate_id>[0-9]+)', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'revoke_delegate' ), 'permission_callback' => array( $this, 'eligible' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/safety-reports', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'safety_report' ), 'permission_callback' => array( $this, 'eligible' ), 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/reports/(?P<report_uuid>[0-9a-fA-F-]{36})/appeal', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'appeal' ), 'permission_callback' => array( $this, 'eligible' ), 'args' => array( 'report_uuid' => $uuid ) ) );
	}

	public function eligible() {
		if ( ! is_user_logged_in() ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $health['status'] ?? '' ) ) {
			return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$claims = SPD_Membership_Adapter::claims( get_current_user_id() );
		if ( ! $claims ) {
			return new WP_Error( 'spd_membership_claim_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return ! empty( $claims['eligible'] ) && empty( $claims['suspended'] ) ? true : new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
	}

	private function response( $result, $success = 200, $public = false ) {
		$trace = SPD_Helpers::trace_id();
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data(); $status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
			$r = new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'trace_id' => $trace ), $status );
		} else {
			$r = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $success );
		}
		$r->header( 'Cache-Control', ( $public ? '' : 'private, ' ) . 'no-store, no-cache, must-revalidate, max-age=0' );
		$r->header( 'Pragma', 'no-cache' );
		if ( is_wp_error( $result ) || ! $public ) { $r->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' ); }
		$r->header( 'X-SPD-Trace-ID', $trace ); $r->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION ); return $r;
	}

	private function version( WP_REST_Request $r ) {
		$raw = trim( (string) $r->get_header( 'If-Match' ) );
		if ( '' !== $raw ) { return preg_match( '/^"?([1-9][0-9]*)"?$/', $raw, $m ) ? absint( $m[1] ) : 0; }
		return absint( $r->get_param( 'version' ) );
	}
	private function idem( WP_REST_Request $r ) { return trim( sanitize_text_field( (string) $r->get_header( 'Idempotency-Key' ) ) ); }
	private function profile_store_certain( $result ) {
		global $wpdb;
		if ( $wpdb->last_error && is_wp_error( $result ) && 'spd_profile_unavailable' === $result->get_error_code() ) {
			return new WP_Error( 'spd_profile_store_unavailable', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return $result;
	}
	public function personal_site( WP_REST_Request $r ) { return $this->response( spd_get_personal_site_profile( $r['public_id'], get_current_user_id() ), 200, ! is_user_logged_in() ); }
	public function search_projection( WP_REST_Request $r ) { return $this->response( spd_get_search_projection( $r['public_id'] ), 200, true ); }
	public function edit_model() {
		global $wpdb;
		$wpdb->last_error = '';
		return $this->response( $this->profile_store_certain( SPD_Profile_Repository::instance()->central_edit_model( get_current_user_id() ) ) );
	}
	public function update_personal_site( WP_REST_Request $r ) {
		global $wpdb;
		$p = (array) $r->get_json_params();
		if ( array_key_exists( 'audiences', $p ) ) {
			$audience_guard = SPD_Authorization::validate_audience_payload( $p['audiences'], SPD_Central_Profile::extended_fields() );
			if ( is_wp_error( $audience_guard ) ) { return $this->response( $audience_guard ); }
		}
		$wpdb->last_error = '';
		$result = SPD_Profile_Repository::instance()->update_central_profile( get_current_user_id(), $p, $this->version( $r ), $this->idem( $r ) );
		return $this->response( $this->profile_store_certain( $result ) );
	}
	public function rotate_share( WP_REST_Request $r ) { return $this->response( SPD_Profile_Repository::instance()->rotate_share_link( get_current_user_id(), $this->version( $r ), $this->idem( $r ) ) ); }
	public function delegates() { return $this->response( SPD_Profile_Repository::instance()->list_delegates( get_current_user_id() ) ); }
	public function grant_delegate( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params();
		$delegate_id = absint( $p['delegate_user_id'] ?? 0 );
		if ( $delegate_id && SPD_Membership_Adapter::is_minor( $delegate_id ) ) {
			return $this->response( new WP_Error( 'spd_delegate_minor_forbidden', __( 'A minor account cannot receive delegated profile-management authority.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ) );
		}
		return $this->response( SPD_Profile_Repository::instance()->grant_delegate( get_current_user_id(), $delegate_id, (array) ( $p['scopes'] ?? array() ), sanitize_text_field( (string) ( $p['expires_at'] ?? '' ) ), $this->idem( $r ) ), 201 );
	}
	public function revoke_delegate( WP_REST_Request $r ) { return $this->response( SPD_Profile_Repository::instance()->revoke_delegate( get_current_user_id(), absint( $r['delegate_id'] ), $this->idem( $r ) ) ); }
	public function safety_report( WP_REST_Request $r ) { $p = (array) $r->get_json_params(); return $this->response( SPD_Profile_Repository::instance()->create_safety_report( $r['public_id'], get_current_user_id(), $p['reason'] ?? '', $p['details'] ?? '', $this->idem( $r ) ), 201 ); }
	public function appeal( WP_REST_Request $r ) { $p = (array) $r->get_json_params(); return $this->response( SPD_Profile_Repository::instance()->request_report_appeal( $r['report_uuid'], get_current_user_id(), $p['reason'] ?? '', $this->idem( $r ) ), 201 ); }
}
