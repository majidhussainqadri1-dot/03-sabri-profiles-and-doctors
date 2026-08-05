<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Contracts {
	public static function manifest() {
		return array(
			'owner'            => 'File 03 — Profiles and Doctors',
			'contract_version' => SPD_CONTRACT_VERSION,
			'plugin_version'   => SPD_VERSION,
			'schema_version'   => SPD_DB_VERSION,
			'canonical_owner'  => array( 'public_profile', 'profile_fields', 'profile_visibility', 'profile_media', 'profile_slug_history', 'profile_reports' ),
			'queries'          => array(
				'get_public_profile'  => 'spd_get_public_profile',
				'get_profile_timeline'=> 'spd_get_profile_timeline',
			),
			'events'           => array(
				'PublicProfileUpdated.v1',
				'ProfileVisibilityChanged.v1',
				'ProfileMediaChanged.v1',
				'ProfileReported.v1',
				'ProfileModerated.v1',
				'ProfileReportReviewed.v1',
				'ProfileTombstoned.v1',
			),
			'routes'           => array(
				'/founder/',
				'/profile/{public_id}/',
				'/profile/{public_id}/timeline/',
				'/profile/{public_id}/report/',
				'/account/profile/',
			),
			'dependencies'     => array(
				'file00' => array( 'required' => true, 'minimum' => SPD_Membership_Adapter::MIN_VERSION ),
				'file09' => array( 'required_for' => 'verified doctor badge' ),
				'file21' => array( 'required_for' => 'publication timeline provider' ),
				'file20' => array( 'required_for' => 'integrated shell placement' ),
				'file25' => array( 'required_for' => 'final visual component contract' ),
			),
			'privacy'          => array(
				'public_private_dto_separation' => true,
				'contact_default'               => 'private',
				'minor_default'                 => 'private',
				'public_id'                     => 'opaque_uuid_v4',
			),
		);
	}

	public static function register() {
		$manifest = self::manifest();
		do_action( 'sabri_register_module_contract', 'file03', SPD_CONTRACT_VERSION, $manifest );
		do_action( 'sabri_file01_register_contract', 'file03', SPD_CONTRACT_VERSION, $manifest );
		do_action( 'sabri_file20_register_route_provider', 'file03', self::route_manifest() );
		do_action( 'sabri_file24_register_assurance_manifest', 'file03', self::assurance_manifest() );
		do_action( 'sabri_file25_register_component_provider', 'file03', self::component_manifest() );
		do_action( 'sabri_file07_register_profile_provider', 'file03', array( __CLASS__, 'public_provider' ) );
		do_action( 'sabri_file26_register_profile_provider', 'file03', array( __CLASS__, 'public_provider' ) );
	}

	public static function route_manifest() {
		return array(
			'owner'   => 'file03',
			'version' => SPD_CONTRACT_VERSION,
			'routes'  => array(
				'founder'          => array( 'path' => '/founder/', 'layout' => 'public-profile', 'cache' => 'public' ),
				'profile'          => array( 'path' => '/profile/{public_id}/', 'layout' => 'public-profile', 'cache' => 'audience-aware' ),
				'profile_timeline' => array( 'path' => '/profile/{public_id}/timeline/', 'layout' => 'public-profile', 'cache' => 'audience-aware' ),
				'profile_report'   => array( 'path' => '/profile/{public_id}/report/', 'layout' => 'focused-form', 'cache' => 'no-store' ),
				'account_profile'  => array( 'path' => '/account/profile/', 'layout' => 'private-application', 'cache' => 'no-store' ),
			),
		);
	}

	public static function assurance_manifest() {
		return array(
			'owner' => 'file03',
			'version' => SPD_CONTRACT_VERSION,
			'controls' => array(
				'object_field_authorization',
				'audience_aware_dto_and_cache',
				'minor_guardian_privacy',
				'upload_validation_metadata_removal_scan_hook',
				'idempotency_optimistic_concurrency',
				'append_only_outbox_audit',
				'privacy_export_erasure_tombstone',
			),
			'evidence_route' => admin_url( 'admin.php?page=sabri-profiles-system-check' ),
		);
	}

	public static function component_manifest() {
		return array(
			'owner'      => 'file03',
			'version'    => SPD_CONTRACT_VERSION,
			'components' => array( 'profile_hero', 'profile_field_list', 'profile_contact_actions', 'profile_timeline_slot', 'profile_report_form' ),
			'tokens'     => array( '--sabri-primary', '--sabri-surface', '--sabri-text', '--sabri-muted', '--sabri-border', '--sabri-radius' ),
			'rtl'        => true,
			'wcag_target'=> '2.2-AA',
		);
	}

	public static function public_provider( $identity, $viewer_id = 0 ) {
		return spd_get_public_profile( $identity, $viewer_id );
	}
}
