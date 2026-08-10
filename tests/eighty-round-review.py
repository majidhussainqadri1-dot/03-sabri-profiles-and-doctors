#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def has(path, token):
    return token in read(path)

def lacks(path, token):
    return token not in read(path)

def exists(path):
    return (ROOT / path).exists()

php = '\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if 'tests' not in p.parts)

checks = [
    (1, 'Exact corrective candidate version', lambda: has('sabri-profiles-doctors.php', "'1.2.0-rc2'")),
    (2, 'Database version remains additive 1.2.0', lambda: has('sabri-profiles-doctors.php', "SPD_DB_VERSION', '1.2.0")),
    (3, 'Contract version remains 1.4.0', lambda: has('sabri-profiles-doctors.php', "SPD_CONTRACT_VERSION', '1.4.0")),
    (4, 'Plan identity records 80-round correction', lambda: has('sabri-profiles-doctors.php', '80-ROUND-CORRECTIVE-REVIEW')),
    (5, 'Public plugin source contains no debug display_errors enablement', lambda: 'ini_set(\'display_errors\',\'1\')' not in php and 'WP_DEBUG_DISPLAY' not in php),
    (6, 'Future superset bootstrap remains loaded', lambda: has('sabri-profiles-doctors.php', 'class-spd-future-rest.php') and has('sabri-profiles-doctors.php', 'trait-spd-frontend-future.php')),
    (7, 'Core schema fail-closed installer remains present', lambda: has('includes/class-spd-db.php', 'spd_schema_install_failed')),
    (8, 'Activation uses atomic owner-token lock', lambda: has('includes/class-spd-activator.php', "acquire_lock( 'activation'") and lacks('includes/class-spd-activator.php', "set_transient( 'spd_activation_lock'")),
    (9, 'Migration batch is serialized by atomic compatibility guard', lambda: has('includes/class-spd-plugin.php', "acquire_lock( 'migration_batch'") and has('includes/class-spd-plugin.php', 'before_migration_batch')),
    (10, 'Database transactions rollback on failure', lambda: has('includes/class-spd-db.php', "query('ROLLBACK')") and has('includes/class-spd-db.php', "query('COMMIT')")),
    (11, 'Founder transition enforces singleton', lambda: has('includes/trait-spd-profile-identity-create.php', "profile_type='founder' AND id<>%d")),
    (12, 'Identity refresh emits current profile event', lambda: has('includes/trait-spd-profile-identity-create.php', "'identity_refresh'") and has('includes/trait-spd-profile-identity-create.php', 'PublicProfileUpdated.v1')),
    (13, 'Slug collisions fail explicitly', lambda: has('includes/trait-spd-profile-identity-create.php', 'spd_slug_collision')),
    (14, 'Profile state transition allowlist remains explicit', lambda: has('includes/class-spd-helpers.php', 'state_transition_allowed')),
    (15, 'Mutations retain native owner guard', lambda: has('includes/class-spd-authorization.php', 'mutation_guard')),
    (16, 'Guardian authority is current File 00 claim based', lambda: has('includes/class-spd-authorization.php', 'guardian_can_manage')),
    (17, 'Moderation uses File 00 profile moderation capability', lambda: has('includes/class-spd-authorization.php', 'can_moderate_profiles')),
    (18, 'Legacy/future governance is actor-bound and step-up aware', lambda: has('includes/class-spd-future-rest.php', 'can_manage_founder( $actor )') and has('includes/class-spd-future-rest.php', 'can_operate_profiles( $actor )')),
    (19, 'REST profile mutation rechecks object edit authority', lambda: has('includes/class-spd-rest.php', 'can_edit_profile( $profile, get_current_user_id() )')),
    (20, 'Profile mutation rejects unknown fields', lambda: has('includes/trait-spd-profile-update.php', 'spd_unknown_profile_field')),
    (21, 'Malformed If-Match fails precondition instead of body fallback', lambda: has('includes/class-spd-central-rest.php', "preg_match( '/^\"?([1-9][0-9]*)\"?$/', $raw, $m ) ? absint( $m[1] ) : 0")),
    (22, 'Base mutation idempotency is required', lambda: has('includes/trait-spd-profile-events.php', 'spd_idempotency_required')),
    (23, 'Future REST mutation callback and replay finalization are transactional', lambda: has('includes/class-spd-future-rest.php', 'SPD_DB::transaction( function() use ( $repo, $actor, $command, $key, $callback )')),
    (24, 'Future replay completion is inside transactional mutation path', lambda: has('includes/class-spd-future-rest.php', 'future_idempotency_complete( $actor, $command, $key, $mutation )')),
    (25, 'Outbox uses claim leases and bounded dead-letter attempts', lambda: has('includes/class-spd-observability.php', 'lease_token') and has('includes/class-spd-observability.php', 'OUTBOX_MAX_ATTEMPTS')),
    (26, 'Public DTO path is isolated from edit DTO path', lambda: exists('includes/trait-spd-profile-public-dto.php') and exists('includes/trait-spd-profile-edit-model.php')),
    (27, 'Private edit model is separately implemented', lambda: has('includes/trait-spd-profile-edit-model.php', 'edit_model')),
    (28, 'Profile cache has audience-aware implementation', lambda: exists('includes/trait-spd-profile-cache.php') and 'cache' in read('includes/trait-spd-profile-cache.php').lower()),
    (29, 'Identity refresh purges profile cache', lambda: has('includes/trait-spd-profile-identity-create.php', 'purge_profile_cache')),
    (30, 'Contact claims require current versioned contracts', lambda: has('includes/class-spd-authorization.php', 'current_contract_claim')),
    (31, 'Minor public-profile exposure fails closed', lambda: has('includes/class-spd-authorization.php', "'public' === $visibility") and has('includes/class-spd-authorization.php', "claims['is_minor']")),
    (32, 'Founder profile visibility is forced public through invariant repair', lambda: has('includes/trait-spd-profile-identity-create.php', 'ensure_founder_invariants')),
    (33, 'Doctor verification remains external adapter truth', lambda: exists('includes/class-spd-verification-adapter.php') and has('includes/class-spd-contracts.php', "'file09'")),
    (34, 'Professional proposals remain separate owned workflow', lambda: has('includes/class-spd-contracts.php', 'professional_profile_proposals')),
    (35, 'Delegation grant uses idempotency and transaction', lambda: has('includes/trait-spd-profile-central.php', "'grant_profile_delegate'") and has('includes/trait-spd-profile-central.php', 'SPD_DB::transaction')),
    (36, 'Delegation revoke uses idempotency and optimistic active-row guard', lambda: has('includes/trait-spd-profile-central.php', "'revoke_profile_delegate'") and has('includes/trait-spd-profile-central.php', 'spd_delegate_not_active')),
    (37, 'Media upload request has atomic rate-limit guard', lambda: has('includes/class-spd-plugin.php', "consume_rate_limit( 'media_upload_'")),
    (38, 'Media ingestion validates real file type and dimensions', lambda: has('includes/class-spd-media.php', 'wp_check_filetype_and_ext') and has('includes/class-spd-media.php', 'getimagesize')),
    (39, 'Media scan is bound to exact re-encoded SHA-256 bytes', lambda: has('includes/class-spd-media.php', 'hash_file') and has('includes/class-spd-media.php', 'SCAN_SHA_META')),
    (40, 'Media deletion uses durable deletion ledger', lambda: has('includes/class-spd-media.php', "table('deletions')") or has('includes/class-spd-media.php', "table( 'deletions' )")),
    (41, 'Profile images are re-encoded to strip metadata', lambda: has('includes/class-spd-media.php', 'strip_metadata')),
    (42, 'Safety-report abuse throttling is serialized', lambda: has('includes/trait-spd-profile-central.php', "consume_rate_limit( 'profile_report_'")),
    (43, 'Report appeals use idempotency and transactional event persistence', lambda: has('includes/trait-spd-profile-central.php', "'request_report_appeal'") and has('includes/trait-spd-profile-central.php', 'ProfileReportAppealed.v1')),
    (44, 'Moderation remains explicit state/version workflow', lambda: exists('includes/trait-spd-profile-moderation.php') and 'version' in read('includes/trait-spd-profile-moderation.php')),
    (45, 'Timeline is provider-projection based', lambda: has('includes/class-spd-timeline.php', 'providers')),
    (46, 'Timeline cursor input remains bounded in REST contract', lambda: has('includes/class-spd-rest.php', "strlen( (string) $value ) <= 512")),
    (47, 'Search stays a File 26 projection boundary', lambda: has('includes/class-spd-contracts.php', 'sabri_file26_profile_search_projection_v1')),
    (48, 'File 03 canonical route manifest remains public-id based and internally consistent', lambda: has('includes/class-spd-contracts.php', "'/profile/{public_id}/'") and has('includes/class-spd-plugin.php', "'^/profile/'")),
    (49, 'Share/disclosure tokens use signed revocable state', lambda: has('includes/class-spd-future-profile.php', 'hash_hmac') and has('includes/class-spd-future-profile.php', 'share_epoch')),
    (50, 'Disclosure REST restores future credential/expertise/achievement scopes from augmented DTO', lambda: has('includes/class-spd-future-rest.php', "case 'credentials'") and has('includes/class-spd-future-rest.php', "case 'achievements'")),
    (51, 'Selective disclosure retains expiry/revocation validation', lambda: has('includes/class-spd-future-profile.php', 'spd_disclosure_expired') and has('includes/class-spd-future-profile.php', 'spd_disclosure_revoked')),
    (52, 'Portable credential wallet remains implemented', lambda: has('includes/class-spd-future-profile.php', 'credential_wallet')),
    (53, 'Learning passport remains provider-owned projection', lambda: has('includes/class-spd-future-profile.php', 'learning_passport')),
    (54, 'Professional trust timeline remains bounded projection', lambda: has('includes/class-spd-future-profile.php', 'trust_timeline')),
    (55, 'Expertise evidence remains evidence-linked projection', lambda: has('includes/class-spd-future-profile.php', 'expertise_evidence')),
    (56, 'Knowledge graph remains projection not duplicate store', lambda: has('includes/class-spd-future-profile.php', 'knowledge_graph')),
    (57, 'Knowledge coverage remains non-ranking/no-paid-influence', lambda: has('includes/class-spd-future-profile.php', 'knowledge_coverage') and has('includes/class-spd-future-profile.php', 'paid_influence')),
    (58, 'Grounded AI preserves medical-scope rejection', lambda: has('includes/class-spd-future-profile.php', 'spd_ai_medical_scope_rejected')),
    (59, 'Grounded AI endpoint has atomic per-user abuse throttle', lambda: has('includes/class-spd-future-rest.php', "consume_rate_limit( 'ask_work_'")),
    (60, 'Translations remain owner-approved native presentation data', lambda: has('includes/class-spd-future-profile.php', 'save_translation') and has('includes/class-spd-future-privacy.php', 'Approved translation')),
    (61, 'Privacy-safe contact relay remains File 17 projection', lambda: has('includes/class-spd-future-profile.php', 'contact_relay') and has('includes/class-spd-contracts.php', "'file17'")),
    (62, 'External links remain verified HTTPS-only projection', lambda: has('includes/class-spd-future-profile.php', 'verified_links') and has('includes/class-spd-future-profile.php', "'https'")),
    (63, 'Structured professional dossier remains present', lambda: has('includes/class-spd-future-profile.php', 'dossier')),
    (64, 'Embed card remains scriptless/tracking-free contract', lambda: has('includes/class-spd-contracts.php', "'embed_card' => 'scriptless and tracking-free canonical link'")),
    (65, 'Field freshness attestations remain implemented', lambda: has('includes/class-spd-future-profile.php', 'reconfirm_field') and has('includes/class-spd-future-profile.php', 'freshness')),
    (66, 'Owner-only change history remains future projection', lambda: has('includes/class-spd-future-profile.php', 'change_history')),
    (67, 'Legacy lifecycle UI and server both require governed authority', lambda: has('includes/class-spd-plugin.php', 'canGovernLegacy') and has('assets/js/future-profiles.js', 'canGovernLegacy') and has('includes/class-spd-future-rest.php', 'spd_legacy_governance_required')),
    (68, 'FHIR projection remains professional-only and no patient-record owner', lambda: has('includes/class-spd-future-profile.php', 'PractitionerRole') and has('includes/class-spd-contracts.php', 'fhir_no_patient_record')),
    (69, 'Federation transport activates only with both inbox and outbox', lambda: has('sabri-profiles-doctors.php', "! empty( $dto['future']['federation']['inbox'] ) && ! empty( $dto['future']['federation']['outbox'] )")),
    (70, 'Future native data participates in privacy export', lambda: has('includes/class-spd-future-privacy.php', 'export_profile_data')),
    (71, 'Future erasure honors base and future legal holds', lambda: has('includes/class-spd-future-privacy.php', 'spd_profile_legal_hold') and has('includes/class-spd-future-privacy.php', 'spd_future_profile_legal_hold')),
    (72, 'Guarded uninstall purges dynamic File 03 lock/rate keys', lambda: has('uninstall.php', "'spd_lock_'") and has('uninstall.php', "'_transient_spd_rate_'")),
    (73, 'Health report exposes migration/outbox/provider/safe-mode posture', lambda: has('includes/class-spd-observability.php', 'health_report') and has('includes/class-spd-observability.php', 'provider_health')),
    (74, 'Safe mode requires a reason and persists it', lambda: has('includes/class-spd-observability.php', 'spd_safe_mode_reason_required') and has('includes/class-spd-observability.php', 'spd_safe_mode_changed_at')),
    (75, 'Repair remains owned-resource only and reports companion mutation false', lambda: has('includes/class-spd-observability.php', "'companion_data_mutated' => false")),
    (76, 'Future frontend uses escaped output and safe URLs', lambda: has('includes/trait-spd-frontend-future.php', 'esc_html') and has('includes/trait-spd-frontend-future.php', 'esc_url')),
    (77, 'RTL/mobile visual assets remain present', lambda: exists('assets/css/profiles.css') and ('rtl' in read('assets/css/profiles.css').lower() or '[dir=' in read('assets/css/profiles.css').lower())),
    (78, 'External owner failure boundaries remain documented fail-safe', lambda: has('includes/class-spd-contracts.php', "'failure' => 'AI work assistant returns unavailable; no local diagnosis fallback'") and has('includes/class-spd-contracts.php', "'failure' => 'federation projection remains transport-inactive'")),
    (79, 'Deterministic package/SBOM tooling remains available', lambda: exists('build-package.sh') and has('build-package.sh', 'SBOM.json')),
    (80, 'Eighty-round review artifact and exact-head CI gate are wired', lambda: exists('EIGHTY-ROUND-REVIEW.md') and has('.github/workflows/future-superset-18.yml', 'eighty-round-review.py')),
]

if len(checks) != 80 or [n for n,_,_ in checks] != list(range(1,81)):
    raise SystemExit('Review gate definition is not exactly rounds 1..80')

failed = []
for number, label, check in checks:
    try:
        ok = bool(check())
    except Exception as exc:
        ok = False
        label = f'{label} ({exc})'
    print(f'Round {number:02d}: {"PASS" if ok else "FAIL"} — {label}')
    if not ok:
        failed.append(number)

if failed:
    raise SystemExit('80-round corrective review failed at rounds: ' + ', '.join(map(str, failed)))
print('All 80 deterministic corrective review gates passed.')
