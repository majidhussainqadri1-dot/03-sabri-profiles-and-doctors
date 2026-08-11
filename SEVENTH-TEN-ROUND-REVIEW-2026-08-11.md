# File 03 — Seventh Fresh Ten-Round Sequential Corrective Review — 2026-08-11

## Governing method

This review started from exact repository `main` `fdde7311409d68af4bae5917f5a49154cb92c9f4`. Each numbered round reviewed the corrected state produced by every earlier round. A proven defect was corrected before the next round started; clean rounds received no speculative patch.

## Result

- Total fresh rounds: **10**
- Defect-bearing rounds: **01, 02, 03, 04, 05, 06, 08, 09, 10**
- Clean rounds: **07**
- Defect-bearing count: **9**
- Clean count: **1**

## Round ledger

### Round 01 — appeal erasure independent DB-read certainty
**Defect found and corrected.** Requester and reviewer appeal counts were separate reads but the eraser could lose the first read's database error when the second read reset `$wpdb->last_error`. Each count read now captures its own error state; either uncertainty retains data and requests retry before any appeal mutation.

### Round 02 — central exact-schema readiness convergence
**Defect found and corrected.** `SPD_Central_Profile::schema_ready()` still meant table presence only, so central callers could disagree with the exact `SPD_Schema_Guard::central_ready()` contract. Central readiness now delegates to the exact guard, including required columns and integrity indexes.

### Round 03 — minor-delegate grant boundary below REST
**Defect found and corrected.** REST rejected a minor delegate but the reusable repository grant method did not, allowing an internal/companion caller to create a stale-invalid grant. The repository now denies minor delegates at grant time; the existing use-time authority check also continues to deny minor delegates.

### Round 04 — DB version written only after exact schema proof
**Defect found and corrected.** Base install could write `spd_db_version` after merely proving table existence. A partial `dbDelta` result could therefore look current despite missing required columns or indexes. `SPD_DB::install()` now records the version only after `SPD_Schema_Guard::base_ready()` succeeds.

### Round 05 — strict audience validation at repository boundary
**Defect found and corrected.** Strict nested audience validation existed at REST but not at the canonical repository integration boundary. Direct internal callers could therefore submit malformed/non-array maps, unknown keys, or invalid values that were normalized/ignored. Base and personal-site repository update wrappers now enforce the same strict validator before mutation.

### Round 06 — mutation-sensitive identity/slug DB uncertainty
**Defect found and corrected.** Legacy identity helpers can represent a failed public-id/slug SQL read as an empty result. Strict repository lookup methods now convert database or field-store uncertainty into 503-class errors, the protected base update preflight uses the strict public-id read, and custom-slug mutation preflight uses the strict registry read rather than treating uncertainty as availability.

### Round 07 — media ingestion/deletion review
**Clean.** Ownership metadata, synchronous scan SHA binding, public-age/visibility gating, deletion retries, leases, dead-letter requeue, privacy reconciliation and already-delivered immutability were re-traced on the corrected state. No new repository defect was proven.

### Round 08 — fail-closed outbox dispatch persistence
**Defect found and corrected.** The legacy event dispatcher did not independently verify lease reset, queue read, claim read, delivered-state persistence, or retry/dead persistence with operational evidence. A dedicated File 03 outbox dispatcher now verifies each DB step, records File 24-facing failure evidence, uses lease-token result binding, and replaces the legacy worker as one File03-owned cron unit.

### Round 09 — lifecycle-sensitive public projection DB failure
**Defect found and corrected.** After building the central personal-site DTO, a second non-strict profile read could fail as an empty result and return the base DTO before future lifecycle suppression ran. That could preserve contacts/appointment projection during DB uncertainty. The second-stage personal-site and search reads now use strict fail-closed public-id lookup; uncertainty is propagated instead of returning an unsuppressed projection.

### Round 10 — rc7 identity and permanent QA closure
**Defect found and corrected.** Material behavior changed while source still identified as `1.2.0-rc6`, and the seventh review had no permanent executable invariant. Source identity advances to **`1.2.0-rc7`**; DB schema remains **`1.2.0`** because no DDL version change was introduced; contract remains **`1.4.0`**. A seventh-review invariant gate and workflow integration are added. Stale historical release-identity assertions discovered by exact-candidate CI are corrected without weakening their substantive privacy, authorization, concurrency, schema, provider-degradation, determinism, or package-parity checks.

## Release identity

- Plugin/source: **1.2.0-rc7**
- Repository DB schema: **1.2.0**
- Contract: **1.4.0**
- Seventh-review starting main: `fdde7311409d68af4bae5917f5a49154cb92c9f4`
- Seventh-review branch: `codex/file-03-seventh-ten-round-20260811`

## Truth boundary

This ledger records repository/source and automated-QA work only. It does not establish Hostinger staging acceptance, exact deployed package/version/checksum parity, live database/schema/migration state, real companion-plugin integration, browser/device/RTL/WCAG acceptance, backup/restore/rollback, Founder acceptance, controlled deployment, live re-test, or operational status.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
