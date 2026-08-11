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
		} else { $r = new WP_REST_Response( array( 'data' => $result, 'trace_id' => $trace ), $status ); }
		$r->header( 'Cache-Control', ( $public ? '' : 'private, ' ) . 'no-store, no-cache, must-revalidate, max-age=0' );
		$r->header( 'Pragma', 'no-cache' );
		if ( is_wp_error( $result ) || ! $public ) { $r->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' ); }
		$r->header( 'X-SPD-Trace-ID', $trace ); $r->header( 'X-SPD-Contract-Version', SPD_CONTRACT_VERSION ); return $r;
	}

	private function dto( $public_id ) { return spd_get_personal_site_profile( (string) $public_id, get_current_user_id() ); }
	private function idem( WP_REST_Request $r ) { return trim( sanitize_text_field( (string) $r->get_header( 'Idempotency-Key' ) ) ); }
	private function reject_unknown( array $payload, array $allowed, $code ) {
		return array_diff( array_keys( $payload ), $allowed ) ? new WP_Error( sanitize_key( $code ), __( 'One or more submitted fields are not supported for this operation.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) : true;
	}

	private function medical_scope_question( $question ) {
		$q = (string) $question;
		return 1 === preg_match( '/\b(diagnos\w*|prescrib\w*|prescription|dosage|dose|potency|emergency|urgent medical|guaranteed cure|treatment recommendation)\b|\b(what|which|recommend|suggest)\b.{0,45}\b(remedy|medicine|medication|drug|treatment|potency|dose)\b|\b(should i|can i)\b.{0,35}\b(take|use)\b.{0,35}\b(remedy|medicine|medication|drug|dose|potency)\b|\b(treat|cure)\b.{0,25}\b(my|me)\b|\b(for my|my)\b.{0,30}\b(symptom|condition|disease|pain)\b|تشخیص|نسخہ|خوراک|ایمرجنسی|علاج تجویز|کون سی دوا|کیا دوا|دوا لوں|مجھے.{0,20}دوا|میرے لیے.{0,20}دوا|پوٹینسی/ui', $q );
	}

	private function mutate( WP_REST_Request $r, $command, array $payload, callable $callback, $status = 200 ) {
		if ( SPD_Observability::safe_mode() ) { return $this->response( new WP_Error( 'spd_safe_mode', __( 'Future-profile changes are temporarily unavailable while the system is in safe mode.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ) ); }
		$repo = SPD_Profile_Repository::instance(); $actor = get_current_user_id(); $key = $this->idem( $r );
		$hash = hash( 'sha256', SPD_Helpers::json_encode( array( sanitize_key( $command ), $payload ) ) );
		$idem = $repo->future_idempotency_begin( $actor, $command, $key, $hash );
		if ( is_wp_error( $idem ) ) { return $this->response( $idem ); }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $this->response( $idem['response'], $status ); }
		$result = SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback ) {
			$mutation = $callback();
			if ( is_wp_error( $mutation ) ) { return $mutation; }
			if ( ! is_array( $mutation ) ) { $mutation = array( 'ok' => (bool) $mutation ); }
			if ( ! $repo->future_idempotency_complete( $actor, $command, $key, $mutation ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ), array( 'status' => 500 ) ); }
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
	public function ask_work( WP_REST_Request $r ) {
		$viewer = get_current_user_id();
		if ( ! SPD_Helpers::consume_rate_limit( 'ask_work_' . $viewer, 30, HOUR_IN_SECONDS ) ) { return $this->response( new WP_Error( 'spd_ai_rate_limited', __( 'Too many profile-work questions were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) ) ); }
		$p = (array) $r->get_json_params(); $question = trim( SPD_Helpers::sanitize_multiline( $p['question'] ?? '', 500 ) );
		if ( $this->medical_scope_question( $question ) ) { return $this->response( new WP_Error( 'spd_ai_scope_restricted', __( 'This assistant answers only about the professional’s published work; it does not diagnose, prescribe, dose or replace emergency care.', 'sabri-profiles-doctors' ), array( 'status' => 422 ) ) ); }
		$result = SPD_Future_Profile::ask_about_work( $r['public_id'], $viewer, $question );
		if ( ! is_wp_error( $result ) ) { $answer = trim( (string) ( $result['answer'] ?? '' ) ); $citations = (array) ( $result['citations'] ?? array() ); if ( '' === $answer || ! $citations ) { $result = new WP_Error( 'spd_ai_grounding_incomplete', __( 'The grounded answer did not include sufficient public source evidence.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); } }
		return $this->response( $result );
	}

	public function create_disclosure( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $shape = $this->reject_unknown( $p, array( 'scopes', 'ttl' ), 'spd_unknown_disclosure_field' ); if ( is_wp_error( $shape ) ) { return $this->response( $shape ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false );
		if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, get_current_user_id() ); if ( is_wp_error( $guard ) ) { return $this->response( $guard ); }
		$payload = array( 'public_id' => $profile['public_id'], 'scopes' => array_values( array_map( 'sanitize_key', (array) ( $p['scopes'] ?? array() ) ) ), 'ttl' => absint( $p['ttl'] ?? 3600 ) );
		return $this->mutate( $r, 'create_selective_disclosure', $payload, function() use ( $profile, $payload ) { $token = SPD_Future_Profile::disclosure_token( $profile, $payload['scopes'], $payload['ttl'] ); if ( is_wp_error( $token ) ) { return $token; } return array( 'token' => $token, 'url' => rest_url( 'sabri-profiles/v1/disclosures/' . rawurlencode( $token ) ), 'revocable_with_share_epoch' => true ); }, 201 );
	}

	public function disclosure( WP_REST_Request $r ) {
		$packet = SPD_Future_Profile::disclosure_packet( $r['token'] ); if ( is_wp_error( $packet ) ) { return $this->response( $packet, 200, false ); }
		$parts = explode( '.', (string) $r['token'], 2 );
		$raw = base64_decode( strtr( (string) ( $parts[0] ?? '' ), '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( (string) ( $parts[0] ?? '' ) ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$payload = json_decode( (string) $raw, true );
		$dto = is_array( $payload ) && ! empty( $payload['pid'] ) ? spd_get_personal_site_profile( sanitize_text_field( $payload['pid'] ), 0 ) : new WP_Error( 'spd_disclosure_invalid', __( 'This disclosure link is invalid.', 'sabri-profiles-doctors' ) );
		if ( is_wp_error( $dto ) ) { return $this->response( $dto, 200, false ); }
		foreach ( (array) ( $packet['scopes'] ?? array() ) as $scope ) { switch ( sanitize_key( $scope ) ) { case 'credentials': $packet['credentials'] = $dto['future']['credential_wallet'] ?? array(); break; case 'expertise': $packet['expertise'] = array( 'declared' => $dto['extended'] ?? array(), 'evidence' => $dto['future']['expertise_evidence'] ?? array() ); break; case 'achievements': $packet['achievements'] = $dto['future']['learning_passport'] ?? array(); break; } }
		return $this->response( $packet, 200, false );
	}

	public function translation( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $shape = $this->reject_unknown( $p, array( 'locale', 'headline', 'bio', 'source' ), 'spd_unknown_translation_field' ); if ( is_wp_error( $shape ) ) { return $this->response( $shape ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false );
		if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		if ( ! SPD_Helpers::valid_locale( $p['locale'] ?? '' ) ) { return $this->response( new WP_Error( 'spd_translation_locale_invalid', __( 'Choose a valid locale.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) ); }
		$payload = array( 'public_id' => $profile['public_id'], 'locale' => (string) $p['locale'], 'headline' => (string) ( $p['headline'] ?? '' ), 'bio' => (string) ( $p['bio'] ?? '' ), 'source' => (string) ( $p['source'] ?? 'human' ) );
		return $this->mutate( $r, 'save_profile_translation', $payload, function() use ( $profile, $payload ) { return SPD_Future_Profile::save_translation( get_current_user_id(), $profile['public_id'], $payload['locale'], $payload['headline'], $payload['bio'], $payload['source'] ); }, 201 );
	}

	public function reconfirm( WP_REST_Request $r ) {
		$p = (array) $r->get_json_params(); $shape = $this->reject_unknown( $p, array( 'field_key', 'days' ), 'spd_unknown_reconfirm_field' ); if ( is_wp_error( $shape ) ) { return $this->response( $shape ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false );
		if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$payload = array( 'public_id' => $profile['public_id'], 'field_key' => sanitize_key( (string) ( $p['field_key'] ?? '' ) ), 'days' => absint( $p['days'] ?? 365 ) );
		return $this->mutate( $r, 'reconfirm_profile_field', $payload, function() use ( $profile, $payload ) { return SPD_Future_Profile::reconfirm_field( get_current_user_id(), $profile['public_id'], $payload['field_key'], $payload['days'] ); }, 201 );
	}

	public function future_state( WP_REST_Request $r ) {
		$actor = get_current_user_id();
		$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( (string) $r['public_id'] );
		if ( is_wp_error( $profile ) ) { return $this->response( $profile ); }
		if ( ! $profile ) { return $this->response( new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ) ); }
		$current_state = spd_read_future_profile_state( absint( $profile['id'] ) );
		if ( is_wp_error( $current_state ) ) { return $this->response( $current_state ); }
		$submitted = (array) $r->get_json_params();
		$allowed = array( 'professional_lifecycle', 'lifecycle_reason', 'federation_opt_in' );
		$unknown = array_diff( array_keys( $submitted ), $allowed );
		if ( $unknown ) { return $this->response( new WP_Error( 'spd_unknown_future_state_field', __( 'One or more future-profile state fields are not supported.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ) ); }
		$is_owner = absint( $profile['user_id'] ) === $actor;
		$is_governor = SPD_Membership_Adapter::can_manage_founder( $actor ) || SPD_Membership_Adapter::can_operate_profiles( $actor );
		if ( ! $is_owner && ! $is_governor ) { return $this->response( new WP_Error( 'spd_forbidden', __( 'You cannot change this professional lifecycle state.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ) ); }
		if ( array_key_exists( 'federation_opt_in', $submitted ) && ! empty( $submitted['federation_opt_in'] ) && ! $is_owner ) { return $this->response( new WP_Error( 'spd_federation_owner_opt_in_required', __( 'Federation opt-in must be given explicitly by the profile owner.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ) ); }
		$effective_lifecycle = sanitize_key( (string) ( array_key_exists( 'professional_lifecycle', $submitted ) ? $submitted['professional_lifecycle'] : ( $current_state['professional_lifecycle'] ?? 'active' ) ) );
		if ( 'legacy' === $effective_lifecycle && ! $is_governor ) { return $this->response( new WP_Error( 'spd_legacy_governance_required', __( 'Legacy/memorial status requires current governed approval.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ) ); }
		// Materialize every state-owned field from the strict preflight. If the
		// lower storage helper experiences a later transient read failure, omitted
		// fields cannot be replaced by its permissive active/version-1 defaults.
		$payload = array(
			'public_id' => (string) $r['public_id'],
			'professional_lifecycle' => $effective_lifecycle,
			'lifecycle_reason' => array_key_exists( 'lifecycle_reason', $submitted ) ? (string) $submitted['lifecycle_reason'] : (string) ( $current_state['lifecycle_reason'] ?? '' ),
			'federation_opt_in' => array_key_exists( 'federation_opt_in', $submitted ) ? ( ! empty( $submitted['federation_opt_in'] ) ? 1 : 0 ) : ( ! empty( $current_state['federation_opt_in'] ) ? 1 : 0 ),
		);
		return $this->mutate( $r, 'set_future_profile_state', $payload, function() use ( $actor, $r, $payload ) { $mutation = $payload; unset( $mutation['public_id'] ); return SPD_Future_Profile::set_future_state( $actor, $r['public_id'], $mutation ); } );
	}
}
