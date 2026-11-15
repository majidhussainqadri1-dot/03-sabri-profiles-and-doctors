#!/usr/bin/env python3
"""Guard File03 V12.3 source-only reality-freeze template against false live resolution.
This is NOT a real Hostinger, database or deployment probe.
"""
import json
from pathlib import Path

root=Path(__file__).resolve().parents[1]
d=json.loads((root/'ops/FILE03-V12.3-RELEASE-EVIDENCE-TEMPLATE.json').read_text(encoding='utf-8'))
assert d.get('record_type')=='unpopulated_live_staging_reality_freeze_template'
assert d.get('governing_master_plan')=='V12.3'
assert d.get('production_release_decision')=='NO-GO'
live=d['live']; stage=d['staging']; issues=d['defect_disposition']
for field in ('installed_file03_version','installed_file03_package_sha256','installed_file03_source_tree_sha256','installed_file00_version','installed_file00_package_sha256','wordpress_version','php_version','db_runtime_version','file03_db_schema_version_actual','file00_db_schema_version_actual','migration_state_actual','safe_mode_state_actual','independent_capture_evidence_id'):
    assert live[field] is None, 'Template contains unverified concrete Live evidence: '+field
for field in ('actual_package_source_parity_verified','browser_retest_completed','relevant_schema_columns_and_indexes_verified','owner_dependencies_versions_verified'):
    assert live[field] is False, 'Template falsely claims Live proof: '+field
assert live['operational_live_verification_status']=='NOT_EXECUTED'
assert stage['accepted_inherited_cases']==stage['accepted_v12_3_delta_cases']==0
assert stage['required_inherited_cases']==72 and stage['required_v12_3_delta_cases']==24
for field in ('backup_restore_drill_verified','predecessor_rollback_drill_verified','independent_reviewer_approved','actual_db_schema_and_migrations_verified'):
    assert stage[field] is False, 'Template falsely claims Staging proof: '+field
assert stage['founder_signoff_hash_and_timestamp'] is None
assert issues['source_issue_families_discovered']==10
assert issues['source_issue_families_corrected_in_draft']==9
assert issues['cross_owner_issues_unresolved']==1
assert issues['staging_verified_corrections']==issues['live_verified_corrections']==0
assert 'File26 Issue 41' in issues['unresolved_cross_owner_issue']
assert d['parity_audit_required_if_repo_differs_from_deployed'] is True
print('PASS File03 V12.3 blank installed-state template: NO-GO, Live unverified, 96 staging cases NOT ACCEPTED, D08 unresolved.')
