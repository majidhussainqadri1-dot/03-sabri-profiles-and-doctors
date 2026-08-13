#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)

main = text('sabri-profiles-doctors.php')
contracts = text('includes/class-spd-contracts.php')
central = text('includes/class-spd-central-profile.php')
future = text('includes/class-spd-future-profile.php')

# R19 — companion modules consume published File 03 PHP functions. Cross-file
# provider callbacks must not be able to escape those public boundaries as
# uncaught Throwables.
require('function spd_file03_contract_call' in main, 'R19 public contract exception boundary missing')
require("catch ( Throwable $exception )" in main, 'R19 public contract exception containment missing')
require('spd_profile_contract_provider_unavailable' in main and "'status' => 503" in main, 'R19 public contract does not fail closed with explicit 503')
for function_name in (
    'spd_get_public_profile',
    'spd_get_personal_site_profile',
    'spd_get_search_projection',
    'spd_get_profile_timeline',
):
    start = main.find(f'function {function_name}')
    require(start >= 0, f'R19 published contract missing: {function_name}')
    chunk = main[start:start+1200]
    require('spd_file03_contract_call' in chunk, f'R19 published contract is not guarded: {function_name}')
require('function spd_delegate_can_manage_profile_scope' in main and 'return false;' in main, 'R19 boolean delegation contract is not fail closed')

# R19 — the machine-readable contract must discover the strict appeal workflow
# added during this same candidate without pretending that another file owns it.
manifest_start = main.find('function spd_get_profile_contract_manifest')
require(manifest_start >= 0, 'R19 published manifest function missing')
manifest = main[manifest_start:manifest_start+3500]
for marker in (
    'rc15_extensions',
    '/sabri-profiles/v1/reports/{report_uuid}/appeal',
    '/sabri-profiles/v1/appeals/review-queue',
    '/sabri-profiles/v1/appeals/{appeal_uuid}/review',
    'ProfileReportAppealed.v1',
    'ProfileReportAppealReviewed.v1',
    'ProfileReportReopenedByAppeal.v1',
    "'owner' => 'file03'",
):
    require(marker in manifest, f'R19 manifest extension missing: {marker}')

# Ownership remains projection-only for cross-file facts; this review must not
# introduce direct companion table access or duplicate canonical writes.
combined = central + future + main
for forbidden in ('smc_applications', 'gdo_', 'wp_gdo_', 'appointments SET', 'reviews SET'):
    require(forbidden not in combined, f'R19 cross-owner direct-write/table coupling detected: {forbidden}')
require('SPD_CONTRACT_VERSION' in contracts, 'R19 base contract manifest lost contract-version identity')

print('File 03 fifth-cycle R19 public-contract invariants: PASS')
