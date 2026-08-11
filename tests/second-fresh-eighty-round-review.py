#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')

ledger = text('SECOND-FRESH-EIGHTY-ROUND-REVIEW-2026-08-11.md')
auth = text('includes/class-spd-authorization.php')
membership = text('includes/class-spd-membership-adapter.php')
helpers = text('includes/class-spd-helpers.php')
identity = text('includes/trait-spd-profile-identity-read.php')
events = text('includes/trait-spd-profile-events.php')
plugin = text('includes/class-spd-plugin.php')
routes = text('includes/class-spd-routes.php')
central_rest = text('includes/class-spd-central-rest.php')
central = text('includes/class-spd-central-profile.php')
provider_guards = text('includes/class-spd-provider-guards.php')
timeline = text('includes/class-spd-timeline.php')
media = text('includes/class-spd-media.php')
privacy = text('includes/class-spd-privacy.php')
future_privacy = text('includes/class-spd-future-privacy.php')
professional = text('includes/trait-spd-profile-professional.php')
profile_update = text('includes/trait-spd-profile-update.php')
central_trait = text('includes/trait-spd-profile-central.php')
bootstrap = text('sabri-profiles-doctors.php')

rounds = re.findall(r'^\|\s*(\d{2})\s*\|', ledger, flags=re.M)
expected_rounds = [f'{i:02d}' for i in range(1, 81)]
assert rounds == expected_rounds, f'expected exactly 01..80, got {rounds}'
expected_defects = {'03','05','07','08','13','16','17','18','20','23','25','28','29','30','32','34','40','41','46','47','49','51','64','65','67','71','75','79'}
actual_defects = set(re.findall(r'^\|\s*(\d{2})\s*\|.*\*\*DEFECT FOUND → FIXED\*\*', ledger, flags=re.M))
assert actual_defects == expected_defects, (actual_defects, expected_defects)

# 03/05/07/08 — current eligibility and field-store failures fail closed.
assert "empty( $claims['eligible'] )" in auth and "! empty( $claims['suspended'] )" in auth
assert "'doctor' === $claims['account_type']" in membership and "! empty( $claims['professional_verified'] )" in membership
assert "_fields_read_failed" in auth and "spd_profile_field_store_unavailable" in auth
assert "_spd_read_error" in identity and "spd_profile_read_failed" in identity

# 13/16/17 — retry recovery, CAS locks and one fail-closed atomic rate limiter.
assert 'IDEMPOTENCY_ABANDONED_SECONDS = 900' in events
assert "'status' => 'started'" in events and "self::IDEMPOTENCY_ABANDONED_SECONDS" in events
assert 'option_name=%s AND option_value=%s' in helpers
assert "acquire_lock( 'rate_' . $bucket" in helpers and 'if ( ! set_transient' in helpers
assert "consume_rate_limit( 'media_upload_' . $user_id" in media
assert 'guard_profile_media_upload_rate' not in plugin

# 20/23/25/30/51/75 — privacy-safe routing/caching and lifecycle uncertainty.
assert 'SPD_Authorization::profile_visibility_allows( $profile, 0 )' in routes
assert "add_action( 'send_headers', array( $this, 'private_headers' )" in routes
assert 'no-store, no-cache, must-revalidate, max-age=0' in routes
assert 'no-store, no-cache, must-revalidate, max-age=0' in central_rest
assert "add_filter( 'rest_post_dispatch', array( $this, 'rest_no_store' )" in plugin
assert 'function spd_read_future_profile_state' in bootstrap
assert "'state_degraded'" in bootstrap and "'active_professional' => false" in bootstrap
assert 'return $state;' in bootstrap and "professional_lifecycle" in bootstrap

# 28/29/34 — cross-file facts are exact-object bound and malformed claims hide.
assert "absint( $claim['doctor_user_id'] ?? 0 ) !== $user_id" in central
assert "empty( $claim['owner_version'] )" in central
for marker in ('sabri_file09_verifiable_credentials_v1','sabri_file16_grounded_profile_ask_v1','sabri_file17_profile_contact_relay_v1','sabri_file26_profile_analytics_projection_v1'):
    assert marker in provider_guards, marker
assert "array( 'user_id', 'doctor_user_id', 'owner_user_id', 'profile_user_id' )" in provider_guards
assert 'return $found ? $claim : array();' in provider_guards

# 32 — timeline cursors cannot silently restart or cross profile/filter context.
assert "hash_hmac( 'sha256', $body" in timeline
assert "'p' => $public_id" in timeline and "'f' => $filter" in timeline
assert 'spd_timeline_cursor_invalid' in timeline
assert 'return $cursor;' in timeline

# 40 — external privacy/eligibility state is reconciled with media storage.
assert 'reconcile_storage_privacy' in media
assert 'removed_for_privacy' in media and 'spd_media_privacy_cursor' in media

# 41/46/47/49 — professional/delegation/locale write safety.
assert 'spd_professional_supersession_failed' in professional
assert "SPD_Verification_Adapter::is_verified( $owner_id )" in central_trait
assert 'spd_delegate_expiry_invalid' in central_trait and 'spd_delegate_expired' in central_trait
assert "if ( array_key_exists( 'locale', $input ) && ! SPD_Helpers::valid_locale" in profile_update

# 64/65 — SQL read failure must never become a completed empty privacy export.
assert privacy.count("$wpdb->last_error = '';") >= 3
assert 'spd_privacy_export_failed' in privacy
assert 'profile_for_privacy' in future_privacy
assert 'spd_future_privacy_profile_read_failed' in future_privacy
assert future_privacy.count('spd_future_privacy_export_failed') >= 3

# 67/71 — retention truthfulness and migration serialization.
assert "remove_action( 'spd_retention_cleanup'" in plugin and 'run_retention_cleanup' in plugin
assert 'spd_last_retention_error' in plugin and 'sabri_file24_retention_failure' in plugin
assert "acquire_lock( 'migration_batch'" in plugin and 'run_migration_batch' in plugin

# 79/80 — independent ledger, CI gate and truthful external status boundary.
assert '28 defect-bearing rounds / 52 clean rounds' in ledger
assert 'Exact deployed code remains unverified' in ledger
assert 'does not establish Hostinger staging acceptance' in ledger

print('File 03 second fresh 80-round corrective invariants: PASS')
