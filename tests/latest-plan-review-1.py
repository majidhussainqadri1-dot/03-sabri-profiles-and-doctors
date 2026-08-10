#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
src='\n'.join(p.read_text(encoding='utf-8') for p in list(root.glob('*.php'))+list((root/'includes').glob('*.php')))
checks=[
 'SPD_PLAN_VERSION','F03-CEN-01','F03-CEN-02','spd_get_search_projection','update_central_profile',
 'profile_presentation','clinic_schedule_request','sabri_file08_public_clinic_projection_v1',
 'sabri_doctor_verification_public_projection_v1','sabri_file26_profile_search_projection_v1',
 'ProfileShareLinkRotated.v1','ProfileDelegationChanged.v1','ProfileReportAppealed.v1'
]
missing=[x for x in checks if x not in src]
if missing: raise SystemExit('Review 1 failed: '+', '.join(missing))
# No direct writes to companion tables or copied appointment/review entities.
for forbidden in ('smc_membership applications', 'appointment CREATE TABLE', 'review CREATE TABLE'):
    if forbidden in src: raise SystemExit('Review 1 ownership violation: '+forbidden)
print('Fresh review gate 1 passed: latest-plan IDs, owner commands, and canonical boundaries present.')
