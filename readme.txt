=== Sabri Profiles and Doctors ===
Contributors: majidhussainqadri1-dot
Tags: profiles, doctors, privacy, founder, timeline, personal-site, credentials, knowledge, interoperability, federation
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.0-rc2
License: GPLv2 or later

Canonical Founder, member and doctor profile domain for the Sabri Social Homeopathy Platform.

== Description ==

File 03 owns stable public profile identity, presentation fields, field visibility, profile media references, slug history, reporting, privacy-bounded delegation, approved presentation translations, freshness attestations, governed professional lifecycle state and profile timeline slots. File 00 remains membership/identity authority; File 09 remains doctor-verification/credential authority; File 07/26 own directory/search discovery and ranking; File 08 owns clinic, appointment and review truth; File 21 and media/learning modules own timeline/knowledge content; File 16 owns AI execution; File 17 owns communication transport; File 20 owns the application/PWA shell; File 24 owns assurance governance; File 25 owns platform-wide visual components; federation transport remains an external approved owner.

1.2.0-rc2 preserves the complete Future Professional Identity & Profile Superset — 18 Enhancements introduced in rc1 and applies an 80-round corrective review. The review corrected atomic activation/migration locking, Founder singleton refresh propagation, strict optimistic-concurrency preconditions, transactional future replay/event coupling, delegation/report/appeal integrity, bounded media/report/AI abuse controls, selective-disclosure future scopes, governed legacy UI/server alignment, federation inbox+outbox readiness and guarded-uninstall cleanup.

The approved 18 enhancements remain:

1. Portable Verifiable Professional Credential Wallet.
2. Selective-Disclosure Professional Card with expiring, revocable public-safe scope packets.
3. Verified Learning & Achievement Passport.
4. Professional Trust Timeline.
5. Evidence-Backed Expertise Map.
6. Professional Knowledge Graph Profile.
7. Transparent Knowledge Coverage Map with no paid/donor influence or cure ranking.
8. Grounded “Ask About This Doctor’s Work” AI, restricted to public professional work and blocked from diagnosis/prescription/dose/emergency/cure claims.
9. Owner-approved multilingual profile editions with machine-translation labelling.
10. Privacy-Safe Contact Relay through File 17 with recipient address hidden.
11. Verified external-domain and institutional-link badges.
12. One-page professional card plus structured full dossier.
13. Scriptless, tracking-free embeddable verified profile card.
14. Field-level freshness and owner reconfirmation.
15. Owner-only visual profile change history.
16. Governed retired / legacy / memorial professional lifecycle state with active contact/appointment suppression.
17. Public-safe FHIR Practitioner / PractitionerRole interoperability projection with no patient record.
18. Explicit-opt-in federation-ready public actor projection while federation transport remains external.

The future extension adds only three native File 03 data structures: approved translations, field freshness attestations and future-profile state. Credential, learning, AI, contact, knowledge, clinic, search/ranking and federation facts remain current/versioned external projections. Future native mutations use File 03’s canonical idempotency store; write, outbox evidence and replay-result finalization are a transactional outcome on rc2. Privacy export/erasure and two-gate destructive uninstall include the File 03-owned data and corrective dynamic lock/rate state.

No paid/pro/premium or donor-advantage gate exists in File 03. No third-party QR/tracking service, hidden viewer-level analytics store, patient chart, raw identity evidence, automatic doctor verification, cure guarantee or AI diagnosis/prescription is introduced. Missing or stale canonical providers fail closed or hide/degrade only the affected feature.

This remains a staging candidate. Source/package/automated-QA status is separate from Hostinger staging, real-provider integration, browser/WCAG/RTL, backup/restore, rollback, Founder acceptance, live deployment and operations.

== Installation ==

1. Back up and verify restore capability.
2. Activate a compatible File 00 Membership Core.
3. Install and activate this plugin on staging.
4. Run Profile System Check and verify base, central and future extension schemas.
5. Execute documented migration, privacy, provider, disclosure, AI-safety, interoperability, accessibility, backup/restore and rollback acceptance matrices.
6. Do not promote to live until Founder approval and exact package/deployment parity are recorded.

== Changelog ==

