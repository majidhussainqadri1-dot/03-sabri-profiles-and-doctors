# File 03 Migration and Rollback Plan

## Source states

Supported inputs are the historical File 03 `0.1.0` baseline, corrective `0.2.0`, and a fresh installation.

## Additive migration

1. Acquire an activation/migration lock.
2. Install or upgrade only File 03-owned tables with `dbDelta`.
3. Create File 03-owned route pages without overwriting unrelated page content.
4. Allocate opaque UUIDv4 public profile IDs lazily and in resumable batches.
5. Allocate collision-safe aliases and retain slug history.
6. Import the legacy whole-profile visibility and public phone/WhatsApp consent once, with minor-safe overrides.
7. Preserve File 00 and File 09 as canonical identity/verification owners; do not infer verified status from labels or roles.
8. Register contracts and rebuild only File 03 caches/projections.

## Batch and interruption behavior

- Migration advances by the highest processed WordPress user ID, not an offset.
- Batch size is 100 users.
- A lock prevents concurrent workers.
- Each user receives an idempotent `_spd_v1_migrated` marker.
- Existing File 03 records are read before mutation; unique user/public-ID/slug constraints prevent duplicates.

## Dry run and evidence

The System Check reports table, route, cron, queue and migration state. Before staging cutover, record source counts, resulting profile counts, slug collisions, imported visibility choices, skipped/failed users and sample public/private DTO comparisons.

## Rollback

- Do not drop new tables during rollback.
- Disable File 03 mutations through Safe Mode.
- Restore the pre-migration database/files backup where rollback requires data reversal.
- Re-activate the previous package only after validating its schema compatibility.
- Preserve new post-cutover data through export/reconciliation rather than blindly overwriting it.
- Flush only File 03 rewrite rules/caches and re-run representative owner/public/minor/doctor journeys.

## Destructive uninstall

Destructive purge is not a rollback mechanism. It requires both `SPD_ALLOW_DESTRUCTIVE_UNINSTALL=true` and the `spd_purge_on_uninstall` option, then removes only File 03-owned records, pages and proven-owned media.
