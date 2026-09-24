#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
main = (ROOT / 'sabri-profiles-doctors.php').read_text(encoding='utf-8')
contracts = (ROOT / 'includes/class-spd-contracts.php').read_text(encoding='utf-8')
timeline = (ROOT / 'includes/class-spd-timeline.php').read_text(encoding='utf-8')
trace = (ROOT / 'LATEST-PLAN-TRACEABILITY.md').read_text(encoding='utf-8')
status = (ROOT / 'STATUS.md').read_text(encoding='utf-8')
readme = (ROOT / 'readme.txt').read_text(encoding='utf-8')
lock = json.loads((ROOT / 'RELEASE-LOCK.json').read_text(encoding='utf-8'))

def require(ok, message):
    if not ok:
        raise SystemExit(message)

require("Version: 1.2.0-rc17" in main, "rc17 plugin header missing")
require("define( 'SPD_VERSION', '1.2.0-rc17' );" in main, "rc17 runtime constant missing")
require("Stable tag: 1.2.0-rc17" in readme, "rc17 stable tag missing")
require(lock.get('current_repository_candidate') == '1.2.0-rc17', "release lock not bound to rc17")
require("Candidate: `1.2.0-rc17`" in trace, "latest-plan trace still has stale candidate identity")

require("define( 'SPD_DB_VERSION', '1.2.0' );" in main, "DB schema drifted")
require("define( 'SPD_CONTRACT_VERSION', '1.4.0' );" in main, "public contract drifted")

for token in (
    'sabri_file21_profile_timeline_provider_health_v1',
    'sabri_file21_profile_timeline_items_v1',
    'PROVIDER_CONTRACT_MIN',
    'SPD_Authorization::audience_allows',
):
    require(token in timeline, 'File 21 consumer invariant missing: ' + token)

for token in (
    "function_exists( 'sabri_file26_register_connector' )",
    'sabri_file26_register_connector( self::file26_connector_manifest() )',
    "'slug'               => 'file03-profiles'",
    "'entity_types'       => array( 'founder', 'doctor', 'member_profile' )",
    "'deletion_semantics' => 'versioned_tombstone'",
    "'status'             => 'proposed'",
    "'list_batch'         => array( __CLASS__, 'file26_list_batch' )",
    "'can_view'           => array( __CLASS__, 'file26_can_view' )",
    "'health'             => array( __CLASS__, 'file26_health' )",
    "'index_schema'       => 'sabri.file26.document.v1.1'",
):
    require(token in contracts, 'File 26 connector invariant missing: ' + token)

require("SPD_DB::table( 'profiles' )" in contracts, "File 26 rebuild is not rooted in File 03-owned profile storage")
require('spd_get_search_projection' in contracts, "File 26 connector does not consume current File 03 public projection")
for token in ("'quality_score'         => 0", "'authority_score'       => 0", "'popularity_score'      => 0"):
    require(token in contracts, "File 03 ranking-neutral projection invariant missing: " + token)
require("'visibility'     => 'restricted'" in contracts, "restricted/private profiles lack tombstone envelope")
require("hash_equals( (string) $projection['canonical_id'], (string) $document['object_id'] )" in contracts, "click-time owner revalidation missing")
require('sabri_file26_register_profile_provider' in contracts, "legacy File 26 compatibility signal unexpectedly removed")
require("spd_get_grounded_profile_work_context" in main, "File 16 grounding context is missing")
require("sabri_file16_register_grounded_profile_context_provider', 'file03', 'spd_get_grounded_profile_work_context" in contracts, "File 16 registration still points at a recursive/full Future projection")
require("SPD_Profile_Repository::instance()->public_dto( $identity, 0 )" in main, "Grounded profile context is not restricted to anonymous-public profile data")
require("SPD_Timeline::query( $public_id, array( 'limit' => 12 ), 0 )" in main, "Grounded profile work is not restricted to anonymous-public timeline evidence")
require('no local search-ranking fallback' in contracts, "canonical File 26 ranking ownership guard missing")

require(lock.get('production_authorized') is False, "repository correction improperly authorizes production")
require(lock.get('staging_authorized') is False, "repository correction improperly authorizes staging")
require(lock.get('deployed_version_verified') is False, "repository correction improperly claims deployed parity")
require('Exact deployed code remains unverified' in status, "live-truth boundary missing from status")

print('File 03 rc17 exact cross-file contract completion invariants passed.')
