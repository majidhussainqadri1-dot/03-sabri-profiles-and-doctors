<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Plugin {
	public function run() {
		if ( get_option( 'spd_db_version' ) !== SPD_DB_VERSION ) { SPD_Activator::activate(); }
		( new SPD_Frontend() )->hooks();
		( new SPD_Admin() )->hooks();
		( new SPD_Privacy() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'spd_legacy_audit_retention', array( 'SPD_Activator', 'retention_cleanup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'updated_user_meta', array( 'SPD_Verification_Adapter', 'maybe_capture_from_meta' ), 10, 3 );
		add_action( 'added_user_meta', array( 'SPD_Verification_Adapter', 'maybe_capture_from_meta' ), 10, 3 );
		add_filter( 'sabri_shell_navigation_destinations', array( $this, 'shell_navigation' ) );
	}

	public function assets() {
		if ( is_singular( 'page' ) ) {
			wp_enqueue_style( 'spd-profiles', SPD_URL . 'assets/css/profiles.css', array(), SPD_VERSION );
			wp_enqueue_script( 'spd-profiles', SPD_URL . 'assets/js/profiles.js', array(), SPD_VERSION, true );
		}
	}
	public function admin_assets($hook){if(false!==strpos((string)$hook,'sabri-')){wp_enqueue_style('spd-admin',SPD_URL.'assets/css/admin.css',array(),SPD_VERSION);}}

	public function shell_navigation($destinations) {
		if(isset($destinations['founder'])){$destinations['founder']['shortcodes']=array_values(array_unique(array_merge((array)$destinations['founder']['shortcodes'],array('sabri_founder_profile'))));}
		if(isset($destinations['doctors'])){$destinations['doctors']['shortcodes']=array_values(array_unique(array_merge((array)$destinations['doctors']['shortcodes'],array('sabri_doctor_directory'))));}
		return $destinations;
	}

	public static function dependency_notice() {
		if(current_user_can('activate_plugins')){echo '<div class="notice notice-error"><p><strong>Sabri Profiles and Doctors:</strong> File 00 — Sabri Membership Core is required. File 03 has failed closed and is not exposing profiles.</p></div>';}
	}
}
