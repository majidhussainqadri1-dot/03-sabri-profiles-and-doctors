# File 03 Hostinger Staging Acceptance

Every item requires dated evidence, environment/version details and reviewer identity.

## Installation and migration

- Fresh install on the current WordPress/PHP/LiteSpeed staging snapshot.
- Upgrade from File 03 `0.2.0` with database/files backup and isolated restore proof.
- Repeated activation and migration produce no duplicate profile, UUID, slug, page, event or cron.
- Interrupted batch resumes from the last user ID.
- Legacy `/member-profile/?user=` and `/u/{slug}/` resolve safely to the stable UUID route.
- Rollback rehearsal preserves post-cutover data or documents a tested reconciliation procedure.

## Identity, authorization and privacy

- Missing/incompatible File 00 fails closed.
- Founder identity is unique and generic moderation cannot alter Founder state.
- Owner, other member, contact, guest, moderator, suspended user, rejected user and minor/guardian cases are tested.
- Every field audience is verified from server-rendered HTML, REST output, cache, search projection and deep links.
- Minor profile defaults and contact restrictions are verified.
- IDOR, mass assignment, stale version, concurrent update, duplicate idempotency key and replay tests pass.
- Contact revocation immediately purges File 03 and downstream search/cache projections.

## Doctor and companion contracts

- File 09 badge/approved-field contract is version-compatible and fail-closed when malformed/stale.
- File 07 consumes File 03 profile DTO without File 03 owning directory ranking/search.
- File 08 supplies only public clinic projection.
- Files 21/10/11/05 supply bounded, public, cursor-paginated timeline items.
- File 20 route/layout and File 25 component/token contracts render without duplicate shell or visual owner.
- File 24 receives assurance and profile-report events without taking native enforcement ownership.

## Media

- JPG/PNG/WebP signature, size, dimension and decompression/pixel-limit cases.
- Renamed/non-image/polyglot/corrupt files rejected.
- Metadata re-encoding and accessible alt/focal-position behavior verified.
- Scan rejection/pending states never enter the public WordPress media store.
- Replacement and privacy erasure remove only proven File 03-owned files.

## UI and accessibility

- 320, 375, 768, 1024, 1440 and 1920 pixel widths.
- 200% and 400% zoom/reflow; no horizontal page overflow.
- Urdu/Arabic RTL and long mixed-language content.
- Keyboard sequence, visible focus, labels, error association, landmarks and screen-reader announcements.
- Reduced motion, forced colors and approximate 44px touch targets.
- Loading, empty, partial-provider, private, restricted, gone, safe-mode, success and failure states.

## Operations and resilience

- Outbox delivery, retry, exponential delay and dead-letter behavior.
- System Check redacts private data and never mutates companion stores.
- Repair dry run and execution touch only File 03-owned resources.
- Safe Mode blocks mutations and preserves safe public reading.
- Queue/database/cache/provider failure tests produce stable errors and trace IDs.
- RPO ≤24h and RTO ≤8h foundation restore drill, including cache/index rebuild and one real-role journey.

## Acceptance decision

Production authorization remains `NO` until every blocker is closed, both final review rounds are rerun against the exact package, the Founder accepts real content on mobile and desktop, and the exact package checksum is recorded.
