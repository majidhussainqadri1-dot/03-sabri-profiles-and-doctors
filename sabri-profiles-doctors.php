<?php
/**
 * Plugin Name: Sabri Profiles and Doctors
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical privacy-controlled profiles, Founder identity and owner-sourced profile timeline contracts for the Sabri Social Homeopathy Platform.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-profiles-doctors
 */

defined( 'ABSPATH' ) || exit;

define( 'SPD_VERSION', '0.3.0' );
define( 'SPD_DB_VERSION', '0.3.0' );
define( 'SPD_CONTRACT_VERSION', '1.0.0' );
define( 'SPD_FILE', __FILE__ );
define( 'SPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPD_URL', plugin_dir_url( __FILE__ ) );

require_once SPD_DIR . 'includes/class-spd-membership-adapter.php';
require_once SPD_DIR . 'includes/class-spd-verification-adapter.php';
require_once SPD_DIR . 'includes/class-spd-media.php';
require_once SPD_DIR . 'includes/class-spd-helpers.php';
require_once SPD_DIR . 'includes/class-spd-contracts.php';
require_once SPD_DIR . 'includes/class-spd-public-identity.php';
require_once SPD_DIR . 'includes/class-spd-profile-policy.php';
require_once SPD_DIR . 'includes/class-spd-timeline.php';
require_once SPD_DIR . 'includes/class-spd-system.php';
require_once SPD_DIR . 'includes/class-spd-activator.php';
require_once SPD_DIR . 'includes/class-spd-frontend.php';
require_once SPD_DIR . 'includes/class-spd-admin.php';
require_once SPD_DIR . 'includes/class-spd-privacy.php';
require_once SPD_DIR . 'includes/class-spd-plugin.php';

register_activation_hook( SPD_FILE, array( 'SPD_Activator', 'activate' ) );
register_deactivation_hook( SPD_FILE, array( 'SPD_Activator', 'deactivate' ) );

function spd_start_plugin() {
	if ( ! SPD_Membership_Adapter::available() ) {
		add_action( 'admin_notices', array( 'SPD_Plugin', 'dependency_notice' ) );
		return;
	}
	SPD_Contracts::register();
	SPD_Public_Identity::register_routes();
	SPD_System::register();
	( new SPD_Plugin() )->run();
}
add_action( 'plugins_loaded', 'spd_start_plugin', 30 );
