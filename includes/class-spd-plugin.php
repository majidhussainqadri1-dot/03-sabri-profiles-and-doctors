<?php
defined( 'ABSPATH' ) || exit;

class SPD_Plugin {
	public function run() {
		$front = new SPD_Frontend();
		$front->hooks();
		( new SPD_Admin() )->hooks();
		( new SPD_Privacy() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	public function assets() {
		wp_enqueue_style( 'spd-profiles', SPD_URL . 'assets/css/profiles.css', array(), SPD_VERSION );
		wp_enqueue_script( 'spd-profiles', SPD_URL . 'assets/js/profiles.js', array(), SPD_VERSION, true );
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'sabri-' ) ) {
			wp_enqueue_style( 'spd-admin', SPD_URL . 'assets/css/admin.css', array(), SPD_VERSION );
		}
	}
}

