<?php
defined( 'ABSPATH' ) || exit;

/**
 * Latest-governing-plan adapter and presentation layer for File 03.
 *
 * This class deliberately owns only profile presentation facts. Verification,
 * appointments, reviews, directory ranking, search ranking and analytics truth
 * remain with their canonical owners and are consumed through current,
 * versioned, fail-closed projections.
 */
final class SPD_Central_Profile {
	const SCHEMA_VERSION = '1.0.0';
	const MIN_PROVIDER_CONTRACT = '1.0.0';

	public static function extended_fields() {
		return array(
			'interests',
			'services',
			'expertise_topics',
			'expertise_populations',
			'expertise_modalities',
			'research_interests',
		);
	}

	public static function extended_labels() {
		return array(
			'interests'             => __( 'Interests', 'sabri-profiles-doctors' ),
			'services'              => __( 'Professional Services', 'sabri-profiles-doctors' ),
			'expertise_topics'      => __( 'Classical Homeopathy Topics', 'sabri-profiles-doctors' ),
			'expertise_populations' => __( 'Populations Served', 'sabri-profiles-doctors' ),
			'expertise_modalities'  => __( 'Consultation / Practice Modalities', 'sabri-profiles-doctors' ),
			'research_interests'    => __( 'Research Interests', 'sabri-profiles-doctors' ),
		);
	}

	public static function delegation_table() {
		global $wpdb;
		return $wpdb->prefix . 'spd_profile_delegations';
	}

	public static function appeals_table() {
		global $wpdb;
		return $wpdb->prefix . 'spd_report_appeals';
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$delegations = self::delegation_table();
		$appeals = self::appeals_table();
		dbDelta( "CREATE TABLE {$delegations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL,
			delegate_user_id bigint(20) unsigned NOT NULL,
			scopes varchar(191) NOT NULL DEFAULT 'profile_presentation',
			status varchar(20) NOT NULL DEFAULT 'active',
			expires_at datetime NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY owner_delegate (owner_user_id,delegate_user_id),
			KEY profile_status (profile_id,status),
			KEY delegate_status (delegate_user_id,status)
		) {$collate};" );
		dbDelta( "CREATE TABLE {$appeals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			appeal_uuid char(36) NOT NULL,
			report_id bigint(20) unsigned NOT NULL,
			requested_by bigint(20) unsigned NOT NULL,
			reason text NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'submitted',
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			decision_note text NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY appeal_uuid (appeal_uuid),
			UNIQUE KEY report_requester (report_id,requested_by),
			KEY status_updated (status,updated_at)
		) {$collate};" );
		if ( ! self::schema_ready() ) {
			return new WP_Error( 'spd_central_schema_failed', __( 'The latest-plan File 03 schema could not be installed.', 'sabri-profiles-doctors' ) );
		}
		update_option( 'spd_central_schema_version', self::SCHEMA_VERSION, false );
		return true;
	}

	public static function schema_ready() {
		global $wpdb;
		foreach ( array( self::delegation_table(), self::appeals_table() ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
		}
		return true;
	}

	public static function provider_claim( $filter, $user_id, $viewer_id = 0, $max_age = 300 ) {
		$claim = apply_filters( $filter, null, absint( $user_id ), absint( $viewer_id ), SPD_CONTRACT_VERSION );
		if ( ! SPD_Helpers::current_contract_claim( $claim, self::MIN_PROVIDER_CONTRACT, $max_age ) ) { return array(); }
		if ( isset( $claim['user_id'] ) && absint( $claim['user_id'] ) !== absint( $user_id ) ) { return array(); }
		return $claim;
	}

	public static function clinic_projection( $user_id, $viewer_id = 0 ) {
		$claim = self::provider_claim( 'sabri_file08_public_clinic_projection_v1', $user_id, $viewer_id, 300 );
		if ( ! $claim || 'active' !== sanitize_key( (string) ( $claim['status'] ?? '' ) ) || 'public' !== sanitize_key( (string) ( $claim['visibility'] ?? '' ) ) || empty( $claim['owner_version'] ) ) { return array(); }
		$out = array();
		foreach ( array( 'name','country','city','timezone','hours','consultation_modes','languages','teleconsult','accessible_facilities','next_slots','services' ) as $key ) {
			if ( ! isset( $claim[ $key ] ) ) { continue; }
			$value = $claim[ $key ];
			if ( is_array( $value ) ) { $value = implode( ', ', array_slice( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ), 0, 50 ) ); }
			$out[ $key ] = SPD_Helpers::sanitize_multiline( $value, 2000 );
		}
		foreach ( array( 'url','appointment_url' ) as $key ) {
			if ( ! empty( $claim[ $key ] ) && SPD_Helpers::same_origin_url( (string) $claim[ $key ] ) ) { $out[ $key ] = esc_url_raw( $claim[ $key ] ); }
		}
		$out['owner_version'] = sanitize_text_field( (string) $claim['owner_version'] );
		return $out;
	}

