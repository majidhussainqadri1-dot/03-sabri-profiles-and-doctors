#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(condition, message):
    if not condition:
        raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
central_rest = text('includes/class-spd-central-rest.php')
public_dto = text('includes/trait-spd-profile-public-dto.php')
privacy = text('includes/class-spd-privacy.php')
plugin = text('includes/class-spd-plugin.php')
media = text('includes/class-spd-media.php')
identity = text('includes/trait-spd-profile-identity-create.php')
future_rest = text('includes/class-spd-future-rest.php')
outbox = text('includes/class-spd-outbox-dispatcher.php')
uninstall = text('uninstall.php')
ledger = text('TENTH-TEN-ROUND-REVIEW-2026-08-12.md')

require('Version: 1.2.0-rc10' in main and "define( 'SPD_VERSION', '1.2.0-rc10' )" in main, 'Tenth-review release identity is not rc10')
require('TENTH-TEN-ROUND-CORRECTIVE-REVIEW' in main, 'Tenth-review plan marker is missing')
require("define( 'SPD_DB_VERSION', '1.2.0' )" in main and "define( 'SPD_CONTRACT_VERSION', '1.4.0' )" in main, 'DB/contract identity drifted during non-DDL tenth review')

require('profile_store_certain' in central_rest and 'spd_profile_store_unavailable' in central_rest and '$wpdb->last_error' in central_rest, 'Central personal-site REST store uncertainty is not distinguished from not-found')
require('spd_profile_store_unavailable' in public_dto and "! empty( $profile['_fields_read_failed'] )" in public_dto and '$wpdb->last_error' in public_dto, 'Public DTO does not fail closed on row/field-store uncertainty')

require('Profile field: ' in privacy and 'editable_fields()' in privacy and 'visibility_fields()' in privacy, 'Privacy export does not include File03-owned profile field values')
require("! empty( $profile['_fields_read_failed'] )" in privacy and 'spd_privacy_export_failed' in privacy, 'Privacy export field hydration is not DB-certain')

require('SPD_Schema_Guard::base_ready()' in plugin and 'migration_integrity_guard' in plugin, 'Migration wrapper lacks exact-schema/post-run integrity gate')
require('migration_integrity_read_failed' in plugin and 'spd_last_migration_error' in plugin and "status='retry'" in plugin and "status='dead'" in plugin, 'Migration completion truth does not account for DB uncertainty/retry/dead state')

require('media_privacy_profile_read_failed' in media and "! empty( $profile['_fields_read_failed'] )" in media, 'Media privacy reconciliation does not stop on uncertain profile/field reads')
require("update_option( 'spd_media_privacy_cursor'" in media, 'Media privacy reconciliation cursor persistence is missing')

require('spd_profile_create_read_failed' in identity and 'spd_profile_refresh_read_failed' in identity, 'Identity post-commit DB-certain read errors are missing')
require("$final = $this->find_by_id" in identity and "! empty( $final['_fields_read_failed'] )" in identity, 'Identity refresh does not use one validated final hydrated read')

require('private function owner_profile()' in future_rest and 'spd_profile_store_unavailable' in future_rest, 'Future owner-only profile lookup is not DB-certain')
require('$profile = $this->owner_profile();' in future_rest, 'Future disclosure/translation/reconfirm do not use the DB-certain owner lookup')

require("self::record_error( 'outbox_invalid_payload' )" in outbox and "self::record_error( 'outbox_delivery_failed' )" in outbox, 'Outbox payload/consumer failures are not latched as operational evidence')
require("if ( ! $had_error ) { delete_option( 'spd_last_outbox_error' ); }" in outbox, 'Outbox error evidence can be cleared by an anomalous run')

require("'spd_last_migration_error'" in uninstall and 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' in uninstall and 'spd_purge_on_uninstall' in uninstall, 'Destructive uninstall does not purge current File03 migration error evidence behind both gates')

require('Defect-bearing rounds: 01–10' in ledger, 'Tenth-review defect-bearing round ledger drifted')
require('Clean rounds: none' in ledger, 'Tenth-review clean-round ledger drifted')
require('Exact deployed code remains unverified' in ledger, 'Live/deployed truth boundary is missing from tenth-review ledger')

print('Tenth fresh ten-round corrective invariants passed.')
