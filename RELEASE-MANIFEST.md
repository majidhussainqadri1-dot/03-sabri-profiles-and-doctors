# File 03 — Repository Candidate Release Manifest

## Current candidate identity

- Software: `1.2.0-rc15`
- DB schema: `1.2.0`
- Public contract: `1.4.0`
- Plan marker includes `FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW`
- Fifth-cycle branch: `audit/file-03-fifth-twenty-round-20260813-clean`
- Fifth-cycle frozen baseline: `157cfca2ed985ac8025b71a9373e974fca72f1a4`
- Fifth-cycle final source before sixth review: `181c4fb33aa0c0637c858b3abf33b74da0ac1609`
- Fifth sequential review: **20/20 completed**
- Defect-bearing rounds: `01, 05, 06, 11, 13, 14, 15, 16, 17, 18, 19, 20`
- Clean rounds: `02, 03, 04, 07, 08, 09, 10, 12`

The exact candidate SHA for any present CI/package assertion is the `github.sha` of the corresponding successful exact-head workflow run. Repository source SHA and deployed package truth remain separate.

## Current exact-head package evidence contract

The `File 03 Fresh Eighty-Round Review` workflow must, on the same reviewed HEAD:

1. pass all retained historical/fresh/sequential regression tests plus the fifth-cycle R18/R19/R20 closure gates;
2. run `build-package.sh` twice using the explicit reproducible-build epoch;
3. prove the two ZIPs are byte-identical;
4. verify ZIP SHA-256 files;
5. prove the two SBOMs are identical and internally valid;
6. unpack the candidate and compare packaged runtime file set and bytes against the exact checked-out source;
7. verify the package excludes tests, `.github`, release-governance documents and non-runtime material;
8. verify every SBOM file size/SHA-256 against extracted package bytes; and
9. upload the exact ZIP, checksum and SBOM artifact named with candidate version and `github.sha`.

The runtime package is intentionally limited to:
- `sabri-profiles-doctors.php`
- `uninstall.php`
- `readme.txt`
- `includes/`
- `assets/`

The expected top-level package directory remains `03-sabri-profiles-and-doctors`.

## Historical release evidence

Historical inventories/checksum ledgers remain provenance evidence for the older candidates they describe. Their version/SHA/checksum values must not be interpreted as the current rc15 exact-head package. Current package truth comes only from the exact-head workflow artifact and its generated checksum/SBOM for that SHA.

## Fifth-cycle correction boundary — final 20/20

The completed fifth cycle corrected REST transport/shape handling; migration ledger certainty; required-route publication repair; strict report/appeal review and reopen workflow; multilingual clinical-intent refusal; provider/timeline exception containment; atomic event encoding; lifecycle-safe browser projections; delegation idempotency; legacy URL canonicalization; operational health certainty; exact deterministic package evidence; fail-closed published PHP contract boundaries; appeal manifest discoverability; and final closure traceability/regression evidence.

Defect-bearing rounds: `01, 05, 06, 11, 13, 14, 15, 16, 17, 18, 19, 20`.  
Clean rounds: `02, 03, 04, 07, 08, 09, 10, 12`.  
Total: `20/20` reviewed; `12/20` defect-bearing; `8/20` clean.

## Promotion boundary

This manifest is **repository candidate evidence only**. It does not prove:
- Hostinger staging contains this package;
- staging/live database schema or migration parity;
- installed artifact checksum parity;
- deployed companion File 00/08/09/16/17/20/21/24/25/26 contract versions;
- staging browser/mobile/RTL/WCAG acceptance;
- backup restore or rollback rehearsal;
- Founder approval;
- production deployment; or
- live re-test / operational monitoring.

Required order remains: exact repository candidate → deterministic package → staging reality freeze → artifact/DB/migration/companion parity → staging acceptance → backup/restore + rollback proof → Founder approval → controlled live deploy → live re-test → final parity confirmation.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
