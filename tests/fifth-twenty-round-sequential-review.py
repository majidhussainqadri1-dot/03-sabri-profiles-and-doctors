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

# R01 — body `version` remains a supported fallback while domain mutation payloads
# stay strict: REST consumes it before the repository allowlist sees the body.
professional = section(rest, 'public function submit_professional', 'public function update_profile')
require("array( 'fields', 'save_draft', 'version' )" in professional, 'R01 professional version-body fallback is not allowlisted')
update = section(rest, 'public function update_profile', 'public function get_timeline')
require('$expected_version = $this->expected_version( $r );' in update, 'R01 profile version must be captured before payload normalization')
require("unset( $params['version'] );" in update, 'R01 REST version transport field must not leak into repository domain input')
require(update.find('$expected_version = $this->expected_version( $r );') < update.find("unset( $params['version'] );") < update.find('$repo->update_profile'), 'R01 version fallback ordering drifted')

print('Fifth fresh twenty-round sequential corrective invariants passed through R01.')
