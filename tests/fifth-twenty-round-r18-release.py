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
    require('R19' in doc and 'R20' in doc, f'R18 {name} does not preserve pending R19/R20 boundary')

# R18 — exact current review workflow must produce reproducible package evidence
# on the same SHA, not rely on a historical workflow.
for marker in (
    'exact-package-parity', 'build-package.sh', 'package-a', 'package-b',
    'sha256sum -c', 'SBOM.json', 'source/package', 'actions/upload-artifact',
    'diff -ru includes', 'diff -ru assets', 'github.sha',
):
    require(marker in workflow, f'R18 exact-head package/parity marker missing: {marker}')
require('audit/file-03-fifth-twenty-round-20260813-clean' in workflow, 'R18 current audit branch is not a Fresh workflow trigger')
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

# Historical release-lock/checksum evidence remains explicitly frozen rather than
# being silently relabelled as current rc15 truth.
for marker in ('RELEASE-LOCK.json', 'RELEASE-INVENTORY.tsv', 'SOURCE-INVENTORY.tsv', 'CHECKSUMS.sha256', 'RELEASE-CHECKSUMS.sha256'):
    require(marker in manifest, f'R18 historical evidence boundary missing: {marker}')
require('frozen historical evidence' in manifest.lower(), 'R18 historical/current release-truth distinction missing')
require('Exact deployed code remains unverified' in manifest, 'R18 deployment-truth boundary missing')

print('File 03 fifth-cycle R18 release/package invariants: PASS')
