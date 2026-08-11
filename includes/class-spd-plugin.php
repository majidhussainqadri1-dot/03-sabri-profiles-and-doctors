<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Plugin {
	private $observability;

	public function run() {
		load_plugin_textdomain( 'sabri-profiles-doctors', false, dirname( plugin_basename( SPD_FILE ) ) . '/languages' );
		if ( ! class_exists( 'SPD_Schema_Guard' ) ) { require_once SPD_DIR . 'includes/class-spd-schema-guard.php'; }
		if ( ! class_exists( 'SPD_Central_Privacy' ) ) { require_once SPD_DIR . 'includes/class-spd-central-privacy.php'; }
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_db_version' ) !== SPD_DB_VERSION || ! SPD_Schema_Guard::base_ready() ) ) {
			$schema = SPD_DB::install();
			if ( is_wp_error( $schema ) || ! SPD_Schema_Guard::base_ready() ) {
				delete_option( 'spd_db_version' );
				$safe = SPD_Observability::set_safe_mode( true, 'schema_install_failed' );
				if ( is_wp_error( $safe ) ) { do_action( 'spd_boot_failure', is_wp_error( $schema ) ? $schema : new WP_Error( 'spd_schema_shape_invalid', __( 'The File 03 database schema is incomplete.', 'sabri-profiles-doctors' ) ), $safe ); }
			}
		}
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_central_schema_version' ) !== SPD_Central_Profile::SCHEMA_VERSION || ! SPD_Schema_Guard::central_ready() ) ) {
			$central = SPD_Central_Profile::install_schema();
			if ( is_wp_error( $central ) || ! SPD_Schema_Guard::central_ready() ) {
				delete_option( 'spd_central_schema_version' );
				SPD_Observability::set_safe_mode( true, 'central_schema_install_failed' );
			}
		}
		if ( SPD_Membership_Adapter::available() && ( get_option( 'spd_future_schema_version' ) !== SPD_Future_Profile::SCHEMA_VERSION || ! SPD_Schema_Guard::future_ready() ) ) {
			$future = SPD_Future_Profile::install_schema();
			if ( is_wp_error( $future ) || ! SPD_Schema_Guard::future_ready() ) {
				delete_option( 'spd_future_schema_version' );
				SPD_Observability::set_safe_mode( true, 'future_schema_install_failed' );
			}
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
		( new SPD_Central_Privacy() )->hooks();
		( new SPD_Future_Privacy() )->hooks();
		( new SPD_Slug_Privacy() )->hooks();
		$this->observability = new SPD_Observability();
		$this->observability->hooks();
		remove_action( 'spd_migrate_profiles_batch', array( $this->observability, 'migrate_profiles_batch' ) );
		add_action( 'spd_migrate_profiles_batch', array( $this, 'run_migration_batch' ), 10 );
		remove_action( 'spd_retention_cleanup', array( $this->observability, 'retention_cleanup' ) );
		add_action( 'spd_retention_cleanup', array( $this, 'run_retention_cleanup' ), 10 );
		( new SPD_Admin() )->hooks();
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'init', array( 'SPD_Contracts', 'register' ), 50 );
		add_filter( 'smc_public_profile_opt_in', array( 'SPD_Contracts', 'file00_public_profile_opt_in' ), 10, 3 );
		add_filter( 'sabri_shell_navigation_destinations', array( $this, 'shell_navigation' ) );
		add_filter( 'sabri_shell_route_contexts', array( $this, 'shell_contexts' ) );
		add_filter( 'sabri_file26_profile_search_projection_v1', array( $this, 'file26_search_projection' ), 10, 2 );
		add_filter( 'sabri_file08_profile_delegation_claim_v1', array( $this, 'file08_delegation_claim' ), 10, 4 );
		add_filter( 'rest_post_dispatch', array( $this, 'rest_no_store' ), 100, 3 );
	}

	public function private_headers() { ( new SPD_Routes() )->private_headers(); }

	public function rest_no_store( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request || 0 !== strpos( (string) $request->get_route(), '/sabri-profiles/v1/' ) ) { return $response; }
		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
		}
		return $response;
	}

	public function run_migration_batch() {
		$token = SPD_Helpers::acquire_lock( 'migration_batch', 10 * MINUTE_IN_SECONDS );
		if ( ! $token || ! $this->observability ) { return; }
		try {
			delete_transient( 'spd_migration_lock' );
			$this->observability->migrate_profiles_batch();
		} finally {
			delete_transient( 'spd_migration_lock' );
			SPD_Helpers::release_lock( 'migration_batch', $token );
		}
	}

	public function run_retention_cleanup() {
		global $wpdb;
		if ( ! SPD_Schema_Guard::base_ready() ) { return; }
		$events = SPD_DB::table( 'events' );
		$idempotency = SPD_DB::table( 'idempotency' );
		$reports = SPD_DB::table( 'reports' );
		$queries = array(
			"DELETE FROM {$idempotency} WHERE expires_at<UTC_TIMESTAMP()",
			"UPDATE {$reports} SET reporter_user_id=0,details='',decision_note='',dedupe_hash=SHA2(CONCAT(report_uuid,':retained'),256) WHERE reporter_user_id<>0 AND status IN ('closed','rejected') AND updated_at<(UTC_TIMESTAMP()-INTERVAL 365 DAY)",
			"DELETE FROM {$events} WHERE status='delivered' AND created_at<(UTC_TIMESTAMP()-INTERVAL 730 DAY) AND event_name NOT IN ('ProfileTombstoned.v1','ProfileReported.v1','ProfileReportReviewed.v1')",
		);
		foreach ( $queries as $index => $sql ) {
			if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$code = 'retention_query_' . absint( $index + 1 ) . '_failed';
				update_option( 'spd_last_retention_error', array( 'code' => $code, 'at' => SPD_Helpers::now() ), false );
				do_action( 'sabri_file24_retention_failure', array( 'owner' => 'file03', 'code' => $code, 'at' => SPD_Helpers::now() ) );
				return;
			}
		}
		delete_option( 'spd_last_retention_error' );
		update_option( 'spd_last_retention_run', SPD_Helpers::now(), false );
	}

	public function assets() {
		$map = (array) get_option( 'spd_page_map', array() );
		$is_profile_route = (bool) get_query_var( 'spd_public_id' );
		$mapped_ids = array_filter( array_map( 'absint', $map ) );
		$is_managed_page = $mapped_ids ? is_page( $mapped_ids ) : false;
		$is_fallback_page = is_page( array( 'founder', 'profile', 'account-profile', 'account-profile-personal-site', 'account-profile-preview' ) );
		if ( ! $is_profile_route && ! $is_managed_page && ! $is_fallback_page ) { return; }
		wp_enqueue_style( 'spd-profiles', SPD_URL . 'assets/css/profiles.css', array(), SPD_VERSION );
		wp_enqueue_script( 'spd-profiles', SPD_URL . 'assets/js/profiles.js', array(), SPD_VERSION, true );
		wp_enqueue_script( 'spd-future-profiles', SPD_URL . 'assets/js/future-profiles.js', array( 'spd-profiles' ), SPD_VERSION, true );
		$user_id = get_current_user_id();
		$can_govern_legacy = $user_id && ( SPD_Membership_Adapter::can_manage_founder( $user_id ) || SPD_Membership_Adapter::can_operate_profiles( $user_id ) );
		wp_localize_script( 'spd-profiles', 'SPDProfileUI', array( 'restUrl' => esc_url_raw( rest_url( 'sabri-profiles/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'rtl' => is_rtl(), 'canGovernLegacy' => (bool) $can_govern_legacy, 'shareText' => __( 'View this verified profile on Sabri Social Homeopathy Platform', 'sabri-profiles-doctors' ), 'copiedText' => __( 'Link copied', 'sabri-profiles-doctors' ) ) );
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
		return spd_get_search_projection( $identity );
	}

	public function file08_delegation_claim( $claim, $owner_user_id, $delegate_user_id, $scope ) {
		if ( null !== $claim ) { return $claim; }
		$allowed = SPD_Profile_Repository::instance()->delegate_can_manage( $owner_user_id, $delegate_user_id, $scope );
		return array( 'contract_version' => SPD_CONTRACT_VERSION, 'owner_user_id' => absint( $owner_user_id ), 'delegate_user_id' => absint( $delegate_user_id ), 'scope' => sanitize_key( $scope ), 'allowed' => (bool) $allowed, 'generated_at' => gmdate( 'c' ), 'valid_until' => gmdate( 'c', time() + 300 ) );
	}
}