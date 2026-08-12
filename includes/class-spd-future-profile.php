<?php
defined( 'ABSPATH' ) || exit;

/**
 * Future Professional Identity & Profile Superset for File 03.
 *
 * File 03 owns only profile/presentation state created here. Credentials,
 * learning achievements, clinic/contact, knowledge, AI, discovery and
 * federation transport remain canonical facts of their native owners and are
 * consumed through current/versioned projections.
 */
final class SPD_Future_Profile {
	const SCHEMA_VERSION = '1.0.0';
	const MIN_PROVIDER_CONTRACT = '1.0.0';
	const DISCLOSURE_MAX_TTL = 86400;

	public static function requirements() {
		return array_map( static function ( $i ) { return sprintf( 'F03-FUT-%02d', $i ); }, range( 1, 18 ) );
	}

	public static function translations_table() { global $wpdb; return $wpdb->prefix . 'spd_profile_translations'; }
	public static function attestations_table() { global $wpdb; return $wpdb->prefix . 'spd_profile_attestations'; }
	public static function state_table() { global $wpdb; return $wpdb->prefix . 'spd_profile_future_state'; }

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::translations_table();
		$a = self::attestations_table();
		$s = self::state_table();
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			locale varchar(20) NOT NULL,
			headline text NULL,
			bio longtext NULL,
			source varchar(20) NOT NULL DEFAULT 'human',
			status varchar(24) NOT NULL DEFAULT 'approved',
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY profile_locale (profile_id,locale),
			KEY profile_status (profile_id,status)
		) {$c};" );
		dbDelta( "CREATE TABLE {$a} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			field_key varchar(64) NOT NULL,
			confirmed_by bigint(20) unsigned NOT NULL,
			confirmed_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY profile_field (profile_id,field_key),
			KEY expires_at (expires_at)
		) {$c};" );
		dbDelta( "CREATE TABLE {$s} (
			profile_id bigint(20) unsigned NOT NULL,
			federation_opt_in tinyint(1) unsigned NOT NULL DEFAULT 0,
			professional_lifecycle varchar(20) NOT NULL DEFAULT 'active',
			lifecycle_reason text NULL,
			lifecycle_changed_at datetime NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			updated_at datetime NOT NULL,
			PRIMARY KEY (profile_id),
			KEY lifecycle (professional_lifecycle)
		) {$c};" );
		if ( ! self::schema_ready() ) {
			return new WP_Error( 'spd_future_schema_failed', __( 'The File 03 future-profile schema could not be installed.', 'sabri-profiles-doctors' ) );
		}
		update_option( 'spd_future_schema_version', self::SCHEMA_VERSION, false );
		return true;
	}

	public static function schema_ready() {
		global $wpdb;
		foreach ( array( self::translations_table(), self::attestations_table(), self::state_table() ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
		}
		return true;
	}

	private static function current_claim( $filter, $user_id, $viewer_id = 0, $max_age = 600, array $extra = array() ) {
		$args = array_merge( array( null, absint( $user_id ), absint( $viewer_id ), SPD_CONTRACT_VERSION ), $extra );
		$claim = apply_filters_ref_array( $filter, $args );
		if ( ! SPD_Helpers::current_contract_claim( $claim, self::MIN_PROVIDER_CONTRACT, $max_age ) ) { return array(); }
		if ( isset( $claim['user_id'] ) && absint( $claim['user_id'] ) !== absint( $user_id ) ) { return array(); }
		return $claim;
	}

	private static function safe_external_url( $url ) {
		$url = esc_url_raw( (string) $url, array( 'https' ) );
		if ( ! $url ) { return ''; }
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['host'] ) || 'https' !== strtolower( (string) ( $p['scheme'] ?? '' ) ) || isset( $p['user'] ) || isset( $p['pass'] ) ) { return ''; }
		return $url;
	}

	private static function text( $value, $limit = 1000 ) { return SPD_Helpers::sanitize_multiline( (string) $value, $limit ); }

	public static function state_for_profile( $profile_id ) {
		return spd_read_future_profile_state( absint( $profile_id ) );
	}

	/** FUT-01: portable, provider-issued professional credentials. */
	public static function credential_wallet( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_file09_verifiable_credentials_v1', $user_id, $viewer_id, 600 );
		$items = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 25 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['verified'] ) || 'current' !== sanitize_key( (string) ( $item['status'] ?? 'current' ) ) ) { continue; }
			$items[] = array(
				'id' => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
				'type' => sanitize_text_field( (string) ( $item['type'] ?? 'ProfessionalCredential' ) ),
				'name' => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'issuer' => sanitize_text_field( (string) ( $item['issuer'] ?? '' ) ),
				'issued_at' => sanitize_text_field( (string) ( $item['issued_at'] ?? '' ) ),
				'expires_at' => sanitize_text_field( (string) ( $item['expires_at'] ?? '' ) ),
				'format' => sanitize_key( (string) ( $item['format'] ?? 'vc' ) ),
				'verification_url' => self::safe_external_url( $item['verification_url'] ?? '' ),
			);
		}
		return array( 'items' => $items, 'portable' => true, 'raw_evidence_exposed' => false );
	}

	/** FUT-02: signed, expiring, revocable selective-disclosure packet. */
	public static function disclosure_token( array $profile, array $scopes, $ttl = 3600 ) {
		$allowed = array( 'identity','verification','credentials','expertise','clinic','achievements','affiliations' );
		$scopes = array_values( array_unique( array_intersect( $allowed, array_map( 'sanitize_key', $scopes ) ) ) );
		if ( ! $scopes ) { return new WP_Error( 'spd_disclosure_scope_required', __( 'Choose at least one disclosure scope.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$ttl = min( self::DISCLOSURE_MAX_TTL, max( 300, absint( $ttl ) ) );
		$payload = array( 'pid' => $profile['public_id'], 'epoch' => SPD_Central_Profile::share_epoch( $profile ), 'scopes' => $scopes, 'exp' => time() + $ttl );
		$json = wp_json_encode( $payload );
		if ( false === $json ) { return new WP_Error( 'spd_disclosure_encode_failed', __( 'The disclosure link could not be created.', 'sabri-profiles-doctors' ), array( 'status' => 500 ) ); }
		$body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$sig = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		return $body . '.' . $sig;
	}

	public static function disclosure_packet( $token ) {
		$parts = explode( '.', (string) $token, 2 );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9_-]{20,2048}$/', $parts[0] ) || ! preg_match( '/^[0-9a-f]{64}$/', $parts[1] ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return new WP_Error( 'spd_disclosure_invalid', __( 'This disclosure link is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$raw = base64_decode( strtr( $parts[0], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[0] ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$p = json_decode( (string) $raw, true );
		if ( ! is_array( $p ) || empty( $p['pid'] ) || empty( $p['scopes'] ) || time() > absint( $p['exp'] ?? 0 ) ) { return new WP_Error( 'spd_disclosure_expired', __( 'This disclosure link has expired.', 'sabri-profiles-doctors' ), array( 'status' => 410 ) ); }
		$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( sanitize_text_field( $p['pid'] ) );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile || absint( $p['epoch'] ?? 0 ) !== SPD_Central_Profile::share_epoch( $profile ) || ! SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) { return new WP_Error( 'spd_disclosure_revoked', __( 'This disclosure link is no longer available.', 'sabri-profiles-doctors' ), array( 'status' => 410 ) ); }
		$dto = spd_get_personal_site_profile( $profile['public_id'], 0 );
		if ( is_wp_error( $dto ) ) { return $dto; }
		$out = array( 'contract_version' => SPD_CONTRACT_VERSION, 'expires_at' => gmdate( 'c', absint( $p['exp'] ) ), 'scopes' => array_values( $p['scopes'] ) );
		foreach ( $p['scopes'] as $scope ) {
			switch ( $scope ) {
				case 'identity': $out['identity'] = array( 'public_id' => $dto['public_id'], 'display_name' => $dto['display_name'], 'canonical_url' => $dto['canonical_url'], 'profile_type' => $dto['profile_type'] ); break;
				case 'verification': $out['verification'] = $dto['badge']; break;
				case 'credentials': $out['credentials'] = $dto['future']['credential_wallet'] ?? array(); break;
				case 'expertise': $out['expertise'] = array( 'declared' => $dto['extended'] ?? array(), 'evidence' => $dto['future']['expertise_evidence'] ?? array() ); break;
				case 'clinic': $out['clinic'] = $dto['clinic'] ?? array(); break;
				case 'achievements': $out['achievements'] = $dto['future']['learning_passport'] ?? array(); break;
				case 'affiliations': $out['affiliations'] = $dto['organizations'] ?? array(); break;
			}
		}
		return $out;
	}

	/** FUT-03: verified learning/achievement passport. */
	public static function learning_passport( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_profile_learning_passport_v1', $user_id, $viewer_id, 900 );
		$items = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 50 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['verified'] ) ) { continue; }
			$items[] = array( 'title' => sanitize_text_field( (string) ( $item['title'] ?? '' ) ), 'issuer' => sanitize_text_field( (string) ( $item['issuer'] ?? '' ) ), 'type' => sanitize_key( (string) ( $item['type'] ?? 'achievement' ) ), 'awarded_at' => sanitize_text_field( (string) ( $item['awarded_at'] ?? '' ) ), 'credential_url' => self::safe_external_url( $item['credential_url'] ?? '' ) );
		}
		return array( 'items' => $items );
	}

	/** FUT-04: public-safe professional trust chronology. */
	public static function trust_timeline( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_profile_trust_timeline_v1', $user_id, $viewer_id, 900 );
		$allowed = array( 'verified','credential_renewed','affiliation_verified','qualification_updated','correction_issued','verification_suspended','verification_restored' );
		$out = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 50 ) as $item ) {
			$type = sanitize_key( (string) ( $item['type'] ?? '' ) ); if ( ! in_array( $type, $allowed, true ) ) { continue; }
			$out[] = array( 'type' => $type, 'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ), 'occurred_at' => sanitize_text_field( (string) ( $item['occurred_at'] ?? '' ) ) );
		}
		return $out;
	}

	/** FUT-05: evidence-backed expertise, never a cure/outcome score. */
	public static function expertise_evidence( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_profile_expertise_evidence_v1', $user_id, $viewer_id, 900 );
		$out = array();
		foreach ( array_slice( (array) ( $claim['topics'] ?? array() ), 0, 40 ) as $topic ) {
			if ( ! is_array( $topic ) ) { continue; }
			$evidence = array();
			foreach ( array_slice( (array) ( $topic['evidence'] ?? array() ), 0, 20 ) as $e ) {
				if ( ! is_array( $e ) ) { continue; }
				$evidence[] = array( 'type' => sanitize_key( (string) ( $e['type'] ?? 'evidence' ) ), 'label' => sanitize_text_field( (string) ( $e['label'] ?? '' ) ), 'url' => ! empty( $e['url'] ) && SPD_Helpers::same_origin_url( (string) $e['url'] ) ? esc_url_raw( $e['url'] ) : '' );
			}
			$out[] = array( 'topic' => sanitize_text_field( (string) ( $topic['topic'] ?? '' ) ), 'evidence' => $evidence );
		}
		return $out;
	}

	/** FUT-06: bounded, public-safe professional knowledge graph. */
	public static function knowledge_graph( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_profile_knowledge_graph_v1', $user_id, $viewer_id, 900 );
		$allowed_types = array( 'profile','article','book','course','video','reel','pdf','research','topic','institution','achievement' );
		$nodes = array();
		foreach ( array_slice( (array) ( $claim['nodes'] ?? array() ), 0, 100 ) as $node ) {
			$type = sanitize_key( (string) ( $node['type'] ?? '' ) ); if ( ! in_array( $type, $allowed_types, true ) ) { continue; }
			$url = (string) ( $node['url'] ?? '' );
			$nodes[] = array( 'id' => sanitize_text_field( (string) ( $node['id'] ?? '' ) ), 'type' => $type, 'label' => sanitize_text_field( (string) ( $node['label'] ?? '' ) ), 'url' => $url && SPD_Helpers::same_origin_url( $url ) ? esc_url_raw( $url ) : '' );
		}
		$edges = array();
		foreach ( array_slice( (array) ( $claim['edges'] ?? array() ), 0, 200 ) as $edge ) { if ( is_array( $edge ) ) { $edges[] = array( 'from' => sanitize_text_field( (string) ( $edge['from'] ?? '' ) ), 'to' => sanitize_text_field( (string) ( $edge['to'] ?? '' ) ), 'relation' => sanitize_key( (string) ( $edge['relation'] ?? 'related' ) ) ); } }
		return array( 'nodes' => $nodes, 'edges' => $edges );
	}

	/** FUT-07: transparent contribution counts; no rank or opaque score. */
	public static function knowledge_coverage( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_profile_knowledge_coverage_v1', $user_id, $viewer_id, 900 );
		$out = array();
		foreach ( array_slice( (array) ( $claim['categories'] ?? array() ), 0, 30 ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$out[] = array( 'category' => sanitize_text_field( (string) ( $item['category'] ?? '' ) ), 'evidence_count' => min( 100000, max( 0, absint( $item['evidence_count'] ?? 0 ) ) ) );
		}
		return array( 'categories' => $out, 'ranking' => false, 'paid_influence' => false );
	}

	/** FUT-08: grounded AI about public professional work only. */
	public static function ask_about_work( $public_id, $viewer_id, $question ) {
		if ( ! $viewer_id || ! SPD_Membership_Adapter::is_member_eligible( $viewer_id ) ) { return new WP_Error( 'spd_ai_login_required', __( 'Log in to ask about this doctor’s public work.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$question = trim( self::text( $question, 500 ) );
		if ( strlen( $question ) < 3 ) { return new WP_Error( 'spd_ai_question_required', __( 'Enter a question about this professional’s public work.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( preg_match( '/\b(diagnos|prescrib|dosage|dose|emergency|guaranteed cure|treatment recommendation)\b|تشخیص|نسخہ|خوراک|ایمرجنسی|علاج تجویز/ui', $question ) ) { return new WP_Error( 'spd_ai_scope_restricted', __( 'This assistant answers only about the professional’s published work; it does not diagnose, prescribe, dose or replace emergency care.', 'sabri-profiles-doctors' ), array( 'status' => 422 ) ); }
		$profile = SPD_Profile_Repository::instance()->find_by_public_id( (string) $public_id );
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $viewer_id ) ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$claim = self::current_claim( 'sabri_file16_grounded_profile_ask_v1', $profile['user_id'], $viewer_id, 120, array( $profile['public_id'], $question ) );
		if ( ! $claim || empty( $claim['grounded'] ) || 'public_professional_work' !== sanitize_key( (string) ( $claim['scope'] ?? '' ) ) ) { return new WP_Error( 'spd_ai_unavailable', __( 'The grounded profile assistant is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$citations = array();
		foreach ( array_slice( (array) ( $claim['citations'] ?? array() ), 0, 12 ) as $c ) { if ( is_array( $c ) && ! empty( $c['url'] ) && SPD_Helpers::same_origin_url( (string) $c['url'] ) ) { $citations[] = array( 'title' => sanitize_text_field( (string) ( $c['title'] ?? '' ) ), 'url' => esc_url_raw( $c['url'] ) ); } }
		return array( 'answer' => self::text( $claim['answer'] ?? '', 6000 ), 'citations' => $citations, 'grounded' => true, 'scope' => 'public_professional_work', 'medical_advice' => false );
	}

	/** FUT-09: owner-approved multilingual profile editions. */
	public static function translations( $profile_id ) {
		global $wpdb;
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT locale,headline,bio,source,status,version,updated_at FROM ' . self::translations_table() . " WHERE profile_id=%d AND status='approved' ORDER BY locale ASC LIMIT 20", absint( $profile_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return new WP_Error( 'spd_future_translation_store_unavailable', __( 'Profile translations are temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$out = array();
		foreach ( $rows as $row ) { $out[] = array( 'locale' => sanitize_text_field( $row['locale'] ), 'headline' => self::text( $row['headline'], 250 ), 'bio' => self::text( $row['bio'], 4000 ), 'source' => in_array( $row['source'], array( 'human','machine' ), true ) ? $row['source'] : 'human', 'version' => absint( $row['version'] ), 'updated_at' => sanitize_text_field( $row['updated_at'] ) ); }
		return $out;
	}

	/** FUT-10: privacy-safe relay owned by File 17. */
	public static function contact_relay( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_file17_profile_contact_relay_v1', $user_id, $viewer_id, 300 );
		$url = (string) ( $claim['url'] ?? '' );
		if ( empty( $claim['available'] ) || ! $url || ! SPD_Helpers::same_origin_url( $url ) ) { return array(); }
		return array( 'url' => esc_url_raw( $url ), 'label' => sanitize_text_field( (string) ( $claim['label'] ?? __( 'Contact securely', 'sabri-profiles-doctors' ) ) ), 'address_hidden' => true );
	}

	/** FUT-11: verified external/institutional links. */
	public static function verified_links( $user_id, $viewer_id = 0 ) {
		$claim = self::current_claim( 'sabri_verified_external_profile_links_v1', $user_id, $viewer_id, 900 );
		$out = array();
		foreach ( array_slice( (array) ( $claim['items'] ?? array() ), 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['verified'] ) ) { continue; }
			$url = self::safe_external_url( $item['url'] ?? '' ); if ( ! $url ) { continue; }
			$out[] = array( 'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ), 'type' => sanitize_key( (string) ( $item['type'] ?? 'website' ) ), 'url' => $url, 'domain' => sanitize_text_field( (string) wp_parse_url( $url, PHP_URL_HOST ) ), 'verified' => true );
		}
		return $out;
	}

	/** FUT-12: structured one-page card + full public professional dossier. */
	public static function dossier( array $dto ) {
		return array(
			'card' => array( 'display_name' => $dto['display_name'], 'badge' => $dto['badge'], 'headline' => $dto['professional']['professional_title'] ?? '', 'location' => trim( ( $dto['fields']['city'] ?? '' ) . ', ' . ( $dto['fields']['country'] ?? '' ), ', ' ), 'canonical_url' => $dto['canonical_url'], 'share_url' => $dto['share']['short_url'] ?? $dto['canonical_url'] ),
			'full' => array( 'fields' => $dto['fields'], 'extended' => $dto['extended'] ?? array(), 'credentials' => $dto['credential_card'] ?? array(), 'professional' => $dto['professional'], 'organizations' => $dto['organizations'] ?? array(), 'timeline_url' => $dto['timeline_url'] ),
			'printable' => true,
		);
	}

	/** FUT-13: scriptless, tracking-free embeddable verified card. */
	public static function embed_card( array $dto ) {
		$avatar = $dto['media']['avatar']['url'] ?? '';
		$html = '<a class="sabri-verified-profile-card" href="' . esc_url( $dto['canonical_url'] ) . '" rel="noopener"><strong>' . esc_html( $dto['display_name'] ) . '</strong><span> — ' . esc_html( $dto['badge']['label'] ?? '' ) . '</span></a>';
		return array( 'html' => $html, 'canonical_url' => $dto['canonical_url'], 'avatar_url' => $avatar && SPD_Helpers::same_origin_url( $avatar ) ? esc_url_raw( $avatar ) : '', 'script_required' => false, 'tracking' => false );
	}

	/** FUT-14: per-field freshness and owner reconfirmation. */
	public static function freshness( array $profile ) {
		global $wpdb;
		$out = array();
		$allowed = array_merge( array( 'bio','country','city','languages','studied_books' ), SPD_Central_Profile::extended_fields() );
		foreach ( $allowed as $key ) {
			$row = $profile['fields'][ $key ] ?? array(); if ( ! $row ) { continue; }
			$wpdb->last_error = '';
			$att = $wpdb->get_row( $wpdb->prepare( 'SELECT confirmed_at,expires_at,version FROM ' . self::attestations_table() . ' WHERE profile_id=%d AND field_key=%s LIMIT 1', absint( $profile['id'] ), $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $wpdb->last_error ) { return new WP_Error( 'spd_future_freshness_store_unavailable', __( 'Profile freshness evidence is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
			$out[ $key ] = array( 'last_updated' => sanitize_text_field( (string) ( $row['updated_at'] ?? $profile['updated_at'] ?? '' ) ), 'last_confirmed' => sanitize_text_field( (string) ( $att['confirmed_at'] ?? '' ) ), 'confirm_by' => sanitize_text_field( (string) ( $att['expires_at'] ?? '' ) ), 'stale' => $att ? strtotime( $att['expires_at'] . ' UTC' ) < time() : true );
		}
		return $out;
	}

	/** FUT-15: user-readable, privacy-safe profile change history. */
	public static function change_history( $public_id, $viewer_id, $owner_user_id ) {
		global $wpdb;
		if ( absint( $viewer_id ) !== absint( $owner_user_id ) ) { return array(); }
		$table = SPD_DB::table( 'events' );
		$names = array( 'PublicProfileUpdated.v1','ProfileVisibilityChanged.v1','ProfileMediaChanged.v1','ProfileShareLinkRotated.v1','ProfileDelegationChanged.v1','ProfileFutureStateChanged.v1','ProfileTranslationUpdated.v1','ProfileFieldReconfirmed.v1' );
		$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$params = array_merge( array( $public_id ), $names );
		$sql = $wpdb->prepare( "SELECT event_name,payload,created_at FROM {$table} WHERE aggregate_type='profile' AND aggregate_id=%s AND event_name IN ({$placeholders}) ORDER BY id DESC LIMIT 50", $params ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return new WP_Error( 'spd_future_history_store_unavailable', __( 'Profile change history is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$out = array();
		foreach ( $rows as $row ) { $p = json_decode( (string) $row['payload'], true ); $out[] = array( 'event' => sanitize_text_field( $row['event_name'] ), 'changed_fields' => array_slice( array_map( 'sanitize_key', (array) ( $p['changed_fields'] ?? array() ) ), 0, 30 ), 'version' => absint( $p['version'] ?? 0 ), 'occurred_at' => sanitize_text_field( $row['created_at'] ) ); }
		return $out;
	}

	/** FUT-16: governed retired/legacy professional state. */
	public static function lifecycle( array $profile ) {
		$state = self::state_for_profile( $profile['id'] );
		if ( is_wp_error( $state ) ) { return array( 'status' => 'unknown', 'active_professional' => false, 'reason' => '', 'changed_at' => '', 'state_degraded' => true ); }
		$status = sanitize_key( (string) $state['professional_lifecycle'] );
		if ( ! in_array( $status, array( 'active','retired','legacy' ), true ) ) { return array( 'status' => 'unknown', 'active_professional' => false, 'reason' => '', 'changed_at' => '', 'state_degraded' => true ); }
		return array( 'status' => $status, 'active_professional' => 'active' === $status, 'reason' => self::text( $state['lifecycle_reason'] ?? '', 500 ), 'changed_at' => sanitize_text_field( (string) ( $state['lifecycle_changed_at'] ?? '' ) ) );
	}

	/** FUT-17: FHIR-compatible public professional projection; no patient record. */
	public static function fhir_projection( array $dto ) {
		$lifecycle = $dto['future']['lifecycle']['status'] ?? 'unknown';
		$practitioner = array( 'resourceType' => 'Practitioner', 'id' => 'sabri-' . strtolower( str_replace( '-', '', $dto['public_id'] ) ), 'active' => 'active' === $lifecycle, 'identifier' => array( array( 'system' => 'https://sabrihomeopathy.com/profile-id', 'value' => $dto['public_id'] ) ), 'name' => array( array( 'text' => $dto['display_name'] ) ) );
		if ( ! empty( $dto['fields']['languages'] ) ) { $practitioner['communication'] = array( array( 'language' => array( 'text' => $dto['fields']['languages'] ) ) ); }
		if ( ! empty( $dto['credential_card'] ) ) { $practitioner['qualification'] = array( array( 'code' => array( 'text' => $dto['credential_card']['qualification'] ?? $dto['credential_card']['degree'] ?? 'Verified professional credential' ), 'issuer' => array( 'display' => $dto['credential_card']['institution'] ?? $dto['credential_card']['licensing_authority'] ?? '' ) ) ); }
		$role = array( 'resourceType' => 'PractitionerRole', 'id' => 'sabri-role-' . strtolower( str_replace( '-', '', $dto['public_id'] ) ), 'active' => 'active' === $lifecycle, 'practitioner' => array( 'reference' => 'Practitioner/' . $practitioner['id'], 'display' => $dto['display_name'] ) );
		if ( ! empty( $dto['professional']['specialty'] ) ) { $role['specialty'] = array( array( 'text' => $dto['professional']['specialty'] ) ); }
		return array( 'practitioner' => $practitioner, 'practitioner_role' => $role, 'clinical_record' => false );
	}

	/** FUT-18: federation-ready actor projection; transport remains external. */
	public static function federation_projection( array $profile, array $dto ) {
		$state = self::state_for_profile( $profile['id'] );
		if ( is_wp_error( $state ) ) { return array( 'opt_in' => false, 'actor_id' => $dto['canonical_url'] . '#actor', 'type' => 'Person', 'name' => $dto['display_name'], 'url' => $dto['canonical_url'], 'transport_owner' => 'external', 'transport_active' => false, 'state_degraded' => true ); }
		$opt_in = ! empty( $state['federation_opt_in'] );
		$out = array( 'opt_in' => $opt_in, 'actor_id' => $dto['canonical_url'] . '#actor', 'type' => 'Person', 'name' => $dto['display_name'], 'url' => $dto['canonical_url'], 'transport_owner' => 'external', 'transport_active' => false );
		if ( ! $opt_in ) { return $out; }
		$claim = self::current_claim( 'sabri_federation_actor_transport_v1', $profile['user_id'], 0, 300 );
		if ( $claim && ! empty( $claim['active'] ) ) {
			foreach ( array( 'inbox','outbox' ) as $key ) { if ( ! empty( $claim[ $key ] ) && SPD_Helpers::same_origin_url( (string) $claim[ $key ] ) ) { $out[ $key ] = esc_url_raw( $claim[ $key ] ); } }
			$out['transport_active'] = ! empty( $out['inbox'] ) && ! empty( $out['outbox'] );
		}
		return $out;
	}

	public static function augment_personal_site_dto( array $dto, array $profile, $viewer_id = 0 ) {
		$user_id = absint( $profile['user_id'] );
		$lifecycle = self::lifecycle( $profile );
		$translations = self::translations( $profile['id'] );
		$freshness = self::freshness( $profile );
		$history = self::change_history( $profile['public_id'], $viewer_id, $user_id );
		$native_degraded = array();
		foreach ( array( 'translations' => $translations, 'freshness' => $freshness, 'change_history' => $history ) as $component => $value ) {
			if ( is_wp_error( $value ) ) { $native_degraded[] = $component; }
		}
		$future = array(
			'credential_wallet' => self::credential_wallet( $user_id, $viewer_id ),
			'selective_disclosure' => array( 'supported' => true, 'max_ttl_seconds' => self::DISCLOSURE_MAX_TTL, 'public_only' => true ),
			'learning_passport' => self::learning_passport( $user_id, $viewer_id ),
			'trust_timeline' => self::trust_timeline( $user_id, $viewer_id ),
			'expertise_evidence' => self::expertise_evidence( $user_id, $viewer_id ),
			'knowledge_graph' => self::knowledge_graph( $user_id, $viewer_id ),
			'knowledge_coverage' => self::knowledge_coverage( $user_id, $viewer_id ),
			'ai_work_assistant' => array( 'available_for_members' => true, 'scope' => 'public_professional_work', 'medical_advice' => false ),
			'multilingual_editions' => is_wp_error( $translations ) ? array() : $translations,
			'contact_relay' => self::contact_relay( $user_id, $viewer_id ),
			'verified_links' => self::verified_links( $user_id, $viewer_id ),
			'freshness' => is_wp_error( $freshness ) ? array() : $freshness,
			'change_history' => is_wp_error( $history ) ? array() : $history,
			'lifecycle' => $lifecycle,
		);
		if ( $native_degraded ) {
			$future['native_store_degraded'] = true;
			$future['degraded_components'] = $native_degraded;
		}
		$dto['future'] = $future;
		$dto['future']['dossier'] = self::dossier( $dto );
		$dto['future']['embed_card'] = self::embed_card( $dto );
		$dto['future']['fhir'] = self::fhir_projection( $dto );
		$dto['future']['federation'] = self::federation_projection( $profile, $dto );
		if ( ! empty( $lifecycle['state_degraded'] ) ) { $dto['future']['state_degraded'] = true; }
		if ( ! $lifecycle['active_professional'] ) { $dto['contacts'] = array(); if ( isset( $dto['clinic']['appointment_url'] ) ) { unset( $dto['clinic']['appointment_url'] ); } $dto['future']['contact_relay'] = array(); }
		return $dto;
	}

	private static function owner_profile( $actor_id, $public_id = '' ) {
		$actor_id = absint( $actor_id );
		$profile = $public_id ? SPD_Profile_Repository::instance()->find_by_public_id( (string) $public_id ) : SPD_Profile_Repository::instance()->find_by_user_id( $actor_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( absint( $profile['user_id'] ) !== $actor_id ) { return new WP_Error( 'spd_forbidden', __( 'Only the profile owner may change this future-profile setting.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
		return is_wp_error( $guard ) ? $guard : $profile;
	}

	private static function insert_event( $event_name, array $profile, array $payload ) {
		global $wpdb; $table = SPD_DB::table( 'events' ); $json = wp_json_encode( $payload ); if ( false === $json ) { return false; }
		return (bool) $wpdb->insert( $table, array( 'event_uuid' => wp_generate_uuid4(), 'event_name' => sanitize_text_field( $event_name ), 'aggregate_type' => 'profile', 'aggregate_id' => $profile['public_id'], 'payload' => $json, 'status' => 'pending', 'attempts' => 0, 'available_at' => SPD_Helpers::now(), 'created_at' => SPD_Helpers::now() ) );
	}

	public static function save_translation( $actor_id, $public_id, $locale, $headline, $bio, $source = 'human' ) {
		global $wpdb; $profile = self::owner_profile( $actor_id, $public_id ); if ( is_wp_error( $profile ) ) { return $profile; }
		$locale = SPD_Helpers::normalize_locale( $locale ); if ( ! $locale ) { return new WP_Error( 'spd_translation_locale_invalid', __( 'Choose a valid locale.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$source = 'machine' === sanitize_key( $source ) ? 'machine' : 'human'; $headline = self::text( $headline, 250 ); $bio = self::text( $bio, 4000 );
		if ( ! $headline && ! $bio ) { return new WP_Error( 'spd_translation_empty', __( 'A translated headline or biography is required.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$table = self::translations_table(); $now = SPD_Helpers::now();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE profile_id=%d AND locale=%s LIMIT 1", $profile['id'], $locale ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array( 'headline' => $headline, 'bio' => $bio, 'source' => $source, 'status' => 'approved', 'approved_by' => absint( $actor_id ), 'updated_at' => $now );
		if ( $existing ) { $data['version'] = absint( $existing['version'] ) + 1; $ok = 1 === $wpdb->update( $table, $data, array( 'id' => absint( $existing['id'] ), 'version' => absint( $existing['version'] ) ) ); }
		else { $data += array( 'profile_id' => absint( $profile['id'] ), 'locale' => $locale, 'version' => 1, 'created_at' => $now ); $ok = (bool) $wpdb->insert( $table, $data ); }
		if ( ! $ok || ! self::insert_event( 'ProfileTranslationUpdated.v1', $profile, array( 'changed_fields' => array( 'translation:' . $locale ), 'version' => absint( $profile['version'] ) ) ) ) { return new WP_Error( 'spd_translation_save_failed', __( 'The profile translation changed concurrently or could not be saved.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		return array( 'locale' => $locale, 'source' => $source, 'approved' => true );
	}

	public static function reconfirm_field( $actor_id, $public_id, $field_key, $days = 365 ) {
		global $wpdb; $profile = self::owner_profile( $actor_id, $public_id ); if ( is_wp_error( $profile ) ) { return $profile; }
		$allowed = array_merge( array( 'bio','country','city','languages','studied_books' ), SPD_Central_Profile::extended_fields() ); $field_key = sanitize_key( $field_key );
		if ( ! in_array( $field_key, $allowed, true ) ) { return new WP_Error( 'spd_reconfirm_field_invalid', __( 'That profile field cannot be reconfirmed here.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$days = min( 730, max( 30, absint( $days ) ) ); $now = SPD_Helpers::now(); $expires = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * $days ); $table = self::attestations_table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE profile_id=%d AND field_key=%s LIMIT 1", $profile['id'], $field_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array( 'confirmed_by' => absint( $actor_id ), 'confirmed_at' => $now, 'expires_at' => $expires );
		if ( $existing ) { $data['version'] = absint( $existing['version'] ) + 1; $ok = 1 === $wpdb->update( $table, $data, array( 'id' => absint( $existing['id'] ), 'version' => absint( $existing['version'] ) ) ); }
		else { $data += array( 'profile_id' => absint( $profile['id'] ), 'field_key' => $field_key, 'version' => 1 ); $ok = (bool) $wpdb->insert( $table, $data ); }
		if ( ! $ok || ! self::insert_event( 'ProfileFieldReconfirmed.v1', $profile, array( 'changed_fields' => array( $field_key ), 'version' => absint( $profile['version'] ) ) ) ) { return new WP_Error( 'spd_reconfirm_failed', __( 'The field confirmation changed concurrently or could not be saved.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		return array( 'field_key' => $field_key, 'confirmed_at' => $now, 'expires_at' => $expires );
	}

	public static function set_future_state( $actor_id, $public_id, array $input ) {
		global $wpdb; $profile = SPD_Profile_Repository::instance()->find_by_public_id( (string) $public_id ); if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$actor_id = absint( $actor_id );
		$is_owner = absint( $profile['user_id'] ) === $actor_id;
		$is_governor = SPD_Membership_Adapter::can_manage_founder( $actor_id ) || SPD_Membership_Adapter::can_operate_profiles( $actor_id );
		if ( ! $is_owner && ! $is_governor ) { return new WP_Error( 'spd_forbidden', __( 'You cannot change this professional lifecycle state.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		if ( $is_owner ) { $guard = SPD_Authorization::mutation_guard( $profile, $actor_id ); if ( is_wp_error( $guard ) ) { return $guard; } }
		$current = self::state_for_profile( $profile['id'] );
		if ( is_wp_error( $current ) ) { return $current; }
		$federation = array_key_exists( 'federation_opt_in', $input ) ? ( ! empty( $input['federation_opt_in'] ) ? 1 : 0 ) : absint( $current['federation_opt_in'] );
		$lifecycle = sanitize_key( (string) ( $input['professional_lifecycle'] ?? $current['professional_lifecycle'] ) ); if ( ! in_array( $lifecycle, array( 'active','retired','legacy' ), true ) ) { return new WP_Error( 'spd_lifecycle_invalid', __( 'Choose an allowed professional lifecycle state.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( 'legacy' === $lifecycle && ! $is_governor ) { return new WP_Error( 'spd_legacy_governance_required', __( 'Legacy/memorial status requires governed approval.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$reason = self::text( $input['lifecycle_reason'] ?? $current['lifecycle_reason'], 500 ); if ( in_array( $lifecycle, array( 'retired','legacy' ), true ) && ! $reason ) { return new WP_Error( 'spd_lifecycle_reason_required', __( 'A reason is required for retired or legacy status.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$now = SPD_Helpers::now(); $changed_at = $lifecycle !== $current['professional_lifecycle'] ? $now : ( $current['lifecycle_changed_at'] ?: $now ); $table = self::state_table();
		$data = array( 'federation_opt_in' => $federation, 'professional_lifecycle' => $lifecycle, 'lifecycle_reason' => $reason, 'lifecycle_changed_at' => $changed_at, 'version' => absint( $current['version'] ) + 1, 'updated_at' => $now );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT profile_id FROM {$table} WHERE profile_id=%d", $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) { $ok = 1 === $wpdb->update( $table, $data, array( 'profile_id' => absint( $profile['id'] ), 'version' => absint( $current['version'] ) ) ); }
		else { $ok = (bool) $wpdb->insert( $table, array_merge( array( 'profile_id' => absint( $profile['id'] ) ), $data ) ); }
		if ( ! $ok || ! self::insert_event( 'ProfileFutureStateChanged.v1', $profile, array( 'changed_fields' => array( 'federation_opt_in','professional_lifecycle' ), 'version' => absint( $profile['version'] ), 'lifecycle' => $lifecycle ) ) ) { return new WP_Error( 'spd_future_state_save_failed', __( 'The future-profile state changed concurrently or could not be saved.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		return array( 'federation_opt_in' => (bool) $federation, 'professional_lifecycle' => $lifecycle, 'lifecycle_changed_at' => $changed_at );
	}
}
