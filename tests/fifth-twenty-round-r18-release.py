#!/usr/bin/env python3
from pathlib import Path
import re

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

# Historical R18 guarantee: the fifth-cycle rc15 release boundary must remain in
# repository history, while later corrective cycles are allowed to advance the
# current source-candidate version without changing DB/public-contract identity.
m = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \);", main)
require(m is not None and int(m.group(1)) >= 15, 'R18 current source candidate regressed below rc15')
current_version = f"1.2.0-rc{m.group(1)}"
require(f'Version: {current_version}' in main, 'R18 plugin header/runtime version mismatch')
require(f'Stable tag: {current_version}' in readme, 'R18 readme/current runtime version mismatch')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'R18 DB version drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'R18 contract version drifted')
require('FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R18 fifth-cycle plan marker missing')
require('= 1.2.0-rc15 =' in readme, 'R18 historical rc15 WordPress changelog entry missing')
require('## 1.2.0-rc15 — fifth fresh 20-round sequential corrective review' in changelog, 'R18 historical rc15 changelog entry missing')
require('R19' in changelog and 'R20' in changelog, 'R18 changelog lost fifth-cycle R19/R20 closure history')

# Current truth documents may advance beyond rc15. Their obligation is current
# candidate coherence, not repetition of fifth-cycle wording.
for doc, name in ((status,'STATUS'), (changelog,'CHANGELOG'), (manifest,'RELEASE-MANIFEST')):
    require(current_version in doc, f'R18 {name} is not aligned to current candidate {current_version}')

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

# R18 — staging acceptance must explicitly test the corrected critical paths.
for marker in (
    'appeal review', 'reopens', 'patient-specific', 'provider exception',
    'retired', 'legacy', 'delegation', 'idempotency', '301', 'public_id',
    'unpublished', 'Safe Mode', 'spd_safe_mode_changed_at', 'SBOM',
    'backup', 'rollback', 'deployed artifact checksum',
):
    require(marker.lower() in staging.lower(), f'R18 staging acceptance marker missing: {marker}')

# Historical inventory/checksum values remain provenance for older snapshots and
# must be explicitly distinguished from current exact-head artifact truth.
for marker in ('RELEASE-LOCK.json', 'RELEASE-INVENTORY.tsv', 'SOURCE-INVENTORY.tsv', 'CHECKSUMS.sha256', 'RELEASE-CHECKSUMS.sha256'):
    require(marker in manifest, f'R18 historical evidence boundary missing: {marker}')
lower_manifest = manifest.lower()
require(
    ('historical provenance' in lower_manifest or 'historical evidence' in lower_manifest)
    and 'not be interpreted as' in lower_manifest
    and f'current {current_version.lower()}' in lower_manifest,
    'R18 historical/current release-truth distinction missing'
)
require('Exact deployed code remains unverified' in manifest, 'R18 deployment-truth boundary missing')

print('File 03 fifth-cycle R18 historical release/package invariants: PASS')
