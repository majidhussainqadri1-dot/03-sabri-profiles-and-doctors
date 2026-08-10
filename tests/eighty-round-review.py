#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
ledger = (root / 'EIGHTY-ROUND-REVIEW.md').read_text(encoding='utf-8')
rest = (root / 'includes/class-spd-future-rest.php').read_text(encoding='utf-8')
helpers = (root / 'includes/class-spd-helpers.php').read_text(encoding='utf-8')
privacy = (root / 'includes/class-spd-future-privacy.php').read_text(encoding='utf-8')
js = (root / 'assets/js/future-profiles.js').read_text(encoding='utf-8')

rounds = re.findall(r'^\|\s*(\d{2})\s*\|', ledger, flags=re.M)
assert rounds == [f'{i:02d}' for i in range(1, 81)], f'expected exactly rounds 01..80, got {rounds}'

expected_defect_rounds = {'12','13','15','16','23','33','43','44','46','51','52','57','63'}
actual_defect_rounds = set(re.findall(r'^\|\s*(\d{2})\s*\|.*\*\*Defect found → fixed\*\*', ledger, flags=re.M))
assert actual_defect_rounds == expected_defect_rounds, (actual_defect_rounds, expected_defect_rounds)

# Atomicity: future mutation callback + idempotency completion must be inside SPD_DB transaction.
assert 'SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback )' in rest
transaction_slice = rest[rest.index('SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback )'):]
assert '$value = $callback();' in transaction_slice
assert 'future_idempotency_complete( $actor, $command, $key, $value )' in transaction_slice
assert 'future_idempotency_fail( $actor, $command, $key )' in rest

# Temporary disclosure must never be publicly cached.
assert "public function disclosure( WP_REST_Request $r ) { return $this->response( SPD_Future_Profile::disclosure_packet( $r['token'] ), 200, false ); }" in rest

# Explicit federation consent and complete transport endpoints.
assert 'spd_federation_owner_opt_in_required' in rest
assert "empty( $out['inbox'] ) || empty( $out['outbox'] )" in rest
assert "permission_callback' => array( $this, 'future_state_actor' )" in rest

# Unknown future-state fields are fail-closed.
assert 'spd_unknown_future_state_field' in rest
assert "array( 'professional_lifecycle', 'lifecycle_reason', 'federation_opt_in' )" in rest

# Locale input must be validated instead of silently falling back into en-US.
assert 'public static function valid_locale' in helpers
assert "if ( ! SPD_Helpers::valid_locale( $p['locale'] ?? '' ) )" in rest

# Grounded AI must block medical-scope requests and require actual evidence.
assert 'medical_scope_question' in rest
for marker in ['potency', 'کون سی دوا', 'spd_ai_grounding_incomplete']:
    assert marker in rest
assert "'' === $answer || ! $citations" in rest

# Browser retries must retain a key for unchanged payload and clear only after success.
for marker in ['formMutationKey', 'spdIdempotencyPayload', 'idempotencyKey: formMutationKey(form, body)', 'clearMutationKey(form)']:
    assert marker in js
assert "options.idempotencyKey" in js

# Future privacy export must surface SQL read failures.
assert privacy.count('spd_future_privacy_export_failed') >= 3
assert 'if ( is_wp_error( $data ) ) { return $data; }' in privacy
assert privacy.count('$wpdb->last_error = \'\';') >= 3

# Status honesty remains explicit in review evidence.
assert 'Exact deployed code is still unverified' in ledger
assert 'does **not** establish Hostinger staging acceptance' in ledger

print('File 03 fresh 80-round corrective review invariants: PASS')
