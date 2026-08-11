#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php_files=[p for p in root.rglob('*.php') if 'tests' not in p.parts]
php='\n'.join(p.read_text(encoding='utf-8') for p in php_files)
required=[
 'SPD_VERSION',"'1.2.0-rc4'",'SPD_DB_VERSION',"'1.2.0'",'SPD_CONTRACT_VERSION',"'1.4.0'",'SPD_PLAN_VERSION','FUTURE-SUPERSET-18','80-ROUND-CORRECTIVE-REVIEW','THIRD-TEN-ROUND-CORRECTIVE-REVIEW','FOURTH-TEN-ROUND-CORRECTIVE-REVIEW',
 'spd_get_public_profile','spd_get_personal_site_profile','spd_get_search_projection','spd_get_future_profile_projection','spd_get_fhir_professional_projection','spd_get_federation_profile_projection','spd_get_profile_timeline',
 '/profile/{public_id}/timeline/','/profile/{public_id}/report/','/account/profile/personal-site/','/account/profile/preview/',
 'trait-spd-profile-professional.php','trait-spd-profile-central.php','trait-spd-frontend-future.php','class-spd-future-profile.php','class-spd-future-rest.php','class-spd-future-privacy.php','class-spd-central-privacy.php','class-spd-schema-guard.php',
 'spd_standalone_media_command_retired','acquire_lock','consume_rate_limit','canGovernLegacy',
 'spd_migration_integrity_guard','idempotency_reservation_marker','profile_mutation_state_allows',
 'SPD_Central_Privacy','SPD_Schema_Guard','record_queue_error'
]
for token in required:
    if token not in php: raise SystemExit(f'Missing architecture token: {token}')
if len(php_files)<44: raise SystemExit(f'Expanded corrective modular tree unexpectedly small: {len(php_files)}')
print(f'Architecture checks passed ({len(php_files)} PHP files, including the fourth ten-round corrective hardening).')
