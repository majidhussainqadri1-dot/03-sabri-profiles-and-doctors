#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(condition, message):
    if not condition:
        raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
plugin = text('includes/class-spd-plugin.php')
central_rest = text('includes/class-spd-central-rest.php')
central_privacy = text('includes/class-spd-central-privacy.php')
future_privacy = text('includes/class-spd-future-privacy.php')
uninstall = text('uninstall.php')
ledger = text('FIFTH-TEN-ROUND-REVIEW-2026-08-11.md')

require("Version: 1.2.0-rc5" in main and "'1.2.0-rc5'" in main, 'Fifth-review release identity is not rc5')
require('FIFTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Fifth-review plan marker is missing')
require("SPD_Schema_Guard::future_ready()" in main and "spd_read_future_profile_state" in main, 'Safe future-state read does not require exact future schema')
require('return spd_get_search_projection( $identity );' in plugin, 'File 26 adapter bypasses canonical safe search projection')
require("spd_get_search_projection( $r['public_id'] )" in central_rest, 'REST search projection bypasses canonical safe helper')
require('SPD_Schema_Guard::central_ready()' in central_privacy, 'Central privacy does not require exact schema shape')
require("$is_requester" in central_privacy and "Relationship role" in central_privacy, 'Appeal privacy export is not relationship-aware')
require("if ( $is_requester ) { $appeal_data[] = array( 'name' => 'Reason'" in central_privacy, 'Requester appeal reason is not minimized to requester export')
require('SPD_Schema_Guard::future_ready()' in future_privacy, 'Future privacy does not require exact schema shape')
require('SPD_Future_Profile::schema_ready()' not in future_privacy, 'Weak future table-only privacy guard returned')
require("'_spd_media_owner_user_id'" in uninstall and "'_spd_media_purpose'" in uninstall and "in_array( $purpose, array( 'avatar', 'cover' ), true )" in uninstall, 'Destructive uninstall lacks marker-bound orphan media recovery')
require('Defect-bearing rounds: **01, 02, 03, 04, 05, 09, 10**' in ledger, 'Fifth-review defect-round ledger drifted')
require('Clean rounds: **06, 07, 08**' in ledger, 'Fifth-review clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing')

print('Fifth fresh ten-round corrective invariants passed.')
