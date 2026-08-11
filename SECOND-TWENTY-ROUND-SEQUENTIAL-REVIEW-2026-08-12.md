# File 03 — Second Fresh Twenty-Round Sequential Corrective Review — 2026-08-12

## Governing basis

This is a new 20-round sequential Review → Fix cycle for File 03 — Profiles and Doctors. The frozen starting repository state was reviewed against the File 03 amended master plan and the Definitive Integrated Master Plan. Every next round began only after every proven defect from the preceding round had been corrected. Repository, staging and live remain separate realities.

- Starting exact `main`: `a34e4e2b808134237ae9945759745595685c8733`
- Starting tree: `c0d41641c66cb897c1073dbb40943c5cf9093d44`
- Review branch: `audit/file-03-second-twenty-round-20260812`
- Corrective software identity after material changes: `1.2.0-rc12`
- Database schema identity: `1.2.0`
- Contract identity: `1.4.0`
- Contract rationale: removing undeclared internal WordPress `attachment_id` from the public media DTO is privacy hardening, not a documented public-contract field removal; the machine-readable contract already requires opaque public identity and public DTO allowlists.

## Sequential rounds

| Round | Result | Correction / evidence focus |
|---|---|---|
| 01 | Defect found and corrected | Central authorization moderation guard could collapse File 00 provider/claim uncertainty into ordinary 403. It now separates safe mode/provider/claim 503 from valid-but-unauthorized 403. |
| 02 | Defect found and corrected | Anonymous/public media DTO exposed raw WordPress `attachment_id`. Internal attachment primary keys were removed from the public DTO while URL/alt/focal presentation remains. |
| 03 | Clean | Canonical public-ID/slug/share/legacy resolution already preserved DB uncertainty, tombstone/gone semantics and revocable signed share behavior. No speculative redesign. |
| 04 | Clean | Base/Central/Future privacy export and erasure already used schema/DB certainty, relationship-aware minimization, legal-hold and retained-integrity handling. |
| 05 | Defect found and corrected | Future Ask Work could turn a profile-store read failure into 404. The REST boundary now checks DB certainty and returns 503 for store uncertainty. |
| 06 | Defect found and corrected | Media privacy and deletion workers shared one error option and a clean run of one worker could erase the other worker’s real failure evidence. Error clearing is now family-specific. |
| 07 | Defect found and corrected | Legacy Founder option migration deleted the source option without proving that the read-only target persisted. Persistence is verified before deleting the source; failure aborts activation safely. |
| 08 | Clean | Profile identity, Founder invariant, public UUID, slug registry, optimistic versioning, transactional mutation and replay behavior retained adequate concurrency barriers. |
| 09 | Defect found and corrected | Base `create_report()` domain command did not distinguish File 00 unavailable/malformed truth from account state. It now revalidates provider/claims and preserves 401/503/403 semantics. |
| 10 | Clean | Timeline entry reads, provider freshness, author/object binding, signed cursor, circuit breaker and explicit degraded response remained fail-closed. |
| 11 | Clean | Canonical outbox retained exact schema gate, stale-lease recovery, CAS claim, DB-certain claim read, bounded retry/dead-letter, lost-lease detection and error latching. |
| 12 | Defect found and corrected | Explicit destructive uninstall could leave File-03-owned usermeta when the profile table was partial/missing. Exact File 03 meta keys are now recovered and removed only inside the two-gate destructive purge. |
| 13 | Clean | Selective disclosure remained signed, scope-limited, TTL-bounded, share-epoch revocable and rebuilt from current public/lifecycle truth. |
| 14 | Clean | File 09 verification projection and File 00 current membership claims remained independently freshness/binding checked; suspended users cannot be re-verified by stale projection. |
| 15 | Defect found and corrected | Central `create_safety_report()` repository command repeated the File 00 uncertainty-to-account-state collapse. Domain authorization now preserves 401/503/403 truth. |
| 16 | Defect found and corrected | `request_report_appeal()` repository command repeated the same File 00 uncertainty collapse. It now verifies provider and claims before report ownership/state and idempotent mutation. |
| 17 | Defect found and corrected | Public Founder rendering used an ensure-on-read repository path, so an anonymous GET could create a missing Founder profile. Public rendering is now read-only (`ensure=false`). |
| 18 | Defect found and corrected | System Check omitted persisted worker error reasons and could hide why an otherwise counted subsystem was degraded. It now exposes only redacted sanitized error code + timestamp for active outbox/media/retention/migration failures. |
| 19 | Defect found and corrected | The new branch was absent from Fresh/Future push coverage. After enabling exact-branch gates, CI exposed a stale Eighth-review textual assertion that required unsafe global media-error clearing. The historical test now requires the stronger worker-family isolation invariant; exact-head Fresh and Future runs then passed. |
| 20 | Defect found and corrected | Material runtime/privacy/migration/operational/QA changes made `1.2.0-rc11` release identity and evidence stale. Identity advances to `1.2.0-rc12`; this permanent ledger/test and exact release/package/parity evidence are required before promotion. |

**Defect-bearing rounds:** `01, 02, 05, 06, 07, 09, 12, 15, 16, 17, 18, 19, 20`

**Clean rounds:** `03, 04, 08, 10, 11, 13, 14`

## Release closure rule

The rc12 candidate is not frozen merely because an intermediate branch SHA is green. The exact promoted candidate must pass all applicable source/runtime regressions, this permanent second-twenty-round gate, PHP 8.1/8.3/8.4, two fresh post-correction adversarial gates and deterministic package/checksum/SBOM/source-package parity on one exact SHA. Any later source, test, workflow, release-metadata or evidence-document change creates a new candidate and requires fresh exact-SHA verification.

## Truth boundary

This review establishes repository findings and corrections only. It does not establish Hostinger staging acceptance, exact deployed package parity, live database/schema/migration state, real companion-provider behavior, Founder acceptance, production deployment or operational service levels.

Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.
