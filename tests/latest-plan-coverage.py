#!/usr/bin/env python3
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
source='\n'.join(p.read_text(encoding='utf-8') for p in list(root.glob('*.php'))+list((root/'includes').glob('*.php'))+list((root/'assets/css').glob('*.css'))+list((root/'assets/js').glob('*.js')))
trace=(root/'LATEST-PLAN-TRACEABILITY.md').read_text(encoding='utf-8')
contracts=(root/'includes/class-spd-contracts.php').read_text(encoding='utf-8')
expected=[f'CV-{i:03d}' for i in range(14,26)]+[f'CV-{i:03d}' for i in range(239,286)]+['F03-CEN-01','F03-CEN-02']
assert len(expected)==61
missing_trace=[x for x in expected if f'| {x} |' not in trace]
missing_manifest=[x for x in expected if f"'{x}'" not in contracts]
if missing_trace or missing_manifest:
    raise SystemExit(f'missing trace={missing_trace}; manifest={missing_manifest}')
checks={
 'personal-site':['personal_site_dto','sabri_profile_personal_site','appointment_url','credential_card'],
 'search':['spd_get_search_projection','sabri_file26_profile_search_projection_v1','search_projection'],
 'share-qr':['ProfileShareLinkRotated.v1','data-spd-qr','qrMatrix','tracking_free'],
 'preview':['sabri_profile_private_preview','spd-preview-frame--mobile','spd-preview-frame--rtl'],
 'delegation':['spd_profile_delegations','delegate_can_manage','clinic_schedule_request'],
 'privacy':['public_extended_fields','audience_allows','no File 03 user-level surveillance store'],
 'safety':['validate_presentation_fields','create_safety_report','child_safety','ProfileReportAppealed.v1'],
 'green':['#087A4E','--sabri-primary'],
 'ownership':['file08','file09','file26','no duplicate truth created'],
}
missing=[]
for name,tokens in checks.items():
    for token in tokens:
        if token not in source and token not in trace and token not in contracts:
            missing.append(f'{name}:{token}')
if missing: raise SystemExit('Latest-plan coverage gaps:\n'+'\n'.join(missing))
if re.search(r'https://(?:chart\.googleapis|api\.qrserver|quickchart)', source, re.I):
    raise SystemExit('Third-party QR/tracking dependency detected')
print(f'Latest central-plan coverage passed: {len(expected)} governing IDs plus native ownership guards.')
