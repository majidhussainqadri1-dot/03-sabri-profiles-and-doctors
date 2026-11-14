# File 03 — Staging Acceptance Matrix for 1.2.0-rc18 (Master Plan v12)

**Status:** checklist only. No staging acceptance, live deployment or operational claim is made by this document.

The new repository-only WP7.0.1/MariaDB11.4 smoke and exact-head File26/FHIR regressions are **not** substitutes for these unchecked staging acceptance receipts. Confirm source-to-installed checksum parity first.

Before any Founder approval or live promotion, freeze staging reality: exact installed File 03 package/version/checksum, active companion versions/contracts, DB/schema versions, migration cursor/failure state, Safe Mode state, scheduled workers, relevant configuration and fresh runtime logs. Compare the installed artifact with the exact reviewed GitHub candidate; a repository-green result is not deployment parity.

## A. Install / upgrade / schema / routes

- [ ] Fresh install on a staging backup/restore point completes without fatal error.
- [ ] Upgrade from the currently deployed File 03 version preserves profile/public-ID/slug history and runs migration idempotently.
- [ ] `spd_db_version=1.2.0`, central/future schema versions and required columns/indexes are verified from the **staging database**, not inferred from source.
- [ ] Founder, Profile, Edit Profile, Personal Website and Private Preview managed pages exist and are `publish`; set each once to draft/private/trash and verify bounded Repair restores the owned page to `publish` without overwriting an unrelated page.
- [ ] System Check labels an owned required page `unpublished` until repair, rather than reporting it available.
- [ ] Legacy public `/profile/?public_id=<UUID>` for an eligible public profile returns a permanent 301 to `/profile/<UUID>/`; invalid/private/DB-uncertain cases do not disclose object existence.
- [ ] Alias and share-token redirects remain canonical and revoked share epochs stop resolving.

## A2. Fresh-activation and current-owner negative acceptance

- [ ] Fresh WordPress 7.0.1 / PHP 8.3 / MariaDB 11.4 staging activation persists `spd_safe_mode=false` and `spd_migration_cursor=0` as actual existing wp_options rows, with correct readback on a fresh request.
- [ ] File 26 search results deny a stale profile-version document and any historical Founder `verified_doctor=true` index document; rebuilt current public results remain accessible.
- [ ] File 09 doctor verification revoke/reinstate changes invalidate cached File 26 doctor-badge claims, even without a File 03 version increment; FHIR Practitioner projection is absent for ordinary or unverified members.

## B. Identity, role and authorization

- [ ] Founder profile remains immutable to generic moderation.
- [ ] Member/doctor/minor/guardian/operator journeys use current File 00 assertions at protected action time.
- [ ] Doctor verification display and directory eligibility use current validated File 09 projection only.
- [ ] Provider outages/stale claims cause unavailable/degraded states rather than permissive authorization.
- [ ] Delegation is restricted to eligible verified adult-doctor ownership and eligible non-minor delegates with allowed scopes/expiry.

## C. Privacy, public projection and lifecycle

- [ ] Public profile contains only allowed current fields/media/contact projections; National ID/passport/guardian/private evidence is absent.
- [ ] Per-field public/members/contacts/private visibility behaves correctly; unknown/minor state remains privacy-safe.
- [ ] `retired` and `legacy` professional lifecycle states suppress public contact relay and appointment URL in **browser rendering**, REST and public contract projection.
- [ ] Future-state DB uncertainty suppresses contact/appointment/federation activity and exposes an explicit degraded state.
- [ ] Erasure respects legal/Founder holds, tombstones the profile, clears personal fields/media references and preserves only documented integrity records.
- [ ] Privacy exporter/eraser DB failures return retry/error evidence rather than false empty success.

## D. Reports and appeals

