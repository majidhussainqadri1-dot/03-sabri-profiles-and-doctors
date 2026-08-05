<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Routes {
	public function hooks() {
		add_action( 'init', array( $this, 'rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'canonical' ), 1 );
	}

	public function rewrites() {
		$map = (array) get_option( 'spd_page_map', array() );
		$profile_target = ! empty( $map['profile'] ) ? 'index.php?page_id=' . absint( $map['profile'] ) : 'index.php?pagename=profile';
		$founder_target = ! empty( $map['founder'] ) ? 'index.php?page_id=' . absint( $map['founder'] ) : 'index.php?pagename=founder';
		$account_target = ! empty( $map['account_profile'] ) ? 'index.php?page_id=' . absint( $map['account_profile'] ) : 'index.php?pagename=account-profile';
		add_rewrite_rule( '^founder/?$', $founder_target, 'top' );
		add_rewrite_rule( '^account/profile/?$', $account_target, 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/timeline/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=timeline', 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/report/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=report', 'top' );
		add_rewrite_rule( '^profile/([0-9a-fA-F-]{36})/?$', $profile_target . '&spd_public_id=$matches[1]&spd_view=profile', 'top' );
		add_rewrite_rule( '^u/([^/]+)/?$', 'index.php?spd_alias=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'spd_public_id';
		$vars[] = 'spd_view';
		$vars[] = 'spd_alias';
		return $vars;
	}

	public function redirects() {
		$alias = sanitize_title( (string) get_query_var( 'spd_alias' ) );
		if ( $alias ) {
			$profile = SPD_Profile_Repository::instance()->find_by_slug( $alias );
			if ( $profile ) {
				wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 301 );
				exit;
			}
			status_header( 404 );
			return;
		}

		$map = (array) get_option( 'spd_page_map', array() );
		if ( ! empty( $map['legacy_profile'] ) && is_page( absint( $map['legacy_profile'] ) ) && isset( $_GET['user'] ) ) {
			$user = get_user_by( 'slug', sanitize_title( wp_unslash( $_GET['user'] ) ) );
			if ( $user ) {
				$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user->ID );
				if ( $profile && ! is_wp_error( $profile ) ) {
					wp_safe_redirect( SPD_Helpers::canonical_profile_url( $profile['public_id'] ), 301 );
					exit;
				}
			}
		}

		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( $public_id ) {
			$profile = SPD_Profile_Repository::instance()->find_by_public_id( $public_id );
			if ( $profile && 'tombstoned' === $profile['state'] ) {
				status_header( 410 );
			}
		}

		if ( ! empty( $map['account_profile'] ) && is_page( absint( $map['account_profile'] ) ) && ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink( absint( $map['account_profile'] ) ) );
			wp_safe_redirect( $login );
			exit;
		}
	}

	public function current_context() {
		$map = (array) get_option( 'spd_page_map', array() );
		if ( ! empty( $map['account_profile'] ) && is_page( absint( $map['account_profile'] ) ) ) {
			return 'private';
		}
		$view = sanitize_key( (string) get_query_var( 'spd_view' ) );
		if ( 'report' === $view ) {
			return 'private';
		}
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( $public_id ) {
			if ( is_user_logged_in() ) {
				return 'private';
			}
			$profile = SPD_Profile_Repository::instance()->find_by_public_id( $public_id );
			if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, get_current_user_id() ) ) {
				return 'private';
			}
			if ( 'public' !== $profile['profile_visibility'] || get_current_user_id() === $profile['user_id'] ) {
				return 'private';
			}
		}
		return 'public';
	}

	public function private_headers() {
		if ( 'private' !== $this->current_context() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
	}

	public function robots( $robots ) {
		if ( 'private' === $this->current_context() ) {
			$robots['noindex'] = true;
			$robots['nofollow'] = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	public function canonical() {
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( ! $public_id || 'private' === $this->current_context() ) {
			return;
		}
		$view = sanitize_key( (string) get_query_var( 'spd_view' ) );
		$url = 'timeline' === $view ? SPD_Helpers::timeline_url( $public_id ) : SPD_Helpers::canonical_profile_url( $public_id );
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}
