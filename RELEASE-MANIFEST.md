# File 03 — Repository Candidate Release Manifest

## Current candidate identity

- Software: `1.2.0-rc15`
- DB schema: `1.2.0`
- Public contract: `1.4.0`
- Plan marker includes `FIFTH-TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW`
- Review branch: `audit/file-03-fifth-twenty-round-20260813-clean`
- Fifth-cycle frozen baseline: `157cfca2ed985ac8025b71a9373e974fca72f1a4`
- Current review state at this manifest revision: R01–R18 reviewed/corrected; R19–R20 pending.

The exact candidate SHA is deliberately not hard-coded here while the same sequential cycle is still advancing. The authoritative repository candidate for any CI/package assertion is the `github.sha` of the corresponding successful exact-head workflow run.

## Current exact-head package evidence

The current `File 03 Fresh Eighty-Round Review` workflow must, on the same reviewed HEAD:

1. pass all retained and fifth-cycle source/runtime gates;
2. run `build-package.sh` twice;
3. prove the two ZIPs are byte-identical;
4. verify ZIP SHA-256 files;
5. prove the two SBOMs are identical and internally valid;
6. unpack the candidate and compare the packaged runtime file set and bytes against the exact checked-out source;
7. verify the package excludes tests, `.github`, release-governance documents and non-runtime material;
8. verify every SBOM file size/SHA-256 against extracted package bytes; and
9. upload the exact ZIP, checksum and SBOM artifact named with the candidate version and `github.sha`.

The runtime package is intentionally limited to:
- `sabri-profiles-doctors.php`
- `uninstall.php`
- `readme.txt`
- `includes/`
- `assets/`

The expected top-level package directory remains `03-sabri-profiles-and-doctors`.

## Historical release evidence

The following repository files are **frozen historical evidence** for earlier recorded candidates and are not silently rewritten to represent rc15:

- `RELEASE-LOCK.json`
- `RELEASE-INVENTORY.tsv`
- `SOURCE-INVENTORY.tsv`
- `CHECKSUMS.sha256`
- `RELEASE-CHECKSUMS.sha256`

Their version/SHA/checksum values must not be interpreted as the current rc15 exact-head package. Current rc15 package truth comes from the exact-head workflow artifact and its generated checksum/SBOM.

## Fifth-cycle correction boundary through R18

Defect-bearing rounds through R18: `01, 05, 06, 11, 13, 14, 15, 16, 17, 18`.

Clean rounds through R18: `02, 03, 04, 07, 08, 09, 10, 12`.

R18 specifically advanced release identity/documentation and made deterministic package/checksum/SBOM/source-package parity part of the exact current review workflow. R19 and R20 remain mandatory before final repository-cycle closure.

## Promotion boundary

This manifest is **repository candidate evidence only**. It does not prove:
- Hostinger staging contains this package;
- the staging/live database has schema/version/migration parity;
- the installed artifact has the same checksum as the exact workflow artifact;
- companion File 00/08/09/16/17/20/21/24/25/26 contracts are the required deployed versions;
- staging browser/mobile/RTL/WCAG acceptance is complete;
- backup restore or rollback rehearsal has passed;
- Founder approval has been granted;
- production deployment or live re-test has occurred.

The required order remains: exact repository candidate → deterministic package → staging reality freeze → artifact/DB/migration/companion parity → staging acceptance → backup/restore + rollback proof → Founder approval → controlled live deploy → live re-test → final parity confirmation.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
