# File 03 — Future Professional Identity & Profile Superset — 18 Enhancements

Candidate: `1.2.0-rc2`  
Contract: `1.4.0`  
Plan identity: `SSH-F03-PLAN-2026-v1.0 + 2026-08-07 central addendum + FUTURE-SUPERSET-18 + 80-ROUND-CORRECTIVE-REVIEW`  
Future extension schema: `1.0.0`

## Governing boundary

This amendment extends File 03 from a public professional profile into a privacy-preserving professional identity and knowledge passport. It does **not** transfer canonical ownership from companion domains. File 09 still owns doctor-verification evidence and decisions; learning owners still own achievements; File 08 owns clinic/appointments/reviews; File 17 owns message/contact transport; File 16 owns AI execution; Files 21/05/06/media/research owners own knowledge objects; File 26 owns search/ranking/analytics; federation transport remains an external/approved owner. File 03 stores only the presentation state it legitimately owns: approved profile translations, field freshness attestations and governed future-profile state.

Every external fact is accepted only as a current/versioned projection and disappears or degrades safely when its canonical provider is missing, stale or malformed. No paid/donor advantage, cure/outcome rank, hidden patient analytics, raw verification evidence, diagnosis, prescription or emergency-care replacement is introduced.

## Requirement-to-code traceability

| ID | Enhancement | File 03 implementation | Canonical-owner / failure boundary |
|---|---|---|---|
| F03-FUT-01 | Portable Verifiable Professional Credential Wallet | `SPD_Future_Profile::credential_wallet()`; public verified-credential cards with issuer, dates and verification URL | File 09/issuer owns credential truth and private evidence; stale/missing claim => wallet empty |
| F03-FUT-02 | Selective-Disclosure Professional Card | HMAC-signed, expiring, scope-limited disclosure packet; max 24h; revoked by existing share epoch; REST create/read routes; public-safe future scopes are resolved from the current augmented profile DTO | Only already-public-safe scopes can be disclosed; no raw evidence/private contacts; invalid/expired/revoked token fails closed |
| F03-FUT-03 | Verified Learning & Achievement Passport | `learning_passport()` projection with bounded verified achievements | Learning/University owner supplies current claims; provider unavailable => section hidden |
| F03-FUT-04 | Professional Trust Timeline | Bounded public-safe chronology of verified/renewed/corrected/suspended/restored professional events | Verification/governance owners supply publishable events; reviewer notes/evidence excluded |
| F03-FUT-05 | Evidence-Backed Expertise Map | Topic-to-evidence projection with same-origin supporting content links | Content/learning/verification owners own evidence; never converted to a cure/outcome score |
| F03-FUT-06 | Knowledge Graph Profile | Bounded node/edge graph across public articles/books/courses/video/PDF/research/topics/institutions | Native content/search owners supply graph projection; File 03 creates no second content store |
| F03-FUT-07 | Knowledge Coverage Map | Transparent evidence counts by category with `ranking=false` and `paid_influence=false` | File 26/knowledge owners compute counts; no opaque prestige rank or donor influence |
| F03-FUT-08 | Grounded “Ask About This Doctor’s Work” AI | Member-only, per-user bounded question route; File 16 grounded provider; same-origin citations; explicit medical-scope block | File 16 owns AI execution; diagnosis/prescription/dose/emergency/cure requests rejected; unavailable provider => 503, no local hallucinated fallback |
| F03-FUT-09 | Authenticated Multilingual Profile Editions | Additive `spd_profile_translations` table; owner-approved human or labelled machine edition; privacy exporter/eraser | File 03 owns approved presentation translation only; machine source remains labelled and owner-approved |
| F03-FUT-10 | Privacy-Safe Contact Relay | File 17 current relay projection, hidden address, same-origin CTA | File 17 owns message transport/authorization; absent/stale relay => CTA hidden |
| F03-FUT-11 | Verified External-Domain & Institutional Link Badge | HTTPS-only verified external-link projection | Verification/affiliation provider owns proof; unverified or unsafe URL omitted |
| F03-FUT-12 | One-Page Digital Professional Card + Full CV/Dossier | Structured card/full dossier query and REST route derived from public DTO | Contains only fields already authorized for the requesting viewer; no private verification evidence |
| F03-FUT-13 | Embeddable Verified Profile Card | Scriptless, tracking-free canonical anchor card and public embed-card endpoint | No third-party JS/tracker; canonical profile remains source of truth |
| F03-FUT-14 | Field-Level Freshness & Reconfirmation | Additive `spd_profile_attestations` table; owner command records confirm/expiry; freshness projection | Attestation does not upgrade external verification; stale field is labelled stale, not silently renewed |
| F03-FUT-15 | Visual Profile Change History | Owner-only bounded event-history projection from approved File 03 audit/outbox event names | No raw audit secrets or other users’ history; public visitors receive no private history |
| F03-FUT-16 | Professional Legacy / Memorial State | Additive `spd_profile_future_state`; active/retired/legacy; current File 00 governed legacy approval; non-active state suppresses direct contact/appointment | Legacy requires current governed profile authority; content may remain as scholarly record but active-service representation stops |
| F03-FUT-17 | FHIR Practitioner/PractitionerRole Interoperability Adapter | Public-safe `Practitioner` + `PractitionerRole` JSON projection; no patient chart; explicit interop provider function | Professional profile only; File 03 does not become a clinical-record/FHIR server or patient-data owner |
| F03-FUT-18 | Federation-Ready Public Profile Projection | Explicit opt-in actor projection; transport is active only when current external provider supplies both inbox and outbox | File 03 owns profile projection, not ActivityPub/federation transport; opt-out or provider failure => transport inactive |

