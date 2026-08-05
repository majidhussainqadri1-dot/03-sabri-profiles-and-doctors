#!/usr/bin/env python3
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
php_files=[p for p in root.rglob('*.php') if 'tests' not in p.parts]
php='\n'.join(p.read_text(encoding='utf-8') for p in php_files)
required=['SPD_VERSION',"'1.0.0-rc2'",'SPD_DB_VERSION',"'1.2.0'",'SPD_CONTRACT_VERSION','spd_get_public_profile','spd_get_profile_timeline','/profile/{public_id}/timeline/','/profile/{public_id}/report/','trait-spd-profile-professional.php','spd_standalone_media_command_retired']
for token in required:
    if token not in php: raise SystemExit(f'Missing architecture token: {token}')
if len(php_files)<30: raise SystemExit(f'Expanded modular tree unexpectedly small: {len(php_files)}')
print(f'Architecture checks passed ({len(php_files)} PHP files).')
