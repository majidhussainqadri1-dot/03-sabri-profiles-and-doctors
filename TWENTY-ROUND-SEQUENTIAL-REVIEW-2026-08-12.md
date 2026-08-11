# File 03 — Fresh Twenty-Round Sequential Corrective Review — 2026-08-12

## Governing basis

This is a new 20-round sequential Review → Fix cycle for File 03 — Profiles and Doctors. The frozen starting repository state was reviewed against the File 03 amended master plan and the Definitive Integrated Master Plan. Each next round began only after every proven defect from the preceding round had been corrected. Repository, staging and live remain separate realities.

- Starting exact `main`: `60207107479c971cae4be379e427e1adb212ea92`
- Starting tree: `1145214fbcff6afd9bf08289e9a112ffacfc4aaf`
- Review branch: `audit/file-03-twentieth-round-sequential-20260812`
- Corrective software identity after material changes: `1.2.0-rc11`
- Database schema identity: `1.2.0`
- Contract identity: `1.4.0`

## Sequential rounds

| Round | Result | Correction / evidence focus |
|---|---|---|
| 01 | Defect found and corrected | Central protected REST actions could collapse File 00 provider/claim uncertainty into ordinary account ineligibility. Permission truth now separates 401 unauthenticated, 503 provider/claim unavailable and 403 valid-but-ineligible. |
| 02 | Defect found and corrected | Personal-site DTO performed a second non-strict profile read after a DB-certain public DTO. The second read is now strict so store uncertainty cannot become 404. |
| 03 | Defect found and corrected | Share, alias, legacy and canonical redirect resolution could collapse profile/field-store uncertainty into not-found/revoked behavior. Redirect resolution is now DB-certain and distinguishes 503 from genuine 404. |
| 04 | Defect found and corrected | Delegation privacy export unnecessarily exposed internal/counterparty WordPress user/profile IDs. Export now returns the data subject’s relationship role and relevant delegation metadata without those internal identifiers. |
| 05 | Defect found and corrected | Future protected REST actions repeated the File 00 provider-unavailable → 403 ambiguity. Future authorization now uses the same 401/503/403 truth boundary. |
| 06 | Defect found and corrected | A failed media attachment deletion could be persisted to retry/dead-letter while the run-level media error latch was later cleared. Real deletion failure now remains operational evidence until a clean run. |
| 07 | Clean | Exact schema guard, migration lock and independent post-run migration integrity reconciliation already prevent false completion and reschedule retryable work. No speculative patch. |
| 08 | Clean | Identity creation/refresh already had DB-certain final read, version preconditions, transactional slug history, unique constraints, Founder singleton protection and replay semantics. No proven defect. |
| 09 | Defect found and corrected | Core REST still collapsed File 00 provider failure into 403, and moderation/report profile lookups were non-strict. Core permission truth and moderation/report object reads are now DB-certain. |
| 10 | Defect found and corrected | Timeline profile entry could convert profile-store uncertainty into private/unavailable 404. Public-ID and numeric profile reads now preserve DB-degraded truth. |
| 11 | Clean | Canonical outbox dispatcher already had exact schema guard, lease CAS, stale-lease recovery, retry/dead persistence, lost-lease detection and run-level error latching. No new defect. |
| 12 | Defect found and corrected | Explicit two-gate destructive uninstall left File-03-owned timeline circuit-breaker transients behind. Those dynamic transients are now purged only inside the explicit destructive uninstall gate. |
| 13 | Defect found and corrected | Selective-disclosure revocation verification could turn profile-store failure into 410 revoked. Store uncertainty is now 503 while verified invalid/expired/revoked states retain their correct semantics. |
| 14 | Defect found and corrected | Future write helpers could encounter a DB failure after strict preflight and return a business conflict/not-found result. Post-preflight helper reads are now DB-certain across the mutation transaction. |
| 15 | Defect found and corrected | File 08 delegation projection could emit a fresh-looking `allowed=false` claim when File 00/File 09/delegation-store truth was unavailable. Uncertain dependency/store state now yields no authoritative claim. |
| 16 | Clean | Core profile/professional/Future mutation envelopes already enforce field allowlists, audience allowlists, version preconditions and semantic idempotency hashing; no new request-integrity defect was proven. |
| 17 | Defect found and corrected | Frontend rendering performed a non-strict owner reread before Future lifecycle augmentation; an outage could skip retired/legacy contact/appointment suppression. Owner state is now DB-certain before rendering. |
| 18 | Defect found and corrected | System Check and Repair could map failed operational count queries to zero. Operational counts now return unknown (`null`) plus degraded query health, and Repair will not execute from an uncertain diagnosis. |
| 19 | Defect found and corrected | The fresh 20-round branch was missing from Fresh/Future workflow push coverage. After enabling exact-branch gates, CI exposed an isolated timeline runtime fatal caused by unconditional `$wpdb` access and a non-semantic If-Match regex spelling drift. Source composition was fixed without weakening DB certainty or strict precondition behavior, then exact-SHA Corrective/Fresh/Future gates passed. |
| 20 | Defect found and corrected | Material runtime, privacy, degraded-state, operational and QA changes made `1.2.0-rc10` release identity and permanent release evidence stale. Identity advances to `1.2.0-rc11`; this ledger and a permanent twenty-round regression gate are integrated into the exact release workflows and package/parity proof. |

**Defect-bearing rounds:** `01, 02, 03, 04, 05, 06, 09, 10, 12, 13, 14, 15, 17, 18, 19, 20`

**Clean rounds:** `07, 08, 11, 16`

## Release closure rule

The rc11 candidate is not frozen merely because an intermediate branch commit is green. The exact promoted candidate must pass all applicable source/runtime regressions, the permanent twenty-round gate, PHP 8.1/8.3/8.4, two fresh post-correction gates and deterministic package/checksum/SBOM/source-package parity on one exact SHA. Any later source, test, workflow, release-metadata or documentation change creates a new candidate and requires re-verification.

## Truth boundary

This review establishes repository findings and corrections only. It does not establish Hostinger staging acceptance, exact deployed package parity, live database/schema/migration state, real companion-provider behavior, Founder acceptance, production deployment or operational service levels.

Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.
