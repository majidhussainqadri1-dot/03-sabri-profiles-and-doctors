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

	private static function can_view( $viewer_id, $owner_id ) {
		if ( absint( $viewer_id ) === absint( $owner_id ) || current_user_can( 'smc_manage_membership' ) ) { return true; }
		if ( SPD_Membership_Adapter::is_founder( $owner_id ) ) { return true; }
		if ( SPD_Membership_Adapter::is_doctor( $owner_id ) ) { return SPD_Verification_Adapter::directory_eligible( $owner_id ); }
		$visibility = SPD_Membership_Adapter::public_visibility( $owner_id );
		return 'public' === $visibility || ( 'members' === $visibility && is_user_logged_in() );
	}

	public static function dispatch() {
		$public_id = sanitize_key( (string) get_query_var( 'spd_public_id' ) );
		$is_account = (bool) get_query_var( 'spd_account_profile' );
		if ( ! $public_id && ! $is_account ) { return; }
		if ( $is_account ) {
			if ( ! is_user_logged_in() ) { auth_redirect(); }
			self::render_page( __( 'Account Profile', 'sabri-profiles-doctors' ), do_shortcode( '[sabri_edit_profile]' ), true, 200 );
		}
		$user_id = SPD_Public_Identity::resolve( $public_id );
		if ( ! $user_id || ! self::can_view( get_current_user_id(), $user_id ) ) {
			self::render_page( __( 'Profile unavailable', 'sabri-profiles-doctors' ), '<div class="spd-notice">This profile is unavailable.</div>', true, 404 );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) { self::render_page( 'Profile unavailable', '<div class="spd-notice">This profile is unavailable.</div>', true, 404 ); }
		$_GET['user'] = $user->user_nicename; // Compatibility input for the existing renderer.
		$is_private = 'public' !== SPD_Membership_Adapter::public_visibility( $user_id ) || get_current_user_id() === $user_id;
		if ( get_query_var( 'spd_timeline' ) ) {
			self::render_page( __( 'Profile Timeline', 'sabri-profiles-doctors' ), SPD_Timeline::render( $user_id ), $is_private, 200 );
		}
		self::render_page( $user->display_name, do_shortcode( '[sabri_member_profile]' ), $is_private, 200 );
	}

	private static function render_page( $title, $content, $private, $status ) {
		status_header( absint( $status ) );
		if ( $private ) {
			nocache_headers();
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
		get_header();
		echo '<main id="primary" class="site-main spd-route"><div class="spd-route__inner"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>' . wp_kses_post( $content ) . '</div></main>';
		get_footer();
		exit;
	}
}
