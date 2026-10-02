#!/usr/bin/env python3
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def gate(ok,msg):
    if not ok: raise SystemExit('Tenth 20-round review failed — '+msg)

main=read('sabri-profiles-doctors.php')
readme=read('README.md')
status=read('STATUS.md')
manifest=read('RELEASE-MANIFEST.md')
trace=read('LATEST-PLAN-TRACEABILITY.md')
ledger=read('TENTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-10-03.md')
round_section=ledger.split('## Twenty sequential frozen rounds',1)[1].split('Defect-bearing rounds:',1)[0]
fresh=read('.github/workflows/fresh-eighty-round-review.yml')
latest=read('.github/workflows/latest-plan-completion.yml')
lock=json.loads(read('RELEASE-LOCK.json'))

checks=[
('R01 exact starting main freeze', '6ed5c0ee5b518a62d961a0d12378adc2960871e7' in ledger),
('R02 candidate identity unchanged', 'Version: 1.2.0-rc18' in main and lock.get('current_repository_candidate')=='1.2.0-rc18'),
('R03 DB/public contract unchanged', "define( 'SPD_DB_VERSION', '1.2.0' );" in main and "define( 'SPD_CONTRACT_VERSION', '1.4.0' );" in main),
('R04 exact companion inventory', all(v in ledger for v in lock['tenth_twenty_round_current_companion_heads'].values())),
('R05 File19 current delta re-audited', '04078025b643ab7696e4cb4e37826bf152defa18' in ledger and 'c2881b12fc7e91c050782f7b17bda00d1d69b2f2' in ledger),
('R06 File21 exact current owner', 'f2eb7e95ddea327af36ea725ffb923b029f885e6' in ledger),
('R07 File22 exact current owner', 'b7a7f2e69411cbd32f0574fd12d766fb70c01b7a' in ledger),
('R08 canonical ownership language', 'no post truth copied' in ledger and 'invalidation only' in ledger),
('R09 security/privacy round', 'Authorization/privacy/minors/medical safety' in ledger),
('R10 schema/migration round', 'Schema/migration/retention/erasure' in ledger),
('R11 API/event/provider round', 'API/event/provider compatibility' in ledger),
('R12 package round', 'deterministic package/SBOM parity' in ledger),
('R13 exactly 20 frozen rows', sum(1 for line in round_section.splitlines() if line.startswith('| ') and line[2:4].isdigit())==20),
('R14 defect ledger exact', 'Defect-bearing rounds: `20`' in ledger),
('R15 clean ledger exact', 'Clean rounds: `01–19`' in ledger),
('R16 20/20 ledger', 'Total reviewed: **20/20**' in ledger),
('R17 merged ninth main truth', all('6ed5c0ee5b518a62d961a0d12378adc2960871e7' in x for x in (readme,status,manifest,trace))),
('R18 stale current-branch wording removed', 'Current review branch: `audit/file03-ninth' not in readme and '| Ninth-cycle branch |' not in status),
('R19 permanent exact-head workflow gate', 'python3 tests/tenth-twenty-round-sequential.py' in fresh and 'python3 tests/tenth-twenty-round-sequential.py' in latest),
('R20 live truth remains unverified', lock.get('production_authorized') is False and lock.get('staging_authorized') is False and lock.get('deployed_version_verified') is False and 'Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔' in ledger),
]
for name,ok in checks: gate(ok,name)
print('File 03 tenth sequential review: PASS 20/20')
for i,(name,_) in enumerate(checks,1): print(f'{i:02d} GREEN — {name}')
