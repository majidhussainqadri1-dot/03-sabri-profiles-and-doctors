<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Contracts {
	public static function manifest() {
		return array(
			'owner'            => 'File 03 — Profiles and Doctors',
			'owner_key'        => 'file03',
			'contract_version' => SPD_CONTRACT_VERSION,
			'plugin_version'   => SPD_VERSION,
			'schema_version'   => SPD_DB_VERSION,
			'canonical_owner'  => array(
				'public_profile', 'profile_fields', 'profile_visibility', 'profile_media',
				'profile_slug_history', 'profile_reports', 'profile_outbox',
				'profile_media_deletion_ledger', 'professional_profile_proposals',
			),
			'commands'         => array(
				'update_profile', 'set_contact_visibility', 'upload_profile_media',
				'submit_professional_fields', 'moderate_profile', 'create_profile_report',
				'erase_profile', 'requeue_owned_failure',
			),
			'queries'          => array(
				'get_public_profile'  => 'spd_get_public_profile',
				'get_profile_timeline' => 'spd_get_profile_timeline',
				'get_profile_edit_model'=> 'repository edit_model',
			),
			'events'           => array(
				'PublicProfileUpdated.v1', 'ProfileVisibilityChanged.v1', 'ProfileMediaChanged.v1',
				'ProfileProfessionalDraftSaved.v1', 'ProfileProfessionalFieldsSubmitted.v1',
				'ProfileReported.v1', 'ProfileModerated.v1', 'ProfileReportReviewed.v1',
				'ProfileTombstoned.v1', 'ProfileReporterErased.v1', 'ProfileProfessionalSubmissionsErased.v1',
			),
			'external_facts_revalidated' => array( 'membership_and_public_eligibility_on_each_access', 'verification_on_each_doctor_projection', 'clinic_and_contact_on_each_dto', 'timeline_on_each_query', 'media_privacy_by_resumable_reconciler' ),
			'routes'           => array( '/founder/', '/profile/{public_id}/', '/profile/{public_id}/timeline/', '/profile/{public_id}/report/', '/account/profile/' ),
			'dependencies'     => array(
				'file00' => array(
					'required'          => true,
					'minimum_plugin'    => SPD_Membership_Adapter::MIN_VERSION,
					'minimum_contract'  => SPD_Membership_Adapter::MIN_CONTRACT_VERSION,
					'functions'         => array( 'smc_membership_assertions', 'smc_user_status', 'smc_founder_user_id', 'smc_is_founder' ),
					'optional_extensions'=> array( 'smc_profile_age_guardian_claim_v1', 'smc_guardian_relationship_claim_v1', 'smc_profile_contact_projection_v1' ),
					'failure'           => 'protected actions fail closed; uncertain age remains minor-safe',
				),
				'file09' => array( 'required_for' => 'verified doctor badge and approved professional projection', 'minimum_contract' => SPD_Verification_Adapter::MIN_CONTRACT_VERSION, 'failure' => 'badge and approved professional projection hidden; private proposals remain pending' ),
				'file21' => array( 'required_for' => 'publication timeline provider', 'minimum_contract' => SPD_Timeline::PROVIDER_CONTRACT_MIN, 'failure' => 'provider unavailable state' ),
				'file20' => array( 'required_for' => 'integrated shell placement', 'failure' => 'semantic shortcode routes remain' ),
				'file25' => array( 'required_for' => 'final visual component contract', 'failure' => 'accessible native components remain' ),
				'file17' => array( 'required_for' => 'contacts/internal-message actions', 'failure' => 'contact action hidden' ),
				'file08' => array( 'required_for' => 'clinic projection', 'failure' => 'clinic projection hidden' ),
				'media_scanner' => array( 'required_for' => 'new public avatar/cover ingestion', 'minimum_contract' => SPD_Media::SCAN_CONTRACT_MIN, 'failure' => 'upload rejected with service-unavailable state' ),
			),
			'privacy'          => array(
				'public_private_dto_separation' => true,
				'contact_default'               => 'private',
				'minor_default'                 => 'private',
				'moderator_private_field_access'=> false,
				'public_id'                     => 'opaque_uuid_v4',
				'cache'                         => 'revocation-safe no-store until all owner modules provide accepted versioned invalidation',
				'professional_proposals'        => 'restricted and never public without File 09 approval',
			),
			'reliability'      => array(
				'outbox'          => 'atomic lease + bounded retry + dead-letter + explicit audited requeue',
				'migration'       => 'bounded cursor; retry blocks current record; exhausted failures quarantined and remain release blockers',
				'media_erasure'   => 'tombstone transaction + retryable deletion ledger + explicit audited requeue',
				'idempotency'     => 'required and transactionally finalized for public mutations',
				'reconciliation'  => 'cache generation + explicit File 26 reconciliation flag',
			),
		);
	}

	public static function register() {
		do_action( 'sabri_register_module_contract', 'file03', SPD_CONTRACT_VERSION, self::manifest() );
		do_action( 'sabri_file01_register_contract', 'file03', SPD_CONTRACT_VERSION, self::manifest() );
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
				'founder'          => array( 'path' => '/founder/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
				'profile'          => array( 'path' => '/profile/{public_id}/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
				'profile_timeline' => array( 'path' => '/profile/{public_id}/timeline/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
				'profile_report'   => array( 'path' => '/profile/{public_id}/report/', 'layout' => 'focused-form', 'cache' => 'no-store' ),
				'account_profile'  => array( 'path' => '/account/profile/', 'layout' => 'private-application', 'cache' => 'no-store' ),
			),
		);
	}

	public static function assurance_manifest() {
		return array(
			'owner'   => 'file03',
			'version' => SPD_CONTRACT_VERSION,
			'controls'=> array(
				'current_versioned_file00_assertions', 'trusted_file09_badge_projection',
				'object_field_authorization', 'audience_aware_dto', 'minor_guardian_privacy',
				'fail_closed_media_scan_and_metadata_reencoding', 'required_idempotency_and_optimistic_concurrency',
				'atomic_outbox_leases', 'privacy_export_erasure_tombstone',
				'retryable_media_deletion', 'migration_failure_quarantine',
				'private_professional_proposals', 'operator_recovery_audit',
			),
			'evidence_route' => admin_url( 'admin.php?page=sabri-profiles-system-check' ),
		);
	}

	public static function component_manifest() {
		return array(
			'owner'      => 'file03',
			'version'    => SPD_CONTRACT_VERSION,
			'components' => array( 'profile_hero', 'profile_field_list', 'profile_contact_actions', 'profile_timeline_slot', 'profile_report_form', 'professional_claim_form' ),
			'icon_keys'  => array( 'profile', 'timeline', 'message', 'phone', 'whatsapp', 'report', 'edit', 'verification', 'clinic' ),
			'tokens'     => array( '--sabri-primary', '--sabri-surface', '--sabri-text', '--sabri-muted', '--sabri-border', '--sabri-radius' ),
			'rtl'        => true,
			'wcag_target'=> '2.2-AA',
		);
	}


	/**
	 * File 00 remains the eligibility authority; File 03 supplies only the
	 * current owner-controlled opt-in fact from its canonical profile record.
	 * It can never turn an ineligible or minor account into a public profile.
	 */
	public static function file00_public_profile_opt_in( $allowed, $user_id, $file00_row = array() ) {
		unset( $file00_row );
		if ( $allowed ) { return true; }
		$user_id = absint( $user_id );
		if ( ! $user_id || ! SPD_DB::tables_exist() ) { return false; }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user_id, false );
		if ( ! $profile || 'active' !== ( $profile['state'] ?? '' ) || 'public' !== ( $profile['profile_visibility'] ?? 'private' ) ) { return false; }
		return 'founder' === ( $profile['profile_type'] ?? '' ) || ( SPD_Membership_Adapter::public_profile_age_eligible( $user_id ) );
	}

	public static function public_provider( $identity, $viewer_id = 0 ) {
		return spd_get_public_profile( $identity, $viewer_id );
	}
}
