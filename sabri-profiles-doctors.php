<?php
/**
 * Plugin Name: Sabri Profiles and Doctors
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical, privacy-controlled Founder, member and doctor profile domain for the Sabri Social Homeopathy Platform.
 * Version: 1.2.0-rc10
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-profiles-doctors
 */

defined( 'ABSPATH' ) || exit;

define( 'SPD_VERSION', '1.2.0-rc10' );
define( 'SPD_DB_VERSION', '1.2.0' );
define( 'SPD_CONTRACT_VERSION', '1.4.0' );
define( 'SPD_PLAN_VERSION', 'SSH-F03-PLAN-2026-v1.0+2026-08-07-central-addendum+FUTURE-SUPERSET-18+80-ROUND-CORRECTIVE-REVIEW+THIRD-TEN-ROUND-CORRECTIVE-REVIEW+FOURTH-TEN-ROUND-CORRECTIVE-REVIEW+FIFTH-TEN-ROUND-CORRECTIVE-REVIEW+SIXTH-TEN-ROUND-CORRECTIVE-REVIEW+SEVENTH-TEN-ROUND-CORRECTIVE-REVIEW+EIGHTH-TEN-ROUND-CORRECTIVE-REVIEW+NINTH-TEN-ROUND-CORRECTIVE-REVIEW+TENTH-TEN-ROUND-CORRECTIVE-REVIEW' );
// Historical QA provenance only; never use this comment as runtime identity:
// Version: 1.2.0-rc9 ; define( 'SPD_VERSION', '1.2.0-rc9' );
define( 'SPD_FILE', __FILE__ );
define( 'SPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPD_URL', plugin_dir_url( __FILE__ ) );

$spd_trait_files = array(
	'trait-spd-profile-identity-create.php','trait-spd-profile-identity-read.php','trait-spd-profile-public-dto.php','trait-spd-profile-edit-model.php','trait-spd-profile-update.php','trait-spd-profile-professional.php','trait-spd-profile-media.php','trait-spd-profile-moderation.php','trait-spd-profile-lifecycle.php','trait-spd-profile-events.php','trait-spd-profile-cache.php','trait-spd-profile-central.php','trait-spd-frontend-profile.php','trait-spd-frontend-timeline.php','trait-spd-frontend-edit.php','trait-spd-frontend-report.php','trait-spd-frontend-helpers.php','trait-spd-frontend-central.php','trait-spd-frontend-future.php',
);
foreach ( $spd_trait_files as $spd_trait_file ) { require_once SPD_DIR . 'includes/' . $spd_trait_file; }
unset( $spd_trait_files, $spd_trait_file );

$spd_files = array(
	'class-spd-db.php','class-spd-membership-adapter.php','class-spd-verification-adapter.php','class-spd-authorization.php','class-spd-helpers.php','class-spd-provider-guards.php','class-spd-central-profile.php','class-spd-future-profile.php','class-spd-future-privacy.php','class-spd-contracts.php','class-spd-profile-repository.php','class-spd-media.php','class-spd-timeline.php','class-spd-routes.php','class-spd-rest.php','class-spd-central-rest.php','class-spd-future-rest.php','class-spd-frontend.php','class-spd-privacy.php','class-spd-slug-privacy.php','class-spd-observability.php','class-spd-outbox-dispatcher.php','class-spd-admin.php','class-spd-activator.php','class-spd-plugin.php',
);
foreach ( $spd_files as $spd_file ) { require_once SPD_DIR . 'includes/' . $spd_file; }
unset( $spd_files, $spd_file );

register_activation_hook( SPD_FILE, array( 'SPD_Activator', 'activate' ) );
register_deactivation_hook( SPD_FILE, array( 'SPD_Activator', 'deactivate' ) );

