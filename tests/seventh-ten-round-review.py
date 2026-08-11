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
central_profile = text('includes/class-spd-central-profile.php')
repo = text('includes/class-spd-profile-repository.php')
db = text('includes/class-spd-db.php')
outbox = text('includes/class-spd-outbox-dispatcher.php')
ledger = text('SEVENTH-TEN-ROUND-REVIEW-2026-08-11.md')

require('Version: 1.2.0-rc7' in main and "define( 'SPD_VERSION', '1.2.0-rc7' )" in main, 'Seventh-review release identity is not rc7')
require('SEVENTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Seventh-review plan marker is missing')

require('$requester_count_error' in central_privacy and '$reviewer_count_error' in central_privacy and "'' !== $requester_count_error || '' !== $reviewer_count_error" in central_privacy, 'Appeal erasure does not preserve independent DB-read uncertainty')
require('return SPD_Schema_Guard::central_ready();' in central_profile, 'Central schema readiness is not bound to the exact schema guard')
require('central_grant_delegate' in repo and 'spd_delegate_minor_forbidden' in repo and 'SPD_Membership_Adapter::is_minor( $delegate_id )' in repo, 'Repository-level minor delegate denial is missing')

shape_check = "! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::base_ready()"
require(shape_check in db, 'Base install does not require exact schema readiness')
require(db.find(shape_check) < db.find("update_option( 'spd_db_version'"), 'DB version can be recorded before exact schema verification')

require('base_update_profile' in repo and 'central_update_profile' in repo, 'Repository mutation wrappers are missing')
require('SPD_Authorization::validate_audience_payload( $input[\'audiences\'], self::visibility_fields() )' in repo, 'Base repository update does not strictly validate audience maps')
require('SPD_Authorization::validate_audience_payload( $input[\'audiences\'], SPD_Central_Profile::extended_fields() )' in repo, 'Personal-site repository update does not strictly validate audience maps')
require('find_by_public_id_strict' in repo and 'find_by_slug_strict' in repo and 'spd_slug_lookup_failed' in repo, 'Strict mutation-sensitive identity lookups are missing')
require('$repo->find_by_public_id_strict' in text('includes/class-spd-rest.php'), 'Protected base REST preflight does not use the strict public-id lookup')

require('class SPD_Outbox_Dispatcher' in outbox and 'outbox_delivery_persist_failed' in outbox and 'outbox_failure_persist_failed' in outbox and "array( 'id' => $id, 'lease_token' => $token )" in outbox, 'Fail-closed outbox result persistence is incomplete')
require("'class-spd-outbox-dispatcher.php'" in main and 'SPD_Outbox_Dispatcher::replace_legacy_hook();' in main, 'Fail-closed outbox dispatcher is not activated')
require("find_by_public_id_strict( $dto['public_id'] )" in main and "find_by_public_id_strict( (string) ( $out['canonical_id'] ?? '' ) )" in main, 'Lifecycle-sensitive public projections do not fail closed on profile reread uncertainty')

require('Defect-bearing rounds: **01, 02, 03, 04, 05, 06, 08, 09, 10**' in ledger, 'Seventh-review defect-round ledger drifted')
require('Clean rounds: **07**' in ledger, 'Seventh-review clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing')

print('Seventh fresh ten-round corrective invariants passed.')
