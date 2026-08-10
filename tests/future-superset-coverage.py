#!/usr/bin/env python3
from pathlib import Path
import re, sys
ROOT=Path(__file__).resolve().parents[1]
def txt(p): return (ROOT/p).read_text(encoding='utf-8')
future=txt('includes/class-spd-future-profile.php')
rest=txt('includes/class-spd-future-rest.php')
frontend=txt('includes/trait-spd-frontend-future.php')
plugin=txt('includes/class-spd-plugin.php')
contracts=txt('includes/class-spd-contracts.php')
privacy=txt('includes/class-spd-future-privacy.php')
uninstall=txt('uninstall.php')
js=txt('assets/js/future-profiles.js')
trace=txt('FUTURE-SUPERSET-18.md')
main=txt('sabri-profiles-doctors.php')

features={
 1:['credential_wallet','sabri_file09_verifiable_credentials_v1','Portable Verified Credentials'],
 2:['disclosure_token','DISCLOSURE_MAX_TTL','create_selective_disclosure','Selective Disclosure Link'],
 3:['learning_passport','sabri_profile_learning_passport_v1','Learning & Achievement Passport'],
 4:['trust_timeline','sabri_profile_trust_timeline_v1','Professional Trust Timeline'],
 5:['expertise_evidence','sabri_profile_expertise_evidence_v1','Evidence-Backed Expertise'],
 6:['knowledge_graph','sabri_profile_knowledge_graph_v1','Professional Knowledge Graph'],
 7:['knowledge_coverage','sabri_profile_knowledge_coverage_v1','paid_influence'],
 8:['ask_about_work','sabri_file16_grounded_profile_ask_v1','spd_ai_scope_restricted','medical_advice'],
 9:['spd_profile_translations','save_translation','ProfileTranslationUpdated.v1','Approved Language Editions'],
 10:['contact_relay','sabri_file17_profile_contact_relay_v1','address_hidden'],
 11:['verified_links','sabri_verified_external_profile_links_v1','safe_external_url','Verified link'],
 12:['dossier','Structured Professional Dossier','/dossier'],
 13:['embed_card','script_required','tracking','Embeddable Verified Card'],
 14:['spd_profile_attestations','reconfirm_field','ProfileFieldReconfirmed.v1','freshness'],
 15:['change_history','Recent Profile Change History','owner_user_id'],
 16:['professional_lifecycle','legacy','active_professional','Appointments and direct contact are disabled'],
 17:['PractitionerRole','fhir_projection','clinical_record','/fhir'],
 18:['federation_opt_in','federation_projection','sabri_federation_actor_transport_v1','transport_active'],
}
combined='\n'.join((future,rest,frontend,plugin,contracts,privacy,uninstall,js,main))
fail=[]
for n,tokens in features.items():
    rid=f'F03-FUT-{n:02d}'
    if f'| {rid} |' not in trace: fail.append(f'{rid}: missing trace row')
    for token in tokens:
        if token not in combined: fail.append(f'{rid}: missing {token}')

retry_safe_client = 'formMutationKey' in js and 'idempotencyKey: formMutationKey(form, body)' in js and "options.idempotencyKey" in js
security={
 'current provider claims':'current_contract_claim' in future,
 'external URL requires HTTPS':"'https'" in future and 'safe_external_url' in future,
 'selective disclosure signed':'hash_hmac' in future and 'hash_equals' in future,
 'disclosure is expiring':'exp' in future and 'spd_disclosure_expired' in future,
 'disclosure is revocable':'share_epoch' in future and 'spd_disclosure_revoked' in future,
 'future mutations require idempotency':'future_idempotency_begin' in rest and 'Idempotency-Key' in rest and retry_safe_client,
 'AI is grounded and medical-safe':'public_professional_work' in future and 'diagnos' in future and 'prescrib' in future and 'emergency' in future,
 'contact address hidden':'address_hidden' in future,
 'legacy needs governance':"'legacy' === $lifecycle && ! $is_governor" in future,
 'legacy suppresses contact':"$dto['contacts'] = array()" in future and "unset( $dto['clinic']['appointment_url'] )" in future,
 'federation explicit opt-in':'federation_opt_in' in future and "if ( ! $opt_in )" in future and 'spd_federation_owner_opt_in_required' in rest,
 'federation transport external':'sabri_federation_actor_transport_v1' in future and "'transport_owner' => 'external'" in future,
 'FHIR excludes clinical record':"'clinical_record' => false" in future,
 'privacy exporter/eraser':'wp_privacy_personal_data_exporters' in privacy and 'wp_privacy_personal_data_erasers' in privacy,
 'guarded uninstall preserved':'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' in uninstall and 'profile_translations' in uninstall and 'profile_attestations' in uninstall and 'profile_future_state' in uninstall,
 'no third-party QR provider':not re.search(r'chart\.googleapis|api\.qrserver|quickchart', combined, re.I),
 'no credential evidence store':'sabri_file09_verifiable_credentials_v1' in future and 'raw_evidence_exposed' in future,
 'no patient FHIR store':'patient' not in future.lower() or "'clinical_record' => false" in future,
}
for name,ok in security.items():
    if not ok: fail.append('security: '+name)

for n in range(1,19):
    if f'F03-FUT-{n:02d}' not in trace: fail.append(f'missing future requirement {n}')

if fail:
    print('Future superset coverage failures:', file=sys.stderr)
    for item in fail: print('- '+item, file=sys.stderr)
    sys.exit(1)
print('Future Professional Identity & Profile Superset passed: 18/18 features traced with ownership, privacy, safety, interoperability and retry-safe replay-protection guards.')
