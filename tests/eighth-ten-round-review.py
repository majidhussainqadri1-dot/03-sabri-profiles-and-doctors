#!/usr/bin/env python3
from pathlib import Path
import json
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
base_privacy = text('includes/class-spd-privacy.php')
central_privacy = text('includes/class-spd-central-privacy.php')
future_privacy = text('includes/class-spd-future-privacy.php')
moderation = text('includes/trait-spd-profile-moderation.php')
repo = text('includes/class-spd-profile-repository.php')
activator = text('includes/class-spd-activator.php')
plugin = text('includes/class-spd-plugin.php')
future_rest = text('includes/class-spd-future-rest.php')
update = text('includes/trait-spd-profile-update.php')
media = text('includes/class-spd-media.php')
ledger = text('EIGHTH-TEN-ROUND-REVIEW-2026-08-11.md')
release_lock = json.loads(text('RELEASE-LOCK.json'))

version = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \)", main)
require(version and int(version.group(1)) >= 8, 'Eighth-review guarantees require rc8 or a later corrective identity')
require('EIGHTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Eighth-review plan marker is missing')

for name, src, end in (
    ('base', base_privacy, None),
    ('central', central_privacy, None),
    ('future', future_privacy, 'public static function export_profile_data'),
):
    erase = section(src, 'public function erase(', end)
    require("absint( $page ) > 1" not in erase, f'{name} privacy eraser can still terminate retries solely from page number')
    require("'done' => ! $retry" in erase or "'done' => empty( $result['retry'] )" in erase or "'done' => false" in erase, f'{name} privacy eraser lacks retry-aware completion evidence')

require("spd_report_store_unavailable" in moderation and "$wpdb->last_error" in moderation and "$count_raw" in moderation, 'Base report moderation/rate reads are not DB-certain')
require("public function create_safety_report" in repo and "spd_report_store_unavailable" in repo and "public function request_report_appeal" in repo, 'Central report/appeal DB-certainty overrides are missing')

for option in ("spd_db_version", "spd_central_schema_version", "spd_future_schema_version"):
    require(f"delete_option( '{option}' )" in activator, f'Activation repair does not invalidate stale {option}')
    require(f"delete_option( '{option}' )" in plugin, f'Boot repair does not invalidate stale {option}')

for code in ('spd_unknown_disclosure_field', 'spd_unknown_translation_field', 'spd_unknown_reconfirm_field'):
    require(code in future_rest, f'Future mutation shape gate is missing: {code}')
require('reject_unknown' in future_rest, 'Shared future mutation unknown-field gate is missing')

require('spd_last_post_commit_reload_error' in update and 'sabri_file24_profile_post_commit_reload_failure' in update, 'Post-commit profile reload uncertainty is not operationally recorded')
require("$updated_profile = $this->find_by_public_id_strict( $profile['public_id'] )" in update and 'return $committed_response;' in update, 'Base update does not preserve committed replay result across post-commit reread failure')

require('$had_error=false' in media and "record_queue_error( 'deletion_lease_lost' )" in media, 'Media deletion anomaly latching regressed')
require("clear_queue_error_family( 'deletion' )" in media, 'Media deletion clean-up is not isolated to deletion-family errors')
require("clear_queue_error_family( 'privacy' )" in media, 'Media privacy clean-up is not isolated to privacy-family errors')

rotate = section(repo, 'public function rotate_share_link', 'public function grant_delegate')
require("'share_url' => SPD_Central_Profile::short_url( $future_profile )" in rotate, 'Share-link rotation does not build the deterministic URL before commit')
require("idempotency_complete( $actor_id, 'rotate_share_link', $idempotency_key, $response )" in rotate, 'Share-link rotation does not store the complete first-response shape')
require('find_by_public_id(' not in rotate and 'find_by_user_id( $actor_id )' in rotate, 'Share-link rotation still depends on a post-commit profile reread')

future_state = section(future_rest, 'public function future_state', None)
require('find_by_public_id_strict' in future_state and 'spd_read_future_profile_state' in future_state, 'Future-state mutation lacks strict profile/state preflight')
require("'professional_lifecycle' => $effective_lifecycle" in future_state and "'lifecycle_reason' => array_key_exists" in future_state and "'federation_opt_in' => array_key_exists" in future_state, 'Future-state mutation does not materialize omitted fields from the strict preflight')

candidate = str(release_lock.get('current_repository_candidate', ''))
locked = re.search(r'^1\.2\.0-rc(\d+)$', candidate)
require(locked and int(locked.group(1)) >= 8, 'Release lock regressed below the eighth-review release boundary')
require('Defect-bearing rounds: **01, 03, 04, 05, 06, 07, 08, 09, 10**' in ledger, 'Eighth-review defect-round ledger drifted')
require('Clean rounds: **02**' in ledger, 'Eighth-review clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing')

print('Eighth fresh ten-round corrective invariants passed.')