	public static function review_projection( $user_id, $viewer_id = 0 ) {
		$claim = self::provider_claim( 'sabri_file08_profile_reviews_projection_v1', $user_id, $viewer_id, 300 );
		if ( ! $claim ) { return array(); }
		$items = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['eligible_consultation'] ) || ! empty( $item['clinical_outcome_rating'] ) ) { continue; }
			$items[] = array(
				'id'         => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
				'rating'     => min( 5, max( 1, absint( $item['service_rating'] ?? 0 ) ) ),
				'text'       => SPD_Helpers::sanitize_multiline( $item['text'] ?? '', 1000 ),
				'created_at' => sanitize_text_field( (string) ( $item['created_at'] ?? '' ) ),
				'disputed'   => ! empty( $item['disputed'] ),
			);
		}
		return array( 'items' => $items, 'owner_version' => sanitize_text_field( (string) ( $claim['owner_version'] ?? '' ) ) );
	}

	public static function analytics_projection( $user_id, $viewer_id ) {
		if ( absint( $user_id ) !== absint( $viewer_id ) ) { return array(); }
		$claim = self::provider_claim( 'sabri_file26_profile_analytics_projection_v1', $user_id, $viewer_id, 600 );
		if ( ! $claim ) { return array(); }
		$out = array();
		foreach ( array( 'views','appointment_clicks','content_engagement','search_referrals' ) as $key ) {
			if ( isset( $claim[ $key ] ) ) { $out[ $key ] = max( 0, absint( $claim[ $key ] ) ); }
		}
		return $out;
	}

	public static function organization_projection( $user_id, $viewer_id = 0 ) {
		$claim = self::provider_claim( 'sabri_verified_organization_affiliations_v1', $user_id, $viewer_id, 600 );
		if ( ! $claim ) { return array(); }
		$out = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['verified'] ) ) { continue; }
			$url = (string) ( $item['url'] ?? '' );
			$out[] = array(
				'name' => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'type' => sanitize_key( (string) ( $item['type'] ?? 'organization' ) ),
				'role' => sanitize_text_field( (string) ( $item['role'] ?? '' ) ),
				'url'  => $url && SPD_Helpers::same_origin_url( $url ) ? esc_url_raw( $url ) : '',
			);
		}
		return $out;
	}

	public static function share_epoch( array $profile ) {
		$value = $profile['fields']['share_epoch']['field_value'] ?? '1';
		return max( 1, absint( $value ) );
	}

	public static function share_token( array $profile ) {
		$id = absint( $profile['id'] ?? 0 );
		if ( ! $id || empty( $profile['public_id'] ) ) { return ''; }
		$epoch = self::share_epoch( $profile );
		$base = base_convert( (string) $id, 10, 36 );
		$sig = substr( hash_hmac( 'sha256', $profile['public_id'] . '|' . $epoch . '|' . $base, wp_salt( 'auth' ) ), 0, 16 );
		return $base . '-' . $sig;
	}

	public static function short_url( array $profile ) {
		$token = self::share_token( $profile );
		return $token ? home_url( user_trailingslashit( 'p/' . rawurlencode( $token ) ) ) : SPD_Helpers::canonical_profile_url( $profile['public_id'] ?? '' );
	}

	public static function resolve_share_token( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( ! preg_match( '/^([0-9a-z]{1,13})-([0-9a-f]{16})$/', $token, $m ) ) { return array(); }
		$id = (int) base_convert( $m[1], 36, 10 );
		$profile = SPD_Profile_Repository::instance()->find_by_id( $id );
		if ( ! $profile || ! hash_equals( self::share_token( $profile ), $token ) ) { return array(); }
		if ( ! SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) { return array(); }
		return $profile;
	}

	public static function public_extended_fields( array $profile, $viewer_id ) {
		$out = array();
		foreach ( self::extended_fields() as $key ) {
			$row = $profile['fields'][ $key ] ?? array( 'audience' => 'private', 'field_value' => '' );
			if ( SPD_Authorization::audience_allows( $row['audience'], $profile['user_id'], $viewer_id ) && '' !== trim( (string) ( $row['field_value'] ?? '' ) ) ) {
				$out[ $key ] = (string) $row['field_value'];
			}
		}
		return $out;
	}

	public static function credential_card( array $professional ) {
		$keys = array( 'degree','institution','jurisdiction','credential_issued_at','credential_expires_at','qualification','licence_number','licensing_authority' );
		$out = array();
		foreach ( $keys as $key ) { if ( isset( $professional[ $key ] ) && '' !== trim( (string) $professional[ $key ] ) ) { $out[ $key ] = sanitize_text_field( (string) $professional[ $key ] ); } }
		return $out;
	}

	public static function personal_site_dto( $identity, $viewer_id = 0 ) {
		$repo = SPD_Profile_Repository::instance();
		$dto = $repo->public_dto( $identity, $viewer_id );
		if ( is_wp_error( $dto ) ) { return $dto; }
		$profile = $repo->find_by_public_id( $dto['public_id'] );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$dto['extended'] = self::public_extended_fields( $profile, $viewer_id );
		$dto['credential_card'] = self::credential_card( (array) $dto['professional'] );
		$dto['clinic'] = self::clinic_projection( $profile['user_id'], $viewer_id ) ?: (array) $dto['clinic'];
		$dto['reviews'] = 'doctor' === $dto['profile_type'] ? self::review_projection( $profile['user_id'], $viewer_id ) : array();
		$dto['organizations'] = self::organization_projection( $profile['user_id'], $viewer_id );
		$dto['share'] = array(
			'short_url' => self::short_url( $profile ),
			'canonical_url' => $dto['canonical_url'],
			'tracking_free' => true,
			'revocable' => true,
		);
		$dto['analytics'] = self::analytics_projection( $profile['user_id'], $viewer_id );
		$dto['safety'] = array(
			'educational_only' => true,
			'emergency_replacement' => false,
			'no_outcome_guarantee' => true,
		);
		return $dto;
	}

	public static function search_projection( $identity ) {
		$dto = self::personal_site_dto( $identity, 0 );
		if ( is_wp_error( $dto ) ) { return $dto; }
		$out = array(
			'contract_version' => SPD_CONTRACT_VERSION,
			'owner' => 'file03',
			'entity_type' => 'public_profile',
			'canonical_id' => $dto['public_id'],
			'canonical_url' => $dto['canonical_url'],
			'display_name' => $dto['display_name'],
			'profile_type' => $dto['profile_type'],
			'locale' => $dto['locale'],
			'badge' => $dto['badge'],
			'fields' => array_merge( (array) $dto['fields'], (array) $dto['extended'] ),
			'professional' => (array) $dto['professional'],
			'credential_card' => (array) $dto['credential_card'],
			'clinic' => array_intersect_key( (array) $dto['clinic'], array_flip( array( 'name','country','city','consultation_modes','languages','teleconsult','accessible_facilities','url' ) ) ),
			'version' => absint( $dto['version'] ),
			'generated_at' => gmdate( 'c' ),
			'valid_until' => gmdate( 'c', time() + 300 ),
		);
		return $out;
	}

	public static function structured_data( $identity ) {
		$dto = self::personal_site_dto( $identity, 0 );
		if ( is_wp_error( $dto ) ) { return array(); }
		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Person',
			'@id' => $dto['canonical_url'] . '#profile',
			'name' => $dto['display_name'],
			'url' => $dto['canonical_url'],
		);
		if ( ! empty( $dto['fields']['bio'] ) ) { $schema['description'] = $dto['fields']['bio']; }
		if ( ! empty( $dto['fields']['country'] ) || ! empty( $dto['fields']['city'] ) ) {
			$schema['homeLocation'] = array( '@type' => 'Place', 'name' => trim( ( $dto['fields']['city'] ?? '' ) . ', ' . ( $dto['fields']['country'] ?? '' ), ', ' ) );
		}
		if ( 'doctor' === $dto['profile_type'] && ! empty( $dto['badge']['verified'] ) ) {
			$schema['jobTitle'] = $dto['professional']['professional_title'] ?? __( 'Homeopathic Doctor', 'sabri-profiles-doctors' );
			if ( ! empty( $dto['professional']['qualification'] ) ) { $schema['hasCredential'] = array( '@type' => 'EducationalOccupationalCredential', 'credentialCategory' => $dto['professional']['qualification'] ); }
		}
		return $schema;
	}

	public static function validate_presentation_fields( array $fields ) {
		$combined = strtolower( implode( "\n", array_map( 'strval', $fields ) ) );
		$patterns = array( '/\b100\s*%\s*(?:cure|guarantee)/i', '/\bguaranteed\s+cure\b/i', '/\bno\s+risk\b/i' );
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $combined ) ) {
				return new WP_Error( 'spd_unsafe_outcome_claim', __( 'Guaranteed cure or risk-free outcome claims are not permitted in a public professional profile.', 'sabri-profiles-doctors' ), array( 'status' => 422 ) );
			}
		}
		return true;
	}

	public static function report_reasons() {
		return array( 'harm','false_claim','impersonation','privacy','abuse','copyright','scam','child_safety','harassment','false_qualification','unsafe_media','privacy_breach','other' );
	}

	public static function delegation_scopes() {
		return array( 'profile_presentation', 'clinic_schedule_request' );
	}
}
