#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php_files=[p for p in root.rglob('*.php') if 'tests' not in p.parts]
php='\n'.join(p.read_text(encoding='utf-8') for p in php_files)
required=[
 'SPD_VERSION',"'1.2.0-rc10'",'SPD_DB_VERSION',"'1.2.0'",'SPD_CONTRACT_VERSION',"'1.4.0'",'SPD_PLAN_VERSION','FUTURE-SUPERSET-18','80-ROUND-CORRECTIVE-REVIEW','THIRD-TEN-ROUND-CORRECTIVE-REVIEW','FOURTH-TEN-ROUND-CORRECTIVE-REVIEW','FIFTH-TEN-ROUND-CORRECTIVE-REVIEW','SIXTH-TEN-ROUND-CORRECTIVE-REVIEW','SEVENTH-TEN-ROUND-CORRECTIVE-REVIEW','EIGHTH-TEN-ROUND-CORRECTIVE-REVIEW','NINTH-TEN-ROUND-CORRECTIVE-REVIEW','TENTH-TEN-ROUND-CORRECTIVE-REVIEW',
 'spd_get_public_profile','spd_get_personal_site_profile','spd_get_search_projection','spd_get_future_profile_projection','spd_get_fhir_professional_projection','spd_get_federation_profile_projection','spd_get_profile_timeline',
 '/profile/{public_id}/timeline/','/profile/{public_id}/report/','/account/profile/personal-site/','/account/profile/preview/',
 'trait-spd-profile-professional.php','trait-spd-profile-central.php','trait-spd-frontend-future.php','class-spd-future-profile.php','class-spd-future-rest.php','class-spd-future-privacy.php','class-spd-central-privacy.php','class-spd-slug-privacy.php','class-spd-schema-guard.php','class-spd-outbox-dispatcher.php',
 'spd_standalone_media_command_retired','acquire_lock','consume_rate_limit','canGovernLegacy',
 'migration_integrity_guard','idempotency_reservation_marker','profile_mutation_state_allows',
 'SPD_Central_Privacy','SPD_Slug_Privacy','SPD_Schema_Guard','SPD_Outbox_Dispatcher','record_queue_error','_spd_media_owner_user_id','_spd_media_purpose',
 'validate_audience_payload','spd_delegate_minor_forbidden','find_by_public_id_strict','find_by_slug_strict','outbox_delivery_persist_failed',
 'spd_last_post_commit_reload_error','spd_unknown_disclosure_field','deletion_lease_lost','spd_professional_submission_read_failed','outbox_claim_lost','outbox_delivery_lease_lost',
 'spd_profile_store_unavailable','spd_privacy_export_failed','media_privacy_profile_read_failed','spd_profile_refresh_read_failed','outbox_invalid_payload','outbox_delivery_failed','spd_last_migration_error'
]
for token in required:
    if token not in php: raise SystemExit(f'Missing architecture token: {token}')
if len(php_files)<46: raise SystemExit(f'Expanded corrective modular tree unexpectedly small: {len(php_files)}')
print(f'Architecture checks passed ({len(php_files)} PHP files, including the tenth ten-round corrective hardening).')
