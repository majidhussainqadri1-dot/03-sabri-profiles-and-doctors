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
future=text('includes/class-spd-future-profile.php')
activator=text('includes/class-spd-activator.php')
repo=text('includes/class-spd-profile-repository.php')
uninstall=text('uninstall.php')
verify=text('includes/class-spd-verification-adapter.php')
plugin=text('includes/class-spd-plugin.php')
frontend=text('includes/trait-spd-frontend-profile.php')
fresh=text('.github/workflows/fresh-eighty-round-review.yml')
future_ci=text('.github/workflows/future-superset-18.yml')
ledger=text('THIRD-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md')

require("define( 'SPD_VERSION'," in main, 'current source version constant missing while preserving third-twenty invariants')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'DB identity drifted')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'Contract identity drifted')
require('THIRD-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'Third twenty-round plan marker missing')
require('FOURTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'Fourth twenty-round preservation marker missing')
require('FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'Later fifth twenty-round preservation marker missing')

moderation=section(auth,'public static function moderation_guard',None)
for token in ('spd_login_required','spd_membership_provider_unavailable','spd_membership_claim_unavailable','spd_forbidden'):
    require(token in moderation, f'R01 invariant missing: {token}')

require('return spd_read_future_profile_state' in future, 'R05 strict future-state read missing')
require("'status' => 'unknown'" in future and "'state_degraded' => true" in future, 'R05 degraded lifecycle invariant missing')

require('persist_exact_option' in activator and 'activation metadata could not be persisted safely' in activator, 'R07 activation metadata persistence invariant missing')

require('central_target_preflight' in repo and 'spd_profile_store_unavailable' in repo, 'R09 DB-certain Central target preflight missing')

require("'spd_founder_profile', 'spd_founder_profile_legacy_read_only'" in uninstall, 'R12 destructive legacy source cleanup missing')

for token in ('spd_future_translation_store_unavailable','spd_future_freshness_store_unavailable','spd_future_history_store_unavailable','native_store_degraded','degraded_components'):
    require(token in future, f'R13 native future-store degradation invariant missing: {token}')

require("version_compare( (string) $projection['claim_version'], self::MIN_VERSION, '<' )" in verify, 'R14 verification minimum claim version is not enforced')

adapter=section(plugin,'public function file26_search_projection','public function file08_delegation_claim')
require('if ( null !== $current ) { return $current; }' in adapter, 'R15 upstream File26 answer/error preservation missing')

profile=section(frontend,'public function profile()','private function render_profile')
require('find_by_user_id( get_current_user_id(), false )' in profile, 'R17 public GET can still ensure/create a profile')

require('latest_plan_upgrade_metadata_failed' in plugin and 'get_option( $option, null ) !== $value' in plugin, 'R18 runtime-upgrade identity persistence guard missing')

branch='audit/file-03-third-twenty-round-20260812'
require(branch in fresh and branch in future_ci, 'R19 exact branch workflow coverage missing')
require('tests/third-twenty-round-sequential-review.py' in fresh, 'R20 permanent Third-Twenty Fresh gate missing')
require('tests/third-twenty-round-sequential-review.py' in future_ci, 'R20 permanent Third-Twenty Future gate missing')

defects='01, 05, 07, 09, 12, 13, 14, 15, 17, 18, 19, 20'
clean='02, 03, 04, 06, 08, 10, 11, 16'
require(defects in ledger and clean in ledger, 'Third twenty-round round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary missing')
print('Third fresh twenty-round sequential corrective invariants passed under current release identity.')
