#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT/path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)
def section(src, start, end=None):
    i=src.find(start); require(i>=0, f'Missing section: {start}')
    j=src.find(end, i+len(start)) if end else len(src)
    if end: require(j>=0, f'Missing section end: {end}')
    return src[i:j]

main=text('sabri-profiles-doctors.php')
auth=text('includes/class-spd-authorization.php')
repo=text('includes/class-spd-profile-repository.php')
activator=text('includes/class-spd-activator.php')
rest=text('includes/class-spd-rest.php')
plugin=text('includes/class-spd-plugin.php')
fresh=text('.github/workflows/fresh-eighty-round-review.yml')
future_ci=text('.github/workflows/future-superset-18.yml')
ledger=text('FOURTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md')

require('Version: 1.2.0-rc14' in main and "define( 'SPD_VERSION', '1.2.0-rc14' )" in main, 'rc14 source identity missing')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'DB identity drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'Contract identity drifted')
require('FOURTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'Fourth twenty-round plan marker missing')

mutation=section(auth,'public static function mutation_guard','public static function moderation_guard')
require('spd_login_required' in mutation and "'status' => 401" in mutation, 'R01 unauthenticated mutation 401 invariant missing')
require(mutation.find('spd_login_required') < mutation.find('spd_forbidden'), 'R01 login guard must precede authorization denial')

grant=section(repo,'public function grant_delegate','public function delegate_can_manage')
for token in ('central_target_preflight','spd_profile_unavailable','SPD_Membership_Adapter::health','SPD_Membership_Adapter::claims','SPD_Verification_Adapter::health','SPD_Verification_Adapter::projection','spd_verification_claim_unavailable'):
    require(token in grant, f'R04/R14 delegation certainty invariant missing: {token}')
require("'status' => 503" in grant and "'status' => 403" in grant, 'R04/R14 delegation 503/403 separation missing')

for token in ("persist_exact_option( 'spd_safe_mode'", "persist_exact_option( 'spd_migration_cursor'", "persist_exact_option( 'spd_last_repair_at'", 'spd_repair_evidence_failed'):
    require(token in activator, f'R06 activation/repair persistence invariant missing: {token}')

for token in ('reject_unknown', 'spd_unknown_professional_request_field', 'spd_unknown_report_field', 'spd_unknown_profile_moderation_field', 'spd_unknown_report_moderation_field'):
    require(token in rest, f'R16 strict REST request invariant missing: {token}')

file08=section(plugin,'public function file08_delegation_claim',None)
require('SPD_Schema_Guard::central_ready()' in file08, 'R18 File08 delegation schema certainty guard missing')
require('if ( null !== $claim ) { return $claim; }' in file08, 'R18 upstream File08 claim preservation missing')

branch='audit/file-03-fourth-twenty-round-20260812'
require(branch in fresh and branch in future_ci, 'R19 exact fourth-twenty branch workflow coverage missing')
require('tests/fourth-twenty-round-sequential-review.py' in fresh, 'R20 permanent Fourth-Twenty Fresh gate missing')
require('tests/fourth-twenty-round-sequential-review.py' in future_ci, 'R20 permanent Fourth-Twenty Future gate missing')

defects='01, 04, 06, 14, 16, 18, 19, 20'
clean='02, 03, 05, 07, 08, 09, 10, 11, 12, 13, 15, 17'
require(defects in ledger and clean in ledger, 'Fourth twenty-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary missing')
print('Fourth fresh twenty-round sequential corrective invariants passed.')
