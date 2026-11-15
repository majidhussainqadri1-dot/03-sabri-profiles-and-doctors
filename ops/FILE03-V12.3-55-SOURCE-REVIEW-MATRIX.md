# File 03 V12.3 — 55 obligation source-to-test reconciliation (15 November 2026)

**Status:** SOURCE-MAPPED, NOT 55 RELEASE-PASS CASES. Governing source is the user's original Master Plan V12.3. Candidate File03 `rc19` and File00 `1.2.46` remain draft PRs. This ledger makes the exact native-file owner and required independent acceptance surface explicit; a file's presence does not prove behavior. Must/Release gates require runnable and human acceptance evidence before promotion.

- 12 CV (CV-014..025), 13 FR, 10 NFR, 2 CEN, 18 FUT = **55 source maps**.
- Separate global CV/UX-04 terms are still binding, as in `LATEST-PLAN-TRACEABILITY.md`.
- All 72 inherited and 24 V12.3 delta staging items are pending actual installation evidence; this table assigns **no** Staging PASS.
- Exact code/line review and live ownership remain P02/P03/P08 gates; native file paths are candidate inspection loci, not unconditional assertions of full implementation.

| Requirement | Source obligation | Primary native inspection path | External truth owner | Independent release acceptance | Source status | Staging |
|---|---|---|---|---|---|---|
| `CV-014` | Multirole fields and per-field privacy | `includes/class-spd-central-profile.php` | File00 identity | Privacy/preview | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-015` | Doctor personal-site hero and CTA | `includes/trait-spd-frontend-central.php` | File08 current clinic/slots | AJ-04/RTL | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-016` | Verified credential card | `includes/class-spd-verification-adapter.php` | File09 current approved scope | Revocation tests | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-017` | Evidence-backed expertise taxonomy | `includes/trait-spd-profile-professional.php` | File09/07/26 owners | No cure claims | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-018` | Clinic, availability and timezone | `includes/class-spd-provider-guards.php` | File08 current truth | Missing provider hides CTA | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-019` | Portfolio timeline projection | `includes/class-spd-timeline.php` | File21/content owners | Cross-author/URL tests | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-020` | Revocable tracking-free QR/share | `includes/trait-spd-frontend-central.php` | File03 local token | Expiry/revocation/print | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-021` | Private RTL preview | `includes/trait-spd-frontend-edit.php` | Owner-only | No-store and noindex | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-022` | Scoped, expiring delegation | `includes/class-spd-authorization.php` | File08 schedule authorization | No credential/message rights | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-023` | Privacy-minimal aggregate analytics | `includes/class-spd-contracts.php` | File26 counts | No viewer surveillance | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-024` | Consultation-eligible ethical review | `includes/class-spd-provider-guards.php` | File08 review eligibility | No clinical outcome rating | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `CV-025` | Organization and verified affiliations | `includes/class-spd-central-profile.php` | Affiliation verification owner | Fail closed | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-001` | Official Founder page | `includes/class-spd-central-profile.php` | File00 Founder claim | Founder-only authority | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-002` | Member public profile | `includes/trait-spd-profile-public-dto.php` | File00 member claims | Minor-safe DTO | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-003` | Doctor professional profile | `includes/trait-spd-profile-professional.php` | File09 verification | No unreviewed badge | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-004` | Avatar, cover and media hygiene | `includes/class-spd-media.php` | File24 scanner/storage | EXIF/scan/owner | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-005` | Four visibility audiences | `includes/class-spd-authorization.php` | File00 age/consent | IDOR/field access | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-006` | Contact consent and revocation | `includes/class-spd-membership-adapter.php` | File00/17 contact owners | No raw contact leak | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-007` | Permanent canonical IDs, slug aliases | `includes/class-spd-routes.php` | File03 own slugs | 301 and privacy | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-008` | Versioned profile edits, moderation | `includes/trait-spd-profile-update.php` | File09 professional approval | Optimistic concurrency | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-009` | Nonmanipulative completeness | `includes/class-spd-central-profile.php` | External owner workflows | No fake rank | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-010` | Current owner-content timeline | `includes/class-spd-timeline.php` | File21/10/11/05 | No copied posts | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-011` | Founder and doctor badges | `includes/class-spd-verification-adapter.php` | File00/09 | Revoke badge now | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-012` | Report impersonation, false claims | `includes/trait-spd-profile-report-appeals.php` | File24 escalation | Review/appeals | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FR-013` | Public SEO and safe search | `includes/class-spd-contracts.php` | File26 index owner | Noindex private | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-001` | Read/write object/field authorization | `includes/class-spd-authorization.php` | File00 actor state | IDOR mandatory | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-002` | Export/erase/legal hold privacy | `includes/class-spd-privacy.php` | File24 legal review | Retention tests | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-003` | Outbox retries, idempotency, dead letter | `includes/class-spd-outbox-dispatcher.php` | File19 notifications | Lease/race tests | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-004` | Bounded query and measured p75/p95 | `includes/class-spd-profile-repository.php` | Host runtime measurement | Performance NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-005` | WCAG 2.2 AA RTL and keyboard | `assets/css/profiles.css` | File25 design tokens | Manual assistive tech NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-006` | Structured operator diagnostics | `includes/class-spd-observability.php` | File24 incident owner | Production metrics NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-007` | Idempotent migration and rollback | `includes/class-spd-activator.php` | Installed DB truth | Restore NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-008` | System Check and safe repair | `includes/class-spd-admin.php` | Actual operator evidence | Host tests NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-009` | WP701/PHP83 cross-file compatibility | `.github/workflows/f03-v12-real-seven-module-integration.yml` | File00/02/07/08/09/26 | CI only, staging NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-NFR-010` | LTR/RTL locale/date/time correctness | `assets/js/profiles.js` | File20/25 shared UI | Browser NOT RUN | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-CEN-01` | Canonical profile contract, free/green/privacy/safety | `includes/class-spd-contracts.php` | File25/26 and File24 owners | Owner-integrity checks | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-CEN-02` | Owner commands with versioned reads/events | `includes/class-spd-profile-repository.php` | File00/08/09/26 and other owners | No direct companion writes | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-01` | Current verifiable credential wallet | `includes/class-spd-future-profile.php` | File09 | Verified only | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-02` | Expiring selective disclosure card | `includes/class-spd-future-rest.php` | File03 token | 24h limit/revoke | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-03` | Verified learning passport | `includes/class-spd-future-profile.php` | Learning owner | No fake achievements | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-04` | Professional trust timeline | `includes/class-spd-future-profile.php` | Verification/governance owner | Public-safe only | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-05` | Expertise evidence map | `includes/class-spd-future-profile.php` | Knowledge/issuer owners | No treatment rank | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-06` | Public knowledge graph | `includes/class-spd-future-profile.php` | File26/content owners | No second graph truth | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-07` | Knowledge coverage counts | `includes/class-spd-future-profile.php` | File26 | No paid boost | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-08` | Grounded work Q&A | `includes/class-spd-future-rest.php` | File16 AI | Clinical request blocked | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-09` | Owner-approved multilingual editions | `includes/class-spd-future-profile.php` | File03 native translations | Machine source labelled | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-10` | Privacy-safe contact relay | `includes/class-spd-future-profile.php` | File17 | Recipient hidden | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-11` | Verified HTTPS external link | `includes/class-spd-future-profile.php` | Verification/affiliation | No unsafe URL | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-12` | Digital card and professional dossier | `includes/class-spd-future-rest.php` | File03 public DTO | No private evidence | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-13` | Scriptless embed card | `includes/class-spd-future-rest.php` | File03 profile URL | Tracking-free | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-14` | Field freshness and confirmation | `includes/class-spd-future-profile.php` | File03 native attestation | No unearned verification | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-15` | Owner visual history | `includes/class-spd-future-profile.php` | File03 audit | No private reviewer data | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-16` | Professional retired/legacy state | `includes/class-spd-future-profile.php` | File00 governance | Hide active booking | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-17` | FHIR Practitioner projection | `includes/class-spd-future-profile.php` | File09 verified doctor | No patient chart | MAPPED / NOT FULLY VERIFIED | NOT RUN |
| `F03-FUT-18` | Federation-ready actor | `includes/class-spd-future-profile.php` | External transport owner | Opt-in and safe inactive | MAPPED / NOT FULLY VERIFIED | NOT RUN |

## Source review and release truth

The presence of 55 mappings is an initial inventory, not 55 completed acceptance cases. A reviewer must inspect each path and follow its runtime callbacks/REST route/authorization boundary, tie the requirement to automated negative tests, and independently confirm the combined UX and Staging case. For dependencies (File00/08/09/16/17/20/21/24/25/26), absence or stale claims must fail closed. Do not invent provider output to mark source complete.

**Known current source defects:** D01–D05 from the paired PRs; candidate corrections tested but not merged, staged, deployed or live-verified. A further QA/Manifest/version/documentation reconciliation has its own evidence and must not inflate runtime defect counts. File00 version `1.2.46` is distinct from competing unmerged `1.2.45` branches; File03 `1.2.0-rc19` is distinct from `rc18`.

**Closing five fields:** Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status. No Live resolution before host parity and re-test.
