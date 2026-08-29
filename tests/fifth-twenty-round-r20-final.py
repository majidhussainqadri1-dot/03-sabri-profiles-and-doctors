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
manifest = text('RELEASE-MANIFEST.md')
ledger = text('FIFTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-13.md')
workflow = text('.github/workflows/fresh-eighty-round-review.yml')
staging = text('STAGING-ACCEPTANCE.md')

# Exactly twenty historical fifth-cycle ledger rows, in order.
rounds = re.findall(r'^\| R(\d{2}) \|', ledger, flags=re.M)
require(rounds == [f'{i:02d}' for i in range(1, 21)], f'R20 fifth-cycle ledger is not exactly R01..R20: {rounds}')

defects = re.findall(r'^\| R(\d{2}) \| DEFECT → FIXED \|', ledger, flags=re.M)
expected_defects = ['01','05','06','11','13','14','15','16','17','18','19','20']
require(defects == expected_defects, f'R20 fifth-cycle defect ledger drifted: {defects}')
clean = re.findall(r'^\| R(\d{2}) \| CLEAN \|', ledger, flags=re.M)
require(clean == ['02','03','04','07','08','09','10','12'], f'R20 fifth-cycle clean ledger drifted: {clean}')
require('20/20' in ledger and '12/20' in ledger and '8/20' in ledger, 'R20 fifth-cycle ledger totals missing')

# The fifth-cycle rc15 closure must remain preserved historically even after a
# later review legitimately advances the current candidate identity.
require('= 1.2.0-rc15 =' in readme, 'R20 historical rc15 readme entry missing')
require('## 1.2.0-rc15 — fifth fresh 20-round sequential corrective review' in changelog, 'R20 historical rc15 changelog entry missing')
for doc, name in ((readme,'readme'),(changelog,'CHANGELOG')):
    require('01, 05, 06, 11, 13, 14, 15, 16, 17, 18, 19, 20' in doc, f'R20 {name} lost fifth-cycle defect-round history')
require('through Round 18' not in readme and 'R19–R20 remain' not in readme, 'R20 readme still claims pre-final fifth-cycle state')
require('R19–R20 remain pending' not in changelog, 'R20 CHANGELOG still claims fifth final rounds pending')

# Current source may be rc15 or later, but DB/public-contract identity and the
# fifth plan marker must remain intact.
m = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \);", main)
require(m is not None and int(m.group(1)) >= 15, 'R20 current source candidate regressed below rc15')
current_version = f"1.2.0-rc{m.group(1)}"
require(f'Version: {current_version}' in main and f'Stable tag: {current_version}' in readme, 'R20 current runtime/readme identity mismatch')
require(current_version in status and current_version in manifest and current_version in changelog, 'R20 current repository truth docs are not aligned to runtime candidate')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'R20 DB version drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'R20 contract version drifted')
require('FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R20 historical fifth-cycle plan marker missing')
require('fifth-twenty-round-r20-final.py' in workflow, 'R20 fifth final closure gate is not wired into exact review workflow')
require('exact-package-parity' in workflow and 'needs: exact-candidate-review' in workflow, 'R20 exact package gate is not chained after review')
require('SBOM.json' in workflow and 'source/package' in workflow and 'runtime parity' in workflow, 'R20 exact package evidence is incomplete')

# Deployment-status truth remains explicit regardless of later source version.
for doc, name in ((status,'STATUS'),(manifest,'RELEASE-MANIFEST'),(ledger,'ledger'),(staging,'staging')):
    require('deployed' in doc.lower() and 'unverified' in doc.lower(), f'R20 {name} lost deployment-unverified boundary')
require('Staging-Accepted' in status and 'Pending / unverified' in status, 'R20 repository closure is being confused with staging acceptance')
require('Operational' in status and 'Not established' in status, 'R20 repository closure is being confused with operational status')

print('File 03 fifth-cycle R20 historical closure invariants: PASS')
