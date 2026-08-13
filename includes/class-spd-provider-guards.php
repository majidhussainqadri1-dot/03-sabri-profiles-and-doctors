<?php
defined( 'ABSPATH' ) || exit;

/** Final consumer-side guard for cross-file profile projections. */
final class SPD_Provider_Guards {
	private static $registered = false;
	private static $file00_membership_uncertain = false;

	public static function register() {
		if ( self::$registered ) { return; }
		self::$registered = true;
		$hooks = array(
			'sabri_file09_verifiable_credentials_v1','sabri_profile_learning_passport_v1','sabri_profile_trust_timeline_v1','sabri_profile_expertise_evidence_v1','sabri_profile_knowledge_graph_v1','sabri_profile_knowledge_coverage_v1','sabri_file16_grounded_profile_ask_v1','sabri_file17_profile_contact_relay_v1','sabri_verified_external_profile_links_v1','sabri_federation_actor_transport_v1','sabri_file26_profile_analytics_projection_v1','sabri_verified_organization_affiliations_v1','sabri_file08_public_clinic_projection_v1','sabri_file08_profile_reviews_projection_v1',
		);
		foreach ( $hooks as $hook ) { add_filter( $hook, array( __CLASS__, 'bind_user' ), 9999, 8 ); }
		add_filter( 'sabri_file26_profile_search_projection_v1', array( __CLASS__, 'guard_file03_search_projection' ), 9999, 2 );

		// R07 — provider exceptions are fail-closed, but a boolean denial must not
		// erase the distinction between genuine ineligibility (403) and dependency
		// uncertainty (503) on strict report/appeal routes.
		add_action( 'sabri_file24_profile_provider_failure', array( __CLASS__, 'remember_file00_membership_failure' ), 1, 1 );
		add_action( 'wp_error_added', array( __CLASS__, 'normalize_service_error' ), 10, 4 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'normalize_strict_report_response' ), 90, 3 );
	}

	public static function bind_user( $claim, $requested_user_id = 0 ) {
		if ( null === $claim ) { return null; }
		if ( ! is_array( $claim ) ) { return array(); }
		$requested_user_id = absint( $requested_user_id );
		if ( ! $requested_user_id ) { return array(); }
		$fields = array( 'user_id', 'doctor_user_id', 'owner_user_id', 'profile_user_id' );
		$found = false;
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $claim ) ) { continue; }
			$found = true;
			if ( absint( $claim[ $field ] ) !== $requested_user_id ) { return array(); }
		}
		return $found ? $claim : array();
	}

	public static function guard_file03_search_projection( $claim, $identity ) {
		if ( ! is_array( $claim ) || 'file03' !== sanitize_key( (string) ( $claim['owner'] ?? '' ) ) ) { return $claim; }
		return spd_get_search_projection( $identity );
	}

	public static function remember_file00_membership_failure( $evidence ) {
		$evidence = is_array( $evidence ) ? $evidence : array();
		if ( 'file00_membership' !== sanitize_key( (string) ( $evidence['provider'] ?? '' ) ) ) { return; }
		$surface = sanitize_key( (string) ( $evidence['surface'] ?? '' ) );
		if ( in_array( $surface, array( 'membership_assertions', 'user_status', 'founder_assertion', 'age_guardian_claim', 'founder_user_id', 'founder_management_restriction', 'guardian_relationship_claim', 'contact_projection' ), true ) ) {
			self::$file00_membership_uncertain = true;
		}
	}

	public static function normalize_service_error( $code, $message, $data, $error ) {
		unset( $message );
		if ( ! $error instanceof WP_Error ) { return; }
		$code = sanitize_key( (string) $code );
		$current = is_array( $data ) ? $data : array();
		if ( 'spd_idempotency_store_failed' === $code ) {
			$current['status'] = 503;
			$error->add_data( $current, $code );
			return;
		}
		if ( self::$file00_membership_uncertain && 'spd_account_ineligible' === $code ) {
			$current['status'] = 503;
			$current['dependency'] = 'file00_membership';
			$error->add_data( $current, $code );
		}
	}

	private static function is_strict_report_route( $route ) {
		$route = (string) $route;
		return 1 === preg_match( '#^/sabri-profiles/v1/(?:profiles/[0-9a-fA-F-]{36}/safety-reports|reports/[0-9a-fA-F-]{36}/appeal)$#', $route );
	}

	public static function normalize_strict_report_response( $response, $server, $request ) {
		unset( $server );
		if ( ! self::$file00_membership_uncertain || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! self::is_strict_report_route( $request->get_route() ) ) { return $response; }
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) || ! method_exists( $response, 'set_status' ) ) { return $response; }
		$data = $response->get_data();
		if ( ! is_array( $data ) || 'spd_account_ineligible' !== sanitize_key( (string) ( $data['code'] ?? '' ) ) ) { return $response; }
		$data['code'] = 'spd_membership_claim_unavailable';
		$data['message'] = __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' );
		$response->set_data( $data );
		$response->set_status( 503 );
		return $response;
	}
}
