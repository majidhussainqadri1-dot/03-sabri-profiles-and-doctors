#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
auth = (ROOT / 'includes/class-spd-authorization.php').read_text(encoding='utf-8')
dto = (ROOT / 'includes/trait-spd-profile-public-dto.php').read_text(encoding='utf-8')
membership = (ROOT / 'includes/class-spd-membership-adapter.php').read_text(encoding='utf-8')
future = (ROOT / 'includes/class-spd-future-profile.php').read_text(encoding='utf-8')
central = (ROOT / 'includes/trait-spd-profile-central.php').read_text(encoding='utf-8')
provider_guards = (ROOT / 'includes/class-spd-provider-guards.php').read_text(encoding='utf-8')
cache = (ROOT / 'includes/trait-spd-profile-cache.php').read_text(encoding='utf-8')
contracts = (ROOT / 'includes/class-spd-contracts.php').read_text(encoding='utf-8')
observability = (ROOT / 'includes/class-spd-observability.php').read_text(encoding='utf-8')
events = (ROOT / 'includes/trait-spd-profile-events.php').read_text(encoding='utf-8')
media = (ROOT / 'includes/class-spd-media.php').read_text(encoding='utf-8')
timeline = (ROOT / 'includes/class-spd-timeline.php').read_text(encoding='utf-8')
identity_create = (ROOT / 'includes/trait-spd-profile-identity-create.php').read_text(encoding='utf-8')

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

# R03 — File 17 contact-graph and internal-message callbacks are untrusted
# cross-file providers. Their exceptions must fail closed without tearing down
# profile visibility or public DTO rendering, and must surface File 24 evidence.
contact = section(auth, 'public static function is_contact', 'public static function audience_allows')
require("apply_filters( 'sabri_network_contact_claim_v1'" in contact, 'R03 File17 contact claim call missing')
require('try {' in contact and 'catch ( Throwable $exception )' in contact, 'R03 File17 contact claim exception containment missing')
require("provider_failure( 'file17_contact_graph', 'contact_audience'" in contact, 'R03 File17 contact failure evidence missing')
require('return false;' in contact, 'R03 File17 contact exception does not fail closed')
message = section(dto, "$internal = $profile['fields']['internal_message']", 'if ( 0 === $viewer_id )')
require("apply_filters( 'sabri_network_message_profile_url'" in message, 'R03 File17 message URL call missing')
require('try {' in message and 'catch ( Throwable $exception )' in message, 'R03 File17 message URL exception containment missing')
require("'provider'        => 'file17_message_profile_url'" in message, 'R03 File17 message URL failure evidence missing')
require("$url = '';" in message, 'R03 File17 message URL exception does not degrade to hidden action')

# R04 — every File 00 execution/filter boundary is contained by one shared Throwable guard.
require('private static function provider_call' in membership, 'R04 shared File00 provider guard missing')
provider_guard = membership[membership.find('private static function provider_call'):membership.find('public static function claims')]
require('catch ( Throwable $exception )' in provider_guard, 'R04 File00 provider guard lacks Throwable containment')
require("'provider'        => 'file00_membership'" in membership and 'sabri_file24_profile_provider_failure' in membership, 'R04 File00 provider failures lack File24 evidence')
for surface in ('membership_assertions','founder_assertion','user_status','age_guardian_claim','founder_user_id','founder_management_restriction','guardian_relationship_claim','contact_projection'):
    require(surface in membership, f'R04 guarded File00 surface missing: {surface}')
require("'dependency_missing'" in membership, 'R04 File00 status exception is not fail-closed')
require("'founder_management_restriction',\n\t\t\tfalse" in membership, 'R04 Founder restriction provider failure does not deny')

