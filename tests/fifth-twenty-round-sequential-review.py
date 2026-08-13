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
observability = text('includes/class-spd-observability.php')
activator = text('includes/class-spd-activator.php')

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

print('Fifth fresh twenty-round sequential corrective invariants passed through R06.')
