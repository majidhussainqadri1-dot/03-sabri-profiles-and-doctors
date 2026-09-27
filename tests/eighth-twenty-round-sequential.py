#!/usr/bin/env python3
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def require(ok, message):
    if not ok:
        raise SystemExit(message)

main = read('sabri-profiles-doctors.php')
bridge = read('includes/class-spd-cross-file-events.php')
contracts = read('includes/class-spd-contracts.php')
outbox = read('includes/class-spd-outbox-dispatcher.php')
observability = read('includes/class-spd-observability.php')
latest = read('LATEST-PLAN-TRACEABILITY.md')
status = read('STATUS.md')
manifest = read('RELEASE-MANIFEST.md')
workflow = read('.github/workflows/fresh-eighty-round-review.yml')
latest_workflow = read('.github/workflows/latest-plan-completion.yml')
release_lock = json.loads(read('RELEASE-LOCK.json'))

rounds = []

def gate(name, ok):
    require(ok, 'Eighth 20-round review failed — ' + name)
    rounds.append(name)

gate('R01 exact candidate identity', "Version: 1.2.0-rc17" in main and "define( 'SPD_VERSION', '1.2.0-rc17' );" in main)
gate('R02 plan lineage', 'EIGHTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main)
gate('R03 bridge composition', "'class-spd-cross-file-events.php'" in main and 'SPD_Cross_File_Events::register();' in main)
gate('R04 legacy inbound event contract', all(x in bridge for x in ('DoctorVerified.v1','DoctorSuspended.v1','PublicationPublished.v1','ClinicProfileChanged.v1')))
gate('R05 current companion event aliases', all(x in bridge for x in ('DoctorVerification.Verified','DoctorVerification.Suspended','Publication.Published','ClinicProfile.Changed')))
gate('R06 external facts are invalidation only', 'purge_profile_cache' in bridge and 'spd_reconciliation_required' in bridge and 'consume_external_event' in bridge)
gate('R07 no companion truth store', 'CREATE TABLE' not in bridge and 'gdo_' not in bridge and 'sun_notifications()->' not in bridge)
gate('R08 File 19 producer registration', "FILE19_PRODUCER = 'file03-profiles'" in bridge and 'sun_register_notification_producer' in bridge)
gate('R09 File 19 event ingestion', 'sun_ingest_domain_event' in bridge and "'Profile.Reported'" in bridge and "'Profile.Moderated'" in bridge)
gate('R10 File 19 dependency declared', "'file19' => array(" in contracts and "'contract' => 'sun.event.v1'" in contracts)
gate('R11 notification boundary declared', "'notification_contract' => array(" in contracts and "'transport_owner' => 'file19'" in contracts)
gate('R12 durable outbox bridge', 'SPD_Cross_File_Events::deliver_file19_notification' in outbox)
gate('R13 degraded notification evidence', 'sabri_file24_profile_notification_degraded' in bridge and 'file19_unavailable' in bridge)
gate('R14 appeal/reopen event manifest parity', 'ProfileReportAppealReviewed.v1' in contracts and 'ProfileReportReopenedByAppeal.v1' in contracts)
gate('R15 system-check dependency health', "'file19_notification' => SPD_Cross_File_Events::file19_health()" in observability)
gate('R16 File 21 reconciliation preserved', 'spd_file21_timeline_items_adapter' in main and 'sabri_file21_profile_timeline_items_v1' in main)
gate('R17 File 26 reconciliation preserved', 'sabri_file26_owner_connector_adapters' in main and 'file26_owner_connector_adapters' in contracts)
gate('R18 exact-head workflow includes eighth gate', 'python3 tests/eighth-twenty-round-sequential.py' in workflow)
gate('R19 latest-plan package identity is dynamic', 'id: pkg' in latest_workflow and 'steps.pkg.outputs.version' in latest_workflow and 'file03-latest-plan-1.1.0-rc1' not in latest_workflow)
gate('R20 release truth synchronized', '1.2.0-rc17' in latest and '1.2.0-rc17' in status and '1.2.0-rc17' in manifest and release_lock.get('current_repository_candidate') == '1.2.0-rc17' and release_lock.get('production_authorized') is False and release_lock.get('staging_authorized') is False and release_lock.get('deployed_version_verified') is False)

print('File 03 eighth sequential review: PASS 20/20')
for i, name in enumerate(rounds, 1):
    print(f'{i:02d} GREEN — {name}')
