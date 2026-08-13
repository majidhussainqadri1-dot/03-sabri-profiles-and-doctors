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

print('Fifth fresh twenty-round sequential corrective invariants passed through R04.')
