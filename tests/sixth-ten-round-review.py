#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(condition, message):
    if not condition:
        raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
central_privacy = text('includes/class-spd-central-privacy.php')
future_privacy = text('includes/class-spd-future-privacy.php')
repo = text('includes/class-spd-profile-repository.php')
schema = text('includes/class-spd-schema-guard.php')
auth = text('includes/class-spd-authorization.php')
rest = text('includes/class-spd-rest.php')
central_rest = text('includes/class-spd-central-rest.php')
media = text('includes/class-spd-media.php')
ledger = text('SIXTH-TEN-ROUND-REVIEW-2026-08-11.md')

require('Version: 1.2.0-rc6' in main and "define( 'SPD_VERSION', '1.2.0-rc6' )" in main, 'Sixth-review release identity is not rc6')
require('SIXTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Sixth-review plan marker is missing')
require("SET reviewer_id=0,decision_note=''" in central_privacy, 'Reviewer-authored appeal decision note is not erased with reviewer identity')
require("'tombstoned' !== sanitize_key" in future_privacy and 'Future professional state is retained until the canonical base profile has been tombstoned' in future_privacy, 'Future erasure is not ordered behind canonical base tombstoning')
require('SPD_Schema_Guard::central_ready()' in repo, 'Delegated authority does not require exact central schema')
require('SPD_Membership_Adapter::is_minor( $delegate_id )' in repo, 'Use-time delegated authority does not reject minor delegates')
require("'Seq_in_index'" in schema and "'Non_unique'" in schema and "'columns'" in schema and "'unique'" in schema, 'Schema guard does not verify exact index semantics')
require("self::index( 'owner_delegate', array( 'owner_user_id','delegate_user_id' ), true )" in schema, 'Delegation uniqueness index contract is not exact')
require("self::index( 'report_requester', array( 'report_id','requested_by' ), true )" in schema, 'Appeal uniqueness index contract is not exact')
require('validate_audience_payload' in auth and 'spd_unknown_audience_field' in auth and 'spd_audience_invalid' in auth, 'Strict audience-map validator is missing')
require('SPD_Authorization::validate_audience_payload' in rest, 'Base profile REST does not enforce strict audience-map validation')
require('SPD_Authorization::validate_audience_payload' in central_rest, 'Personal-site REST does not enforce strict audience-map validation')
require('spd_delegate_minor_forbidden' in central_rest, 'Delegation grant does not reject minor delegates')
require("attempts=IF(status='delivered',attempts,0)" in media and "lease_token=IF(status='delivered',lease_token,'')" in media and "last_error_code=IF(status='delivered',last_error_code,'')" in media, 'Renewed media deletion does not receive a fresh bounded retry state')
require('Defect-bearing rounds: **01, 02, 03, 04, 05, 07, 09, 10**' in ledger, 'Sixth-review defect-round ledger drifted')
require('Clean rounds: **06, 08**' in ledger, 'Sixth-review clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing')

print('Sixth fresh ten-round corrective invariants passed.')
