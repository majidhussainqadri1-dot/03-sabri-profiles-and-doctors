#!/usr/bin/env python3
from pathlib import Path
text=(Path(__file__).resolve().parents[1]/'includes/class-spd-db.php').read_text(encoding='utf-8')
tables={
 'profiles':['public_id char(36)','UNIQUE KEY user_id','UNIQUE KEY public_id','version bigint'],
 'fields':['UNIQUE KEY profile_field','audience varchar','source_owner varchar'],
 'slugs':['UNIQUE KEY slug','profile_current'],
 'media':['UNIQUE KEY profile_purpose','scan_provider','scan_reference'],
 'reports':['UNIQUE KEY reporter_dedupe','decision_note','version bigint'],
 'events':['UNIQUE KEY event_uuid','lease_token','lease_expires','last_error_code'],
 'idempotency':['UNIQUE KEY actor_command_key','response_json','expires_at'],
 'deletions':['UNIQUE KEY attachment_purpose','lease_token','last_error_code'],
 'migration_failures':['UNIQUE KEY user_id','next_attempt_at'],
 'professional_submissions':['UNIQUE KEY submission_uuid','payload_hash','profile_state'],
}
for table,tokens in tables.items():
    marker=f'CREATE TABLE {{$'+'%s}'%table
    if marker not in text: raise SystemExit(f'Missing table {table}')
    for token in tokens:
        if token not in text: raise SystemExit(f'{table}: missing {token}')
if "return new WP_Error( 'spd_schema_install_failed'" not in text:
    raise SystemExit('Schema install does not fail closed')
print(f'Schema checks passed ({len(tables)} owned tables).')
