<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Router {
	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'prevent_false_redirect' ), 10, 2 );
	}

	public static function prevent_false_redirect( $redirect, $requested ) {
		unset( $requested );
		return get_query_var( 'spd_public_id' ) || get_query_var( 'spd_account_profile' ) ? false : $redirect;
	}

	public static function dispatch() {
		$public_id = sanitize_key( (string) get_query_var( 'spd_public_id' ) );
		$is_account = (bool) get_query_var( 'spd_account_profile' );
		if ( ! $public_id && ! $is_account ) { return; }
		if ( $is_account ) {
			if ( ! is_user_logged_in() ) { auth_redirect(); }
			self::render_page( __( 'Account Profile', 'sabri-profiles-doctors' ), do_shortcode( '[sabri_edit_profile]' ), true );
		}
		$user_id = SPD_Public_Identity::resolve( $public_id );
		if ( ! $user_id ) { status_header( 404 ); self::render_page( __( 'Profile unavailable', 'sabri-profiles-doctors' ), '<div class="spd-notice">This profile is unavailable.</div>', true ); }
		$_GET['user'] = get_userdata( $user_id )->user_nicename; // Compatibility input for the existing renderer.
		if ( get_query_var( 'spd_timeline' ) ) {
			if ( ! apply_filters( 'spd_can_view_profile', true, get_current_user_id(), $user_id ) ) { status_header( 404 ); self::render_page( 'Profile unavailable', '<div class="spd-notice">This profile is unavailable.</div>', true ); }
			self::render_page( __( 'Profile Timeline', 'sabri-profiles-doctors' ), SPD_Timeline::render( $user_id ), false );
		}
		self::render_page( get_userdata( $user_id )->display_name, do_shortcode( '[sabri_member_profile]' ), false );
	}

	private static function render_page( $title, $content, $private ) {
		if ( $private ) { nocache_headers(); header( 'X-Robots-Tag: noindex, nofollow, noarchive', true ); }
		status_header( 200 );
		get_header();
		echo '<main id="primary" class="site-main spd-route"><div class="spd-route__inner"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>' . wp_kses_post( $content ) . '</div></main>';
		get_footer();
		exit;
	}
}