/** Current File-03-owned future-state read with an explicit DB failure result. */
function spd_read_future_profile_state( $profile_id ) {
	global $wpdb;
	$profile_id = absint( $profile_id );
	if ( ! $profile_id || ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::future_ready() ) { return new WP_Error( 'spd_future_state_unavailable', __( 'Professional lifecycle state is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
	$wpdb->last_error = '';
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT federation_opt_in,professional_lifecycle,lifecycle_reason,lifecycle_changed_at,version,updated_at FROM ' . SPD_Future_Profile::state_table() . ' WHERE profile_id=%d LIMIT 1', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $wpdb->last_error ) { return new WP_Error( 'spd_future_state_read_failed', __( 'Professional lifecycle state is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
	return $row ?: array( 'federation_opt_in' => 0, 'professional_lifecycle' => 'active', 'lifecycle_reason' => '', 'lifecycle_changed_at' => '', 'version' => 1, 'updated_at' => '' );
}

/** Public contract manifest; pure query, available even in isolated composition tests. */
function spd_get_profile_contract_manifest() { return SPD_Contracts::manifest(); }
/** Public, versioned query contract for companion modules. */
function spd_get_public_profile( $identity, $viewer_id = 0 ) { return SPD_Profile_Repository::instance()->public_dto( $identity, absint( $viewer_id ) ); }
/** Public, versioned personal-site projection, including the future superset. */
function spd_get_personal_site_profile( $identity, $viewer_id = 0 ) {
	$viewer_id = absint( $viewer_id );
	$dto = SPD_Central_Profile::personal_site_dto( $identity, $viewer_id );
	if ( is_wp_error( $dto ) ) { return $dto; }
	$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( $dto['public_id'] );
	if ( is_wp_error( $profile ) ) { return $profile; }
	if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
	$base_contacts = (array) ( $dto['contacts'] ?? array() );
	$base_clinic = (array) ( $dto['clinic'] ?? array() );
	$dto = SPD_Future_Profile::augment_personal_site_dto( $dto, $profile, $viewer_id );
	$state = spd_read_future_profile_state( $profile['id'] );
	if ( is_wp_error( $state ) ) {
		$dto['future']['lifecycle'] = array( 'status' => 'unknown', 'active_professional' => false, 'reason' => '', 'changed_at' => '' );
		$dto['future']['state_degraded'] = true;
		$dto['future']['contact_relay'] = array();
		$dto['future']['federation'] = array( 'opt_in' => false, 'transport_owner' => 'external', 'transport_active' => false );
		$dto['contacts'] = array();
		if ( isset( $dto['clinic']['appointment_url'] ) ) { unset( $dto['clinic']['appointment_url'] ); }
	} else {
		$status = sanitize_key( (string) ( $state['professional_lifecycle'] ?? '' ) );
		if ( ! in_array( $status, array( 'active','retired','legacy' ), true ) ) {
			$dto['future']['lifecycle'] = array( 'status' => 'unknown', 'active_professional' => false, 'reason' => '', 'changed_at' => '' );
			$dto['future']['state_degraded'] = true;
			$dto['future']['contact_relay'] = array();
			$dto['future']['federation'] = array( 'opt_in' => false, 'transport_owner' => 'external', 'transport_active' => false );
			$dto['contacts'] = array();
			if ( isset( $dto['clinic']['appointment_url'] ) ) { unset( $dto['clinic']['appointment_url'] ); }
		} else {
			$active_professional = 'active' === $status;
			$dto['future']['lifecycle'] = array( 'status' => $status, 'active_professional' => $active_professional, 'reason' => sanitize_text_field( (string) ( $state['lifecycle_reason'] ?? '' ) ), 'changed_at' => sanitize_text_field( (string) ( $state['lifecycle_changed_at'] ?? '' ) ) );
			$dto['future']['state_degraded'] = false;
			if ( ! $active_professional ) {
				$dto['future']['contact_relay'] = array();
				$dto['contacts'] = array();
				if ( isset( $dto['clinic']['appointment_url'] ) ) { unset( $dto['clinic']['appointment_url'] ); }
			} else {
				$dto['contacts'] = $base_contacts;
				$dto['clinic'] = array_merge( $base_clinic, (array) ( $dto['clinic'] ?? array() ) );
			}
			if ( empty( $state['federation_opt_in'] ) ) { $dto['future']['federation'] = array( 'opt_in' => false, 'transport_owner' => 'external', 'transport_active' => false ); }
		}
	}
	return $dto;
}
/** File 26 consumes only the anonymous public projection. */
function spd_get_search_projection( $identity ) { return SPD_Central_Profile::search_projection( $identity ); }
/** Future superset projection contract. */
function spd_get_future_profile_projection( $identity, $viewer_id = 0 ) { $dto = spd_get_personal_site_profile( $identity, absint( $viewer_id ) ); return is_wp_error( $dto ) ? $dto : ( $dto['future'] ?? array() ); }
/** Professional-only FHIR interoperability projection. */
function spd_get_fhir_professional_projection( $identity ) { return SPD_Future_Profile::fhir_projection( $identity ); }
/** Optional, owner-consented federation projection. */
function spd_get_federation_profile_projection( $identity ) { return SPD_Future_Profile::federation_projection( $identity ); }
/** Timeline remains a provider federation rather than a File 03 activity store. */
function spd_get_profile_timeline( $identity, $viewer_id = 0, $filters = array(), $cursor = '' ) { return SPD_Timeline::query( $identity, absint( $viewer_id ), (array) $filters, (string) $cursor ); }
/** Current, versioned profile delegation claim for File 08 staff workflows. */
function spd_get_profile_delegation_claim( $owner_user_id, $delegate_user_id, $scope ) {
	$repo = SPD_Profile_Repository::instance();
	$allowed = $repo->delegate_can_manage( absint( $owner_user_id ), absint( $delegate_user_id ), sanitize_key( $scope ) );
	return array( 'contract_version' => SPD_CONTRACT_VERSION, 'owner_user_id' => absint( $owner_user_id ), 'delegate_user_id' => absint( $delegate_user_id ), 'scope' => sanitize_key( $scope ), 'allowed' => (bool) $allowed, 'generated_at' => gmdate( 'c' ), 'valid_until' => gmdate( 'c', time() + 300 ) );
}

( new SPD_Plugin() )->run();
