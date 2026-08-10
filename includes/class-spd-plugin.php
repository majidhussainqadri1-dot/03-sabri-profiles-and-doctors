<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Plugin {
	private $migration_lock_token = '';

	public function run() {
		load_plugin_textdomain( 'sabri-profiles-doctors', false, dirname( plugin_basename( SPD_FILE ) ) . '/languages' );
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_db_version' ) !== SPD_DB_VERSION || ! SPD_DB::tables_exist() ) ) {
			$schema = SPD_DB::install();
			if ( is_wp_error( $schema ) ) { $safe = SPD_Observability::set_safe_mode( true, 'schema_install_failed' ); if ( is_wp_error( $safe ) ) { do_action( 'spd_boot_failure', $schema, $safe ); } }
		}
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_central_schema_version' ) !== SPD_Central_Profile::SCHEMA_VERSION || ! SPD_Central_Profile::schema_ready() ) ) {
			$central = SPD_Central_Profile::install_schema();
			if ( is_wp_error( $central ) ) { SPD_Observability::set_safe_mode( true, 'central_schema_install_failed' ); }
		}
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_future_schema_version' ) !== SPD_Future_Profile::SCHEMA_VERSION || ! SPD_Future_Profile::schema_ready() ) ) {
			$future = SPD_Future_Profile::install_schema();
			if ( is_wp_error( $future ) ) { SPD_Observability::set_safe_mode( true, 'future_schema_install_failed' ); }
		}
		if ( SPD_Membership_Adapter::available() && get_option( 'spd_version' ) !== SPD_VERSION ) {
			$upgrade = SPD_Activator::repair_owned_resources();
			if ( is_wp_error( $upgrade ) ) { SPD_Observability::set_safe_mode( true, 'latest_plan_upgrade_failed' ); }
			else { update_option( 'spd_version', SPD_VERSION, false ); update_option( 'spd_contract_version', SPD_CONTRACT_VERSION, false ); update_option( 'spd_plan_version', SPD_PLAN_VERSION, false ); }
		}
		( new SPD_Routes() )->hooks();
		( new SPD_REST() )->hooks();
		( new SPD_Central_REST() )->hooks();
		( new SPD_Future_REST() )->hooks();
		( new SPD_Frontend() )->hooks();
		( new SPD_Privacy() )->hooks();
		( new SPD_Future_Privacy() )->hooks();
		( new SPD_Observability() )->hooks();
		( new SPD_Admin() )->hooks();
		add_action( 'admin_post_spd_save_profile', array( $this, 'guard_profile_media_upload_rate' ), 1 );
		add_action( 'spd_migrate_profiles_batch', array( $this, 'before_migration_batch' ), 0 );
		add_action( 'spd_migrate_profiles_batch', array( $this, 'after_migration_batch' ), 99 );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'init', array( 'SPD_Contracts', 'register' ), 50 );
		add_filter( 'smc_public_profile_opt_in', array( 'SPD_Contracts', 'file00_public_profile_opt_in' ), 10, 3 );
		add_filter( 'sabri_shell_navigation_destinations', array( $this, 'shell_navigation' ) );
		add_filter( 'sabri_shell_route_contexts', array( $this, 'shell_contexts' ) );
		add_filter( 'sabri_file26_profile_search_projection_v1', array( $this, 'file26_search_projection' ), 10, 2 );
		add_filter( 'sabri_file08_profile_delegation_claim_v1', array( $this, 'file08_delegation_claim' ), 10, 4 );
	}

	public function private_headers() { ( new SPD_Routes() )->private_headers(); }

	public function guard_profile_media_upload_rate() {
		if ( ! is_user_logged_in() ) { return; }
		$user_id = get_current_user_id();
		foreach ( array( 'avatar', 'cover' ) as $purpose ) {
			if ( empty( $_FILES[ $purpose ]['name'] ) ) { continue; }
			if ( ! SPD_Helpers::consume_rate_limit( 'media_upload_' . $user_id, 10, HOUR_IN_SECONDS ) ) {
				wp_die( esc_html__( 'Too many profile uploads were attempted. Try again later.', 'sabri-profiles-doctors' ), '', array( 'response' => 429, 'back_link' => true ) );
			}
		}
	}

	/**
	 * Serialize migration requests before the historical transient guard runs.
	 * A contending request sets the compatibility transient so the legacy runner
	 * returns without touching the migration cursor. The owner token remains the
	 * authoritative lock and is released only by this request at priority 99.
	 */
	public function before_migration_batch() {
		$this->migration_lock_token = SPD_Helpers::acquire_lock( 'migration_batch', 10 * MINUTE_IN_SECONDS );
		if ( ! $this->migration_lock_token ) {
			set_transient( 'spd_migration_lock', 1, 10 * MINUTE_IN_SECONDS );
			return;
		}
		delete_transient( 'spd_migration_lock' );
	}

	public function after_migration_batch() {
		if ( $this->migration_lock_token ) {
			SPD_Helpers::release_lock( 'migration_batch', $this->migration_lock_token );
			$this->migration_lock_token = '';
		}
	}

	public function assets() {
		$map = (array) get_option( 'spd_page_map', array() );
		$is_profile_route = (bool) get_query_var( 'spd_public_id' );
		$is_managed_page = is_page( array_filter( array_map( 'absint', $map ) ) );
		if ( ! $is_profile_route && ! $is_managed_page ) { return; }
		wp_enqueue_style( 'spd-profiles', SPD_URL . 'assets/css/profiles.css', array(), SPD_VERSION );
		wp_enqueue_script( 'spd-profiles', SPD_URL . 'assets/js/profiles.js', array(), SPD_VERSION, true );
		wp_enqueue_script( 'spd-future-profiles', SPD_URL . 'assets/js/future-profiles.js', array( 'spd-profiles' ), SPD_VERSION, true );
		wp_localize_script( 'spd-profiles', 'SPDProfileUI', array( 'restUrl' => esc_url_raw( rest_url( 'sabri-profiles/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'rtl' => is_rtl(), 'shareText' => __( 'View this verified profile on Sabri Social Homeopathy Platform', 'sabri-profiles-doctors' ), 'copiedText' => __( 'Link copied', 'sabri-profiles-doctors' ) ) );
	}

	public function admin_assets( $hook ) { if ( false !== strpos( (string) $hook, 'sabri-profiles' ) ) { wp_enqueue_style( 'spd-admin', SPD_URL . 'assets/css/admin.css', array(), SPD_VERSION ); } }

	public function shell_navigation( $destinations ) {
		$map = (array) get_option( 'spd_page_map', array() );
		$destinations['founder'] = array_merge( (array) ( $destinations['founder'] ?? array() ), array( 'label' => __( 'Founder', 'sabri-profiles-doctors' ), 'url' => ! empty( $map['founder'] ) ? get_permalink( $map['founder'] ) : home_url( '/founder/' ), 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION ) );
		if ( is_user_logged_in() ) {
			$destinations['my_profile'] = array( 'label' => __( 'My Profile', 'sabri-profiles-doctors' ), 'url' => home_url( '/account/profile/' ), 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION );
			$destinations['my_personal_site'] = array( 'label' => __( 'My Personal Website', 'sabri-profiles-doctors' ), 'url' => home_url( '/account/profile/personal-site/' ), 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION );
		}
		return $destinations;
	}

	public function shell_contexts( $contexts ) {
		$contexts['file03_public_profile'] = array( 'pattern' => '^/profile/', 'layout' => 'public-profile', 'owner' => 'file03' );
		$contexts['file03_account_profile'] = array( 'pattern' => '^/account/profile/', 'layout' => 'private-application', 'owner' => 'file03' );
		return $contexts;
	}

	public function file26_search_projection( $current, $identity ) {
		if ( null !== $current && ! is_wp_error( $current ) ) { return $current; }
		$out = SPD_Central_Profile::search_projection( $identity );
		if ( is_wp_error( $out ) ) { return $out; }
		$profile = SPD_Profile_Repository::instance()->find_by_public_id( (string) $out['canonical_id'] );
		if ( $profile ) { $out['professional_lifecycle'] = SPD_Future_Profile::lifecycle( $profile )['status']; }
		return $out;
	}

	public function file08_delegation_claim( $claim, $owner_user_id, $delegate_user_id, $scope ) {
		if ( null !== $claim ) { return $claim; }
		$allowed = SPD_Profile_Repository::instance()->delegate_can_manage( $owner_user_id, $delegate_user_id, $scope );
		return array( 'contract_version' => SPD_CONTRACT_VERSION, 'owner_user_id' => absint( $owner_user_id ), 'delegate_user_id' => absint( $delegate_user_id ), 'scope' => sanitize_key( $scope ), 'allowed' => (bool) $allowed, 'generated_at' => gmdate( 'c' ), 'valid_until' => gmdate( 'c', time() + 300 ) );
	}
}
