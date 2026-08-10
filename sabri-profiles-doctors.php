<?php
/**
 * Plugin Name: Sabri Profiles and Doctors
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical, privacy-controlled Founder, member and doctor profile domain for the Sabri Social Homeopathy Platform.
 * Version: 1.2.0-rc2
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-profiles-doctors
 */

defined( 'ABSPATH' ) || exit;

define( 'SPD_VERSION', '1.2.0-rc2' );
define( 'SPD_DB_VERSION', '1.2.0' );
define( 'SPD_CONTRACT_VERSION', '1.4.0' );
define( 'SPD_PLAN_VERSION', 'SSH-F03-PLAN-2026-v1.0+2026-08-07-central-addendum+FUTURE-SUPERSET-18+80-ROUND-CORRECTIVE-REVIEW' );
define( 'SPD_FILE', __FILE__ );
define( 'SPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPD_URL', plugin_dir_url( __FILE__ ) );

$spd_trait_files = array(
	'trait-spd-profile-identity-create.php',
	'trait-spd-profile-identity-read.php',
	'trait-spd-profile-public-dto.php',
	'trait-spd-profile-edit-model.php',
	'trait-spd-profile-update.php',
	'trait-spd-profile-professional.php',
	'trait-spd-profile-media.php',
	'trait-spd-profile-moderation.php',
	'trait-spd-profile-lifecycle.php',
	'trait-spd-profile-events.php',
	'trait-spd-profile-cache.php',
	'trait-spd-profile-central.php',
	'trait-spd-frontend-profile.php',
	'trait-spd-frontend-timeline.php',
	'trait-spd-frontend-edit.php',
	'trait-spd-frontend-report.php',
	'trait-spd-frontend-helpers.php',
	'trait-spd-frontend-central.php',
	'trait-spd-frontend-future.php',
);
foreach ( $spd_trait_files as $spd_trait_file ) { require_once SPD_DIR . 'includes/' . $spd_trait_file; }
unset( $spd_trait_files, $spd_trait_file );

$spd_files = array(
	'class-spd-db.php',
	'class-spd-membership-adapter.php',
	'class-spd-verification-adapter.php',
	'class-spd-authorization.php',
	'class-spd-helpers.php',
	'class-spd-central-profile.php',
	'class-spd-future-profile.php',
	'class-spd-future-privacy.php',
	'class-spd-contracts.php',
	'class-spd-profile-repository.php',
	'class-spd-media.php',
	'class-spd-timeline.php',
	'class-spd-routes.php',
	'class-spd-rest.php',
	'class-spd-central-rest.php',
	'class-spd-future-rest.php',
	'class-spd-frontend.php',
	'class-spd-privacy.php',
	'class-spd-observability.php',
	'class-spd-admin.php',
	'class-spd-activator.php',
	'class-spd-plugin.php',
);
foreach ( $spd_files as $spd_file ) { require_once SPD_DIR . 'includes/' . $spd_file; }
unset( $spd_files, $spd_file );

register_activation_hook( SPD_FILE, array( 'SPD_Activator', 'activate' ) );
register_deactivation_hook( SPD_FILE, array( 'SPD_Activator', 'deactivate' ) );

/** Public, versioned query contract for companion modules. */
function spd_get_public_profile( $identity, $viewer_id = 0 ) { return SPD_Profile_Repository::instance()->public_dto( $identity, absint( $viewer_id ) ); }
/** Public, versioned personal-site projection, including the future superset. */
function spd_get_personal_site_profile( $identity, $viewer_id = 0 ) {
	$viewer_id = absint( $viewer_id );
	$dto = SPD_Central_Profile::personal_site_dto( $identity, $viewer_id );
	if ( is_wp_error( $dto ) ) { return $dto; }
	$profile = SPD_Profile_Repository::instance()->find_by_public_id( $dto['public_id'] );
	if ( ! $profile ) { return $dto; }
	$dto = SPD_Future_Profile::augment_personal_site_dto( $dto, $profile, $viewer_id );
	if ( isset( $dto['future']['federation'] ) && is_array( $dto['future']['federation'] ) ) {
		$dto['future']['federation']['transport_active'] = ! empty( $dto['future']['federation']['inbox'] ) && ! empty( $dto['future']['federation']['outbox'] );
	}
	return $dto;
}
/** File 26 current, public-safe search projection. */
function spd_get_search_projection( $identity ) { return SPD_Central_Profile::search_projection( $identity ); }
/** Future professional identity superset projection. */
function spd_get_future_profile_projection( $identity, $viewer_id = 0 ) { $dto = spd_get_personal_site_profile( $identity, absint( $viewer_id ) ); return is_wp_error( $dto ) ? $dto : (array) ( $dto['future'] ?? array() ); }
/** Public-safe FHIR Practitioner/PractitionerRole projection. */
function spd_get_fhir_professional_projection( $identity ) { $dto = spd_get_personal_site_profile( $identity, 0 ); return is_wp_error( $dto ) ? $dto : (array) ( $dto['future']['fhir'] ?? array() ); }
/** Federation-ready public actor projection; transport remains external. */
function spd_get_federation_profile_projection( $identity ) { $dto = spd_get_personal_site_profile( $identity, 0 ); return is_wp_error( $dto ) ? $dto : (array) ( $dto['future']['federation'] ?? array() ); }
/** Public, versioned timeline query contract. */
function spd_get_profile_timeline( $identity, array $args = array(), $viewer_id = 0 ) { return SPD_Timeline::query( $identity, $args, absint( $viewer_id ) ); }
/** Machine-readable profile-domain contract manifest. */
function spd_get_profile_contract_manifest() { return SPD_Contracts::manifest(); }
/** Delegated authority claim for File 08. This is authorization context, never appointment truth. */
function spd_delegate_can_manage_profile_scope( $owner_user_id, $delegate_user_id, $scope ) { return SPD_Profile_Repository::instance()->delegate_can_manage( absint( $owner_user_id ), absint( $delegate_user_id ), sanitize_key( $scope ) ); }

function spd_start_plugin() { ( new SPD_Plugin() )->run(); }
add_action( 'plugins_loaded', 'spd_start_plugin', 30 );
