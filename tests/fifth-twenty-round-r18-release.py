#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
readme = text('readme.txt')
status = text('STATUS.md')
changelog = text('CHANGELOG.md')
staging = text('STAGING-ACCEPTANCE.md')
manifest = text('RELEASE-MANIFEST.md')
workflow = text('.github/workflows/fresh-eighty-round-review.yml')
builder = text('build-package.sh')

# R18 — current release identity must describe the fifth-cycle candidate without
# changing DB/public-contract versions.
require("Version: 1.2.0-rc15" in main, 'R18 plugin header is not rc15')
require("define( 'SPD_VERSION', '1.2.0-rc15' )" in main, 'R18 runtime version is not rc15')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'R18 DB version drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'R18 contract version drifted')
require('FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R18 fifth-cycle plan marker missing')
require('Stable tag: 1.2.0-rc15' in readme, 'R18 readme stable tag is stale')
for doc, name in ((status,'STATUS'), (changelog,'CHANGELOG'), (manifest,'RELEASE-MANIFEST')):
    require('1.2.0-rc15' in doc, f'R18 {name} is not aligned to rc15')
    # R19/R20 must remain visible in final history/closure, but the completed
    # fifth cycle must not be forced back into a stale "pending" state.
    require('R19' in doc and 'R20' in doc, f'R18 {name} lost R19/R20 closure history')

# R18 — exact current review workflow must produce reproducible package evidence
# on the same SHA, not rely on a historical workflow.
for marker in (
    'exact-package-parity', 'build-package.sh', 'package-a', 'package-b',
    'sha256sum -c', 'SBOM.json', 'source/package', 'actions/upload-artifact',
    'diff -ru includes', 'diff -ru assets', 'github.sha',
):
    require(marker in workflow, f'R18 exact-head package/parity marker missing: {marker}')
require('audit/file-03-fifth-twenty-round-20260813-clean' in workflow, 'R18 fifth-cycle audit branch is not retained as a Fresh workflow trigger')
require('needs: exact-candidate-review' in workflow, 'R18 package job can run without review gate')
require('tests/fifth-twenty-round-r18-release.py' in workflow, 'R18 release regression test is not wired into Fresh workflow')
for marker in ('SOURCE_DATE_EPOCH', 'SBOM.json', 'sha256'):
    require(marker in builder, f'R18 deterministic builder evidence missing: {marker}')

# R18 — staging acceptance must explicitly test the newly corrected critical paths.
for marker in (
    'appeal review', 'reopens', 'patient-specific', 'provider exception',
    'retired', 'legacy', 'delegation', 'idempotency', '301', 'public_id',
    'unpublished', 'Safe Mode', 'spd_safe_mode_changed_at', 'SBOM',
    'backup', 'rollback', 'deployed artifact checksum',
):
    require(marker.lower() in staging.lower(), f'R18 staging acceptance marker missing: {marker}')

# Historical inventory/checksum values remain provenance for older snapshots and
# must be explicitly distinguished from current rc15 exact-head artifact truth.
# RELEASE-LOCK.json is a mixed record: immutable historical fields plus a current
# candidate boundary, so the whole file must not be falsely described as frozen.
for marker in ('RELEASE-LOCK.json', 'RELEASE-INVENTORY.tsv', 'SOURCE-INVENTORY.tsv', 'CHECKSUMS.sha256', 'RELEASE-CHECKSUMS.sha256'):
    require(marker in manifest, f'R18 historical evidence boundary missing: {marker}')
lower_manifest = manifest.lower()
require(
    ('historical provenance' in lower_manifest or 'historical evidence' in lower_manifest)
    and ('not be interpreted as the current rc15' in lower_manifest or 'not current rc15' in lower_manifest),
    'R18 historical/current release-truth distinction missing'
)
require('Exact deployed code remains unverified' in manifest, 'R18 deployment-truth boundary missing')

print('File 03 fifth-cycle R18 release/package invariants: PASS')
