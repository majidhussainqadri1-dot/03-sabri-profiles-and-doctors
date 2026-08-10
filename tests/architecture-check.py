#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php_files=[p for p in root.rglob('*.php') if 'tests' not in p.parts]
php='\n'.join(p.read_text(encoding='utf-8') for p in php_files)
required=['SPD_VERSION',"'1.1.0-rc1'",'SPD_DB_VERSION',"'1.2.0'",'SPD_CONTRACT_VERSION',"'1.3.0'",'SPD_PLAN_VERSION','spd_get_public_profile','spd_get_personal_site_profile','spd_get_search_projection','spd_get_profile_timeline','/profile/{public_id}/timeline/','/profile/{public_id}/report/','/account/profile/personal-site/','/account/profile/preview/','trait-spd-profile-professional.php','trait-spd-profile-central.php','spd_standalone_media_command_retired']
for token in required:
    if token not in php: raise SystemExit(f'Missing architecture token: {token}')
if len(php_files)<34: raise SystemExit(f'Expanded modular tree unexpectedly small: {len(php_files)}')
print(f'Architecture checks passed ({len(php_files)} PHP files).')
