<?php
/**
 * Plugin Name: Sabri Profiles and Doctors
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical, privacy-controlled Founder, member and doctor profile domain for the Sabri Social Homeopathy Platform.
 * Version: 1.0.0-rc1
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-profiles-doctors
 */

defined( 'ABSPATH' ) || exit;

define( 'SPD_VERSION', '1.0.0-rc1' );
define( 'SPD_DB_VERSION', '1.0.0' );
define( 'SPD_CONTRACT_VERSION', '1.0.0' );
define( 'SPD_FILE', __FILE__ );
define( 'SPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPD_URL', plugin_dir_url( __FILE__ ) );

$spd_trait_files = array(
	'trait-spd-profile-identity-create.php',
	'trait-spd-profile-identity-read.php',
	'trait-spd-profile-public-dto.php',
	'trait-spd-profile-edit-model.php',
	'trait-spd-profile-update.php',
	'trait-spd-profile-media.php',
	'trait-spd-profile-moderation.php',
	'trait-spd-profile-lifecycle.php',
	'trait-spd-profile-events.php',
	'trait-spd-profile-cache.php',
	'trait-spd-frontend-profile.php',
	'trait-spd-frontend-timeline.php',
	'trait-spd-frontend-edit.php',
	'trait-spd-frontend-report.php',
	'trait-spd-frontend-helpers.php',
);
foreach ( $spd_trait_files as $spd_trait_file ) {
	require_once SPD_DIR . 'includes/' . $spd_trait_file;
}
unset( $spd_trait_files, $spd_trait_file );

$spd_files = array(
	'class-spd-db.php',
	'class-spd-membership-adapter.php',
	'class-spd-verification-adapter.php',
	'class-spd-contracts.php',
	'class-spd-authorization.php',
	'class-spd-helpers.php',
	'class-spd-profile-repository.php',
	'class-spd-media.php',
	'class-spd-timeline.php',
	'class-spd-routes.php',
	'class-spd-rest.php',
	'class-spd-frontend.php',
	'class-spd-privacy.php',
	'class-spd-observability.php',
	'class-spd-admin.php',
	'class-spd-activator.php',
	'class-spd-plugin.php',
);
foreach ( $spd_files as $spd_file ) {
	require_once SPD_DIR . 'includes/' . $spd_file;
}
unset( $spd_files, $spd_file );

register_activation_hook( SPD_FILE, array( 'SPD_Activator', 'activate' ) );
register_deactivation_hook( SPD_FILE, array( 'SPD_Activator', 'deactivate' ) );

/**
 * Public, versioned query contract for companion modules.
 *
 * @param int|string $identity User ID or canonical public UUID.
 * @param int        $viewer_id Viewer user ID, or 0 for a guest.
 * @return array|WP_Error
 */
function spd_get_public_profile( $identity, $viewer_id = 0 ) {
	return SPD_Profile_Repository::instance()->public_dto( $identity, absint( $viewer_id ) );
}

/**
 * Public, versioned timeline query contract.
 *
 * @param int|string $identity User ID or canonical public UUID.
 * @param array      $args Query arguments.
 * @param int        $viewer_id Viewer ID.
 * @return array|WP_Error
 */
function spd_get_profile_timeline( $identity, array $args = array(), $viewer_id = 0 ) {
	return SPD_Timeline::query( $identity, $args, absint( $viewer_id ) );
}

/**
 * Machine-readable profile-domain contract manifest.
 *
 * @return array
 */
function spd_get_profile_contract_manifest() {
	return SPD_Contracts::manifest();
}

function spd_start_plugin() {
	( new SPD_Plugin() )->run();
}
add_action( 'plugins_loaded', 'spd_start_plugin', 30 );
