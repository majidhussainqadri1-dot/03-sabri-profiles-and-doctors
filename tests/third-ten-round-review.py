#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')

ledger = text('THIRD-TEN-ROUND-REVIEW-2026-08-11.md')
bootstrap = text('sabri-profiles-doctors.php')
future_rest = text('includes/class-spd-future-rest.php')
auth = text('includes/class-spd-authorization.php')
repo = text('includes/class-spd-profile-repository.php')
events = text('includes/trait-spd-profile-events.php')
media = text('includes/class-spd-media.php')
lifecycle = text('includes/trait-spd-profile-lifecycle.php')
privacy = text('includes/class-spd-privacy.php')
future_privacy = text('includes/class-spd-future-privacy.php')

rounds = re.findall(r'^\|\s*(\d{2})\s*\|', ledger, flags=re.M)
assert rounds == [f'{i:02d}' for i in range(1, 11)], rounds
expected_defects = {'01','02','03','04','06','07','08','09','10'}
actual_defects = set(re.findall(r'^\|\s*(\d{2})\s*\|\s*\*\*DEFECT FOUND → FIXED\*\*', ledger, flags=re.M))
assert actual_defects == expected_defects, (actual_defects, expected_defects)
assert re.search(r'^\|\s*05\s*\|\s*\*\*CLEAN\*\*', ledger, flags=re.M)

# R01/R10 historical release evidence remains frozen in the third-review ledger;
# current release identity is deliberately owned by the newest review gate.
assert 'Starting plugin identity: `1.2.0-rc2`' in ledger
assert 'source identity to `1.2.0-rc3`' in ledger
assert 'THIRD-TEN-ROUND-CORRECTIVE-REVIEW' in bootstrap
assert 'Exact deployed code' in ledger and 'unverified' in ledger.lower()

# R02 — revocation-sensitive future REST and all future mutations fail closed.
assert 'no-store, no-cache, must-revalidate, max-age=0' in future_rest
assert 'max-age=60' not in future_rest
assert 'SPD_Observability::safe_mode()' in future_rest
assert 'spd_read_future_profile_state' in future_rest

# R03 — owner/guardian/delegated writes are bound to native state and current authority.
assert 'function profile_mutation_state_allows' in auth
for state in ('incomplete', 'active', 'limited'):
    assert f"'{state}'" in auth
assert 'spd_profile_state_locked' in auth
assert 'function delegate_can_manage' in repo
assert 'SPD_Authorization::profile_mutation_state_allows' in repo
assert 'SPD_Verification_Adapter::is_verified' in repo
assert '$wpdb->last_error' in repo

# R04 — idempotency finalize/fail is bound to the exact reservation, not merely key+status.
assert 'spd_idempotency_reservations' in events
assert 'idempotency_reservation_marker' in events
assert "'reservation_token'" in events
assert "'response_json' => $this->idempotency_reservation_marker( $token )" in events
assert 'request_hash=%s AND updated_at=%s' in events

# R06 — required media ownership and scan evidence cannot fail open after upload.
assert 'add_post_meta( $attachment_id, $meta_key, $meta_value, true )' in media
assert 'spd_media_metadata_persist_failed' in media
assert 'wp_delete_attachment( $attachment_id, true )' in media

# R07 — one fail-closed state read is authoritative after augmentation and before FHIR output.
augment_pos = bootstrap.index('SPD_Future_Profile::augment_personal_site_dto')
state_pos = bootstrap.index('spd_read_future_profile_state', augment_pos)
fhir_pos = bootstrap.index("$dto['future']['fhir'] = SPD_Future_Profile::fhir_projection", state_pos)
assert augment_pos < state_pos < fhir_pos
assert "'state_degraded'" in bootstrap
assert "'active_professional' => false" in bootstrap
assert "unset( $dto['clinic']['appointment_url'] )" in bootstrap

# R08 — privacy SQL/schema uncertainty is retry/error, never empty success.
assert 'Profile data could not be read safely for erasure' in lifecycle
assert 'Profile-report data could not be read safely for erasure' in privacy
assert 'Professional-profile proposal data could not be read safely for erasure' in privacy
assert 'spd_future_privacy_schema_unavailable' in future_privacy
assert "'retry' => true" in future_privacy

# R09 — migration completion is independently re-proved after the legacy batch.
assert 'function spd_migration_integrity_guard' in bootstrap
assert "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID>%d" in bootstrap
assert "status='retry'" in bootstrap and "status='dead'" in bootstrap
assert "delete_option( 'spd_migration_completed_at' )" in bootstrap
assert "add_action( 'spd_migrate_profiles_batch', 'spd_migration_integrity_guard', 99 )" in bootstrap

print('File 03 third ten-round corrective invariants: PASS')