## Native data introduced by this amendment

Only three additive tables are owned by File 03:

1. `spd_profile_translations` — owner-approved presentation editions;
2. `spd_profile_attestations` — freshness/reconfirmation metadata;
3. `spd_profile_future_state` — federation opt-in and governed professional lifecycle state.

They are installed idempotently, included in activation/repair, exposed through WordPress privacy export/erasure, and included only in the two-gate destructive-uninstall purge. External credential, learning, AI, contact, knowledge, search, clinic or federation facts are **not** copied into new permanent File 03 truth stores.

## Mutation law

Future-profile mutations are owner/governor authorized and use the existing File 03 idempotency store through explicit `Idempotency-Key` replay protection. Native write, outbox evidence and replay-result finalization are one transactional outcome: persistence failure rolls the mutation back. Selective disclosure is stateless, signed, bounded and revocable. Translation, freshness and future-state writes are additive/retry-safe and cannot grant doctor verification, publishing, ranking, appointment or clinical authority.

Activation and migration operations use owner-token atomic locks; bounded user-facing abuse controls use serialized rate-limit counters. Founder singleton transitions, identity refresh propagation, delegation changes, safety reports and report appeals are rechecked by the `EIGHTY-ROUND-REVIEW.md` exact-candidate gate.

## Privacy, minors and medical safety

- Public/private DTO separation remains authoritative.
- No hidden viewer-level analytics store is added.
- No raw national ID, verification document, patient chart, message body or payment data is introduced.
- Contact relay hides recipient address and inherits File 17 authorization/privacy.
- Non-active professional lifecycle disables File 03 direct contact/appointment presentation.
- Grounded AI is restricted to public professional work and cannot diagnose, prescribe, dose, guarantee outcomes or replace emergency care.
- Knowledge coverage is descriptive evidence count, not treatment-success ranking.
- All public projections still inherit File 00 age/guardian/public-eligibility and File 03 audience rules.

## Eighty-round corrective review

The `1.2.0-rc2` repository candidate is subject to eighty numbered review gates. Defects were identified and corrected during rounds `08, 09, 11, 12, 18, 21, 23, 24, 35, 36, 37, 42, 43, 50, 59, 67, 69, 72`; all other rounds were clean at the reviewed source invariant. `tests/eighty-round-review.py` rechecks all 80 gates and `tests/eighty-round-adversarial.py` provides an independent post-correction negative-path gate. Exact-head CI must pass before merge.

## Acceptance evidence required

Repository source completion is not staging or live completion. Before promotion, staging must verify: additive upgrade from `1.1.0-rc1`; fresh install; privacy export/erasure; disclosure expiry/revocation; malformed/stale provider claims; File 16 medical-scope rejection; File 17 relay privacy; legacy contact/appointment suppression; FHIR schema shape; federation opt-in/out; RTL/mobile/keyboard/screen-reader behavior; weak-network/provider outage behavior; backup/restore and rollback; package/deployed parity and Founder acceptance.

Exact deployed code remains unverified until an approved package is actually deployed and re-tested.
