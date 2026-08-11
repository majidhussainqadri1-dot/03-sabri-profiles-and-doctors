#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
def text(path): return (ROOT / path).read_text(encoding='utf-8')

def require(label, condition, failures):
    if not condition: failures.append(label)

main = text('sabri-profiles-doctors.php')
lock = text('RELEASE-LOCK.json')
manifest = text('RELEASE-MANIFEST.md')
routes = text('includes/class-spd-routes.php')
plugin = text('includes/class-spd-plugin.php')
activator = text('includes/class-spd-activator.php')
central_privacy = text('includes/class-spd-central-privacy.php')
schema = text('includes/class-spd-schema-guard.php')
identity = text('includes/trait-spd-profile-identity-create.php')
media = text('includes/class-spd-media.php')
uninstall = text('uninstall.php')
ledger = text('FOURTH-TEN-ROUND-REVIEW-2026-08-11.md')

rounds = []

def gate(number, label, checks):
    failures=[]
    for name, condition in checks: require(name, condition, failures)
    rounds.append((number,label,failures))

gate(1,'historical release truth is unambiguous',[
    ('historical lock type', 'historical_source_archive_lock' in lock),
    ('current freeze recorded', '1ff55ecd91be68bbf6d68e54c630f78f901992af' in lock),
    ('historical checksums warning', 'historical provenance' in manifest.lower() and 'not' in manifest.lower()),
])
gate(2,'fallback private routes remain protected',[
    ('fallback slug helper', 'fallback_slug' in routes),
    ('mapped or fallback helper', 'is_mapped_or_fallback_page' in routes),
    ('guest redirect covers fallback pages', 'private_page_url' in routes and "wp_login_url" in routes),
])
gate(3,'fallback routes receive profile assets',[
    ('fallback asset pages', "account-profile-personal-site" in plugin and "account-profile-preview" in plugin and 'is_fallback_page' in plugin),
])
gate(4,'managed page repair is idempotent',[
    ('existing marker read first', 'current_marker' in activator and '$is_owned' in activator),
    ('marker write only when not owned', '! $is_owned && false === update_post_meta' in activator),
])
gate(5,'central native privacy data is covered',[
    ('central privacy exporter', 'sabri-profile-central-domain' in central_privacy and 'wp_privacy_personal_data_exporters' in central_privacy),
    ('delegation export and erase', 'delegation_table' in central_privacy and 'spd_profile_delegation_legal_hold' in central_privacy),
    ('appeal export and erase', 'appeals_table' in central_privacy and 'spd_profile_appeal_legal_hold' in central_privacy),
    ('runtime wiring', 'SPD_Central_Privacy' in plugin),
])
gate(6,'partial schema cannot masquerade as ready',[
    ('column guard', 'SHOW COLUMNS FROM' in schema),
    ('index guard', 'SHOW INDEX FROM' in schema),
    ('base central future guards', all(x in schema for x in ('base_ready','central_ready','future_ready'))),
    ('boot uses exact guards', 'SPD_Schema_Guard::base_ready()' in plugin and 'SPD_Schema_Guard::central_ready()' in plugin and 'SPD_Schema_Guard::future_ready()' in plugin),
    ('activation uses exact guards', 'SPD_Schema_Guard::base_ready()' in activator and 'SPD_Schema_Guard::central_ready()' in activator and 'SPD_Schema_Guard::future_ready()' in activator),
])
gate(7,'identity and slug reads fail closed',[
    ('identity read error', 'spd_profile_identity_read_failed' in identity),
    ('slug lock read error', 'spd_profile_slug_lock_read_failed' in identity),
    ('slug registry read error', 'spd_slug_lookup_failed' in identity),
    ('slug exhaustion error', 'spd_slug_space_exhausted' in identity),
    ('post refresh read guarded', 'spd_profile_refresh_read_failed' in identity),
])
gate(8,'media privacy and deletion queue record uncertainty',[
    ('media queue failure evidence', 'spd_last_media_queue_error' in media and 'record_queue_error' in media),
    ('privacy scan failure guarded', 'media_privacy_scan_failed' in media),
    ('deletion read failure guarded', 'deletion_queue_read_failed' in media),
    ('lease result persistence guarded', 'deletion_result_persist_failed' in media and 'deletion_lease_lost' in media),
])
gate(9,'destructive uninstall owns orphan cleanup',[
    ('page ownership marker recovery', "meta_key='_spd_managed_page_key'" in uninstall and 'managed_ids' in uninstall),
    ('media failure option removed', 'spd_last_media_queue_error' in uninstall),
    ('migration integrity option removed', 'spd_last_migration_integrity_error' in uninstall),
    ('retention failure option removed', 'spd_last_retention_error' in uninstall),
    ('two destructive gates preserved', 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' in uninstall and 'spd_purge_on_uninstall' in uninstall),
])
gate(10,'fourth-review release identity and truth boundary are explicit',[
    ('rc4 source identity', "Version: 1.2.0-rc4" in main and "SPD_VERSION', '1.2.0-rc4'" in main),
    ('fourth review plan marker', 'FOURTH-TEN-ROUND-CORRECTIVE-REVIEW' in main),
    ('ten defect-bearing rounds recorded', '**01, 02, 03, 04, 05, 06, 07, 08, 09, 10**' in ledger),
    ('live truth remains unverified', 'Exact deployed code remains unverified' in ledger),
])

bad=False
for number,label,failures in rounds:
    if failures:
        bad=True
        print(f'Round {number:02d}: FAIL — {label}', file=sys.stderr)
        for failure in failures: print('  - '+failure, file=sys.stderr)
    else:
        print(f'Round {number:02d}: PASS — {label}')
if len(rounds)!=10:
    print(f'ERROR: expected 10 rounds, got {len(rounds)}', file=sys.stderr); sys.exit(2)
if bad: sys.exit(1)
print('File 03 fourth fresh ten-round corrective invariants: PASS')
