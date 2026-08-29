#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
main = (ROOT / 'sabri-profiles-doctors.php').read_text(encoding='utf-8')
readme_txt = (ROOT / 'readme.txt').read_text(encoding='utf-8')
repository_readme = (ROOT / 'README.md').read_text(encoding='utf-8')
status = (ROOT / 'STATUS.md').read_text(encoding='utf-8')
release_manifest = (ROOT / 'RELEASE-MANIFEST.md').read_text(encoding='utf-8')
release_lock = json.loads((ROOT / 'RELEASE-LOCK.json').read_text(encoding='utf-8'))
changelog = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
ledger = (ROOT / 'SEVENTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-29.md').read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/fresh-eighty-round-review.yml').read_text(encoding='utf-8')


def require(ok, message):
    if not ok:
        raise SystemExit(message)


# R20-1 — a materially different repository candidate must have a distinct runtime/package identity.
require('Version: 1.2.0-rc16' in main, 'R20 runtime header is not rc16')
require("define( 'SPD_VERSION', '1.2.0-rc16' );" in main, 'R20 runtime version constant is not rc16')
require('Stable tag: 1.2.0-rc16' in readme_txt, 'R20 WordPress stable tag is not rc16')
require(release_lock.get('current_repository_candidate') == '1.2.0-rc16', 'R20 release lock is not bound to rc16')

# R20-2 — source identity advance must not silently become a DB/public-contract migration.
require("define( 'SPD_DB_VERSION', '1.2.0' );" in main, 'R20 DB schema version drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' );" in main, 'R20 public contract version drifted')
for document in (repository_readme, status, release_manifest, changelog, ledger):
    require('1.2.0-rc16' in document, 'R20 repository truth document lacks rc16 candidate identity')

# R20-3 — plan lineage records both newer twenty-round cycles.
require('SIXTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R20 sixth twenty-round plan marker missing')
require('SEVENTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'R20 seventh twenty-round plan marker missing')
require('Plan marker includes `SIXTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW` and `SEVENTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW`' in release_manifest, 'R20 release manifest plan lineage is stale')

# R20-4 — final review ledger is human-readable and classifications agree across current truth docs.
defect_rounds = '03, 04, 05, 06, 07, 08, 11, 14, 15, 17, 19, 20'
clean_rounds = '01, 02, 09, 10, 12, 13, 16, 18'
require('20/20' in ledger, 'R20 final ledger does not record 20/20 completion')
require(defect_rounds in ledger and clean_rounds in ledger, 'R20 final ledger classifications are incomplete')
for document in (repository_readme, status, release_manifest, changelog, readme_txt):
    require(defect_rounds in document and clean_rounds in document, 'R20 current repository truth documents disagree on round classification')
require('95c90da025d2157b578126d69559fc6bac733918' in ledger, 'R20 pre-correction exact HEAD missing from ledger')
require('95c90da025d2157b578126d69559fc6bac733918' in status, 'R20 pre-correction exact HEAD missing from status')

# R20-5 — repository/package evidence can never self-authorize staging/live truth.
require(release_lock.get('production_authorized') is False, 'R20 release lock improperly authorizes production')
require(release_lock.get('staging_authorized') is False, 'R20 release lock improperly authorizes staging')
require(release_lock.get('deployed_version_verified') is False, 'R20 release lock improperly claims deployed-version verification')
for document in (repository_readme, status, release_manifest, changelog, ledger):
    require('Exact deployed code remains unverified' in document or 'Exact deployed code is unverified' in document, 'R20 repository/live separation wording missing')

# R20-6 — permanent exact-head CI must actually execute this closure gate.
require('python3 tests/seventh-twenty-round-r20-final.py' in workflow, 'R20 permanent closure test is not wired into Fresh exact-head CI')
require('Seventh-cycle R20 final closure invariants' in workflow, 'R20 workflow step label missing')

print('File 03 seventh twenty-round R20 final closure invariants passed.')