- [ ] Safety-report creation validates reason/detail, current account eligibility, target visibility, rate evidence and idempotency.
- [ ] Report-store/rate-evidence DB failure returns unavailable/503 semantics; it never appears as a 404 or a zero report count.
- [ ] Eligible reporter can submit an appeal only for an appealable report state.
- [ ] Appeal lookup DB failure returns unavailable/503 semantics rather than “appeal unavailable.”
- [ ] Moderator journey executes `submitted → in_review → upheld|rejected` with required decision note, optimistic version check and audit event.
- [ ] Upheld appeal reopens the underlying `rejected|closed|actioned` report to `in_review` with CAS/version protection and `ProfileReportReopenedByAppeal.v1` evidence.
- [ ] Appeal review queue DB failure is explicit and cannot render as an empty healthy queue.

## E. Profile-work AI and Future Superset

- [ ] Neutral questions about the professional’s published work can receive only grounded answers with public citations.
- [ ] Patient-specific treatment/diagnosis/prescription/dose requests are refused in English and representative Urdu, Arabic, Hindi, Chinese, Spanish, French, Portuguese, Bengali and Turkish phrases.
- [ ] Direct File 16 grounded-profile claim consumer is also blocked by the clinical-intent guard; REST is not the sole protection layer.
- [ ] Missing/stale/malformed/throwing Future providers produce safe 503/degraded responses, not a fatal page/request.
- [ ] Selective-disclosure token signature, TTL, scope and share-epoch revocation are verified; disclosure is no-store/private.
- [ ] Owner-approved translations and field reconfirmation reject invalid locale/unknown keys and preserve DB uncertainty.
- [ ] Federation remains explicit profile-owner opt-in and transport remains external; non-active lifecycle suppresses transport activity.
- [ ] FHIR/public Future projections contain only public-safe profile/professional facts and no clinical chart/patient data.

## F. Timeline / provider resilience

- [ ] Timeline provider-health claims require current contract/freshness and valid status.
- [ ] Provider registry filter exception falls back to canonical providers without page failure.
- [ ] Provider health exception marks that provider degraded/partial and trips bounded circuit protection.
- [ ] Provider item exception/oversize/malformed/cross-author/external URL/future timestamp item is safely rejected or degraded.
- [ ] Cursor HMAC, profile/filter binding and pagination remain deterministic and tamper-resistant.
- [ ] Browser profile and structured-data generation contain cross-file provider exceptions; no PHP fatal is produced.

## G. Browser mutations / idempotency

- [ ] Profile, professional, report, moderation and share mutations require correct nonce/authorization/version/idempotency contracts.
- [ ] Non-JavaScript delegation **grant** form emits an idempotency key and succeeds; retry with the same unchanged request does not create a duplicate effect.
- [ ] Non-JavaScript delegation **revoke** form emits an idempotency key and succeeds; retry is replay-safe/does not create a second mutation.
- [ ] JavaScript mutation keys remain stable for unchanged payload retry and rotate after form edits/new payload.
- [ ] Concurrent update/version conflicts return controlled conflict state and do not overwrite newer data.

## H. Media / storage privacy

- [ ] Avatar/cover enforce MIME/ext/size/dimension constraints, metadata stripping/re-encoding and exact-byte scanner binding.
- [ ] Public media is owner/purpose/state revalidated before projection.
- [ ] Privacy tightening, moderation and erasure detach media and use retryable/dead-letter deletion ledger.
- [ ] Media deletion/store uncertainty remains visible in System Check and File 24 evidence.

## I. Events / queues / operational evidence

- [ ] Unencodable event payload aborts the owning transaction; no `pending` row with invalid/null payload is committed.
- [ ] Hardened outbox dispatcher exclusively owns `spd_dispatch_outbox`, resets expired leases, CAS-claims work and records delivery-persist uncertainty.
- [ ] Retry/backoff/dead-letter/manual requeue preserve at-least-once semantics without false delivered state.
- [ ] System Check DB read failure for dead outbox/media/migration queues, reports or moderation records displays an error—not “No items.”
- [ ] Provider-health System Check rejects stale/malformed claims and contains a throwing health callback.
- [ ] Enable and disable Safe Mode with a reason; verify `spd_safe_mode`, reason **and `spd_safe_mode_changed_at`** all persist/read back and appear in System Check.
- [ ] Retention job failure records error evidence and does not advance `last_retention_run` after a failed query.

