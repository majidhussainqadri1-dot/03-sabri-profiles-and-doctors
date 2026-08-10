#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')

ledger = text('FRESH-EIGHTY-ROUND-REVIEW-2026-08-10.md')
rest = text('includes/class-spd-future-rest.php')
future = text('includes/class-spd-future-profile.php')
helpers = text('includes/class-spd-helpers.php')
privacy = text('includes/class-spd-future-privacy.php')
js = text('assets/js/future-profiles.js')

rounds = re.findall(r'^\|\s*(\d{2})\s*\|', ledger, flags=re.M)
expected_rounds = [f'{i:02d}' for i in range(1, 81)]
assert rounds == expected_rounds, f'expected exactly rounds 01..80, got {rounds}'

expected_defects = {'15','16','23','33','44','51','52','57','63','78'}
actual_defects = set(re.findall(r'^\|\s*(\d{2})\s*\|.*\*\*DEFECT FOUND → FIXED\*\*', ledger, flags=re.M))
assert actual_defects == expected_defects, (actual_defects, expected_defects)

# Preserve the already-correct exact-main atomicity fix.
assert 'SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback )' in rest
assert 'future_idempotency_complete( $actor, $command, $key, $mutation )' in rest
assert 'future_idempotency_fail( $actor, $command, $key )' in rest

# Browser retries use one key per unchanged payload and rotate after edits.
for marker in ('formMutationKey', 'spdIdempotencyPayload', 'clearMutationKey(form)', 'options.idempotencyKey'):
    assert marker in js, marker
assert js.count('idempotencyKey: formMutationKey(form, body)') >= 4
assert 'window.crypto.getRandomValues' in js

# Selective disclosure remains signed/revocable but is never shared-cacheable.
assert 'DISCLOSURE_MAX_TTL = 86400' in future
assert 'hash_hmac' in future and 'hash_equals' in future and 'share_epoch' in future
assert 'return $this->response( $packet, 200, false );' in rest

# Invalid locale input must fail closed at REST.
assert 'public static function valid_locale' in helpers
assert "if ( ! SPD_Helpers::valid_locale( $p['locale'] ?? '' ) )" in rest
assert 'spd_translation_locale_invalid' in rest

# Federation opt-in is explicit profile-owner consent; transport remains external and complete.
assert 'spd_federation_owner_opt_in_required' in rest
assert "'transport_owner' => 'external'" in future
assert "! empty( $out['inbox'] ) && ! empty( $out['outbox'] )" in future

# AI safety retains throttling and adds defense-in-depth medical/grounding rejection.
assert "consume_rate_limit( 'ask_work_' . $viewer" in rest
assert 'medical_scope_question' in rest
for marker in ('potency', 'کون سی دوا', 'spd_ai_grounding_incomplete'):
    assert marker in rest, marker
assert "'' === $answer || ! $citations" in rest
assert 'SPD_Helpers::same_origin_url' in future

# State mutation rejects unsupported input keys.
assert 'spd_unknown_future_state_field' in rest
assert "array( 'professional_lifecycle', 'lifecycle_reason', 'federation_opt_in' )" in rest

# Privacy export must not transform SQL read failures into a false empty-success.
assert 'if ( is_wp_error( $data ) ) { return $data; }' in privacy
assert privacy.count('spd_future_privacy_export_failed') >= 3
assert privacy.count("$wpdb->last_error = '';") >= 3

# Truth-status boundary is part of the deterministic audit evidence.
assert 'Exact deployed code remains unverified' in ledger
assert 'does not establish Hostinger staging acceptance' in ledger

print('File 03 fresh exact-main 80-round corrective invariants: PASS')
