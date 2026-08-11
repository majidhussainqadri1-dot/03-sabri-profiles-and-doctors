# Changelog

## 1.2.0-rc7 — Seventh fresh ten-round sequential corrective hardening

- Completed a fresh sequential 10-round review from exact `main` baseline `fdde7311409d68af4bae5917f5a49154cb92c9f4`.
- Defects were found and corrected in rounds `01, 02, 03, 04, 05, 06, 08, 09, 10`; round `07` was clean.
- Preserved requester/reviewer appeal-erasure database failures independently so one failed count read cannot be hidden by a later successful read.
- Converged central readiness on `SPD_Schema_Guard::central_ready()` and made base DB-version recording require exact required tables, columns and integrity indexes.
- Enforced minor-delegate denial and strict audience-map validation at the reusable repository boundary as well as REST.
- Added strict public-ID and slug lookups for mutation- and lifecycle-sensitive paths so SQL/field-store uncertainty returns a 503-class error instead of looking absent or available.
- Added a dedicated lease-bound `SPD_Outbox_Dispatcher` that verifies lease reset, queue/claim reads, delivery result persistence and retry/dead persistence while exposing operational failure evidence to File 24.
- Prevented lifecycle-sensitive personal-site and File 26 search projections from returning unsuppressed contact/appointment data after an uncertain second-stage profile read.
- Advanced materially changed source identity to `1.2.0-rc7`; repository DB schema remains `1.2.0` and contract remains `1.4.0` because no DDL/contract-version change was introduced.
- Added `SEVENTH-TEN-ROUND-REVIEW-2026-08-11.md`, `tests/seventh-ten-round-review.py`, permanent seventh-review Fresh CI, and rc7 Future Superset deterministic package/checksum/SBOM/source-parity gating.
- Converted stale third/fourth/fifth/sixth release-identity assertions into historical-ledger assertions while preserving their substantive authorization, privacy, concurrency, provider-degradation, security, determinism and release-boundary checks; aligned architecture, 80-round, forty-round and adversarial QA with the rc7 tree.
- Exact reviewed candidate `56b0d315457918d6e89a530807da272f77065e0f` (tree `7c10a713f315e55aa68f12f4bacc7c139806bb33`) passed exact-candidate and PR-head QA; PR #19 merged the same tree as `553a1a006c43e6791b7e71407d0421e851df5bc3`.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback, Founder acceptance and live operational verification remain separate gates.

## 1.2.0-rc6 — Sixth fresh ten-round sequential corrective hardening

- Completed a fresh sequential 10-round review from exact `main` baseline `822837daa3cebc4c5ae80410f31511aadf3885b0`.
- Defects were found and corrected in rounds `01, 02, 03, 04, 05, 07, 09, 10`; rounds `06, 08` were clean.
- Made reviewer privacy erasure clear reviewer-authored appeal `decision_note` together with reviewer identity when no legal/governance hold applies.
- Ordered Future-owned privacy erasure behind canonical base-profile tombstoning so callback order cannot remove retired/legacy suppression before base erasure is safely established.
- Made delegated use-time authority require exact central schema readiness and strengthened owned schema verification from index-name presence to exact ordered columns plus uniqueness semantics.
- Added strict shared audience-map validation so malformed maps, unsupported field keys and invalid audience values fail with 400 before base-profile or personal-site mutation.
- Blocked minor accounts from delegated profile-management authority at both grant time and use time, including stale pre-existing grants.
- Reset bounded retry, lease and error state when a legitimate non-delivered media deletion is renewed while preserving already-delivered success records.
- Advanced materially changed source identity to `1.2.0-rc6`; repository DB schema remains `1.2.0` and contract remains `1.4.0` because no DDL/contract-version change was introduced.
- Added `SIXTH-TEN-ROUND-REVIEW-2026-08-11.md`, `tests/sixth-ten-round-review.py`, permanent sixth-review Fresh CI, and rc6 Future Superset deterministic package/checksum/SBOM/source-parity gating.
- Updated historical QA identity assertions only where they had become stale, preserving their substantive authorization, privacy, concurrency, provider-degradation, security and release-boundary invariants.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback, Founder acceptance and live operational verification remain separate gates.

## 1.2.0-rc5 — Fifth fresh ten-round corrective hardening

- Completed a fresh sequential 10-round review from exact `main` baseline `3358472bc374958c66f5e84997b7633f598caa73` (tree `49283b40823aaa31348403588311e1912af5851d`).
- Defects were found and corrected in rounds `01, 02, 03, 04, 05, 09, 10`; rounds `06, 07, 08` were clean.
- Unified the File 26 profile-search adapter and public REST search endpoint with the canonical fail-closed future-lifecycle-aware `spd_get_search_projection()` helper.
- Replaced table-existence-only privacy guards with exact required-column/index guards for central delegation/appeal and future-profile privacy data.
- Minimized appeal privacy exports by relationship so a reviewer does not receive requester-authored reason/counterparty identifiers merely because the appeal row is shared.
- Added ownership-marker recovery for orphan File-03 avatar/cover attachments during explicit two-gate destructive uninstall.
- Made the authoritative future-state safe read require exact future schema readiness rather than table existence alone.
- Added `FIFTH-TEN-ROUND-REVIEW-2026-08-11.md`, `tests/fifth-ten-round-review.py`, permanent fifth-review CI integration and rc5 deterministic-package identity.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback, Founder acceptance and live operational verification remain separate gates.

## 1.2.0-rc4 — Fourth fresh ten-round corrective hardening

- Completed a fresh 10-round sequential review from exact `main` baseline `1ff55ecd91be68bbf6d68e54c630f78f901992af`; repository-level defects were found and corrected in **all rounds `01–10`**.
- Reclassified early `0.2.0` release inventory/checksum records as historical provenance so they cannot be mistaken for current rc4 package parity evidence.
- Hardened private fallback route recognition, guest redirects, no-store/noindex context and profile UI asset loading when the managed page map is absent or corrupt.
- Made managed-page repair idempotent when the ownership marker is already correct.
- Added bounded, schema-aware WordPress privacy export/erasure for File 03 delegation and report-appeal records, including explicit legal-hold hooks.
- Added exact base/central/future schema shape verification for required columns and integrity indexes; boot, activation repair and retention fail closed on partial/deferred schemas.
- Made profile identity, custom slug lock, Founder uniqueness and slug registry/history reads fail closed on SQL uncertainty.
- Made media privacy reconciliation and deletion processing fail closed on schema/read/lease/result-persistence uncertainty and record operational failure evidence.
- Completed two-gate destructive-uninstall cleanup by recovering File-03-owned pages from ownership markers and purging newer migration/retention/media failure-state options.
- Added `FOURTH-TEN-ROUND-REVIEW-2026-08-11.md`, `tests/fourth-ten-round-review.py`, and the permanent fourth-review exact-candidate CI gate; advanced materially changed source identity from rc3 to rc4.
- Staging acceptance, exact deployed-package parity, live DB/schema/migration state, browser/WCAG/RTL evidence, backup/restore/rollback and operational acceptance remain separate gates.

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
