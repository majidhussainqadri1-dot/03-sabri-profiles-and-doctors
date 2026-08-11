# File 03 — Profiles and Doctors

Canonical profile-domain source candidate for the Sabri Social Homeopathy Platform.

## Governing scope

This repository implements the profile-owned requirements of:

1. Definitive Integrated Master Plan v3.0 and later Founder-approved amendments;
2. File 03 — Profiles and Doctors Complete Master Plan 2026 v1.0 plus the 2026-08-07 central-plan addendum;
3. `FUTURE-SUPERSET-18.md` and the repository corrective-review ledgers.

File 03 owns stable public profile identity, profile fields and audiences, Founder official profile, member/doctor presentation records, slug history, profile-media references and deletion ledger, profile reports, private professional proposals, profile timeline federation, privacy export/erasure and operational evidence. It does not own membership truth, doctor verification decisions, doctor search/ranking, clinic truth, publication records, communication graph, global shell or final visual-system ownership.

## Release identity

- Plugin: `1.2.0-rc9`
- Database schema: `1.2.0`
- Contract: `1.4.0`
- PHP target matrix: `8.1`, `8.3`, `8.4`
- WordPress baseline: `7.0+`

Companion minimums are enforced by their versioned adapters in source and must be re-verified against the exact deployed companion builds during staging. Documentation must not freeze a historical companion version as current deployment truth.

## Ninth fresh sequential ten-round review

The ninth cycle started from exact `main` `4a23627b3320b7eb5957e5610da786abdec98c95` and is recorded in `NINTH-TEN-ROUND-REVIEW-2026-08-11.md`. Each round reviewed the corrected state from the previous round; no proven defect was carried into the next round.

Defect-bearing rounds: **01, 03, 08, 09, 10**.

Clean rounds: **02, 04, 05, 06, 07**.

The ninth cycle makes protected professional-submission reads fail closed on SQL uncertainty; adds DB-certain personal-data export for permanently retained canonical/historical profile slug aliases and explicit erasure-retention disclosure; latches outbox lease anomalies until an anomaly-free run; includes current outbox operational evidence in the explicit two-gate destructive uninstall; and advances release/QA identity to rc9. Round 10 also corrected release-test drift and a slug-privacy bootstrap composition defect found by runtime CI: the exporter class is now registered through the normal plugin boot lifecycle rather than self-registering during source include.

The exact final reviewed branch candidate is **not frozen until all applicable workflows succeed on the same SHA**. Candidate QA/PR/merge evidence must therefore be read from `RELEASE-MANIFEST.md` and `STATUS.md` after closure rather than inferred from an earlier rc8 cycle.

## Prior review history

The repository also retains the original 80-round corrective review, a second independent fresh 80-round sequential review, and the third through eighth fresh ten-round review ledgers. Those historical records remain regression evidence; none is allowed to freeze a later legitimate release identity or substitute for current exact-HEAD verification.

## Truthful status

Source and repository-based automated QA are candidate evidence only. Production authorization is **not** granted by this repository. Hostinger staging, exact deployed-package parity, live database/schema/migration state, real File 00/07/08/09/16/17/20/21/24/25/26 provider integration, representative role journeys, browser/device and Urdu/Arabic RTL, WCAG 2.2 AA, backup/restore, migration/rollback rehearsal and Founder acceptance remain mandatory.

File 09 and other providers must be judged from their current accepted runtime contracts at staging/deployment time; this README does not treat an older provider-repository state as present runtime truth.

**Exact deployed code is currently unverified; repository evidence must not be described as live verification.**