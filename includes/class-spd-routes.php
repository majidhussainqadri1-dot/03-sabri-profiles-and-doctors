<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Routes {
	private static function fallback_slug( $key ) {
		$map = array(
			'founder'         => 'founder',
			'profile'         => 'profile',
			'account_profile' => 'account-profile',
			'personal_site'   => 'account-profile-personal-site',
			'private_preview' => 'account-profile-preview',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	private function is_mapped_or_fallback_page( $key, array $map = array() ) {
		if ( ! empty( $map[ $key ] ) && is_page( absint( $map[ $key ] ) ) ) { return true; }
		$slug = self::fallback_slug( $key );
		return $slug ? is_page( $slug ) : false;
	}

	private function private_page_url( $key, array $map ) {
		if ( ! empty( $map[ $key ] ) ) {
			$url = get_permalink( absint( $map[ $key ] ) );
			if ( $url ) { return $url; }
		}
		$slug = self::fallback_slug( $key );
		return $slug ? home_url( user_trailingslashit( $slug ) ) : home_url( '/' );
	}

	public function hooks() {
		add_action( 'init', array( $this, 'rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_action( 'send_headers', array( $this, 'private_headers' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'canonical' ), 1 );
	}

	public function rewrites() {
		$map = (array) get_option( 'spd_page_map', array() );
		$profile_target = ! empty( $map['profile'] ) ? 'index.php?page_id=' . absint( $map['profile'] ) : 'index.php?pagename=profile';
		$founder_target = ! empty( $map['founder'] ) ? 'index.php?page_id=' . absint( $map['founder'] ) : 'index.php?pagename=founder';
		$account_target = ! empty( $map['account_profile'] ) ? 'index.php?page_id=' . absint( $map['account_profile'] ) : 'index.php?pagename=account-profile';
		$personal_target = ! empty( $map['personal_site'] ) ? 'index.php?page_id=' . absint( $map['personal_site'] ) : 'index.php?pagename=account-profile-personal-site';
		$preview_target = ! empty( $map['private_preview'] ) ? 'index.php?page_id=' . absint( $map['private_preview'] ) : 'index.php?pagename=account-profile-preview';
		add_rewrite_rule( '^founder/?$', $founder_target, 'top' );
		add_rewrite_rule( '^account/profile/personal-site/?$', $personal_target, 'top' );
		add_rewrite_rule( '^account/profile/preview/?$', $preview_target, 'top' );
		add_rewrite_rule( '^account/profile/?$', $account_target, 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/timeline/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=timeline', 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/report/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=report', 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=profile', 'top' );
		add_rewrite_rule( '^u/([^/]+)/?$', 'index.php?spd_alias=$matches[1]', 'top' );
		add_rewrite_rule( '^p/([0-9a-z]+-[0-9a-f]{16})/?$', 'index.php?spd_share=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) { $vars[] = 'spd_public_id'; $vars[] = 'spd_view'; $vars[] = 'spd_alias'; $vars[] = 'spd_share'; return $vars; }

	public function redirects() {
		global $wpdb;
		$share = sanitize_text_field( (string) get_query_var( 'spd_share' ) );
		if ( $share ) {
			$wpdb->last_error = '';
			$profile = SPD_Central_Profile::resolve_share_token( $share );
			if ( $wpdb->last_error || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) { status_header( 503 ); return; }
			if ( $profile ) { wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 302 ); exit; }
			status_header( 404 ); return;
		}
		$alias = sanitize_title( (string) get_query_var( 'spd_alias' ) );
		if ( $alias ) {
			$profile = SPD_Profile_Repository::instance()->find_by_slug_strict( $alias );
			if ( is_wp_error( $profile ) ) { $data = $profile->get_error_data(); status_header( is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 503 ); return; }
			if ( $profile && SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) { wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 301 ); exit; }
			status_header( 404 ); return;
		}

		$map = (array) get_option( 'spd_page_map', array() );

		// Historical /profile/?public_id=<UUID> links are accepted only as a
		// migration edge. They must never render a second public representation:
		// public records move permanently to the immutable UUID route, while
		// invalid/private/uncertain records fail without exposing object existence.
		$legacy_public_id = '';
		if ( ! get_query_var( 'spd_public_id' ) && isset( $_GET['public_id'] ) && $this->is_mapped_or_fallback_page( 'profile', $map ) ) {
			$legacy_public_id = sanitize_text_field( wp_unslash( $_GET['public_id'] ) );
		}
		if ( '' !== $legacy_public_id ) {
			if ( ! SPD_Helpers::valid_uuid( $legacy_public_id ) ) { status_header( 404 ); return; }
			$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( $legacy_public_id );
			if ( is_wp_error( $profile ) ) {
				$data = $profile->get_error_data();
				status_header( is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 503 );
				return;
			}
			if ( $profile && SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) {
				wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 301 );
				exit;
			}
			status_header( 404 );
			return;
		}

		if ( ! empty( $map['legacy_profile'] ) && is_page( absint( $map['legacy_profile'] ) ) && isset( $_GET['user'] ) ) {
			$user = get_user_by( 'slug', sanitize_title( wp_unslash( $_GET['user'] ) ) );
			if ( $user ) {
				$wpdb->last_error = '';
				$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user->ID, false );
				if ( $wpdb->last_error || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) { status_header( 503 ); return; }
				if ( $profile && ! is_wp_error( $profile ) && SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) { wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 301 ); exit; }
			}
			status_header( 404 ); return;
		}
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( $public_id ) {
			$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( $public_id );
			if ( is_wp_error( $profile ) ) { $data = $profile->get_error_data(); status_header( is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 503 ); return; }
			if ( $profile && 'tombstoned' === $profile['state'] ) { status_header( 410 ); }
		}
		foreach ( array( 'account_profile', 'personal_site', 'private_preview' ) as $private_page ) {
			if ( $this->is_mapped_or_fallback_page( $private_page, $map ) && ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( $this->private_page_url( $private_page, $map ) ) );
				exit;
			}
		}
		if ( isset( $_GET['print_profile'] ) && $public_id ) { header( 'X-Robots-Tag: noindex, nofollow, noarchive', true ); }
	}

	public function current_context() {
		$map = (array) get_option( 'spd_page_map', array() );
		foreach ( array( 'account_profile', 'personal_site', 'private_preview' ) as $key ) { if ( $this->is_mapped_or_fallback_page( $key, $map ) ) { return 'private'; } }
		$view = sanitize_key( (string) get_query_var( 'spd_view' ) ); if ( 'report' === $view ) { return 'private'; }
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( $public_id ) {
			if ( is_user_logged_in() ) { return 'private'; }
			$profile = SPD_Profile_Repository::instance()->find_by_public_id( $public_id );
			if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, 0 ) || 'public' !== $profile['profile_visibility'] ) { return 'private'; }
		}
		return 'public';
	}

	private function is_file03_dynamic_route() {
		$map = (array) get_option( 'spd_page_map', array() );
		if ( get_query_var( 'spd_public_id' ) || get_query_var( 'spd_alias' ) || get_query_var( 'spd_share' ) ) { return true; }
		foreach ( array( 'founder', 'profile', 'account_profile', 'personal_site', 'private_preview' ) as $key ) {
			if ( $this->is_mapped_or_fallback_page( $key, $map ) ) { return true; }
		}
		return false;
	}

	public function private_headers() {
		if ( ! $this->is_file03_dynamic_route() ) { return; }
		$private = 'private' === $this->current_context();
		nocache_headers();
		header( 'Cache-Control: ' . ( $private ? 'private, ' : '' ) . 'no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
		if ( $private ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			header( 'Referrer-Policy: no-referrer', true );
		}
	}

	public function robots( $robots ) { if ( 'private' === $this->current_context() ) { $robots['noindex'] = true; $robots['nofollow'] = true; $robots['noarchive'] = true; } return $robots; }
	public function canonical() {
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) ); if ( ! $public_id || 'private' === $this->current_context() ) { return; }
		$view = sanitize_key( (string) get_query_var( 'spd_view' ) ); $url = 'timeline' === $view ? SPD_Helpers::timeline_url( $public_id ) : SPD_Helpers::canonical_profile_url( $public_id ); echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}
