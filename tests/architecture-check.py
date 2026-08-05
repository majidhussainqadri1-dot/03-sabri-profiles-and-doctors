#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
php_files = [p for p in root.rglob('*.php') if 'tests' not in p.parts]
php = '\n'.join(p.read_text(encoding='utf-8') for p in php_files)

forbidden = {
    'role creation': r'\badd_role\s*\(',
    'role mutation': r'->\s*(?:set_role|add_role|remove_role)\s*\(',
    'legacy File 01 page map': r'spf_page_map',
    'legacy requested-role authorization': r'_smc_requested_role',
    'direct File 00 table access': r'(?:smc_professional_credentials|smc_clinics)',
    'File 03 doctor search ownership': r'get_users\s*\([^)]*_smc_doctor_verified',
    'hardcoded Pakistan mobile': r'\+923[0-9]{9}',
    'doctor approval endpoint': r'spd_verify_doctor',
}
for label, pattern in forbidden.items():
    if re.search(pattern, php, re.S):
        raise SystemExit(f'Forbidden {label} detected')

required = [
    "define( 'SPD_VERSION', '1.0.0-rc1' );",
    'spd_get_public_profile',
    'spd_get_profile_timeline',
    'SPD_CONTRACT_VERSION',
    'public_id char(36)',
    'profile_slug_history',
    'ProfileVisibilityChanged.v1',
    'ProfileMediaChanged.v1',
    'ProfileReported.v1',
    'Idempotency-Key',
    'If-Match',
    'spd_audience_not_allowed',
    'guardian_verified',
    'spd_profile_media_scan_v1',
    'sabri_file21_profile_timeline_items_v1',
    'sabri_file07_doctor_directory_html_v1',
    'sabri_file24_register_assurance_manifest',
    'sabri_file25_register_component_provider',
    'X-Robots-Tag: noindex, nofollow, noarchive',
    'SPD_ALLOW_DESTRUCTIVE_UNINSTALL',
    '/profile/{public_id}/timeline/',
    '/profile/{public_id}/report/',
    "trait-spd-profile-update.php",
    "trait-spd-frontend-edit.php",
]
for token in required:
    if token not in php:
        raise SystemExit(f'Required architecture token missing: {token}')

if len(php_files) < 18:
    raise SystemExit(f'Expected expanded modular source tree, found {len(php_files)} PHP files')
print(f'Architecture checks passed ({len(php_files)} PHP files).')
