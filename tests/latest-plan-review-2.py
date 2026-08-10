#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php='\n'.join(p.read_text(encoding='utf-8') for p in (root/'includes').glob('*.php'))
js=(root/'assets/js/profiles.js').read_text(encoding='utf-8')
css=(root/'assets/css/profiles.css').read_text(encoding='utf-8')
checks={
 'minor fail-closed':'is_minor',
 'current provider claims':'current_contract_claim',
 'same-origin appointment':'same_origin_url',
 'owner-only analytics':'analytics_projection',
 'no-store preview':'private_headers',
 'appeal':'request_report_appeal',
 'rate limit':'spd_report_rate_limited',
 'share revocation':'share_epoch',
 'delegation expiry':'expires_at',
 'medical outcome guard':'spd_unsafe_outcome_claim',
}
missing=[name for name,token in checks.items() if token not in php]
if missing: raise SystemExit('Review 2 failed: '+', '.join(missing))
if 'https://api.qrserver' in js or 'https://chart.googleapis' in js: raise SystemExit('External QR tracker forbidden')
if '#087A4E' not in css or 'prefers-reduced-motion' not in css or 'spd-preview-frame--rtl' not in css: raise SystemExit('Green/a11y/RTL controls missing')
print('Fresh review gate 2 passed: privacy, delegation, provider failure, safety, QR and accessibility guards present.')
