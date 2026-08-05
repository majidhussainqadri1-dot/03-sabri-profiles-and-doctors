#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
implementation_paths = list(root.glob('*.php')) + list((root/'includes').glob('*.php')) + list((root/'assets'/'css').glob('*.css')) + list((root/'assets'/'js').glob('*.js'))
text = '\n'.join(p.read_text(encoding='utf-8') for p in implementation_paths)
requirements = {
    'F03-FR-001': ['Official Founder', 'official_founder'],
    'F03-FR-002': ['public_dto', 'bio'],
    'F03-FR-003': ['approved_fields', 'specialty'],
    'F03-FR-004': ['prepare_upload', 'focal_x', 'strip_metadata'],
    'F03-FR-005': ['allowed_audiences', 'audience_allows'],
    'F03-FR-006': ['ProfileVisibilityChanged.v1', 'contact'],
    'F03-FR-007': ['public_id', 'find_by_slug', 'wp_safe_redirect'],
    'F03-FR-008': ['expected_version', 'spd_version_conflict', 'idempotency'],
    'F03-FR-009': ['completeness', 'missing'],
    'F03-FR-010': ['SPD_Timeline', 'cursor', 'provider_health'],
    'F03-FR-011': ['claim_version', 'verified_doctor'],
    'F03-FR-012': ['create_report', 'ProfileReported.v1'],
    'F03-FR-013': ['application/ld+json', 'noindex'],
    'F03-NFR-001': ['mutation_guard', 'audience_allows'],
    'F03-NFR-002': ['wp_privacy_personal_data_exporters', 'erase_profile'],
    'F03-NFR-003': ['dispatch_outbox', 'dead'],
    'F03-NFR-004': ['LIMIT 50', 'limit'],
    'F03-NFR-005': ['focus-visible', 'prefers-reduced-motion'],
    'F03-NFR-006': ['health_report', 'trace_id'],
    'F03-NFR-007': ['spd_migration_cursor', 'transaction'],
    'F03-NFR-008': ['System Check', 'repair'],
    'F03-NFR-009': ['Requires at least: 7.0', 'Requires PHP: 8.1'],
    'F03-NFR-010': ['is_rtl', 'normalize_locale'],
}
missing = []
for rid, tokens in requirements.items():
    absent = [token for token in tokens if token not in text]
    if absent:
        missing.append(f'{rid}: {", ".join(absent)}')
if missing:
    raise SystemExit('Plan coverage gaps:\n' + '\n'.join(missing))
print(f'Plan coverage tokens passed ({len(requirements)} requirements).')