## J. UI / accessibility / RTL

- [ ] Desktop/mobile browser journeys for Founder/member/doctor/private edit/personal-site/report/timeline have no layout-breaking overflow.
- [ ] Urdu/Arabic RTL and English/numbers/URLs LTR render correctly.
- [ ] Keyboard-only interaction, visible focus, labels, error/status announcements, reduced-motion and screen-reader headings/landmarks meet acceptance expectations.
- [ ] Private/authenticated/report/print surfaces remain no-store/noindex where required; public canonical profile/timeline canonical tags are correct.
- [ ] File 20 remains shell/navigation/layout owner; File 03 does not duplicate global header/sidebar/mobile shell.

## K. Exact package / backup / rollback / promotion

- [ ] Exact reviewed HEAD Fresh workflow passes all retained/fifth-cycle tests **and** deterministic package job.
- [ ] Two builds from the exact HEAD produce byte-identical ZIP, `.sha256` and SBOM.
- [ ] ZIP has one top-level `03-sabri-profiles-and-doctors` folder; runtime file set/bytes exactly match reviewed `sabri-profiles-doctors.php`, `uninstall.php`, `readme.txt`, `includes/`, `assets/`.
- [ ] Package contains no `.github`, `tests`, release-governance documents, secrets or private runbooks.
- [ ] Artifact SHA-256/SBOM are recorded beside staging’s installed artifact and deployed checksum parity is verified independently.
- [ ] Backup restore is performed and verified, not merely created.
- [ ] Rollback from rc15 to the previously deployed package/version is rehearsed with DB/data compatibility and queue/migration state documented.
- [ ] Founder acceptance records exact package checksum, staging evidence and explicit promotion decision.
- [ ] After controlled live deployment, repeat critical smoke tests and confirm deployed artifact checksum/version/DB/migration parity before calling the incident/change resolved.

## V12.3 D14–D16 supplemental safety gate (not part of the original 72)

These extra checks do not increase the original 72-case baseline and do not count as accepted without actual independently logged staging outcomes.

- [ ] **D14 owner consent**: a generic profile operator cannot activate/deactivate federation for another owner; the eligible owner can grant and withdraw opt-in. Test current authorization, revoked operators, role switches and a second session.
- [ ] **D14 transactional outbox**: on genuine MariaDB, deliberately fail event insertion in a safe disposable staging fixture for profile translation, field reconfirmation and future-state change; each must roll back **both** records. Healthy cases commit mutation and event once, and a current downstream consumer receives the event without duplicate effects.
- [ ] **D15 Founder governance**: a generic File 00 profile operator cannot retire, memorialize or alter Founder future state. Only a current actor with explicit Founder-governance authority may perform allowed changes; audit and revocation receipts are required.
- [ ] **D16 retired/legacy federation**: with a previously opted-in active profile, change the lifecycle to retired/legacy; ensure public DTO, File 17 provider, caches, search and actual client never expose active inbox/outbox transport. Restoring active state without owner consent must not silently reactivate a previously revoked opt-in.
- [ ] **D16 uncertain future state**: when the owner future-state provider is unreachable, null, stale or malformed, federation transport is unavailable, an explicit degraded state is retained, and no HTTP dispatch takes place.

For each supplemental case attach: exact GitHub SHA, installed package SHA256, actor/role, timestamp, actual DB rows before/after, provider version, HTTP response, relevant sanitized logs, expected/actual, clean-up/restore proof, independent QA approver, and retest status. These checks are in addition to the existing 72 and V12.3 QA-15..24.

## Required final staging evidence record

Record separately: **Repository HEAD / Reviewed package SHA-256 / Staging installed version & checksum / DB version / central & future schema versions / Migration state / Safe Mode state / Companion contract versions / Backup restore evidence / Rollback evidence / Founder acceptance / Live verification status**.

Until those records exist: **Exact deployed code is unverified; repository-based diagnosis is provisional for any live incident.**
