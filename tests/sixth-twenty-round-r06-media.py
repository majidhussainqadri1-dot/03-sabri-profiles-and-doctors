#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
media = (ROOT / 'includes/class-spd-media.php').read_text(encoding='utf-8')
update = (ROOT / 'includes/trait-spd-profile-update.php').read_text(encoding='utf-8')
events = (ROOT / 'includes/trait-spd-profile-events.php').read_text(encoding='utf-8')

def require(ok, message):
    if not ok:
        raise SystemExit(message)

# R06 — external scanner exceptions are fail-closed before attachment creation.
start = media.find('public static function prepare_upload')
end = media.find('private static function valid_clean_scan', start)
require(start >= 0 and end > start, 'R06 prepare_upload section missing')
section = media[start:end]
scan = "apply_filters('spd_profile_media_scan_v1'"
require(scan in section, 'R06 media scan provider call missing')
require('try {' in section and 'catch ( Throwable $exception )' in section, 'R06 media scanner Throwable containment missing')
require(section.find('try {') < section.find(scan) < section.find('catch ( Throwable $exception )'), 'R06 scanner call is outside guarded provider boundary')
require("'spd_scan_unavailable'" in section and "'status'=>503" in section, 'R06 scanner exception is not mapped to fail-closed 503')
require("'provider' => 'profile_media_scan'" in section and "'surface' => 'media_upload_scan'" in section, 'R06 scanner exception observability evidence missing')
require('sabri_file24_profile_provider_failure' in section, 'R06 scanner failure is not surfaced to File 24 assurance')
require(section.find('catch ( Throwable $exception )') < section.find('media_handle_upload'), 'R06 attachment creation can occur before scanner exception is contained')
require('valid_clean_scan' in section, 'R06 stale/malformed/dirty scan validation lost')

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
audit_start = events.find('private function audit_diff')
audit_end = events.find('private function idempotency_begin', audit_start)
require(audit_start >= 0 and audit_end > audit_start, 'R07 audit_diff section missing')
audit = events[audit_start:audit_end]
require('try {' in audit and 'catch ( Throwable $exception )' in audit, 'R07 post-commit audit callback exception containment missing')
require('SMC_Security::audit' in audit and "do_action( 'spd_profile_audit'" in audit, 'R07 audit consumers missing')
require('sabri_file24_profile_audit_callback_failure' in audit, 'R07 contained audit failure lacks assurance signal')

print('File 03 sixth-cycle sequential invariants through R07: PASS')
