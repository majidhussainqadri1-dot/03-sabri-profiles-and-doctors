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

print('File 03 eighth twenty-round sequential invariants through R01: PASS')
