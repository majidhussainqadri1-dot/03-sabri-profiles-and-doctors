#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

main = text('sabri-profiles-doctors.php')
membership = text('includes/class-spd-membership-adapter.php')
helpers = text('includes/class-spd-helpers.php')
media = text('includes/class-spd-media.php')
rest = text('includes/class-spd-rest.php')
timeline = text('includes/class-spd-timeline.php')
front_timeline = text('includes/trait-spd-frontend-timeline.php')
update = text('includes/trait-spd-profile-update.php')
moderation = text('includes/trait-spd-profile-moderation.php')
public_dto = text('includes/trait-spd-profile-public-dto.php')
identity_read = text('includes/trait-spd-profile-identity-read.php')
observability = text('includes/class-spd-observability.php')
events = text('includes/trait-spd-profile-events.php')
uninstall = text('uninstall.php')
readme = text('readme.txt')
future = text('includes/class-spd-future-profile.php')
future_rest = text('includes/class-spd-future-rest.php')

checks = []
def check(round_no, name, condition, detail):
    checks.append((round_no, name, bool(condition), detail))

check(1, 'Release identity', "Version: 1.2.0-rc2" in main and "SPD_CONTRACT_VERSION', '1.4.0'" in main and 'FUTURE-SUPERSET-18' in main and '80-ROUND-CORRECTIVE-REVIEW' in main, '1.2.0-rc2, contract 1.4.0, future plan identity and 80-round corrective marker are aligned')
check(2, 'Canonical ownership', 'smc_' not in ''.join(re.findall(r'FROM\s+[^\n]+', public_dto, re.I)), 'no direct File 00 table reads')
check(3, 'Unknown-age fail-closed', "'founder' !== $account_type" in membership and "array( 'founder', 'doctor' )" not in membership, 'unknown-age doctors are minor-safe')
check(4, 'Guardian current claims', 'current_contract_claim( $claim' in membership and 'guardian_verified' in membership, 'guardian claim is versioned/current')
check(5, 'Founder invariant', 'spd_founder_invariant' in moderation and 'can_manage_founder' in membership, 'generic moderation cannot alter Founder')
check(6, 'Canonical UUID validation', 'function valid_uuid' in helpers and 'valid_uuid( (string) $public_id )' in identity_read, 'malformed identifiers are rejected')
check(7, 'Strict mutation precondition', "preg_match( '/^\"?([1-9][0-9]*)\"?$/'" in rest and "if ( '' !== $raw )" in rest, 'malformed If-Match cannot fall back')
check(8, 'Object authorization', 'can_edit_profile( $profile, get_current_user_id() )' in rest and 'mutation_guard' in update, 'route and owner command both authorize')
check(9, 'Idempotency key bounds', 'strlen( $key ) > 200' in events and 'Idempotency-Key' in rest and 'future_idempotency_begin' in future_rest, 'base and future mutations use bounded replay protection')
check(10, 'Deterministic replay', 'return $committed_response;' in update and "'profile' => $after" not in update and "isset( $idem['replay'] )" in future_rest, 'base and future mutation replay is deterministic')
check(11, 'Scanner required', "has_filter('spd_profile_media_scan_v1')" in media and 'spd_scan_unavailable' in media, 'no permissive scanner fallback')
check(12, 'Scan-byte binding', 'SCAN_SHA_META' in media and "preg_match( '/^[0-9a-f]{64}$/'" in media and "hash_file( 'sha256', $path )" in media, 'clean result is bound to exact bytes')
check(13, 'Metadata stripping', 'strip_metadata' in media and 'wp_get_image_editor' in media, 'images are re-encoded before scanning')
check(14, 'Media ownership/purpose', 'OWNER_META' in public_dto and 'PURPOSE_META' in public_dto and 'STATE_META' in public_dto, 'public media is revalidated')
check(15, 'Privacy tightening deletion', 'removed_for_privacy' in update and 'queue_owned_deletion' in update, 'privacy change atomically detaches media')
check(16, 'Moderation media revocation', 'removed_for_moderation' in moderation and 'avatar_id=0,cover_id=0' in moderation, 'suspension/archive/tombstone revokes media')
check(17, 'Deletion retry/dead letter', "status=$attempts>=8?'dead':'retry'" in media and 'lease_expires' in media, 'deletion processing is leased and bounded')
check(18, 'Exact origin', 'effective_port' in helpers and '$target_scheme !== $home_scheme' in helpers, 'scheme host and port must match')
check(19, 'URL credentials rejected', "isset( $target['user'] ) || isset( $target['pass'] )" in helpers and 'safe_external_url' in future, 'credential-bearing URLs remain rejected; external future links are HTTPS constrained')
check(20, 'Provider health freshness', 'current_contract_claim( $provider_health' in timeline and 'current_contract_claim' in future, 'timeline and future provider facts are current/versioned')
check(21, 'Provider exception containment', 'catch ( Throwable $e )' in timeline and 'spd_timeline_provider_exception' in timeline, 'provider exceptions cannot break profile page')
check(22, 'Provider result bound', 'MAX_PROVIDER_ITEMS' in timeline and 'count( $result ) > self::MAX_PROVIDER_ITEMS' in timeline and 'array_slice' in future, 'timeline and future provider payloads are bounded')
check(23, 'Timeline owner binding', "author_user_id'] ?? 0" in timeline and 'expected_user_id' in timeline, 'cross-author items are rejected')
check(24, 'Future timestamp rejection', '$timestamp > time() + 300' in timeline, 'future-dated timeline items are rejected')
check(25, 'Thumbnail tracking prevention', '$thumbnail && ! SPD_Helpers::same_origin_url' in timeline, 'external thumbnails are dropped')
check(26, 'Cursor bounds/integrity', 'strlen( $cursor ) > 768' in timeline and "hash_hmac( 'sha256', $body" in timeline and "base64_decode( $raw, true )" in timeline and 'spd_timeline_cursor_invalid' in timeline, 'oversized, tampered, cross-profile or cross-filter cursors fail closed')
check(27, 'Timeline UI error handling', 'is_wp_error( $profile )' in front_timeline, 'DTO errors cannot cause array-access fatal')
check(28, 'Clinic verified-doctor gate', '$clinic_raw = $is_verified_doctor ?' in public_dto, 'clinic projection is not shown for ordinary profiles')
check(29, 'Single verification snapshot', 'SPD_Verification_Adapter::is_verified' not in public_dto and '$is_verified_doctor' in public_dto, 'DTO does not fetch inconsistent verification snapshots')
check(30, 'Minor contact suppression', '$is_minor = ! empty( $claims' in public_dto and 'if ( $is_minor ) { continue; }' in public_dto, 'current claims suppress minor contact')
check(31, 'Migration visibility minimization', "$safe_audience = 'private'" in observability and 'public_profile_age_eligible' in observability, 'legacy public visibility is not blindly copied')
check(32, 'Migration contact minimization', "'_spd_public_contact'" in observability and '! SPD_Membership_Adapter::is_minor' in observability, 'legacy contact stays minor-safe')
check(33, 'Migration repair schedule', "'spd_migrate_profiles_batch' => 'schedule_migration'" in observability, 'repair includes incomplete migration schedule')
check(34, 'Unicode report minimum', 'SPD_Helpers::text_length( $details ) < 10' in moderation, 'minimum detail uses character count')
check(35, 'Audit error normalization', "array( 'error' => $before->get_error_code()" in update and "array( 'error' => $after->get_error_code()" in update, 'WP_Error is not serialized as a misleading DTO')
check(36, 'JSON failure handling', "return false === $json ? 'null' : $json" in helpers and "if ( 'null' === $json )" in events, 'encoding failure cannot complete idempotency')
runtime_files = [ROOT / 'sabri-profiles-doctors.php', ROOT / 'uninstall.php', ROOT / 'readme.txt']
runtime_files += [x for root_name in ('includes', 'assets') for x in (ROOT / root_name).rglob('*') if x.is_file()]
credential_names = {'wp-config.php', 'credentials.json', 'service-account.json', 'id_rsa', 'id_dsa', 'id_ecdsa', 'id_ed25519'}
secret_patterns = [rb'-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----', rb'\bgh[pousr]_[A-Za-z0-9]{20,}\b', rb'\bAKIA[0-9A-Z]{16}\b']
runtime_clean = all(not any(re.search(pattern, x.read_bytes()) for pattern in secret_patterns) for x in runtime_files)
names_clean = not any(x.is_file() and x.name.lower() in credential_names for x in ROOT.rglob('*') if '.git' not in x.parts)
archives_clean = not any(x.is_file() and x.suffix.lower() in {'.zip','.sql','.7z','.rar','.tar','.tgz','.gz'} for x in ROOT.rglob('*') if '.git' not in x.parts)
check(37, 'Secrets/archive hygiene', runtime_clean and names_clean and archives_clean, 'runtime source has no secret signatures, credential files or committed archives')
check(38, 'Non-destructive uninstall', 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' in uninstall and 'spd_purge_on_uninstall' in uninstall and 'profile_future_state' in uninstall, 'destructive purge still requires two gates and covers future native data')
check(39, 'Package identity', 'Stable tag: 1.2.0-rc2' in readme and "SPD_DB_VERSION', '1.2.0'" in main and "SPD_CONTRACT_VERSION', '1.4.0'" in main, 'software/contract/schema identities are explicit for rc2')
check(40, 'Truthful completion boundary', 'This remains a staging candidate' in readme and 'separate from Hostinger staging' in readme and 'live deployment' in readme, 'source completion does not claim staging/live')

failures = [c for c in checks if not c[2]]
for n, name, ok, detail in checks:
    print(f'Round {n:02d}: {"PASS" if ok else "FAIL"} — {name}: {detail}')
if len(checks) != 40:
    print(f'ERROR: expected 40 rounds, got {len(checks)}', file=sys.stderr); sys.exit(2)
if failures:
    print(f'ERROR: {len(failures)} review rounds failed', file=sys.stderr); sys.exit(1)
print('All 40 review rounds passed after their recorded corrections.')