# R05 — shared Future Superset provider reads contain upstream callback exceptions.
current_claim = section(future, 'private static function current_claim', 'private static function safe_external_url')
require('apply_filters_ref_array' in current_claim, 'R05 shared future provider call missing')
require('try {' in current_claim and 'catch ( Throwable $exception )' in current_claim, 'R05 future provider Throwable containment missing')
require('sabri_file24_profile_provider_failure' in current_claim and "'surface'         => 'future_profile_projection'" in current_claim, 'R05 future provider failure evidence missing')
require('return array();' in current_claim, 'R05 future provider exception does not degrade to empty claim')
require('current_contract_claim' in current_claim, 'R05 future provider freshness/version validation regressed')

# R06 — delegated authorization uncertainty surfaces 503, not false 403.
delegated = section(central, 'private function delegated_access_result', 'public function central_edit_model')
for token in ('SPD_Schema_Guard::central_ready()', 'SPD_Membership_Adapter::health()', 'SPD_Membership_Adapter::claims', 'SPD_Verification_Adapter::health()', 'SPD_Verification_Adapter::projection', '$wpdb->last_error', "'status' => 503"):
    require(token in delegated, f'R06 delegated uncertainty preflight missing: {token}')
edit = section(central, 'public function central_edit_model', 'public function update_central_profile')
update = section(central, 'public function update_central_profile', 'public function rotate_share_link')
require('delegated_access_result' in edit and 'is_wp_error( $delegated )' in edit, 'R06 edit model does not propagate delegated uncertainty')
require('delegated_access_result' in update and 'is_wp_error( $delegated )' in update, 'R06 central mutation does not propagate delegated uncertainty')
require("'spd_safe_mode'" in edit and "'status' => 503" in edit, 'R06 delegated edit safe mode is not service-unavailable')

# R07 — dependency uncertainty and idempotency-store failure semantics.
require("private static $file00_membership_uncertain = false;" in provider_guards, 'R07 File00 uncertainty state missing')
require("add_action( 'sabri_file24_profile_provider_failure', array( __CLASS__, 'remember_file00_membership_failure' )" in provider_guards, 'R07 File00 dependency evidence hook missing')
require("'file00_membership' !== sanitize_key" in provider_guards, 'R07 File00 evidence is not provider-scoped')
require("'spd_idempotency_store_failed' === $code" in provider_guards and "$current['status'] = 503;" in provider_guards, 'R07 idempotency store failure is not normalized to 503')
require("self::$file00_membership_uncertain && 'spd_account_ineligible' === $code" in provider_guards, 'R07 dependency uncertainty does not intercept false 403 semantics')
require("$current['dependency'] = 'file00_membership';" in provider_guards, 'R07 dependency source is not preserved in error evidence')
strict_response = section(provider_guards, 'public static function normalize_strict_report_response', None)
for token in ("is_strict_report_route", "'spd_account_ineligible'", "'spd_membership_claim_unavailable'", 'set_status( 503 )'):
    require(token in strict_response, f'R07 strict report/appeal response normalization missing: {token}')

# R08 — mutation authorization preserves provider/store uncertainty as 503.
mutation = section(auth, 'public static function mutation_guard', 'public static function moderation_guard')
for token in ('SPD_Membership_Adapter::health()', 'SPD_Membership_Adapter::claims( $actor_id )', 'SPD_Membership_Adapter::claims( $owner_id )', "'spd_membership_claim_unavailable'", "'status' => 503"):
    require(token in mutation, f'R08 mutation dependency preflight missing: {token}')
require('public static function file00_dependency_uncertain()' in provider_guards, 'R08 request-local dependency uncertainty accessor missing')
require('SPD_Provider_Guards::file00_dependency_uncertain()' in mutation, 'R08 mutation denial does not consult provider-failure evidence')
require("'spd_membership_dependency_unavailable'" in mutation, 'R08 mutation provider failure does not surface 503 dependency error')
require("'spd_forbidden'" in mutation, 'R08 genuine authorization denial path was lost')

