# File 03 — Sabri Profiles and Doctors

Plan-completion corrective candidate for the Sabri Social Homeopathy Platform.

## Current release identity

- Plugin version: `1.0.0-rc1`
- Database schema: `1.0.0`
- Contract version: `1.0.0`
- Required File 00 minimum: `1.2.3`
- Required PHP: `8.1+`
- Target WordPress baseline: `7.0+`
- Status: source implementation and local static/unit review complete; GitHub CI and Hostinger staging acceptance pending.

## Canonical ownership

File 03 owns canonical public-profile identity, profile presentation fields, field audiences, profile media references, stable public UUIDs, slug history, profile-report intake, and profile timeline slots. It does not own membership identity, doctor-verification decisions, doctor directory/ranking, clinic truth, publication truth, global shell, or final platform-wide visual-system ownership.

## Canonical routes

- `/founder/`
- `/profile/{public_id}/`
- `/profile/{public_id}/timeline/`
- `/profile/{public_id}/report/`
- `/account/profile/`
- `/u/{slug}/` is a redirect-only compatibility alias.

## Public contracts

- `spd_get_public_profile( $identity, $viewer_id = 0 )`
- `spd_get_profile_timeline( $identity, $args = array(), $viewer_id = 0 )`
- `spd_get_profile_contract_manifest()`
- REST namespace: `/wp-json/sabri-profiles/v1/`

## Security and privacy characteristics

- File 00 identity and capability assertions are required; missing or incompatible File 00 fails closed.
- No direct reads or writes to companion-module internal tables.
- Public/private DTO separation and audience-aware rendering.
- Minor accounts receive private/restricted defaults; public direct contact is prohibited.
- Optimistic concurrency and request idempotency on profile updates.
- Strict image validation, synchronous safety-scan hook, metadata re-encoding, ownership tracking and physical erasure.
- Private/member/contact views receive `no-store`, `noindex`, `nofollow`, and `noarchive` controls.
- Append-only outbox/audit events with retries and dead-letter state.
- Non-destructive uninstall by default; destructive purge requires a constant and administrator setting.

## Local verification completed

- PHP syntax lint over all PHP files.
- Architecture boundary scan.
- Full File 03 FR/NFR token-to-implementation coverage scan.
- Security/state-machine unit checks.
- Four review/fix cycles, including exact-head CI evidence review, recorded in `REVIEW-CYCLES.md`.

## Remaining release gates

GitHub Actions, deterministic package evidence, WordPress/Hostinger fresh-install and upgrade tests, real File 00/09/20/21/25 contracts, browser/device/RTL/accessibility tests, migration/rollback rehearsal, backup/restore proof and Founder staging acceptance remain mandatory before production.
