# File 03 — Eighth Fresh Ten-Round Sequential Corrective Review — 2026-08-11

## Governing method

This review started from exact repository `main` `c2c78d774207532e035e738834760b6b7395729e`. Each numbered round reviewed the corrected state produced by every earlier round. A proven defect was corrected before the next round started; a suspected issue that was disproved by effective runtime/repository composition was reverted and recorded as clean rather than retained as speculative hardening.

## Result

- Total fresh rounds: **10**
- Defect-bearing rounds: **01, 03, 04, 05, 06, 07, 08, 09, 10**
- Clean rounds: **02**
- Defect-bearing count: **9**
- Clean count: **1**

## Round ledger

### Round 01 — WordPress privacy erasure retry continuity
**Defect found and corrected.** Base, central and future WordPress erasers could return `done=false` on page 1 after DB uncertainty or lifecycle ordering, but page >1 immediately returned `done=true`. A retry invocation could therefore terminate without retrying the unresolved erasure. All three erasers are now idempotent across page invocations and report completion only when the actual erasure path has completed.

### Round 02 — effective delegation authorization composition
**Clean after disproving an initial suspicion.** The imported central trait alone did not show the minor-delegate checks, but the effective `SPD_Profile_Repository` class privately aliases the trait grant method and supplies the public grant/use-time boundaries that already reject minor delegates. A temporary redundant trait patch was reverted before proceeding. No effective-code defect remained in this round.

### Round 03 — report/appeal database certainty
**Defect found and corrected.** Report moderation lookups, base/central daily report-count reads and appeal eligibility could collapse SQL uncertainty into `404`, `403` or a zero count. Authorization/rate evidence now checks database read errors explicitly; uncertainty is a 503-class failure and any reserved idempotency request is released before a write can proceed.

### Round 04 — schema-version truth on exact-shape repair failure
**Defect found and corrected.** Exact schema guards could detect a partial base/central/future schema after repair, while a previously stored schema-version option could remain current-looking. Canonical boot and activation repair now invalidate the corresponding version option whenever exact required tables/columns/indexes are not proven, while preserving Safe Mode/error behavior.

### Round 05 — future mutation request shape and idempotency fingerprint
**Defect found and corrected.** Selective-disclosure, translation and reconfirm future mutations accepted unknown top-level JSON keys that were omitted from the canonical idempotency fingerprint. Distinct raw requests could therefore collapse to one replay identity. These mutation boundaries now reject unknown fields before any idempotency reservation.

### Round 06 — post-commit profile reread and replay equivalence
**Defect found and corrected.** Base profile update could commit the business mutation, events and successful idempotency response and then fail during a non-strict post-commit profile reread. First execution could therefore diverge from the already committed replay result. Cache invalidation now uses known pre-commit identity, post-commit reread is strict and operationally observable, and reread failure cannot turn the committed client result into a different response.

### Round 07 — media deletion worker operational evidence
**Defect found and corrected.** A media deletion lease-loss/claim-missing anomaly could be recorded during the worker loop and then erased by an unconditional end-of-run deletion of `spd_last_media_queue_error`. The worker now latches non-fatal anomalies for the run and clears operational error evidence only after a genuinely error-free pass.

### Round 08 — share-link rotation idempotent response identity
**Defect found and corrected.** Share-link rotation stored only `public_id` and `version` in the idempotency result, then appended `share_url` only to the first execution after a post-commit reread. Replay therefore had a different response shape. The new epoch-derived share URL is now deterministic before commit, the complete response is stored transactionally, and no post-commit reread is required for the returned result.

### Round 09 — future lifecycle mutation under transient second-read failure
**Defect found and corrected.** The public mutation route performed a strict lifecycle preflight, but the lower future-state helper reread through a permissive function that could substitute `active`/version-1 defaults after a transient SQL failure. A partial request could thereby risk replacing omitted retired/legacy state with defaults. The route now materializes every lifecycle-owned field from the strict preflight before idempotency/mutation, so later transient reread defaults cannot alter omitted state.

### Round 10 — rc8 release identity, permanent QA and release-truth closure
**Defect found and corrected.** Material source behavior had changed while the plugin still identified as `1.2.0-rc7`, the eighth cycle had no permanent executable gate, and release metadata still contained stale earlier-candidate identity. Source identity advances to **`1.2.0-rc8`**; repository DB schema remains **`1.2.0`** and contract remains **`1.4.0`** because this cycle introduces no DDL/contract-version change. Permanent eighth-review invariants, CI integration, deterministic package/checksum/SBOM/source-parity gating and current release-truth metadata are added. Historical review gates remain historical evidence rather than freezing the current source at an older rc identity.

## Release identity

- Plugin/source: **1.2.0-rc8**
- Repository DB schema: **1.2.0**
- Contract: **1.4.0**
- Eighth-review starting main: `c2c78d774207532e035e738834760b6b7395729e`
- Eighth-review branch: `codex/file-03-eighth-ten-round-20260811`

## Truth boundary

This ledger records repository/source and automated-QA work only. It does not establish Hostinger staging acceptance, exact deployed package/version/checksum parity, live database/schema/migration state, real companion-plugin integration, browser/device/RTL/WCAG acceptance, backup/restore/rollback, Founder acceptance, controlled deployment, live re-test, or operational status.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**