# R09 — public/private DTO and revocation-cache audit: clean.
require('return false;' in section(cache, 'private function get_anonymous_public_dto_cache', 'private function set_anonymous_public_dto_cache'), 'R09 anonymous DTO cache unexpectedly enabled')
require("foreach ( array( 'phone', 'email', 'whatsapp' ) as $key )" in dto and 'if ( $is_minor ) { continue; }' in dto, 'R09 direct-contact minor guard regressed')
require('SPD_Membership_Adapter::contact( $user_id, $key )' in dto, 'R09 public contact stopped using owner-sourced File00 projection')

# R10 — cross-file ownership audit: clean.
for token in ("'file09' => array", "'file08' => array", "'file17' => array", "'file26' => array", "'file20' => array", "'file25' => array"):
    require(token in contracts, f'R10 dependency/ownership manifest missing: {token}')
require("'ownership' => 'Verification/appointments/reviews/directory ranking/search ranking/AI/contact relay/learning/federation transport remain external canonical facts.'" in contracts, 'R10 canonical ownership boundary regressed')

# R11 — unknown/invalid File00 age state never means adulthood.
is_minor = section(membership, 'public static function is_minor', 'public static function age_known')
require('return ! $claims || ! empty( $claims[\'is_minor\'] );' in is_minor, 'R11 unknown membership/age is not minor-safe')
migration = section(observability, 'private function migrate_legacy_projection', 'public function process_media_deletions')
require("get_user_meta( $user_id, '_spd_public_contact', true )" in migration, 'R11 legacy contact migration path disappeared unexpectedly')
require('! SPD_Membership_Adapter::is_minor( $user_id )' in migration, 'R11 legacy contact migration no longer consumes fail-closed minor guard')

# R12 — concurrency/idempotency audit: clean; lock reservation and atomic replay invariants.
require('private $spd_idempotency_reservations = array();' in events, 'R12 reservation ownership ledger missing')
for token in ('reservation_token', "'status' => 'started'", "'status' => 'completed'", 'idempotency_abandoned_seconds', 'hash_equals'):
    require(token in events, f'R12 idempotency invariant missing: {token}')
require("new WP_Error( 'spd_idempotency_in_progress'" in events, 'R12 concurrent replay does not fail deterministically')

# R13 — media safety/deletion audit: clean.
for token in ('strip_metadata', 'valid_clean_scan', 'SCAN_SHA_META', 'queue_owned_deletion', 'reconcile_storage_privacy'):
    require(token in media, f'R13 media safety invariant missing: {token}')
require("'spd_scan_unavailable'" in media and 'hash_equals( $sha, strtolower( $actual ) )' in media, 'R13 scanner fail-closed/hash binding regressed')

# R14 — timeline must enforce current viewer audience at the File03 boundary.
query = section(timeline, 'public static function query', 'private static function normalize_item')
require("SPD_Authorization::audience_allows( $normalized['visibility'], $profile['user_id'], $viewer_id )" in query, 'R14 timeline still hard-codes public-only visibility')
require("'visibility' => SPD_Authorization::normalize_audience" in timeline, 'R14 timeline provider visibility is not normalized fail-closed')
require("array( 'published', 'corrected', 'retracted' )" in query, 'R14 timeline publication-state guard regressed')

# R15 — an established institutional Founder profile is not silently demotable
# by an identity refresh. Contradiction is an explicit reconciliation conflict.
ensure_identity = section(identity_create, 'public function ensure_for_user', 'private function runtime_state')
require("'founder' === sanitize_key( (string) $row['profile_type'] ) && 'founder' !== $type" in ensure_identity, 'R15 established Founder profile can still be silently demoted')
require("'spd_founder_identity_refresh_conflict'" in ensure_identity and "'status' => 409" in ensure_identity, 'R15 Founder contradiction does not fail explicitly')
require("'founder' === $type && SPD_Membership_Adapter::founder_id() !== $user_id" in ensure_identity, 'R15 File00 Founder identity binding regressed')

print('File 03 seventh-cycle sequential invariants through R15: PASS')