= 1.2.0-rc2 =
* Completes the numbered 80-round corrective review recorded in `EIGHTY-ROUND-REVIEW.md`; defects were found and immediately corrected in 18 rounds and 62 rounds were clean.
* Replaces race-prone activation/migration and user-abuse counters with owner-safe atomic locking/serialized rate-limit primitives where File 03 owns the state.
* Enforces Founder singleton during identity transitions and propagates identity refresh through outbox, cache invalidation and reconciliation evidence.
* Hardens malformed `If-Match`, delegation grant/revoke, safety reports and report appeals with stricter concurrency, replay and transactional event semantics.
* Makes future-profile native mutation, outbox evidence and replay-result finalization one transactional outcome.
* Completes selective-disclosure credential/expertise/achievement scopes from the current augmented public-safe profile projection.
* Adds bounded grounded-AI abuse throttling and aligns legacy lifecycle UI with current governed server authority.
* Requires both current federation inbox and outbox before transport can be marked active.
* Extends guarded destructive uninstall to remove File-03-owned dynamic corrective lock/rate state.
* Adds exact-candidate `tests/eighty-round-review.py`, independent adversarial post-correction review and rc2 deterministic package/SBOM/parity gates.
* Preserves staging/live/operational separation; no live deployment claim is made.

= 1.2.0-rc1 =
* Adds the complete approved 18-feature Future Professional Identity & Profile Superset under `F03-FUT-01..18` while preserving all canonical ownership boundaries.
* Adds current/provider-backed credential wallet, learning passport, trust timeline, expertise evidence, knowledge graph/coverage, verified external links and File 17 privacy-safe contact relay.
* Adds signed expiring selective disclosure, structured dossier, tracking-free embed card, owner-approved multilingual editions, freshness reconfirmation and owner-only change history.
* Adds grounded File 16 work-Q&A with explicit diagnosis/prescription/dose/emergency/cure guardrails.
* Adds governed active/retired/legacy lifecycle state, public-safe FHIR Practitioner/PractitionerRole projection and explicit-opt-in federation-ready actor projection without taking federation transport ownership.
* Adds three additive future-profile tables, activation/repair integration, WordPress privacy export/erasure and guarded-uninstall coverage.
* Reuses canonical File 03 Idempotency-Key replay protection for future mutations and emits future-profile audit/outbox events.
* Preserves repository/staging/live/operational status separation.

= 1.1.0-rc1 =
* Implements the latest File 03 plan and 2026-08-07 central-plan addendum without taking ownership from Files 00/07/08/09/20/21/24/25/26.
* Adds personal-site fields, verified credential card, File 08 clinic/availability/appointment/review projections, local revocable QR/share, private desktop/mobile/RTL preview and scoped assistant delegation.
* Adds explicit File 26 public search projection, SEO JSON-LD, aggregate-only analytics projection and verified organization-affiliation projection.
* Expands safety reporting taxonomy and adds reporter appeal records; preserves moderation history and fail-closed provider behavior.
* Uses Sabri Green #087A4E fallback, no third-party QR/tracker and no paid/donor feature gate.
* Adds machine-readable 61-ID latest-plan traceability and two fresh post-code review gates. Staging/live/operational gates remain separate.

= 1.0.0-rc3 =
* Forty-round review candidate: fail-closed unknown-age profiles, exact-origin URLs, strict REST preconditions/UUIDs, byte-bound media scan evidence, deterministic idempotency replay, immediate moderation media revocation, migration privacy tightening, bounded/exception-safe timelines and stronger public projections.
* Adds forty-round evidence and source gates. Source/package/automated-QA only; staging and live gates remain separate.

= 1.0.0-rc2 =
* Hardens File 00 and File 09 versioned authority, moderator privacy, minor defaults, profile media scanning and deletion, report transitions, atomic outbox leases, migration quarantine, revocation-safe no-store responses and operator repair.
* Adds behavior-oriented authorization, verification, state, timeline, schema, source-regression and full-bootstrap tests.
* Source/package/automated-QA candidate only; staging, real provider integration, browser/WCAG/RTL, backup/restore, rollback and Founder acceptance remain mandatory.

= 1.0.0-rc1 =
* Initial plan-completion candidate.