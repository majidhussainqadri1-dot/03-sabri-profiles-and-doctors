#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
media = (ROOT / 'includes/class-spd-media.php').read_text(encoding='utf-8')

def require(ok, message):
    if not ok:
        raise SystemExit(message)

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

print('File 03 sixth-cycle R06 media scanner fail-closed invariants: PASS')
