#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT/path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)
def section(src, start, end=None):
    i = src.find(start); require(i >= 0, f'Missing section: {start}')
    j = src.find(end, i + len(start)) if end else len(src)
    if end: require(j >= 0, f'Missing section end: {end}')
    return src[i:j]

rest = text('includes/class-spd-rest.php')
central_rest = text('includes/class-spd-central-rest.php')
future_rest = text('includes/class-spd-future-rest.php')
timeline = text('includes/class-spd-timeline.php')
observability = text('includes/class-spd-observability.php')
activator = text('includes/class-spd-activator.php')
lifecycle = text('includes/trait-spd-profile-lifecycle.php')
appeals = text('includes/trait-spd-profile-report-appeals.php')
events = text('includes/trait-spd-profile-events.php')
outbox = text('includes/class-spd-outbox-dispatcher.php')

# R01 — transport `version` may be supplied in the body, but it must be
# consumed before strict domain allowlists see the mutation payload.
professional = section(rest, 'public function submit_professional', 'public function update_profile')
require("array( 'fields', 'save_draft', 'version' )" in professional, 'R01 professional version-body fallback is not allowlisted')
update = section(rest, 'public function update_profile', 'public function get_timeline')
require('$expected_version = $this->expected_version( $r );' in update, 'R01 profile version must be captured before payload normalization')
require("unset( $params['version'] );" in update, 'R01 REST version transport field must not leak into repository domain input')
require(update.find('$expected_version = $this->expected_version( $r );') < update.find("unset( $params['version'] );") < update.find('$repo->update_profile'), 'R01 version fallback ordering drifted')
require('private function reject_unknown' in central_rest, 'R01 Central REST strict request helper missing')
for token in (
    'spd_unknown_personal_site_field', 'spd_unknown_share_rotation_field',
    'spd_unknown_delegate_field', 'spd_unknown_delegate_revoke_field',
    'spd_unknown_safety_report_field', 'spd_unknown_appeal_field',
):
    require(token in central_rest, f'R01 Central REST request-shape invariant missing: {token}')
central_update = section(central_rest, 'public function update_personal_site', 'public function rotate_share')
require('$version = $this->version( $r );' in central_update and "unset( $p['version'] );" in central_update, 'R01 Central REST version transport normalization missing')

# R05 — migration must never advance on an uncertain failure ledger.
migrate = section(observability, 'public function migrate_profiles_batch', 'private function migrate_one_user')
require('migration_user_batch_read_failed' in migrate, 'R05 user-batch DB read uncertainty is not fail-closed')
require('is_wp_error( $failure )' in migrate, 'R05 failure-ledger read error is not handled before migration')
require('is_wp_error( $status )' in migrate, 'R05 failure-ledger write error is not handled before cursor movement')
require('is_wp_error( $cleared )' in migrate, 'R05 stale failure-ledger clear error is not handled before cursor movement')
require('migration_failure_count_read_failed' in migrate, 'R05 completion counts can still succeed on uncertain DB reads')
ledger_read = section(observability, 'private function migration_failure', 'private function record_migration_failure')
require("$wpdb->last_error = '';" in ledger_read and 'migration_failure_ledger_read_failed' in ledger_read, 'R05 migration failure-ledger reads do not expose DB uncertainty')
ledger_write = section(observability, 'private function record_migration_failure', 'private function clear_migration_failure')
require('migration_failure_ledger_write_failed' in ledger_write and 'false === $written' in ledger_write, 'R05 failure-ledger writes are not verified')
ledger_clear = section(observability, 'private function clear_migration_failure', 'public static function requeue_migration_user')
require('migration_failure_ledger_clear_failed' in ledger_clear and 'false === $deleted' in ledger_clear, 'R05 failure-ledger cleanup is not verified')

# R06 — required managed route pages must be publishable after repair even if a
# previously managed/exact page was moved to draft/private/trash.
managed = section(activator, 'private static function managed_page', 'private static function migrate_legacy_options')
require(managed.count("'publish' !==") >= 2, 'R06 managed route publication-state checks missing')
require("$changes['post_status'] = 'publish';" in managed, 'R06 stored managed route is not restored to publish')
require("wp_update_post( array( 'ID' => absint( $slug_page->ID ), 'post_status' => 'publish' ), true )" in managed, 'R06 discovered owned/exact route is not restored to publish')

# R11 — safety-report and appeal paths must fail closed on DB uncertainty, and
# appeal submission must have an executable assigned-review/outcome lifecycle.
require("require_once SPD_DIR . 'includes/trait-spd-profile-report-appeals.php';" in lifecycle, 'R11 strict report/appeal workflow is not loaded')
require('use SPD_Profile_Report_Appeals;' in lifecycle, 'R11 strict report/appeal workflow is not composed into the repository')
for token in (
    'create_safety_report_strict', 'request_report_appeal_strict',
    'report_appeal_review_queue', 'moderate_report_appeal',
    'ProfileReportAppealReviewed.v1', 'ProfileReportReopenedByAppeal.v1',
    'spd_report_store_unavailable', 'spd_appeal_store_unavailable',
):
    require(token in appeals, f'R11 appeal/report invariant missing: {token}')
