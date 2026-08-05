#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
source='\n'.join(p.read_text(encoding='utf-8') for p in list(root.glob('*.php'))+list((root/'includes').glob('*.php'))+list((root/'assets/css').glob('*.css'))+list((root/'assets/js').glob('*.js')))
tests='\n'.join(p.read_text(encoding='utf-8') for p in (root/'tests').glob('*') if p.is_file())
coverage={
 'F03-FR-001':(['official_founder','founder_fields'],['authorization-runtime.php']),
 'F03-FR-002':(['public_dto','profile_visibility'],['authorization-runtime.php']),
 'F03-FR-003':(['submit_professional_fields','approved_fields'],['verification-runtime.php']),
 'F03-FR-004':(['prepare_upload','strip_metadata','focal_x','spd_profile_media_scan_v1'],['source-security-regression.py']),
 'F03-FR-005':(['allowed_audiences','audience_allows','can_publish_audience'],['authorization-runtime.php']),
 'F03-FR-006':(['smc_profile_contact_projection_v1','ProfileVisibilityChanged.v1','purge_profile_cache'],['source-security-regression.py']),
 'F03-FR-007':(['public_id char(36)','find_by_slug','record_slug'],['schema-check.py']),
 'F03-FR-008':(['expected_version','idempotency_begin','audit_diff'],['source-security-regression.py']),
 'F03-FR-009':(['completeness','missing'],['state-runtime.php']),
 'F03-FR-010':(['SPD_Timeline','provider_health','cursor'],['timeline-runtime.php']),
 'F03-FR-011':(['gdo_validate_public_projection','valid_until','claim_version'],['verification-runtime.php']),
 'F03-FR-012':(['create_report','ProfileReported.v1','report_transition_targets'],['source-security-regression.py']),
 'F03-FR-013':(['application/ld+json','noindex','canonical'],['source-security-regression.py']),
 'F03-NFR-001':(['mutation_guard','profile_visibility_allows'],['authorization-runtime.php']),
 'F03-NFR-002':(['wp_privacy_personal_data_exporters','erase_profile'],['source-security-regression.py']),
 'F03-NFR-003':(['dispatch_outbox','lease_token','dead'],['source-security-regression.py']),
 'F03-NFR-004':(['LIMIT 50','private, no-store'],['source-security-regression.py']),
 'F03-NFR-005':(['focus-visible','prefers-reduced-motion'],['source-security-regression.py']),
 'F03-NFR-006':(['health_report','trace_id'],['source-security-regression.py']),
 'F03-NFR-007':(['migration_failures','spd_migration_cursor','transaction'],['schema-check.py']),
 'F03-NFR-008':(['System Check','repair_owned_resources','safe_mode'],['source-security-regression.py']),
 'F03-NFR-009':(['Requires at least: 7.0','Requires PHP: 8.1'],['source-security-regression.py']),
 'F03-NFR-010':(['is_rtl','normalize_locale'],['state-runtime.php']),
}
missing=[]
for rid,(src_tokens,test_tokens) in coverage.items():
    absent=[x for x in src_tokens if x not in source]+[f'test:{x}' for x in test_tokens if x not in tests]
    if absent: missing.append(f"{rid}: {', '.join(absent)}")
if missing: raise SystemExit('Plan coverage gaps:\n'+'\n'.join(missing))
print(f'Plan-to-code-and-test traceability passed ({len(coverage)} requirements).')
