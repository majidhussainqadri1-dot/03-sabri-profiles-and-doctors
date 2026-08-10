<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Future_REST {
	public function hooks() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		$uuid = array( 'validate_callback' => static function ( $v ) { return SPD_Helpers::valid_uuid( (string) $v ); } );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/future', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'future' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/dossier', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'dossier' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/fhir', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'fhir' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/federation', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'federation' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/embed-card', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'embed_card' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/ask-work', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'ask_work' ), 'permission_callback' => array( $this, 'eligible' ), 'args' => array( 'public_id' => $uuid ) ) );
		register_rest_route( 'sabri-profiles/v1', '/me/disclosures', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_disclosure' ), 'permission_callback' => array( $this, 'eligible' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/disclosures/(?P<token>[A-Za-z0-9_.-]+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'disclosure' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'sabri-profiles/v1', '/me/translations', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'translation' ), 'permission_callback' => array( $this, 'eligible' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/me/reconfirm', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reconfirm' ), 'permission_callback' => array( $this, 'eligible' ) ) );
		register_rest_route( 'sabri-profiles/v1', '/profiles/(?P<public_id>[0-9a-fA-F-]{36})/future-state', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'future_state' ), 'permission_callback' => array( $this, 'eligible' ), 'args' => array( 'public_id' => $uuid ) ) );
	}

	public function eligible() {
		if ( ! is_user_logged_in() ) { return new WP_Error( 'spd_login_required', __( 'Authentication is required.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		return SPD_Membership_Adapter::is_member_eligible( get_current_user_id() ) ? true : new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
	}

	private function response( $result, $status = 200, $public = false ) {
		$trace = SPD_Helpers::trace_id();
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data(); $code = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 400;
			$r = new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'trace_id' => $trace ), $code );
			$r->header( 'Cache-Control', 'private, no-store' ); $r->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		} else {
			$r = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $status );
			$r->header( 'Cache-Control', $public ? 'public, max-age=60, stale-while-revalidate=120' : 'private, no-store' ); if ( ! $public ) { $r->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' ); }
		}
		$r->header( 'X-SPD-Trace-ID', $trace ); $r->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION ); return $r;
	}

	private function dto( $public_id ) { return spd_get_personal_site_profile( (string) $public_id, get_current_user_id() ); }
	private function idem( WP_REST_Request $r ) { return trim( sanitize_text_field( (string) $r->get_header( 'Idempotency-Key' ) ) ); }

	private function mutate( WP_REST_Request $r, $command, array $payload, callable $callback, $status = 200 ) {
		$repo = SPD_Profile_Repository::instance(); $actor = get_current_user_id(); $key = $this->idem( $r );
		$hash = hash( 'sha256', SPD_Helpers::json_encode( array( sanitize_key( $command ), $payload ) ) );
		$idem = $repo->future_idempotency_begin( $actor, $command, $key, $hash );
		if ( is_wp_error( $idem ) ) { return $this->response( $idem ); }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $this->response( $idem['response'], $status ); }
		$result = SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback ) {
			$mutation = $callback();
			if ( is_wp_error( $mutation ) ) { return $mutation; }
			if ( ! is_array( $mutation ) ) { $mutation = array( 'ok' => (bool) $mutation ); }
			if ( ! $repo->future_idempotency_complete( $actor, $command, $key, $mutation ) ) {
				return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ), array( 'status' => 500 ) );
			}
			return $mutation;
		} );
		if ( is_wp_error( $result ) ) { $repo->future_idempotency_fail( $actor, $command, $key ); return $this->response( $result ); }
		return $this->response( $result, $status );
	}

	public function future( WP_REST_Request $r ) { $dto = $this->dto( $r['public_id'] ); return $this->response( is_wp_error( $dto ) ? $dto : $dto['future'], 200, ! is_user_logged_in() ); }
	public function dossier( WP_REST_Request $r ) { $dto = $this->dto( $r['public_id'] ); return $this->response( is_wp_error( $dto ) ? $dto : $dto['future']['dossier'], 200, ! is_user_logged_in() ); }
	public function fhir( WP_REST_Request $r ) { $dto = spd_get_personal_site_profile( $r['public_id'], 0 ); return $this->response( is_wp_error( $dto ) ? $dto : $dto['future']['fhir'], 200, true ); }
	public function federation( WP_REST_Request $r ) { $dto = spd_get_personal_site_profile( $r['public_id'], 0 ); return $this->response( is_wp_error( $dto ) ? $dto : $dto['future']['federation'], 200, true ); }
	public function embed_card( WP_REST_Request $r ) { $dto = spd_get_personal_site_profile( $r['public_id'], 0 ); return $this->response( is_wp_error( $dto ) ? $dto : $dto['future']['embed_card'], 200, true ); }
	public function ask_work( WP_REST_Request $r ) { $p = (array) $r->get_json_params(); return $this->response( SPD_Future_Profile::ask_about_work( $r['public_id'], get_current_user_id(), $p['question'] ?? '' ) ); }
	public function create_disclosure( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false );
		if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, get_current_user_id() ); if ( is_wp_error( $guard ) ) { return $this->response( $guard ); }
		$payload = array( 'public_id' => $profile['public_id'], 'scopes' => array_values( array_map( 'sanitize_key', (array) ( $p['scopes'] ?? array() ) ) ), 'ttl' => absint( $p['ttl'] ?? 3600 ) );
		return $this->mutate( $r, 'create_selective_disclosure', $payload, function() use ( $profile, $payload ) {
			$token = SPD_Future_Profile::disclosure_token( $profile, $payload['scopes'], $payload['ttl'] ); if ( is_wp_error( $token ) ) { return $token; }
			return array( 'token' => $token, 'url' => rest_url( 'sabri-profiles/v1/disclosures/' . rawurlencode( $token ) ), 'revocable_with_share_epoch' => true );
		}, 201 );
	}
	public function disclosure( WP_REST_Request $r ) { return $this->response( SPD_Future_Profile::disclosure_packet( $r['token'] ), 200, true ); }
	public function translation( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false ); if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$payload = array( 'public_id' => $profile['public_id'], 'locale' => (string) ( $p['locale'] ?? '' ), 'headline' => (string) ( $p['headline'] ?? '' ), 'bio' => (string) ( $p['bio'] ?? '' ), 'source' => (string) ( $p['source'] ?? 'human' ) );
		return $this->mutate( $r, 'save_profile_translation', $payload, function() use ( $profile, $payload ) { return SPD_Future_Profile::save_translation( get_current_user_id(), $profile['public_id'], $payload['locale'], $payload['headline'], $payload['bio'], $payload['source'] ); }, 201 );
	}
	public function reconfirm( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false ); if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$payload = array( 'public_id' => $profile['public_id'], 'field_key' => sanitize_key( (string) ( $p['field_key'] ?? '' ) ), 'days' => absint( $p['days'] ?? 365 ) );
		return $this->mutate( $r, 'reconfirm_profile_field', $payload, function() use ( $profile, $payload ) { return SPD_Future_Profile::reconfirm_field( get_current_user_id(), $profile['public_id'], $payload['field_key'], $payload['days'] ); }, 201 );
	}
	public function future_state( WP_REST_Request $r ) {
		$payload = (array) $r->get_json_params(); $payload['public_id'] = (string) $r['public_id'];
		return $this->mutate( $r, 'set_future_profile_state', $payload, function() use ( $r, $payload ) { unset( $payload['public_id'] ); return SPD_Future_Profile::set_future_state( get_current_user_id(), $r['public_id'], $payload ); } );
	}
}
