#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
media = (ROOT / 'includes/class-spd-media.php').read_text(encoding='utf-8')
update = (ROOT / 'includes/trait-spd-profile-update.php').read_text(encoding='utf-8')
events = (ROOT / 'includes/trait-spd-profile-events.php').read_text(encoding='utf-8')
verification = (ROOT / 'includes/class-spd-verification-adapter.php').read_text(encoding='utf-8')
central = (ROOT / 'includes/class-spd-central-profile.php').read_text(encoding='utf-8')
public_dto = (ROOT / 'includes/trait-spd-profile-public-dto.php').read_text(encoding='utf-8')
repo = (ROOT / 'includes/class-spd-profile-repository.php').read_text(encoding='utf-8')

def require(ok, message):
    if not ok:
        raise SystemExit(message)

def section(src, start, end=None):
    i = src.find(start)
    require(i >= 0, f'Missing section: {start}')
    j = src.find(end, i + len(start)) if end else len(src)
    if end:
        require(j >= 0, f'Missing section end: {end}')
    return src[i:j]

# R06 — external scanner exceptions are fail-closed before attachment creation.
scan_section = section(media, 'public static function prepare_upload', 'private static function valid_clean_scan')
scan = "apply_filters('spd_profile_media_scan_v1'"
require(scan in scan_section, 'R06 media scan provider call missing')
require('try {' in scan_section and 'catch ( Throwable $exception )' in scan_section, 'R06 media scanner Throwable containment missing')
require(scan_section.find('try {') < scan_section.find(scan) < scan_section.find('catch ( Throwable $exception )'), 'R06 scanner call is outside guarded provider boundary')
require("'spd_scan_unavailable'" in scan_section and "'status'=>503" in scan_section, 'R06 scanner exception is not mapped to fail-closed 503')
require("'provider' => 'profile_media_scan'" in scan_section and "'surface' => 'media_upload_scan'" in scan_section, 'R06 scanner exception observability evidence missing')
require('sabri_file24_profile_provider_failure' in scan_section, 'R06 scanner failure is not surfaced to File 24 assurance')
require(scan_section.find('catch ( Throwable $exception )') < scan_section.find('media_handle_upload'), 'R06 attachment creation can occur before scanner exception is contained')
require('valid_clean_scan' in scan_section, 'R06 stale/malformed/dirty scan validation lost')

# R07 — retry fingerprint is logical rather than tied to retry-created IDs.
fingerprint_start = update.find('private function prepared_media_hashes')
require(fingerprint_start >= 0, 'R07 logical prepared-media fingerprint helper missing')
fingerprint = update[fingerprint_start:]
require("'scan_sha256'" in fingerprint and "'scan_contract_version'" in fingerprint and "'alt_text'" in fingerprint, 'R07 logical media fingerprint is incomplete')
require("'attachment_id' =>" not in fingerprint, 'R07 media fingerprint still binds to WordPress attachment identity')
require("'scan_reference'=>" not in fingerprint and "'scan_reference' =>" not in fingerprint, 'R07 media fingerprint still binds to scanner reference identity')
require('cleanup_replayed_prepared_media' in update, 'R07 replay-created media cleanup helper missing')
replay = update.find("isset( $idem['replay'] )")
cleanup = update.find('cleanup_replayed_prepared_media', replay)
require(replay >= 0 and cleanup > replay, 'R07 completed replay does not clean redundant prepared media before returning')
require('attachment_id === $current_id' in update, 'R07 replay cleanup can delete the attachment already committed to the profile')
require('SPD_Media::delete_owned' in update and 'SPD_Media::queue_owned_deletion' in update, 'R07 replay cleanup lacks direct-delete plus durable fallback')

# R07 — optional audit consumers run after commit and cannot falsify the result.
audit = section(events, 'private function audit_diff', 'private function idempotency_begin')
require('try {' in audit and 'catch ( Throwable $exception )' in audit, 'R07 post-commit audit callback exception containment missing')
require('SMC_Security::audit' in audit and "do_action( 'spd_profile_audit'" in audit, 'R07 audit consumers missing')
require('sabri_file24_profile_audit_callback_failure' in audit, 'R07 contained audit failure lacks assurance signal')

# R08 — File 09 projection and validator are untrusted cross-file code.
projection = section(verification, 'public static function projection', 'private static function normalize')
require("apply_filters( 'sabri_doctor_verification_public_projection_v1'" in projection, 'R08 File09 verification projection call missing')
require('gdo_validate_public_projection' in projection, 'R08 File09 public projection validator missing')
require('try {' in projection and 'catch ( Throwable $exception )' in projection, 'R08 File09 provider/validator exception containment missing')
require('provider_failure' in projection and 'return array();' in projection, 'R08 File09 exception does not fail closed to no trusted projection')
require("'provider'        => 'file09_verification'" in verification and 'sabri_file24_profile_provider_failure' in verification, 'R08 File09 provider exception lacks assurance evidence')

