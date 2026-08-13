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

# Exactly twenty final ledger rows, in order.
rounds = re.findall(r'^\| R(\d{2}) \|', ledger, flags=re.M)
require(rounds == [f'{i:02d}' for i in range(1, 21)], f'R20 final ledger is not exactly R01..R20: {rounds}')

defects = re.findall(r'^\| R(\d{2}) \| DEFECT → FIXED \|', ledger, flags=re.M)
expected_defects = ['01','05','06','11','13','14','15','16','17','18','19','20']
require(defects == expected_defects, f'R20 defect-round ledger drifted: {defects}')
clean = re.findall(r'^\| R(\d{2}) \| CLEAN \|', ledger, flags=re.M)
require(clean == ['02','03','04','07','08','09','10','12'], f'R20 clean-round ledger drifted: {clean}')
require('20/20' in ledger and '12/20' in ledger and '8/20' in ledger, 'R20 ledger totals missing')

# Current candidate documentation must describe final closure, not the pre-R19/R20 state.
for doc, name in ((readme,'readme'),(status,'STATUS'),(changelog,'CHANGELOG'),(manifest,'RELEASE-MANIFEST')):
    require('1.2.0-rc15' in doc, f'R20 {name} version drifted')
    require('20/20' in doc, f'R20 {name} does not record twenty-round completion')
    require('01, 05, 06, 11, 13, 14, 15, 16, 17, 18, 19, 20' in doc, f'R20 {name} defect-round list missing')
require('through Round 18' not in readme and 'R19–R20 remain' not in readme, 'R20 readme still claims pre-final review state')
require('R19–R20 pending' not in status, 'R20 STATUS still claims final rounds pending')
require('R19–R20 remain pending' not in changelog, 'R20 CHANGELOG still claims final rounds pending')
require('R19–R20 pending' not in manifest, 'R20 manifest still claims final rounds pending')

# Final review/source/package identity must remain coherent.
require("define( 'SPD_VERSION', '1.2.0-rc15' )" in main, 'R20 runtime version drifted')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'R20 DB version drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'R20 contract version drifted')
require('FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R20 final plan marker missing')
require('fifth-twenty-round-r20-final.py' in workflow, 'R20 final closure gate is not wired into exact review workflow')
require('exact-package-parity' in workflow and 'needs: exact-candidate-review' in workflow, 'R20 exact package gate is not chained after review')
require(
    'SBOM.json' in workflow
    and 'source/package' in workflow
    and 'runtime parity' in workflow,
    'R20 exact package evidence is incomplete'
)

# Deployment-status truth must remain explicit after repository closure.
for doc, name in ((status,'STATUS'),(manifest,'RELEASE-MANIFEST'),(ledger,'ledger'),(staging,'staging')):
    require('deployed' in doc.lower() and 'unverified' in doc.lower(), f'R20 {name} lost deployment-unverified boundary')
require('Staging-Accepted' in status and 'Pending / unverified' in status, 'R20 repository closure is being confused with staging acceptance')
require('Operational' in status and 'Not established' in status, 'R20 repository closure is being confused with operational status')

print('File 03 fifth-cycle R20 final closure invariants: PASS')
