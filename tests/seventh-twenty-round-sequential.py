#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
auth = (ROOT / 'includes/class-spd-authorization.php').read_text(encoding='utf-8')
dto = (ROOT / 'includes/trait-spd-profile-public-dto.php').read_text(encoding='utf-8')

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

print('File 03 seventh-cycle sequential invariants through R03: PASS')
