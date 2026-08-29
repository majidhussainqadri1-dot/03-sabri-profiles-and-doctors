#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)

future_rest = text('includes/class-spd-future-rest.php')
frontend = text('includes/trait-spd-frontend-future.php')
future_js = text('assets/js/future-profiles.js')
rest = text('includes/class-spd-rest.php')

# R01 — REST mutation/version/idempotency/concurrency/degraded-state review.
for marker in (
    "array( 'locale', 'headline', 'bio', 'source', 'version' )",
    "array( 'field_key', 'days', 'version' )",
    "'federation_opt_in', 'version'",
    'private function expected_version', 'FOR UPDATE',
    'spd_version_required', 'spd_version_conflict',
):
    require(marker in future_rest, f'R01 future optimistic-concurrency marker missing: {marker}')
require(future_rest.count("$result['version'] = absint( $payload['version'] ) + 1") >= 3,
        'R01 mutation responses do not advance authoritative versions')
for marker in ('data-spd-edition-version', 'data-version=', 'future_state_version'):
    require(marker in frontend, f'R01 owner form version binding missing: {marker}')
for marker in ('editionVersions', "version: Number(editionVersions.get(localeKey) || 0)", 'selected.dataset.version', 'form.dataset.version'):
    require(marker in future_js, f'R01 browser expected-version handling missing: {marker}')
shape = future_rest.index('spd_unknown_ai_question_field')
short = future_rest.index('spd_ai_question_required')
medical = future_rest.index('spd_ai_scope_restricted')
rate = future_rest.index("consume_rate_limit( 'ask_work_")
require(shape < short < medical < rate, 'R01 ask-work invalid requests can consume quota before validation')
require('strlen( $v ) >= 85 && strlen( $v ) <= 2113' in future_rest,
        'R01 disclosure token route is not explicitly bounded')
require('harden_strict_report_permissions' in rest and 'rest_endpoints' in rest and "eligible_member'" in rest,
        'R01 strict report routes are not normalized to health-aware permission checks')

# R02 — DB certainty, operational queues and recovery identifiers.
obs = text('includes/class-spd-observability.php')
media = text('includes/class-spd-media.php')
admin = text('includes/class-spd-admin.php')
for marker in ('outbox_lease_reset_failed','outbox_queue_read_failed','outbox_claim_failed','outbox_claim_read_failed','outbox_delivery_persist_failed','outbox_failure_persist_failed'):
    require(marker in obs, f'R02 outbox DB-certainty marker missing: {marker}')
for marker in ('retention_idempotency_delete_failed','retention_report_anonymize_failed','retention_event_delete_failed','spd_last_retention_error'):
    require(marker in obs, f'R02 retention certainty marker missing: {marker}')
require('spd_outbox_store_unavailable' in obs and 'spd_migration_store_unavailable' in obs, 'R02 operational requeue DB failures are not explicit')
require('spd_media_queue_store_unavailable' in media, 'R02 media requeue DB failure is not explicit')
require('SELECT deletion_uuid,attachment_id' in admin and "$row['deletion_uuid']" in admin and 'requeue_deletion($reference' in admin, 'R02 media dead-letter recovery is not UUID-correct')
require('is_wp_error($ok)' in admin, 'R02 admin recovery does not preserve 503 DB uncertainty')

# R05 — manual migration recovery must never report success before cursor/schedule recovery is certain.
for marker in (
    'SELECT status FROM {$table} WHERE user_id=%d LIMIT 1',
    'spd_migration_cursor_rewind_failed',
    'spd_migration_schedule_failed',
    "$scheduled = wp_schedule_event( time() + 60, 'spd_five_minutes', 'spd_migrate_profiles_batch' )",
):
    require(marker in obs, f'R05 migration recovery fail-closed marker missing: {marker}')
requeue = obs.index('public static function requeue_migration_user')
legacy = obs.index('private function migrate_legacy_projection', requeue)
block = obs[requeue:legacy]
status_read = block.index('SELECT status FROM {$table}')
rewind = block.index('spd_migration_cursor_rewind_failed')
schedule = block.index('spd_migration_schedule_failed')
state_flip = block.index("UPDATE {$table} SET status='retry'")
require(status_read < rewind < schedule < state_flip,
        'R05 migration recovery changes dead state before cursor/schedule certainty')

print('File 03 eighth twenty-round sequential invariants through R05: PASS')
