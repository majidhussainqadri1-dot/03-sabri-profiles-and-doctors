<?php
defined( 'ABSPATH' ) || exit;

/** Final consumer-side guard for cross-file profile projections. */
final class SPD_Provider_Guards {
	private static $registered = false;

	public static function register() {
		if ( self::$registered ) { return; }
		self::$registered = true;
		$hooks = array(
			'sabri_file09_verifiable_credentials_v1','sabri_profile_learning_passport_v1','sabri_profile_trust_timeline_v1','sabri_profile_expertise_evidence_v1','sabri_profile_knowledge_graph_v1','sabri_profile_knowledge_coverage_v1','sabri_file16_grounded_profile_ask_v1','sabri_file17_profile_contact_relay_v1','sabri_verified_external_profile_links_v1','sabri_federation_actor_transport_v1','sabri_file26_profile_analytics_projection_v1','sabri_verified_organization_affiliations_v1','sabri_file08_public_clinic_projection_v1','sabri_file08_profile_reviews_projection_v1',
		);
		foreach ( $hooks as $hook ) { add_filter( $hook, array( __CLASS__, 'bind_user' ), 9999, 8 ); }
		add_filter( 'sabri_file26_profile_search_projection_v1', array( __CLASS__, 'guard_file03_search_projection' ), 9999, 2 );
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
}
