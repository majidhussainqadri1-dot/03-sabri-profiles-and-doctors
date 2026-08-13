#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(condition, message):
    if not condition:
        raise SystemExit(message)

def section(src, start, end=None):
    i = src.find(start)
    require(i >= 0, f'Missing section start: {start}')
    j = src.find(end, i + len(start)) if end else len(src)
    if end:
        require(j >= 0, f'Missing section end: {end}')
    return src[i:j]

main = text('sabri-profiles-doctors.php')
auth = text('includes/class-spd-authorization.php')
public_dto = text('includes/trait-spd-profile-public-dto.php')
future_rest = text('includes/class-spd-future-rest.php')
media = text('includes/class-spd-media.php')
activator = text('includes/class-spd-activator.php')
moderation = text('includes/trait-spd-profile-moderation.php')
report_appeals = text('includes/trait-spd-profile-report-appeals.php')
repo = text('includes/class-spd-profile-repository.php')
frontend = text('includes/trait-spd-frontend-profile.php')
observability = text('includes/class-spd-observability.php')
uninstall = text('uninstall.php')
fresh = text('.github/workflows/fresh-eighty-round-review.yml')
future_workflow = text('.github/workflows/future-superset-18.yml')
ledger = text('SECOND-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md')

m = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \);", main)
require(m and int(m.group(1)) >= 12, 'Current source must preserve or supersede rc12 identity')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main, 'Database identity drifted unexpectedly')
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'Contract identity drifted unexpectedly')
require('SECOND-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main, 'Second twenty-round plan marker is missing')

moderation_guard = section(auth, 'public static function moderation_guard', None)
for token in ('SPD_Membership_Adapter::health()', 'spd_membership_provider_unavailable', 'spd_membership_claim_unavailable', 'spd_forbidden'):
    require(token in moderation_guard, f'Round 01 moderation authorization invariant missing: {token}')

public_media = section(public_dto, 'private function public_media', 'private function badge')
require("'attachment_id'" not in public_media, 'Round 02 public media DTO still leaks internal attachment_id')
require("'url'" in public_media and "'alt'" in public_media, 'Round 02 public media presentation fields regressed')

ask_work = section(future_rest, 'public function ask_work', 'public function create_disclosure')
require("$wpdb->last_error = '';" in ask_work and 'spd_profile_store_unavailable' in ask_work, 'Round 05 Ask Work DB certainty is missing')

require('clear_queue_error_family' in media, 'Round 06 media error-family isolation helper is missing')
require("clear_queue_error_family( 'privacy' )" in media, 'Round 06 privacy worker error isolation is missing')
require("clear_queue_error_family( 'deletion' )" in media, 'Round 06 deletion worker error isolation is missing')

legacy = section(activator, 'private static function migrate_legacy_options', None)
require('spd_legacy_option_migration_failed' in legacy, 'Round 07 legacy migration persistence failure is not explicit')
require("get_option( 'spd_founder_profile_legacy_read_only'" in legacy, 'Round 07 legacy target persistence is not verified')
require(legacy.find('spd_legacy_option_migration_failed') < legacy.find("delete_option( 'spd_founder_profile' )"), 'Round 07 can still delete legacy source before persistence verification')

# The compatibility report command may be a thin wrapper. Verify both the
# delegation and the effective strict command rather than requiring old inline
# authorization tokens to remain duplicated in the wrapper.
base_report = section(moderation, 'public function create_report', None)
require('create_safety_report_strict' in base_report, 'Round 09 base report route no longer delegates to the strict safety-report command')
strict_report = section(report_appeals, 'public function create_safety_report_strict', 'public function request_report_appeal_strict')
for token in ('SPD_Membership_Adapter::health()', 'spd_membership_provider_unavailable', 'spd_membership_claim_unavailable', 'spd_account_ineligible'):
    require(token in strict_report, f'Round 09 effective strict report authorization invariant missing: {token}')

safety_report = section(repo, 'public function create_safety_report', 'public function request_report_appeal')
for token in ('SPD_Membership_Adapter::health()', 'spd_membership_provider_unavailable', 'spd_membership_claim_unavailable', 'spd_account_ineligible'):
    require(token in safety_report, f'Round 15 safety report authorization invariant missing: {token}')

appeal = section(repo, 'public function request_report_appeal', 'public function future_idempotency_begin')
for token in ('SPD_Membership_Adapter::health()', 'spd_membership_provider_unavailable', 'spd_membership_claim_unavailable', 'spd_account_ineligible'):
    require(token in appeal, f'Round 16 appeal authorization invariant missing: {token}')

for key in ('_spd_approved_projection_snapshot_v2', '_spd_profile_visibility', '_spd_public_contact', '_spd_v1_migrated'):
    require(key in uninstall, f'Round 12 exact File03 usermeta purge key missing: {key}')
require("SPD_ALLOW_DESTRUCTIVE_UNINSTALL" in uninstall and "spd_purge_on_uninstall" in uninstall, 'Round 12 destructive two-gate uninstall protection regressed')

founder = section(frontend, 'public function founder', 'public function profile_router')
require('find_by_user_id( $founder_id, false )' in founder, 'Round 17 public Founder rendering can still ensure/create on read')
require('find_by_user_id( $founder_id )' not in founder, 'Round 17 ambiguous ensure-on-read Founder lookup remains')

health = section(observability, 'public static function health_report', 'private static function provider_health')
require("'active_errors'" in health and 'redacted_error_record' in health, 'Round 18 active redacted worker errors are missing from System Check')
for option in ('spd_last_outbox_error', 'spd_last_media_queue_error', 'spd_last_retention_error', 'spd_last_migration_error', 'spd_last_migration_integrity_error'):
    require(option in health, f'Round 18 health report misses worker error evidence: {option}')

branch = 'audit/file-03-second-twenty-round-20260812'
require(branch in fresh, 'Round 19 Fresh Eighty push coverage is missing')
require(branch in future_workflow, 'Round 19 Future Superset push coverage is missing')
require("clear_queue_error_family( 'deletion' )" in text('tests/eighth-ten-round-review.py'), 'Round 19 historical QA assertion regressed to unsafe global media-error clearing')

defect_rounds = '01, 02, 05, 06, 07, 09, 12, 15, 16, 17, 18, 19, 20'
clean_rounds = '03, 04, 08, 10, 11, 13, 14'
require('Defect-bearing rounds' in ledger and defect_rounds in ledger, 'Second twenty-round defect-round ledger drifted')
require('Clean rounds' in ledger and clean_rounds in ledger, 'Second twenty-round clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing from second twenty-round ledger')

print('Second fresh twenty-round sequential corrective invariants passed.')
