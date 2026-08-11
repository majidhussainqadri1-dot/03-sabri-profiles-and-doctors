# Changelog

## 1.2.0-rc3 — Third fresh ten-round corrective hardening

- Completed a fresh 10-round sequential review from exact `main` baseline `ffcd790b831e2ae028c48f8aa664e4c496c115e0`; defects were found in rounds `01, 02, 03, 04, 06, 07, 08, 09, 10`, while round `05` was clean.
- Synchronized repository truth documentation and preserved the explicit staging/live evidence boundary.
- Made future/profile REST responses no-store, blocked future mutations in Safe Mode and required fail-closed lifecycle preflight.
- Bound owner, guardian and delegated writes to native profile state plus current File 00/File 09 authority.
- Bound idempotency completion/failure to the exact reservation so an abandoned stale request cannot finalize or delete a reclaimed request.
- Made required upload owner/purpose/state/scan metadata persistence fail closed; an attachment is removed if those bindings cannot be stored.
- Made one explicit future-state read authoritative for lifecycle/contact/appointment/federation/FHIR projections so a secondary read failure cannot reactivate retired/legacy behavior.
- Made base and future privacy export/erasure retry on SQL/schema uncertainty instead of treating uncertainty as empty success.
- Added a post-batch migration integrity gate that independently re-proves remaining users and retry/dead failure counts before migration completion can stand.
- Added `THIRD-TEN-ROUND-REVIEW-2026-08-11.md`, `tests/third-ten-round-review.py`, and the permanent exact-candidate CI gate; advanced source identity from rc2 to rc3 so materially different source trees cannot share one release identifier.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback and operational acceptance remain separate gates.

## 1.2.0-rc2 — Corrective hardening and second fresh 80-round review

- Completed the original numbered 80-round corrective review and then a second independent fresh 80-round sequential review on the corrected repository state.
- The second fresh cycle recorded 28 defect-bearing rounds and 52 clean rounds; every discovered repository-level finding was corrected before the following round.
- Revalidated current File 00 eligibility/suspension and professional authority at decision time; tightened fail-closed profile reads and field-store failures.
- Added bounded abandoned-idempotency recovery, exact-value lock takeover/release, process-safe rate limiting and migration serialization.
- Hardened File 08 clinic/review binding, cross-provider identity binding, signed profile/filter-bound timeline cursors and revocation-sensitive no-store behavior.
- Added media privacy reconciliation after external eligibility changes, stronger delegation expiry/use-time revalidation, SQL-failure-safe privacy export and truthful retention failure evidence.
- Preserved PHP 8.1 compatibility after the stronger idempotency/cursor/provider-guard contracts and aligned historical regression gates with the hardened implementation.
- PR #14 merged the reviewed source tree to `main` as `ffcd790b831e2ae028c48f8aa664e4c496c115e0`.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback and operational acceptance remain separate gates.

## 1.2.0-rc1 — Future Professional Identity & Profile Superset — 18 Enhancements

- Added `F03-FUT-01..18` as a bounded extension of the approved File 03 and central-plan architecture.
- Added provider-backed portable credentials, learning/achievement passport, professional trust timeline, evidence-backed expertise, knowledge graph and transparent knowledge-coverage projections.
- Added signed, expiring, revocable selective-disclosure packets restricted to public-safe scopes.
- Added a File 16 grounded public-work assistant that rejects diagnosis, prescription, dose, emergency replacement and cure/outcome requests instead of falling back to local generative answers.
- Added owner-approved multilingual editions, File 17 privacy-safe contact relay and provider-verified HTTPS external/institutional links.
- Added one-page/full professional dossier projection and scriptless, tracking-free embeddable verified profile card.
- Added field-level freshness attestations/reconfirmation and owner-only privacy-safe change-history projection.
- Added governed active/retired/legacy professional lifecycle state; retired/legacy states suppress direct contact and appointment presentation.
- Added FHIR Practitioner/PractitionerRole public professional projection without patient/clinical record ownership.
- Added explicit-opt-in federation-ready actor projection; actual inbox/outbox transport remains external and current-contract gated.
- Added only three new File 03-owned data structures: approved translations, freshness attestations and future-profile state. External credentials, achievements, AI, contact, content/knowledge, search/ranking, clinic and federation facts remain canonical projections.
- Added activation/repair integration, WordPress privacy export/erasure, guarded uninstall coverage and canonical idempotency replay protection for future mutations.
- Kept staging, live deployment and operational acceptance as separate evidence gates.

## 1.1.0-rc1 — Latest governing plans completion

- Reconciled File 03 source with the newly supplied File 03 plan plus its 7 August 2026 central-plan addendum.
- Added doctor personal-site projection: expertise/services, evidence-backed credential card, File 08 clinic/availability/appointment CTA, portfolio timeline bridge, current reviews and verified affiliations.
- Added revocable first-party signed short links, local QR generation, print/share controls, canonical JSON-LD and explicit File 26 search projection.
- Added owner-only desktop/mobile/RTL preview and bounded, expiring assistant delegation; credentials and medical messages are never delegated by File 03.
- Added aggregate-only analytics consumption; File 03 creates no viewer-level behavioral surveillance store.
- Added expanded safety report taxonomy and reporter appeal records while preserving append-only event/audit evidence.
- Changed native fallback brand green to `#087A4E`; File 25 remains the visual token authority.
- Added additive central extension schema, automatic upgrade repair, 61-ID central-plan traceability, two fresh post-code review gates and deterministic package verification.
- Preserved truthful status boundary: source completion does not imply Hostinger staging, live deployment or operational acceptance.

## 1.0.0-rc3

- Completed forty sequential review/correction rounds.
- Made unknown-age profiles fail closed except the immutable Founder.
- Added strict UUID, exact-origin and If-Match validation.
- Bound media scans to mandatory exact-byte SHA-256 evidence.
- Made update idempotency first/replay responses identical.
- Added immediate moderation media revocation.
- Hardened migration privacy, timeline provider failure/volume/cursor/time handling, verified-doctor clinic projection and Unicode report validation.
- Preserved staging/live/operational status as separate evidence gates.
