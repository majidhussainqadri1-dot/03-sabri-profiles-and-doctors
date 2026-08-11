#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(condition, message):
    if not condition:
        raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
plugin = text('includes/class-spd-plugin.php')
professional = text('includes/trait-spd-profile-professional.php')
edit_model = text('includes/trait-spd-profile-edit-model.php')
slug_privacy = text('includes/class-spd-slug-privacy.php')
lifecycle = text('includes/trait-spd-profile-lifecycle.php')
outbox = text('includes/class-spd-outbox-dispatcher.php')
uninstall = text('uninstall.php')
ledger = text('NINTH-TEN-ROUND-REVIEW-2026-08-11.md')

version = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \)", main)
require(version and int(version.group(1)) >= 9, 'Ninth-review guarantees require rc9 or a later corrective identity')
require('NINTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Ninth-review plan marker is missing')
require("'class-spd-slug-privacy.php'" in main, 'Permanent slug-history privacy exporter is not loaded')

require('spd_professional_submission_read_failed' in professional and '$wpdb->last_error' in professional, 'Professional submission state read is not DB-certain')
require('is_wp_error( $professional_submission )' in edit_model and 'return $professional_submission' in edit_model, 'Edit model does not propagate professional-state read failure')

require('sabri-profile-slug-history' in slug_privacy, 'Slug-history privacy exporter registration is missing')
require("SPD_DB::table( 'slugs' )" in slug_privacy and '$wpdb->last_error' in slug_privacy, 'Slug-history export is not DB-certain')
require('Permanent redirect/citation integrity' in slug_privacy, 'Slug-history retention purpose is not disclosed in export')
require('( new SPD_Slug_Privacy() )->hooks();' not in slug_privacy, 'Slug privacy self-registers during source include instead of plugin boot')
require('( new SPD_Slug_Privacy() )->hooks();' in plugin, 'Slug privacy is not registered by the plugin boot lifecycle')
require('historical profile slug aliases are retained' in lifecycle, 'Erasure receipt does not disclose permanent slug-history retention')

require('$had_error = false;' in outbox, 'Outbox worker lacks run-level anomaly latch')
require("$had_error = true; self::record_error( 'outbox_claim_lost' )" in outbox, 'Outbox claim-loss evidence is not latched')
require("$had_error = true; self::record_error( 'outbox_delivery_lease_lost' )" in outbox, 'Outbox delivery lease-loss evidence is not latched')
require("if ( ! $had_error ) { delete_option( 'spd_last_outbox_error' ); }" in outbox, 'Outbox errors can still be cleared by an anomalous run')
require("'spd_last_outbox_error'" in uninstall, 'Destructive uninstall leaves File 03 outbox error evidence behind')

require('Defect-bearing rounds: **01, 03, 08, 09, 10**' in ledger, 'Ninth-review defect-bearing round ledger drifted')
require('Clean rounds: **02, 04, 05, 06, 07**' in ledger, 'Ninth-review clean round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing from ninth-review ledger')

print('Ninth fresh ten-round corrective invariants passed.')
