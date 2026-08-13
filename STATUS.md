# File 03 Status — 1.2.0-rc15

| Status | Evidence / decision |
|---|---|
| Specified | File 03 amended plan + central governing plan + `FUTURE-SUPERSET-18.md` |
| Repository identity | Plugin `1.2.0-rc15` · DB schema `1.2.0` · contract `1.4.0` |
| Coded | Repository-owned File 03 scope plus `F03-FUT-01..18` and fifth sequential corrective hardening through R18 |
| Fifth 20-round review state | R01–R18 reviewed in order; R19–R20 pending and must start only from the fully corrected/green R18 state |
| Fifth-cycle defect-bearing through R18 | `01, 05, 06, 11, 13, 14, 15, 16, 17, 18` |
| Fifth-cycle clean through R18 | `02, 03, 04, 07, 08, 09, 10, 12` |
| Fifth-cycle frozen baseline | `157cfca2ed985ac8025b71a9373e974fca72f1a4` |
| Current audit branch | `audit/file-03-fifth-twenty-round-20260813-clean`; exact candidate SHA is not frozen until the sequential R20 closure |
| Automated review gate | `.github/workflows/fresh-eighty-round-review.yml` runs all retained review/runtime gates plus fifth-cycle assertions |
| Exact package gate | Same exact-HEAD workflow builds twice, verifies deterministic ZIP/checksum/SBOM, compares source/package runtime file sets and bytes, and uploads the exact artifact |
| PHP compatibility gate | Corrective Integrity must pass PHP 8.1, 8.3 and 8.4 plus source-integrity checks on the same candidate |
| Contract decision | DB remains `1.2.0`; contract remains `1.4.0`; rc15 is a source/release-candidate identity advance, not a DB-schema or public-contract-version change |
| Correction themes through R18 | strict REST transport/shape; migration ledger certainty; route publication repair; strict report/appeal workflow; multilingual AI clinical-intent boundary; provider/timeline containment; atomic event encoding; lifecycle-safe frontend; browser delegation idempotency; canonical legacy URLs; operational health certainty; exact package/release evidence |
| Historical release locks/checksums | `RELEASE-LOCK.json`, `RELEASE-INVENTORY.tsv`, `SOURCE-INVENTORY.tsv`, `CHECKSUMS.sha256`, `RELEASE-CHECKSUMS.sha256` are retained as frozen historical evidence and are **not** current rc15 package truth |
| Staging-Accepted | **Pending / unverified** |
| Live-Deployed | **Unverified** |
| Live DB / migration | **Unverified** |
| Deployment parity | **Unverified** |
| Operational | **Not established** |

## Current closure boundary

R18 release/package corrections are repository work only. R19 and R20 must still complete the same required sequence: full review → consolidated defects → fix all defects from that round → exact-head retest → next round. Repository completion is not asserted until R20 and its final exact-head gates are green.

The fifth-cycle staging matrix now includes appeal review/reopen, multilingual patient-specific treatment refusal, provider-exception degradation, lifecycle-safe browser rendering, browser delegation idempotency, legacy URL canonicalization, managed-page publication repair, Safe Mode timestamp evidence, exact package checksum/SBOM/parity, backup/restore and rollback.

Repository and CI evidence do not establish Hostinger staging or live state. External acceptance remains: staging reality freeze → exact installed package/version/checksum → DB/schema/migration verification → current companion contracts → representative browser/mobile/RTL/WCAG journeys → backup/restore/rollback → Founder acceptance → controlled deployment → live re-test → parity confirmation.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