# R08 — all Central companion projections use one exception-safe provider boundary.
provider = section(central, 'public static function provider_claim', 'public static function clinic_projection')
require('try {' in provider and 'catch ( Throwable $exception )' in provider, 'R08 Central provider callback exception containment missing')
require('apply_filters( $filter' in provider and 'provider_failure( $filter' in provider, 'R08 Central provider call is not guarded by common failure handling')
require('return array();' in provider, 'R08 Central provider exception does not degrade to empty projection')
require('sabri_file24_profile_provider_failure' in central and "'surface'         => 'central_profile_projection'" in central, 'R08 Central provider exception lacks assurance evidence')

# R08 — the base verified-doctor DTO has its own File08 clinic read and must also
# contain an exception instead of tearing down REST/public-profile rendering.
dto_start = public_dto.find('$clinic_raw = null;')
dto_end = public_dto.find('$dto = array(', dto_start)
require(dto_start >= 0 and dto_end > dto_start, 'R08 base clinic provider section missing')
clinic = public_dto[dto_start:dto_end]
require("apply_filters( 'sabri_file08_public_clinic_projection_v1'" in clinic, 'R08 base File08 clinic projection call missing')
require('try {' in clinic and 'catch ( Throwable $exception )' in clinic, 'R08 base File08 clinic exception containment missing')
require("'provider'        => 'file08_public_clinic'" in clinic and "'surface'         => 'public_profile_dto'" in clinic, 'R08 base File08 exception evidence missing')
require('$clinic_raw = null;' in clinic, 'R08 base File08 exception does not degrade to an empty clinic projection')

# R09 — a completed share rotation must replay before stale-version rejection.
rotate = section(repo, 'public function rotate_share_link', 'public function grant_delegate')
prior = rotate.find("completed_idempotency_response( $actor_id, 'rotate_share_link'")
conflict = rotate.find("$expected_version !== absint( $profile['version'] )")
require(prior >= 0 and conflict > prior, 'R09 share-link replay is still rejected by stale-version validation before replay lookup')
require("absint( $prior['version'] ?? 0 ) === $expected_version + 1" in rotate and "$prior['public_id']" in rotate, 'R09 share-link replay is not bound to the original version/profile')
require("'share_url' => SPD_Central_Profile::short_url" in rotate, 'R09 share rotation response lost deterministic share URL')

# R09 — if raw central post-commit cleanup/audit throws after idempotency commit,
# the effective repository boundary recovers the committed response instead of
# falsifying the successful mutation to the caller.
central_update = section(repo, 'public function update_central_profile', 'public function rotate_share_link')
require('try {' in central_update and 'catch ( Throwable $exception )' in central_update, 'R09 central post-commit Throwable recovery missing')
require("completed_idempotency_response( $actor_id, 'update_central_profile'" in central_update, 'R09 central committed-response recovery does not consult exact replay truth')
require('sabri_file24_profile_post_commit_recovery' in central_update, 'R09 central post-commit recovery lacks assurance evidence')
require('return $replay;' in central_update, 'R09 recovered committed central mutation is not returned to the caller')

# R09 — delegation-store uncertainty must be distinct from genuine absence or
# authorization denial on effective grant/revoke/list paths.
require('private function delegation_store_error' in repo and "'spd_delegation_store_unavailable'" in repo and "'status' => 503" in repo, 'R09 delegation-store 503 error contract missing')
grant = section(repo, 'public function grant_delegate', 'public function revoke_delegate')
require("$wpdb->last_error = '';" in grant and '$wpdb->get_row' in grant and 'if ( $wpdb->last_error ) { return $this->delegation_store_error(); }' in grant, 'R09 delegation grant read uncertainty is not fail closed')
require('if ( $wpdb->last_error || false === $ok ) { return $this->delegation_store_error(); }' in grant, 'R09 delegation grant write uncertainty is not fail closed')
revoke = section(repo, 'public function revoke_delegate', 'public function list_delegates')
require("$wpdb->last_error = '';" in revoke and 'if ( $wpdb->last_error ) { return $this->delegation_store_error(); }' in revoke, 'R09 delegation revoke read uncertainty is not fail closed')
require("'spd_delegate_not_active'" in revoke and revoke.find('delegation_store_error') < revoke.find("'spd_delegate_not_active'"), 'R09 delegation revoke still maps DB uncertainty to genuine absence')
listing = section(repo, 'public function list_delegates', 'public function delegate_can_manage')
require("$wpdb->last_error = '';" in listing and 'if ( $wpdb->last_error || ! is_array( $rows ) ) { return $this->delegation_store_error(); }' in listing, 'R09 delegation list uncertainty can still look like an empty list')
edit = section(repo, 'public function central_edit_model', 'public function update_profile')
require("is_wp_error( $result['delegations'] )" in edit and "return $result['delegations'];" in edit, 'R09 owner edit model does not propagate delegation-store uncertainty')

print('File 03 sixth-cycle sequential invariants through R09: PASS')
