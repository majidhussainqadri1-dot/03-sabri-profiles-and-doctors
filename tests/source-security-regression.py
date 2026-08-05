#!/usr/bin/env python3
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
php='\n'.join(p.read_text(encoding='utf-8') for p in root.rglob('*.php') if 'tests' not in p.parts)
forbidden={
 'File00 internal table':r'\bsmc_(?:professional_credentials|clinics|applications|identity_documents)\b',
 'legacy requested role':r'_smc_requested_role',
 'legacy verification authority':r'_spd_verification_status|_gdo_reviewer_id|_gdo_reviewed_at',
 'role creation':r'\badd_role\s*\(',
 'moderator private bypass':r'can_moderate_profiles\s*\([^)]*\)\s*\)\s*\{?\s*return\s+true',
 'fake clean scanner':r"array\s*\(\s*['\"]status['\"]\s*=>\s*['\"]clean['\"]\s*,\s*['\"]provider['\"]\s*=>\s*['\"]native-image-validation",
 'direct doctor search ownership':r'get_users\s*\([^)]*_smc_doctor_verified',
}
for label,pattern in forbidden.items():
    if re.search(pattern,php,re.S|re.I): raise SystemExit(f'Forbidden pattern: {label}')
required=[
 'SMC_CONTRACT_VERSION','smc_membership_assertions','smc_public_profile_opt_in',
 'public_profile_allowed','moderator_private_field_access','spd_profile_media_scan_v1',
 'spd_media_secure_delivery_required','removed_for_privacy','Idempotency-Key','If-Match',
 'lease_token','migration_failures','professional_submissions','report_transition_targets',
 'ProfileVisibilityChanged.v1','ProfileReporterErased.v1','sabri_file26_profile_changed',
 'standalone_media_command_retired','gdo_validate_public_projection','valid_until',
 'spd_media_privacy_cursor','private, no-store','spd_safe_mode_persist_failed',
]
for token in required:
    if token not in php: raise SystemExit(f'Missing security/architecture token: {token}')
print('Source security regression checks passed.')
