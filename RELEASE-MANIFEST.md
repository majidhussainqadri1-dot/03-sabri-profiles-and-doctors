# File 03 — Repository Candidate Release Manifest

## Current candidate identity

- Software: `1.2.0-rc16`
- DB schema: `1.2.0`
- Public contract: `1.4.0`
- Plan marker includes `SIXTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW` and `SEVENTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW`
- Seventh-cycle branch: `audit/file-03-seventh-twenty-round-20260813`
- R20 pre-correction exact HEAD: `95c90da025d2157b578126d69559fc6bac733918`
- Seventh sequential review: **20/20 completed**
- Defect-bearing rounds: `03, 04, 05, 06, 07, 08, 11, 14, 15, 17, 19, 20`
- Clean rounds: `01, 02, 09, 10, 12, 13, 16, 18`
- Final human-readable ledger: `SEVENTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-29.md`

The exact candidate SHA for any present CI/package assertion is the `github.sha` of the corresponding successful exact-head workflow run. Repository source SHA and deployed package truth remain separate.

## Current exact-head package evidence contract

The `File 03 Fresh Eighty-Round Review` workflow must, on the same reviewed HEAD:

1. pass all retained historical/fresh/sequential regression tests plus the seventh-cycle and R20 closure gates;
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

`RELEASE-INVENTORY.tsv`, `SOURCE-INVENTORY.tsv`, `CHECKSUMS.sha256`, `RELEASE-CHECKSUMS.sha256` and historical fields inside `RELEASE-LOCK.json` are **historical provenance/evidence only**. They must not be interpreted as current rc16 exact-head package truth. Current rc16 package truth comes only from the exact-head workflow artifact and its generated checksum/SBOM for that SHA.

## Seventh-cycle correction boundary — final 20/20

The completed seventh cycle corrected cross-file provider exception containment; File00 membership-provider uncertainty; delegated/mutation authorization uncertainty; fail-closed legacy age/contact migration; current-viewer timeline audience enforcement; immutable Founder refresh semantics; Founder/legal-hold erasure safety; and explicit retention-schema operational failure evidence. R20 then reconciled repository/release identity: materially changed source now uses `1.2.0-rc16`, DB remains `1.2.0`, public contract remains `1.4.0`, plan lineage records the sixth and seventh cycles, current repository documents are synchronized, and a permanent R20 closure regression gate is required.

Defect-bearing rounds: `03, 04, 05, 06, 07, 08, 11, 14, 15, 17, 19, 20`.  
Clean rounds: `01, 02, 09, 10, 12, 13, 16, 18`.  
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
