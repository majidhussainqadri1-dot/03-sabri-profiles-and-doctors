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
activator = text('includes/class-spd-activator.php')
appeals = text('includes/class-spd-appeals.php')

# R01 — body `version` remains a supported fallback while domain mutation payloads
# stay strict: REST consumes it before the repository allowlist sees the body.
professional = section(rest, 'public function submit_professional', 'public function update_profile')
require("array( 'fields', 'save_draft', 'version' )" in professional, 'R01 professional version-body fallback is not allowlisted')
update = section(rest, 'public function update_profile', 'public function get_timeline')
require('$expected_version = $this->expected_version( $r );' in update, 'R01 profile version must be captured before payload normalization')
require("unset( $params['version'] );" in update, 'R01 REST version transport field must not leak into repository domain input')
require(update.find('$expected_version = $this->expected_version( $r );') < update.find("unset( $params['version'] );") < update.find('$repo->update_profile'), 'R01 version fallback ordering drifted')

# R04 — Central profile/delegation/report endpoints use explicit request shapes;
# personal-site version transport is consumed before the domain allowlist.
require('private function reject_unknown' in central_rest, 'R04 Central REST strict request helper missing')
for token in (
    'spd_unknown_personal_site_field',
    'spd_unknown_share_rotation_field',
    'spd_unknown_delegate_field',
    'spd_unknown_delegate_revoke_field',
    'spd_unknown_safety_report_field',
    'spd_unknown_appeal_field',
):
    require(token in central_rest, f'R04 Central REST request-shape invariant missing: {token}')
central_update = section(central_rest, 'public function update_personal_site', 'public function rotate_share')
require("'version'" in central_update and '$version = $this->version( $r );' in central_update and "unset( $p['version'] );" in central_update, 'R04 Central REST version-body transport normalization missing')
require(central_update.find('$version = $this->version( $r );') < central_update.find("unset( $p['version'] );") < central_update.find('update_central_profile'), 'R04 Central REST version ordering drifted')

# R06 — activation/repair must not accept a File 03-owned required page that is
# still draft/private/trash merely because its marker/content match.
managed = section(activator, 'private static function managed_page', 'private static function migrate_legacy_options')
require(managed.count("'publish' !==") >= 2, 'R06 managed route publication-state checks missing')
require("$changes['post_status'] = 'publish';" in managed, 'R06 stored managed route is not restored to publish')
require("wp_update_post( array( 'ID' => absint( $slug_page->ID ), 'post_status' => 'publish' ), true )" in managed, 'R06 discovered owned/exact route is not restored to publish')

# R09 — due process must let the reporter appeal a rejected complaint and the
# profile subject appeal enforcement, then require a separate moderator to decide.
for token in ("'reporter'", "'profile_subject'", "ProfileReportAppealed.v2", "ProfileReportAppealReviewed.v1", 'spd_appeal_independent_reviewer_required'):
    require(token in appeals, f'R09 appeal due-process invariant missing: {token}')
require("in_array( $status, array( 'rejected', 'closed' ), true )" in appeals, 'R09 reporter appeal eligibility missing')
require("in_array( $status, array( 'actioned', 'closed' ), true )" in appeals, 'R09 profile-subject enforcement appeal eligibility missing')
require("$reviewer_id === absint( $row['requested_by'] )" in appeals and "$reviewer_id === absint( $row['assigned_to'] )" in appeals, 'R09 independent-review separation missing')
require("/appeals/(?P<appeal_uuid>" in central_rest and 'public function review_appeal' in central_rest, 'R09 appeal review REST operation missing')
require('spd_unknown_appeal_review_field' in central_rest, 'R09 appeal review strict request shape missing')

print('Fifth fresh twenty-round sequential corrective invariants passed through R09.')
