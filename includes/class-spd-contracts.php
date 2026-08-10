<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Contracts {
	public static function manifest() {
		return array(
			'owner'            => 'File 03 — Profiles and Doctors',
			'owner_key'        => 'file03',
			'plan_version'     => SPD_PLAN_VERSION,
			'contract_version' => SPD_CONTRACT_VERSION,
			'plugin_version'   => SPD_VERSION,
			'schema_version'   => SPD_DB_VERSION,
			'central_schema_version' => SPD_Central_Profile::SCHEMA_VERSION,
			'governing_requirements' => array( 'CV-014', 'CV-015', 'CV-016', 'CV-017', 'CV-018', 'CV-019', 'CV-020', 'CV-021', 'CV-022', 'CV-023', 'CV-024', 'CV-025', 'CV-239', 'CV-240', 'CV-241', 'CV-242', 'CV-243', 'CV-244', 'CV-245', 'CV-246', 'CV-247', 'CV-248', 'CV-249', 'CV-250', 'CV-251', 'CV-252', 'CV-253', 'CV-254', 'CV-255', 'CV-256', 'CV-257', 'CV-258', 'CV-259', 'CV-260', 'CV-261', 'CV-262', 'CV-263', 'CV-264', 'CV-265', 'CV-266', 'CV-267', 'CV-268', 'CV-269', 'CV-270', 'CV-271', 'CV-272', 'CV-273', 'CV-274', 'CV-275', 'CV-276', 'CV-277', 'CV-278', 'CV-279', 'CV-280', 'CV-281', 'CV-282', 'CV-283', 'CV-284', 'CV-285', 'F03-CEN-01', 'F03-CEN-02' ),
			'canonical_owner'  => array(
				'public_profile','profile_fields','profile_visibility','profile_media','profile_slug_history','profile_reports','profile_report_appeals','profile_outbox','profile_media_deletion_ledger','professional_profile_proposals','profile_presentation_delegation',
			),
			'commands' => array(
				'update_profile','set_contact_visibility','upload_profile_media','submit_professional_fields','moderate_profile','create_profile_report','create_safety_report','request_report_appeal','update_central_profile','rotate_share_link','grant_profile_delegate','revoke_profile_delegate','erase_profile','requeue_owned_failure',
			),
			'queries' => array(
				'get_public_profile' => 'spd_get_public_profile',
				'get_personal_site_profile' => 'spd_get_personal_site_profile',
				'get_profile_timeline' => 'spd_get_profile_timeline',
				'get_search_projection' => 'spd_get_search_projection',
				'get_profile_edit_model' => 'repository edit_model',
				'get_central_edit_model' => 'repository central_edit_model',
			),
			'events' => array(
				'PublicProfileUpdated.v1','ProfileVisibilityChanged.v1','ProfileMediaChanged.v1','ProfileProfessionalDraftSaved.v1','ProfileProfessionalFieldsSubmitted.v1','ProfileReported.v1','ProfileModerated.v1','ProfileReportReviewed.v1','ProfileReportAppealed.v1','ProfileShareLinkRotated.v1','ProfileDelegationChanged.v1','ProfileTombstoned.v1','ProfileReporterErased.v1','ProfileProfessionalSubmissionsErased.v1',
			),
			'external_facts_revalidated' => array(
				'membership_and_public_eligibility_on_each_access','verification_on_each_doctor_projection','clinic_and_appointment_on_each_personal_site_projection','review_eligibility_on_each_review_projection','organization_affiliations_on_each_projection','analytics_owner_projection_only','timeline_on_each_query','media_privacy_by_resumable_reconciler','file26_search_projection_rebuilt_from_current_public_dto',
			),
			'routes' => array( '/founder/','/profile/{public_id}/','/profile/{public_id}/timeline/','/profile/{public_id}/report/','/u/{slug}/','/p/{revocable-token}/','/account/profile/','/account/profile/personal-site/','/account/profile/preview/' ),
			'dependencies' => array(
				'file00' => array( 'required' => true, 'minimum_plugin' => SPD_Membership_Adapter::MIN_VERSION, 'minimum_contract' => SPD_Membership_Adapter::MIN_CONTRACT_VERSION, 'functions' => array( 'smc_membership_assertions','smc_user_status','smc_founder_user_id','smc_is_founder' ), 'optional_extensions' => array( 'smc_profile_age_guardian_claim_v1','smc_guardian_relationship_claim_v1','smc_profile_contact_projection_v1' ), 'failure' => 'protected actions fail closed; uncertain age remains minor-safe' ),
				'file09' => array( 'required_for' => 'verified doctor badge and approved professional/credential projection', 'minimum_contract' => SPD_Verification_Adapter::MIN_CONTRACT_VERSION, 'failure' => 'badge and professional/credential projection hidden' ),
				'file21' => array( 'required_for' => 'publication timeline provider', 'minimum_contract' => SPD_Timeline::PROVIDER_CONTRACT_MIN, 'failure' => 'provider unavailable state' ),
				'file20' => array( 'required_for' => 'integrated shell placement and PWA/offline shell', 'failure' => 'semantic shortcode routes remain; no second shell is created' ),
				'file25' => array( 'required_for' => 'global visual tokens/components', 'failure' => 'accessible native green fallback remains' ),
				'file26' => array( 'required_for' => 'global search/discovery/ranking and privacy-minimized profile analytics', 'contract' => 'sabri_file26_profile_search_projection_v1', 'failure' => 'profile remains directly readable; no local search-ranking fallback' ),
				'file17' => array( 'required_for' => 'contacts/internal-message actions', 'failure' => 'contact action hidden' ),
				'file08' => array( 'required_for' => 'clinic availability, appointments and reviews', 'failure' => 'clinic/review/appointment projection hidden; no duplicate truth created' ),
				'file24' => array( 'required_for' => 'assurance/governance and incident evidence', 'failure' => 'native security remains; assurance state reported unavailable' ),
				'media_scanner' => array( 'required_for' => 'new public avatar/cover ingestion', 'minimum_contract' => SPD_Media::SCAN_CONTRACT_MIN, 'failure' => 'upload rejected with service-unavailable state' ),
			),
			'central_laws' => array(
				'free_tier' => 'File 03 has no paid/pro/premium feature gate and no donor advantage.',
				'brand' => 'Sabri Green #087A4E fallback; File 25 token registry wins when present.',
				'privacy' => 'No hidden profiling, no third-party tracker, public DTO allowlists only, minors fail closed.',
				'medical_safety' => 'Professional information/education only; no autonomous diagnosis/prescription/dose/emergency replacement or guaranteed outcomes.',
				'ownership' => 'Verification/appointments/reviews/directory ranking/search ranking remain external canonical facts.',
			),
			'privacy' => array(
				'public_private_dto_separation' => true,
				'contact_default' => 'private',
				'minor_default' => 'private',
				'moderator_private_field_access' => false,
				'public_id' => 'opaque_uuid_v4',
				'analytics' => 'aggregate current File 26 projection only; no File 03 user-level surveillance store',
				'qr' => 'first-party tracking-free revocable signed token',
				'cache' => 'revocation-safe no-store until owner modules provide accepted invalidation',
				'professional_proposals' => 'restricted and never public without File 09 approval',
			),
			'reliability' => array(
				'outbox' => 'atomic lease + bounded retry + dead-letter + explicit audited requeue',
				'migration' => 'bounded cursor; exhausted failures quarantined and remain release blockers',
				'media_erasure' => 'tombstone transaction + retryable deletion ledger + explicit audited requeue',
				'idempotency' => 'required and transactionally finalized for public mutations',
				'reconciliation' => 'cache generation + File 26 current projection + explicit reconciliation flag',
				'delegation' => 'bounded scope, expiry, current eligibility and owner revalidation on every use',
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
		do_action( 'sabri_file26_register_profile_provider', 'file03', array( __CLASS__, 'search_provider' ) );
		do_action( 'sabri_file08_register_profile_delegation_provider', 'file03', 'spd_delegate_can_manage_profile_scope' );
	}

	public static function route_manifest() {
		return array( 'owner' => 'file03', 'version' => SPD_CONTRACT_VERSION, 'routes' => array(
			'founder' => array( 'path' => '/founder/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
			'profile' => array( 'path' => '/profile/{public_id}/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
			'profile_timeline' => array( 'path' => '/profile/{public_id}/timeline/', 'layout' => 'public-profile', 'cache' => 'no-store-until-owner-invalidation' ),
			'profile_report' => array( 'path' => '/profile/{public_id}/report/', 'layout' => 'focused-form', 'cache' => 'no-store' ),
			'account_profile' => array( 'path' => '/account/profile/', 'layout' => 'private-application', 'cache' => 'no-store' ),
			'personal_site' => array( 'path' => '/account/profile/personal-site/', 'layout' => 'private-application', 'cache' => 'no-store' ),
			'private_preview' => array( 'path' => '/account/profile/preview/', 'layout' => 'private-application', 'cache' => 'no-store' ),
		) );
	}

	public static function assurance_manifest() {
		return array( 'owner' => 'file03', 'version' => SPD_CONTRACT_VERSION, 'controls' => array(
			'current_versioned_file00_assertions','trusted_file09_badge_and_credential_projection','object_field_authorization','audience_aware_dto','minor_guardian_privacy','fail_closed_media_scan_and_metadata_reencoding','required_idempotency_and_optimistic_concurrency','atomic_outbox_leases','privacy_export_erasure_tombstone','retryable_media_deletion','migration_failure_quarantine','private_professional_proposals','bounded_delegation','revocable_tracking_free_share_link','current_file26_search_projection','operator_recovery_audit',
		), 'evidence_route' => admin_url( 'admin.php?page=sabri-profiles-system-check' ) );
	}

	public static function component_manifest() {
		return array( 'owner' => 'file03', 'version' => SPD_CONTRACT_VERSION, 'components' => array( 'profile_hero','profile_field_list','profile_contact_actions','profile_timeline_slot','profile_report_form','professional_claim_form','credential_card','clinic_availability_block','share_qr_card','private_profile_preview','delegation_panel' ), 'icon_keys' => array( 'profile','timeline','message','phone','whatsapp','report','edit','verification','clinic','appointment','share','qr' ), 'tokens' => array( '--sabri-primary','--sabri-surface','--sabri-text','--sabri-muted','--sabri-border','--sabri-radius' ), 'rtl' => true, 'wcag_target' => '2.2-AA' );
	}

	public static function file00_public_profile_opt_in( $allowed, $user_id, $file00_row = array() ) {
		unset( $file00_row ); if ( $allowed ) { return true; } $user_id = absint( $user_id ); if ( ! $user_id || ! SPD_DB::tables_exist() ) { return false; }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user_id, false );
		if ( ! $profile || 'active' !== ( $profile['state'] ?? '' ) || 'public' !== ( $profile['profile_visibility'] ?? 'private' ) ) { return false; }
		return 'founder' === ( $profile['profile_type'] ?? '' ) || SPD_Membership_Adapter::public_profile_age_eligible( $user_id );
	}
	public static function public_provider( $identity, $viewer_id = 0 ) { return spd_get_personal_site_profile( $identity, $viewer_id ); }
	public static function search_provider( $identity ) { return spd_get_search_projection( $identity ); }
}
