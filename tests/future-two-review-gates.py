#!/usr/bin/env python3
from pathlib import Path
import re, sys
ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
future=t('includes/class-spd-future-profile.php')
rest=t('includes/class-spd-future-rest.php')
privacy=t('includes/class-spd-future-privacy.php')
contracts=t('includes/class-spd-contracts.php')
main=t('sabri-profiles-doctors.php')
uninstall=t('uninstall.php')
trace=t('FUTURE-SUPERSET-18.md')
frontend=t('includes/trait-spd-frontend-future.php')
js=t('assets/js/future-profiles.js')
all_source='\n'.join((future,rest,privacy,contracts,main,uninstall,frontend,js))

rounds=[]
def gate(name, checks):
    failed=[label for label,ok in checks if not ok]
    rounds.append((name,failed))

# Fresh Review/Fix Gate 1: requirement completeness, canonical ownership, mutations and lifecycle.
gate('Review/Fix Gate 1 — requirements, ownership and command boundaries', [
 ('18 future trace rows', all(f'| F03-FUT-{i:02d} |' in trace for i in range(1,19))),
 ('future requirements generated in contract', 'F03-FUT-%02d' in future and 'SPD_Future_Profile::requirements()' in contracts),
 ('no direct File00/File09/File17/File26 companion table access', not re.search(r'FROM\s+[^\n]*(?:smc_|gdo_|sun_|ddd_|file26)', future, re.I)),
 ('credentials projected from File09', 'sabri_file09_verifiable_credentials_v1' in future and 'raw_evidence_exposed' in future),
 ('learning achievements projected', 'sabri_profile_learning_passport_v1' in future),
 ('AI stays File16-owned', 'sabri_file16_grounded_profile_ask_v1' in future),
 ('contact transport stays File17-owned', 'sabri_file17_profile_contact_relay_v1' in future),
 ('search/coverage stays provider-based', 'sabri_profile_knowledge_coverage_v1' in future and 'paid_influence' in future),
 ('federation transport stays external', "'transport_owner' => 'external'" in future and 'sabri_federation_actor_transport_v1' in future),
 ('only three additive future data tables', future.count('CREATE TABLE') == 3),
 ('future schema activation/repair wired', 'SPD_Future_Profile::install_schema()' in t('includes/class-spd-activator.php') and 'spd_future_schema_version' in t('includes/class-spd-plugin.php')),
 ('future mutations use canonical idempotency', 'future_idempotency_begin' in rest and 'future_idempotency_complete' in rest and 'future_idempotency_fail' in rest),
 ('browser mutations send keys', "'Idempotency-Key'" in js and js.count('idempotent: true') >= 4),
 ('lifecycle governance protects legacy', "'legacy' === $lifecycle && ! $is_governor" in future),
 ('retired/legacy suppress service actions', "$dto['contacts'] = array()" in future and "unset( $dto['clinic']['appointment_url'] )" in future),
])

# Fresh Review/Fix Gate 2: adversarial privacy, medical safety, interoperability and provider degradation.
gate('Review/Fix Gate 2 — adversarial privacy, safety and degradation', [
 ('public selective disclosure is signed', 'hash_hmac' in future and 'hash_equals' in future),
 ('disclosure bounded and expiring', 'DISCLOSURE_MAX_TTL = 86400' in future and 'spd_disclosure_expired' in future),
 ('disclosure revocable with share epoch', 'share_epoch' in future and 'spd_disclosure_revoked' in future),
 ('AI blocks medical scope', all(x in future for x in ('diagnos','prescrib','dosage','emergency','spd_ai_scope_restricted'))),
 ('AI requires grounded scope', "'public_professional_work'" in future and "empty( $claim['grounded'] )" in future),
 ('AI citations limited to same origin', 'SPD_Helpers::same_origin_url' in future and "claim['citations']" in future),
 ('external verified links require HTTPS', 'safe_external_url' in future and "array( 'https' )" in future),
 ('contact relay hides address', "'address_hidden' => true" in future),
 ('FHIR explicitly excludes clinical record', "'clinical_record' => false" in future and 'PractitionerRole' in future),
 ('federation requires explicit opt-in', "if ( ! $opt_in )" in future and 'federation_opt_in' in future),
 ('future data has privacy export', 'wp_privacy_personal_data_exporters' in privacy and 'export_profile_data' in privacy),
 ('future data has privacy erasure', 'wp_privacy_personal_data_erasers' in privacy and 'erase_profile_data' in privacy),
 ('legal hold is explicit', 'spd_future_profile_legal_hold' in privacy),
 ('guarded uninstall includes all future tables', all(x in uninstall for x in ('profile_translations','profile_attestations','profile_future_state','SPD_ALLOW_DESTRUCTIVE_UNINSTALL'))),
 ('no third-party QR/tracking endpoint', not re.search(r'chart\.googleapis|api\.qrserver|quickchart', all_source, re.I)),
 ('no paid/donor advantage', not re.search(r'paid[_ -]?(?:boost|rank|verification)|donor[_ -]?(?:boost|rank)', all_source, re.I)),
 ('truth boundary preserved', 'staging candidate' in t('readme.txt').lower() and 'Exact deployed code remains unverified' in trace),
])

bad=False
for name,failed in rounds:
    if failed:
        bad=True; print('FAIL — '+name, file=sys.stderr)
        for x in failed: print('  - '+x, file=sys.stderr)
    else: print('PASS — '+name)
if bad: sys.exit(1)
print('Two fresh post-code review gates passed after future-superset corrections.')
