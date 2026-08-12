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

future=text('includes/class-spd-future-profile.php')
verification=text('includes/class-spd-verification-adapter.php')
timeline=text('includes/class-spd-timeline.php')
privacy=text('includes/class-spd-future-privacy.php')
public_dto=text('includes/traits/trait-spd-profile-public-dto.php')
media=text('includes/class-spd-media.php')
outbox=text('includes/class-spd-outbox-dispatcher.php')
fresh=text('.github/workflows/fresh-eighty-round-review.yml')
future_ci=text('.github/workflows/future-superset-18.yml')
ledger=text('FIFTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md')

state=section(future,'public static function set_future_state',None)
require('spd_federation_owner_opt_in_required' in state and '! $is_owner' in state, 'R02 owner-only federation opt-in invariant missing')
require('SPD_Membership_Adapter::health()' in state and 'spd_membership_dependency_unavailable' in state and 'spd_membership_claim_unavailable' in state, 'R05 current File00 authority certainty invariant missing')
require('writable_schema_guard' in future and future.count('self::writable_schema_guard()') >= 3, 'R06 strong future write schema guard missing')
require('catch ( Throwable $e )' in section(future,'private static function current_claim','private static function safe_external_url'), 'R03 Future provider exception boundary missing')

projection=section(verification,'public static function projection','private static function normalize')
require('catch ( Throwable $e )' in projection and 'gdo_validate_public_projection' in projection, 'R10 File09 exception-safe projection invariant missing')

providers=section(timeline,'public static function providers','public static function query')
query=section(timeline,'public static function query','private static function normalize_item')
require('catch ( Throwable $e )' in providers, 'R11 provider-registry exception boundary missing')
require("$health[ $key ] = 'unavailable'" in query and 'apply_filters( $health_filter' in query and 'catch ( Throwable $e )' in query, 'R11 provider-health exception isolation missing')

require('SPD_Membership_Adapter::health()' in privacy and 'Current identity governance could not be verified' in privacy, 'R12 identity-governance erasure guard missing')
require('Legal-hold status could not be verified' in privacy and 'catch ( Throwable $e )' in privacy, 'R12 legal-hold exception guard missing')

for token in ('Current identity assertions are temporarily unavailable','sabri_file08_public_clinic_projection_v1','sabri_network_message_profile_url','catch ( Throwable $e )'):
    require(token in public_dto, f'R13 public DTO provider-degradation invariant missing: {token}')

scan=section(media,'public static function prepare_upload','private static function valid_clean_scan')
require('spd_profile_media_scan_v1' in scan and 'catch ( Throwable $e )' in scan and 'spd_scan_unavailable' in scan, 'R14 media scanner exception fail-closed invariant missing')

record=section(outbox,'private static function record_error','public static function dispatch')
require('sabri_file24_outbox_failure' in record and 'catch ( Throwable $e )' in record, 'R16 File24 assurance isolation missing')

branch='audit/file-03-fifth-twenty-round-20260812'
require(branch in fresh and branch in future_ci, 'R19 exact fifth-twenty branch workflow coverage missing')
require('tests/fifth-twenty-round-sequential-review.py' in fresh, 'R19 permanent Fifth-Twenty Fresh gate missing')
require('tests/fifth-twenty-round-sequential-review.py' in future_ci, 'R19 permanent Fifth-Twenty Future gate missing')

require('02, 03, 05, 06, 10, 11, 12, 13, 14, 16, 19' in ledger, 'Fifth twenty-round defect ledger drifted')
require('01, 04, 07, 08, 09, 15, 17, 18' in ledger, 'Fifth twenty-round clean ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary missing')
print('Fifth fresh twenty-round sequential corrective invariants passed through R19.')
