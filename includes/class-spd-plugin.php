<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Plugin {
	public function run() {
		load_plugin_textdomain( 'sabri-profiles-doctors', false, dirname( plugin_basename( SPD_FILE ) ) . '/languages' );
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_db_version' ) !== SPD_DB_VERSION || ! SPD_DB::tables_exist() ) ) {
			SPD_DB::install();
		}
		( new SPD_Routes() )->hooks();
		( new SPD_REST() )->hooks();
		( new SPD_Frontend() )->hooks();
		( new SPD_Privacy() )->hooks();
		( new SPD_Observability() )->hooks();
		( new SPD_Admin() )->hooks();
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'added_user_meta', array( 'SPD_Verification_Adapter', 'maybe_refresh_from_meta' ), 10, 3 );
		add_action( 'updated_user_meta', array( 'SPD_Verification_Adapter', 'maybe_refresh_from_meta' ), 10, 3 );
		add_action( 'init', array( 'SPD_Contracts', 'register' ), 50 );
		add_action( 'spd_profile_media_scan_completed_v1', array( 'SPD_Media', 'complete_scan' ), 10, 3 );
		add_filter( 'sabri_shell_navigation_destinations', array( $this, 'shell_navigation' ) );
		add_filter( 'sabri_shell_route_contexts', array( $this, 'shell_contexts' ) );
	}

	public function private_headers() {
		( new SPD_Routes() )->private_headers();
	}

	public function assets() {
		$map = (array) get_option( 'spd_page_map', array() );
		$is_profile_route = (bool) get_query_var( 'spd_public_id' );
		$is_managed_page = is_page( array_filter( array_map( 'absint', $map ) ) );
		if ( ! $is_profile_route && ! $is_managed_page ) {
			return;
		}
		wp_enqueue_style( 'spd-profiles', SPD_URL . 'assets/css/profiles.css', array(), SPD_VERSION );
		wp_enqueue_script( 'spd-profiles', SPD_URL . 'assets/js/profiles.js', array(), SPD_VERSION, true );
		wp_localize_script(
			'spd-profiles',
			'SPDProfileUI',
			array(
				'restUrl' => esc_url_raw( rest_url( 'sabri-profiles/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'rtl'     => is_rtl(),
			)
		);
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( (string) $hook, 'sabri-profiles' ) ) {
			wp_enqueue_style( 'spd-admin', SPD_URL . 'assets/css/admin.css', array(), SPD_VERSION );
		}
	}

	public function shell_navigation( $destinations ) {
		$map = (array) get_option( 'spd_page_map', array() );
		$destinations['founder'] = array_merge(
			(array) ( $destinations['founder'] ?? array() ),
			array( 'label' => __( 'Founder', 'sabri-profiles-doctors' ), 'url' => ! empty( $map['founder'] ) ? get_permalink( $map['founder'] ) : home_url( '/founder/' ), 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION )
		);
		if ( is_user_logged_in() ) {
			$destinations['my_profile'] = array( 'label' => __( 'My Profile', 'sabri-profiles-doctors' ), 'url' => home_url( '/account/profile/' ), 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION );
		}
		return $destinations;
	}

	public function shell_contexts( $contexts ) {
		$contexts['file03_public_profile'] = array( 'pattern' => '^/profile/', 'layout' => 'public-profile', 'owner' => 'file03' );
		$contexts['file03_account_profile'] = array( 'pattern' => '^/account/profile/', 'layout' => 'private-application', 'owner' => 'file03' );
		return $contexts;
	}
}
