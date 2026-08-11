# File 03 — Fifth Fresh Ten-Round Corrective Review — 2026-08-11

## Frozen repository truth

- Starting `main`: `3358472bc374958c66f5e84997b7633f598caa73`
- Starting Git tree: `49283b40823aaa31348403588311e1912af5851d`
- Review branch: `codex/file-03-fifth-ten-round-20260811`
- Governing scope: File 03 plan + current central-plan addendum + Future Superset 18 + all preserved corrective invariants.

This ledger records a fresh sequential review. Every later round reviewed the corrected repository state produced by the earlier rounds. A round is marked clean only when no new repository-level defect was proven; no speculative patch was added merely to make a round defect-bearing.

## Sequential rounds

| Round | Result | Review and correction |
|---|---|---|
| 01 | Defect found and corrected | File 26 filter adapter bypassed the hardened future-state-aware `spd_get_search_projection()` path and could diverge on lifecycle/degraded-state semantics. The adapter now delegates to the canonical safe helper. |
| 02 | Defect found and corrected | Central privacy export/erasure used table-existence-only `SPD_Central_Profile::schema_ready()`. It now requires `SPD_Schema_Guard::central_ready()` so partial/deferred schema fails closed. |
| 03 | Defect found and corrected | Future privacy export/erasure used table-existence-only `SPD_Future_Profile::schema_ready()`. Both paths now require exact `SPD_Schema_Guard::future_ready()`. |
| 04 | Defect found and corrected | Appeal privacy export could expose requester-authored reason and counterparty user identifiers to a reviewer whose own privacy export happened to include the shared appeal row. Export is now relationship-aware and data-minimized: requester reason is exported only to the requester; counterparty identifiers are not emitted merely because the row is shared. |
| 05 | Defect found and corrected | Public REST `/search-projection` still used the older direct central projection while File 26 used the hardened canonical helper. REST now uses `spd_get_search_projection()` too, eliminating contract divergence. |
| 06 | Clean | Re-reviewed public/private routes, share/alias redirects, IDOR boundaries, minor/guardian restrictions, delegated object binding and report-appeal ownership. No new repository defect was proven. |
| 07 | Clean | Re-reviewed migration serialization, retry/dead traversal, post-batch integrity proof, retention ownership and cron behavior. Existing outer lock and fail-closed integrity guard remained consistent; no new patch was justified. |
| 08 | Clean | Re-reviewed media re-encode/scan SHA binding, required owner/purpose/state metadata, privacy reconciliation, deletion leases, retry/dead state and result persistence. No new repository defect was proven. |
| 09 | Defect found and corrected | Destructive uninstall recovered File-03-owned pages from ownership markers but orphan media recovery still depended on profile/deletion rows. Under the explicit two-gate destructive uninstall only, owned avatar/cover attachments are now also recovered by File 03 ownership + purpose postmeta before deletion. |
| 10 | Defect found and corrected | Material source changes still carried rc4 release identity and the future-state safe-read preflight itself still relied on table-existence-only schema readiness. The candidate advances to `1.2.0-rc5`, adds this fifth-review marker/gate, and `spd_read_future_profile_state()` now requires exact future schema shape. Historical regression/package assertions are updated only as needed to represent the strengthened rc5 implementation without weakening security or parity gates. |

## Totals

- Total fresh rounds: **10**
- Defect-bearing rounds: **01, 02, 03, 04, 05, 09, 10**
- Clean rounds: **06, 07, 08**
- Defect-bearing count: **7**
- Clean count: **3**

## Release identity after corrections

- Plugin candidate: `1.2.0-rc5`
- Repository DB schema: `1.2.0`
- Contract: `1.4.0`
- Plan marker includes `FIFTH-TEN-ROUND-CORRECTIVE-REVIEW`.

## Closure requirements

This ledger is repository evidence, not staging or live evidence. Closure requires exact-candidate PHP/JS syntax, all preserved regression/adversarial gates, this fifth-review invariant gate, deterministic package/checksum/SBOM/source-package parity, PR merge, and exact merged-main re-verification.

Hostinger staging state, deployed package/version/checksum, actual database/schema/migration state, real companion versions, browser/mobile/RTL/WCAG evidence, restore/rollback, Founder acceptance, live deployment and operational verification remain separate gates.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
