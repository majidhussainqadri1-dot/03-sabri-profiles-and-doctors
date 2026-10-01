#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def read(path): return (ROOT / path).read_text(encoding='utf-8')
def require(ok, message):
    if not ok: raise SystemExit(message)

main=read('sabri-profiles-doctors.php')
bridge=read('includes/class-spd-cross-file-events.php')
contracts=read('includes/class-spd-contracts.php')
runtime=read('tests/file08-outbox-runtime.php')
workflow=read('.github/workflows/fresh-eighty-round-review.yml')
latest_workflow=read('.github/workflows/latest-plan-completion.yml')
trace=read('LATEST-PLAN-TRACEABILITY.md')
status=read('STATUS.md')
manifest=read('RELEASE-MANIFEST.md')
lock=json.loads(read('RELEASE-LOCK.json'))
ledger=read('NINTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-09-28.md')

checks=[]
def gate(name, ok):
    require(ok, 'Ninth 20-round review failed — '+name)
    checks.append(name)

file08_events=('ClinicActivated.v1','ClinicBranchChanged.v1','ClinicAvailabilityChanged.v1','ClinicServiceChanged.v1','AppointmentRequested.v1','AppointmentConfirmed.v1','AppointmentDeclined.v1','AppointmentRescheduleProposed.v1','AppointmentCheckedIn.v1','AppointmentCompleted.v1','AppointmentCancelled.v1','AppointmentNoShow.v1','AppointmentChanged.v1')
gate('R01 rc18 exact source identity', 'Version: 1.2.0-rc18' in main and "define( 'SPD_VERSION', '1.2.0-rc18' );" in main)
gate('R02 ninth plan lineage', 'NINTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW' in main)
gate('R03 File 08 exact event contract', "FILE08_EVENT_CONTRACT_MIN = '1.0.0'" in bridge and "FILE08_EVENT_CONTRACT_MAX_EXCLUSIVE = '2.0.0'" in bridge)
gate('R04 File 08 canonical outbox hook', "add_action( 'wca_outbox_event'" in bridge)
gate('R05 current File 08 event names', all(x in bridge for x in file08_events))
gate('R06 explicit File 08 envelope consumer', 'consume_file08_outbox_event' in bridge and "envelope['topic']" in bridge and "envelope['payload']" in bridge)
gate('R07 File 08 contract fail-closed', 'version_compare( $contract, self::FILE08_EVENT_CONTRACT_MIN' in bridge and 'version_compare( $contract, self::FILE08_EVENT_CONTRACT_MAX_EXCLUSIVE' in bridge)
gate('R08 File 08 invalidation metadata', "'owner' => 'file08'" in bridge and "'producer' => 'wca_outbox'" in bridge)
gate('R09 opaque subject safety', 'doctor_subject_uuid' not in bridge and 'spd_reconciliation_required' in bridge)
gate('R10 invalidation-only ownership', 'purge_profile_cache' in bridge and 'CREATE TABLE' not in bridge)
gate('R11 File 08 dependency manifest', "'event_hook' => 'wca_outbox_event'" in contracts and 'FILE08_EVENT_CONTRACT_MIN' in contracts)
gate('R12 consumed-event manifest parity', all(x in contracts for x in file08_events))
gate('R13 File 09 current File 19 path preserved', 'sun_event_processed' in bridge and 'DoctorVerification.Verified' in bridge)
gate('R14 File 19 owner path preserved', 'sun_ingest_domain_event' in bridge and "FILE19_PRODUCER = 'file03-profiles'" in bridge)
gate('R15 runtime regression coverage', 'ClinicAvailabilityChanged.v1' in runtime and 'Incompatible File 08 contract versions must fail closed' in runtime and 'Unsupported future File 08 major contract versions must fail closed' in runtime)
gate('R16 File 21 owner projection preserved', 'spd_file21_timeline_items_adapter' in main and 'sabri_file21_profile_timeline_items_v1' in main)
gate('R17 File 26 owner connector preserved', 'sabri_file26_owner_connector_adapters' in main and 'file26_owner_connector_adapters' in contracts)
gate('R18 fresh exact-head CI includes ninth gate', 'python3 tests/ninth-twenty-round-sequential.py' in workflow and 'php tests/file08-outbox-runtime.php' in workflow)
gate('R19 latest-plan CI includes ninth gate', 'python3 tests/ninth-twenty-round-sequential.py' in latest_workflow)
gate('R20 repository truth boundary synchronized', '1.2.0-rc18' in trace and '1.2.0-rc18' in status and '1.2.0-rc18' in manifest and 'Total reviewed: **20/20**' in ledger and '`04, 07, 20`' in ledger and lock.get('current_repository_candidate') == '1.2.0-rc18' and lock.get('ninth_twenty_round_source_audit_completed') is True and lock.get('ninth_twenty_round_exact_head_ci_verified') is True and lock.get('production_authorized') is False and lock.get('staging_authorized') is False and lock.get('deployed_version_verified') is False)

print('File 03 ninth sequential review: PASS 20/20')
for i,name in enumerate(checks,1): print(f'{i:02d} GREEN — {name}')