strict_report = section(appeals, 'public function create_safety_report_strict', 'public function request_report_appeal_strict')
require("$wpdb->last_error = '';" in strict_report and 'report-rate evidence could not be read safely' in strict_report, 'R11 safety-report rate evidence does not fail closed')
strict_appeal = section(appeals, 'public function request_report_appeal_strict', 'public static function report_appeal_transition_targets')
require("$wpdb->last_error = '';" in strict_appeal and 'spd_report_store_unavailable' in strict_appeal, 'R11 appeal source-report lookup does not fail closed')
review = section(appeals, 'public function moderate_report_appeal', "}\n}\n\n/** REST overrides")
require("'submitted' => array( 'in_review' )" in appeals and "'in_review' => array( 'upheld', 'rejected' )" in appeals, 'R11 assigned appeal review lifecycle missing')
require("status='in_review'" in review and "status IN ('rejected','closed','actioned')" in review, 'R11 upheld appeal does not safely reopen its report')
require("add_action( 'rest_api_init', 'spd_file03_register_strict_report_routes', 20 );" in appeals, 'R11 strict report/appeal REST overrides are not registered')
require('/appeals/review-queue' in appeals and '/review' in appeals, 'R11 appeal reviewer endpoints missing')

# R13 — public Future surfaces fail closed on provider exceptions and the
# grounded-work assistant rejects multilingual patient-specific treatment intent.
require('public static function medical_scope_question' in future_rest, 'R13 shared medical-scope guard missing')
for phrase in ('میری', 'علاج', 'دواء', 'इलाज', '诊断', 'diagnóstico', 'diagnostic', 'রোগনির্ণয়', 'teşhis'):
    require(phrase in future_rest, f'R13 multilingual medical-scope coverage missing: {phrase}')
require('patient-specific treatment advice' in future_rest, 'R13 patient-specific treatment boundary is not explicit')
require('private function provider_exception' in future_rest and 'spd_future_provider_unavailable' in future_rest, 'R13 provider exceptions are not mapped to fail-closed 503')
require('private function safe_personal_site_profile' in future_rest and 'catch ( Throwable $exception )' in future_rest, 'R13 public Future projections are not exception-safe')
for surface in ("'dossier'", "'fhir'", "'federation'", "'embed_card'", "'disclosure_projection'", "'ask_work'"):
    require(surface in future_rest, f'R13 provider-failure surface missing: {surface}')
require("spd_unknown_ai_question_field" in future_rest, 'R13 AI request shape is not strict')
require("add_filter( 'sabri_file16_grounded_profile_ask_v1', 'spd_file03_guard_grounded_profile_ask_claim', PHP_INT_MAX, 6 );" in future_rest, 'R13 direct grounded-work consumers are not protected by the clinical-intent guard')

# R14 — timeline provider extension and health callbacks are untrusted cross-file
# code and must not be able to tear down the public profile timeline.
require('private static function provider_failure' in timeline, 'R14 timeline provider failure recorder missing')
providers = section(timeline, 'public static function providers', 'public static function query')
require('try {' in providers and 'catch ( Throwable $exception )' in providers, 'R14 provider-registry exception is not contained')
require("return $providers;" in providers, 'R14 provider-registry failure does not fall back to canonical providers')
query = section(timeline, 'public static function query', 'private static function normalize_item')
require("'timeline_provider_health'" in query and "catch ( Throwable $exception )" in query, 'R14 provider-health exception is not contained')
require("$health[ $key ] = 'degraded';" in query, 'R14 provider-health exception is not represented as degraded')
require("'timeline_provider_items'" in query, 'R14 item-provider exceptions are not recorded consistently')

# R15 — an event is part of the same mutation transaction; unencodable payloads
# must fail before a pending outbox row can be accepted as valid audit truth.
event_method = section(events, 'public function event', 'private function audit_diff')
require('$json  = SPD_Helpers::json_encode( $payload );' in event_method, 'R15 event payload is not encoded before persistence')
require("if ( 'null' === $json )" in event_method and 'spd_event_payload_invalid' in event_method, 'R15 unencodable event payload is not rejected atomically')
require("'payload'        => $json" in event_method, 'R15 event persistence does not use the validated payload')
require("remove_all_actions( 'spd_dispatch_outbox' )" in outbox and "array( __CLASS__, 'dispatch' )" in outbox, 'R15 hardened dispatcher does not exclusively own the File 03 outbox hook')
require('outbox_delivery_persist_failed' in outbox and 'outbox_failure_persist_failed' in outbox, 'R15 outbox DB uncertainty is not preserved as operational evidence')

print('Fifth fresh twenty-round sequential corrective invariants passed through R15.')
