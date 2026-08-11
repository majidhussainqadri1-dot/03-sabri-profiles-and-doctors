# File 03 — Second Fresh Eighty-Round Review — 2026-08-11

Repository: `majidhussainqadri1-dot/03-sabri-profiles-and-doctors`  
Source reality frozen for this review: `main` at `b96f74457f54341701c6cdb1a57d42baa1100081`  
Corrective branch: `codex/file-03-second-fresh-80-review-20260810`  
Governing basis: latest File 03 amended plan + consolidated central plan.  
Method: each round reviewed the corrected state produced by all prior rounds; a discovered defect was corrected before the next round.

This ledger is repository evidence only. It does **not** establish Hostinger staging acceptance, deployed-package parity, live database/schema state, or live operational correctness. Exact deployed code remains unverified.

| Round | Review focus | Result / correction |
|---:|---|---|
| 01 | Governing scope, Future Superset 18, plan hierarchy | CLEAN — no new scope/owner contradiction found. |
| 02 | Canonical ownership and direct-write boundaries | CLEAN — File 03 remains profile/presentation owner; companion truths remain external. |
| 03 | File 00 current eligibility and suspension recheck | **DEFECT FOUND → FIXED** — public/edit decisions could outlive current owner eligibility; current File 00 eligibility/suspension is now revalidated fail-closed. |
| 04 | Founder invariant and institutional authority | CLEAN — Founder path remains File 00-bound and step-up protected. |
| 05 | Doctor authority and current professional eligibility | **DEFECT FOUND → FIXED** — doctor helper could rely on account type without full current eligibility; doctor authority now requires eligible, non-suspended, professionally verified state. |
| 06 | Minor/guardian visibility and management rules | CLEAN — minor direct contact/public audience restrictions and verified guardian management remain enforced. |
| 07 | Public profile visibility after external revocation | **DEFECT FOUND → FIXED** — profile visibility could survive owner ineligibility; anonymous visibility now fails closed on current claims and field-read failure. |
| 08 | Field audience integrity and field-store failure | **DEFECT FOUND → FIXED** — a failed field-map read could look like an empty editable map; field-read failure is marked and all mutations are blocked with 503. |
| 09 | Object authorization / IDOR | CLEAN — public IDs are opaque and protected mutations reauthorize the actual object. |
| 10 | REST permission callbacks and object existence leakage | CLEAN — protected routes require current eligible actor; public reads use safe DTOs. |
| 11 | CSRF/browser nonce boundary | CLEAN — WordPress REST nonce path remains in browser mutation flow; authorization is still server-side. |
| 12 | Idempotency replay for completed operations | CLEAN — same key+same payload deterministically replays; changed payload conflicts. |
| 13 | Abandoned idempotency reservation / timeout recovery | **DEFECT FOUND → FIXED** — a crashed request could leave `started` for 24h; bounded 15-minute abandoned reservations are conditionally reclaimed without deleting a concurrent completed record. |
| 14 | Transaction + outbox atomicity | CLEAN — mutation and event/idempotency completion remain transaction-bound on canonical write paths. |
| 15 | Optimistic version / lost-update control | CLEAN — profile, delegation and professional writes retain expected-version/CAS behavior. |
| 16 | Process locks, expiry takeover and stale-owner release | **DEFECT FOUND → FIXED** — expired option locks used unsafe delete/recreate semantics; takeover and release now use exact-value compare-and-swap behavior. |
| 17 | Abuse throttling and media double-consumption | **DEFECT FOUND → FIXED** — rate counters were not process-safe and media upload consumed the bucket twice; one atomic fail-closed limiter is now authoritative. |
| 18 | Profile create-on-read during DB failure | **DEFECT FOUND → FIXED** — a failed profile SELECT with `ensure=true` could fall into profile creation; read failure now returns 503 instead of creating. |
| 19 | Slug allocation/history/collision | CLEAN — uniqueness, custom-slug collision and history behavior remain guarded. |
| 20 | Legacy profile redirect privacy | **DEFECT FOUND → FIXED** — legacy `?user=` route could redirect to a private/ineligible profile; redirect now requires current anonymous visibility. |
| 21 | Archived/tombstoned/gone profile behavior | CLEAN — protected/tombstoned state remains unavailable and 410 semantics are preserved where applicable. |
| 22 | Core profile state transition rules | CLEAN — invalid transitions remain denied; history is not rewritten silently. |
| 23 | Public personal-site/search REST caching | **DEFECT FOUND → FIXED** — provider/revocation-sensitive public REST DTOs advertised cacheability; File 03 REST responses are now no-store/no-cache. |
| 24 | Server-side anonymous DTO revocation cache | CLEAN — anonymous object DTO cache remains deliberately disabled until accepted cross-owner invalidation exists. |
| 25 | File 26 search projection + lifecycle safety | **DEFECT FOUND → FIXED** — lifecycle DB failure could be represented as active in search; File 26 projection now fails closed when future lifecycle state cannot be read. |
| 26 | Search ownership/ranking boundary | CLEAN — File 03 provides public-safe profile facts only; File 26 remains ranking owner. |
| 27 | File 09 verification projection | CLEAN — File 09 projection remains current/versioned/user-bound and approved fields only. |
| 28 | File 08 clinic projection binding | **DEFECT FOUND → FIXED** — clinic claim was insufficiently bound/normalized; exact doctor ID, owner version, active/public state and safe values are now required. |
| 29 | File 08 reviews projection binding | **DEFECT FOUND → FIXED** — review projection lacked strict doctor binding/rating rejection; exact doctor/owner version and valid service ratings are now required. |
| 30 | Appointment/contact suppression on lifecycle uncertainty | **DEFECT FOUND → FIXED** — future-state DB failure could leave contact/appointment surfaces active; degraded lifecycle now suppresses contact relay, contacts and appointment CTA. |
| 31 | Timeline provider health and author binding | CLEAN — provider health, author user ID, owner version and same-origin destination are validated. |
| 32 | Timeline cursor integrity | **DEFECT FOUND → FIXED** — malformed/reused cursors could silently restart or cross profile/filter context; cursors are now HMAC-signed and bound to profile+filter, invalid input returns 400. |
| 33 | Corrected/retracted timeline semantics | CLEAN — published/corrected/retracted states remain explicit and owner-projected. |
| 34 | Cross-file provider malformed/unbound projections | **DEFECT FOUND → FIXED** — several future/analytics/organization projections could be accepted without explicit requested-user binding; a final consumer guard now rejects missing/mismatched bindings. |
| 35 | Media MIME/size/dimension constraints | CLEAN — genuine JPG/PNG/WebP, size and dimension limits remain enforced. |
| 36 | Media metadata stripping | CLEAN — image is re-encoded before acceptance; failure blocks upload. |
| 37 | Scanner evidence / exact bytes | CLEAN — scanner SHA-256 must match the exact re-encoded file and current evidence window. |
| 38 | Secure media delivery/privacy boundary | CLEAN — new profile media requires adult anonymous-public profile until approved secure delivery exists. |
| 39 | Media deletion queue lease/retry | CLEAN — ownership, purpose, lease expiry, retry/dead-letter semantics remain bounded. |
| 40 | External eligibility change vs stored public media | **DEFECT FOUND → FIXED** — public attachment references could persist after profile stopped being anonymous-public; periodic privacy reconciliation removes refs and queues deletion. |
| 41 | Professional proposal supersession atomicity | **DEFECT FOUND → FIXED** — failure while superseding an earlier proposal was not explicitly checked; SQL failure now aborts the transaction. |
| 42 | Professional moderation / verified owner projection | CLEAN — reviewer path remains scoped; raw verification evidence is not copied into File 03 public truth. |
| 43 | Safety report creation / spam limits | CLEAN — eligible reporter, reason allowlist, details minimum, dedupe and throttle remain enforced. |
| 44 | Safety report moderation | CLEAN — privileged moderation uses current File 00 capability/2FA boundary and audit path. |
| 45 | Report appeal ownership/state | CLEAN — only eligible reporter and allowed terminal report states can appeal. |
| 46 | Delegation grant authorization | **DEFECT FOUND → FIXED** — delegation grant did not fully require verified adult doctor owner/current eligible delegate; these requirements are now rechecked. |
| 47 | Delegation expiry and use-time revalidation | **DEFECT FOUND → FIXED** — invalid/past expiry and stale owner/delegate eligibility could survive; expiry is validated and every use rechecks both parties plus owner verification. |
| 48 | Cross-file delegated scope boundary | CLEAN — only bounded declared scopes are exposed; delegation never becomes appointment truth. |
| 49 | Locale validation / silent fallback | **DEFECT FOUND → FIXED** — base profile update could silently coerce malformed locale to `en-US`; submitted invalid locale now returns 400. |
| 50 | Field freshness/reconfirmation | CLEAN — reconfirmation remains presentation freshness only and does not upgrade external verification truth. |
| 51 | Future lifecycle DB failure semantics | **DEFECT FOUND → FIXED** — missing DB read distinction could default a failed lifecycle read to active; public helper now performs explicit state read and marks/suppresses degraded professional actions. |
| 52 | Portable credential wallet | CLEAN — only verified current provider facts render; raw credential evidence remains external. |
| 53 | Selective disclosure packet | CLEAN — signed, expiring, bounded scopes, share-epoch revocation and public-safe selection remain intact. |
| 54 | Learning/achievement passport | CLEAN — external verified achievements remain presentation-only and provider-bound. |
| 55 | Professional trust timeline | CLEAN — bounded public-safe event types only; private reviewer evidence remains excluded. |
| 56 | Expertise/knowledge graph/coverage | CLEAN — no local ranking/cure score; native content owners remain source of truth. |
| 57 | Grounded AI medical-scope safety | CLEAN — diagnosis/prescription/dose/potency/emergency/treatment requests remain rejected and throttled. |
| 58 | Grounded AI evidence/citations | CLEAN — empty/ungrounded answers cannot return success; citations remain accepted safe public links. |
| 59 | Privacy-safe contact relay | CLEAN — transport stays File 17-owned; recipient address is not exposed. |
| 60 | Verified external/institutional links | CLEAN — only verified HTTPS links are projected. |
| 61 | Dossier/embed/QR/share | CLEAN — scriptless/tracking-free/canonical references remain presentation-only. |
| 62 | FHIR projection boundary | CLEAN — public professional interoperability only; no patient clinical record or FHIR server ownership. |
| 63 | Federation opt-in/transport | CLEAN — explicit owner opt-in path and complete inbox+outbox requirement remain; File 03 does not own transport. |
| 64 | Base privacy export SQL failures | **DEFECT FOUND → FIXED** — profile/report/professional read failures could be mistaken for an empty successful export; explicit DB-read errors now propagate. |
| 65 | Future privacy export profile read | **DEFECT FOUND → FIXED** — failed base-profile lookup in future exporter/eraser could look like no profile; DB failure is now explicit/retryable. |
| 66 | Non-destructive uninstall / purge separation | CLEAN — default uninstall remains non-destructive; destructive purge remains separately gated. |
| 67 | Retention cleanup failure truthfulness | **DEFECT FOUND → FIXED** — cleanup SQL failures could still stamp a successful retention run; success is recorded only if all queries succeed, otherwise an observable File 24 failure record is emitted. |
| 68 | Outbox retry/dead-letter behavior | CLEAN — event delivery lease/retry/dead-letter behavior remains bounded and observable. |
| 69 | Health/observability redaction | CLEAN — health exposes bounded status/reasons rather than secrets or raw sensitive evidence. |
| 70 | Safe mode / high-risk mutation blocking | CLEAN — mutation and moderation paths remain fail-closed while safe mode is active. |
| 71 | Migration concurrency lock | **DEFECT FOUND → FIXED** — transient timing could race with concurrent cron callbacks; one outer CAS lock now serializes the migration batch before legacy inner state is touched. |
| 72 | Schema readiness/repair | CLEAN — owned schemas are checked and repair remains owner-scoped; companion data is not mutated. |
| 73 | Activation/version contract | CLEAN — runtime/DB/contract/plan identities remain distinct and migration-aware. |
| 74 | Scheduled jobs registration/deactivation | CLEAN — owned cron jobs remain bounded and are unscheduled on deactivation. |
| 75 | HTML/private/public cache/index headers | **DEFECT FOUND → FIXED** — route header helper was not reliably wired and revocation-sensitive dynamic pages could be externally cached; `send_headers` + no-store/no-cache + private noindex hardening is now active. |
| 76 | SEO/canonical/structured-data safety | CLEAN — canonical output is limited to public context; private context is noindex. |
| 77 | RTL/accessibility/static UI states | CLEAN AT SOURCE — semantic/keyboard/RTL/reduced-motion source controls remain; real browser/WCAG acceptance is still an external staging gate. |
| 78 | Browser retry/idempotency behavior | CLEAN — unchanged payload retries retain the same mutation key; edited payload rotates it. |
| 79 | CI/traceability, compatibility and runtime composition for this second fresh review | **DEFECT FOUND → FIXED** — the repository initially had only the previous fresh-80 ledger/gate. Exact-candidate/PR gates then exposed three QA/compatibility defects in sequence: a stale legacy forty-round cursor assertion after the stronger cursor contract; provider-guard registration occurring too early for immutable bootstrap composition; and a PHP 8.1 incompatibility caused by a trait constant used for the abandoned-idempotency timeout. The independent 2026-08-11 ledger/invariant gate was added, the legacy test now requires the stronger signed profile/filter-bound cursor, provider guards register at `plugins_loaded`, and the timeout is implemented as a PHP-8.1-compatible trait method. Each failure was corrected before the next acceptance run. |
| 80 | Exact-head/package/status truthfulness | CLEAN AT REPOSITORY REVIEW — no staging/live claim is made; final exact-head CI/package status must be taken from the post-review PR/main runs, not from historical evidence. |

## Defect-bearing rounds

`03, 05, 07, 08, 13, 16, 17, 18, 20, 23, 25, 28, 29, 30, 32, 34, 40, 41, 46, 47, 49, 51, 64, 65, 67, 71, 75, 79`

Count: **28 defect-bearing rounds / 52 clean rounds**.

## Truth-status boundary

This review establishes only the corrected repository candidate after the above sequential review/fix cycle. It does not establish Hostinger staging acceptance, exact deployed package parity, live database/schema state, migration completion on the live site, backup/restore success, browser/device accessibility acceptance, or production operational acceptance.
