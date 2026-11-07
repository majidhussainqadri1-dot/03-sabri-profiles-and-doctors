# File 03 — Master Plan v12 evidence-first source/CI remediation, 2026-11-07

## Evidence boundary

This ledger records **repository-source corrections and disposable CI only**. It does not prove staging/live deployment, installed package/DB parity, operational readiness or approval of all v12 requirements.

Governing user-supplied `Sabri-Website-Master-Plan-v12.docx` File 03 section, actual paragraphs 983–1026: **13 F03-FR + 10 F03-NFR + 2 F03-CEN + 18 F03-FUT = 43** unique File 03 requirement IDs. Additional central CV constraints remain in `LATEST-PLAN-TRACEABILITY.md`; that register is source traceability, not 100% end-to-end approval.

Frozen start: GitHub File 03 `main` `636e3ef965423887f810718abec3cd1c11c3659d` (merged PR #39, runtime `1.2.0-rc18`, DB `1.2.0`, public contract `1.4.0`). Draft source remediation PR #40 from this exact baseline: `audit/f03-v12-file26-version-visibility-20261107`. Current File 26 read-only companion source: `1b064c6ea9f81195611ed001d5457e8ced65460a`, 53 commits beyond the exact File 26 companion source frozen during File 03's October tenth review. Current File 00 main pinned for real CI: `2fa7c022ee9cd1b65432e900579512f304532442`, runtime `1.2.44`.

## Defect ledger: 5 distinct source-code defect families; all 5 corrected in draft candidate

| ID | Requirement and proven source issue | Fail-first receipt | Candidate correction / verified effect |
|---|---|---|---|
| D01 | F03-FR-013, NFR-001/002, CEN-01/02: File 26 Search displays a stored document after File 03 boolean `can_view`. File 03 compared current canonical ID but ignored indexed `object_version`; after profile field/audience privacy update, old indexed fields could still be returned. | Run `42357334115`, `FAIL: stale search document disclosed after profile version changed`. | Commit `b7f8136df1eea430c2d8bcce4b2c05c6d838e467`. Require positive canonical document and current profile versions to match exactly; fail closed on missing/error/identity mismatch. |
| D02 | F03-FR-011/013, CEN-02: File 09 doctor-verification revoke/restore may change current Badge truth without incrementing File 03's native profile version. Old File 26 `payload.verified_doctor` could survive into search results. | Run `42357575655`, `FAIL: revoked File09 verified-doctor fact remains visible through stale File26 index`. | Commit `8c545332d6c1f6e435d130a69fc1c960a30a5178` and final hardening `b4a364106e202fbfabd600d60a531f55816165e3`. Require exact File 09 `verified_doctor` badge key/verified state to agree with stored index for every entity type. |
| D03 | F03-NFR-007/009: **real WordPress 7.0.1 + MariaDB 11.4** File 03 fresh activation failed with `File 03 safe-mode state could not be initialized safely`. `update_option( absent, false )` did not insert the missing wp_options row; strict numeric readback could also reject actual DB string scalars. | Real WP run `42357777437` RED. Isolated read-only diagnostic run `42357943715` proved `spd_safe_mode` and `spd_migration_cursor` were absent, not merely mistyped. | Commit `817c22ceac555b5302e27b16d7143b65fa8433c5`. Explicit `add_option` for missing values, safe `update_option` for existing ones, exact readback with WordPress scalar representations; preserve fail-closed on no persisted row. Real WP repeat CI run `42358053159` GREEN. |
| D04 | F03-FUT-17, FR-011: `fhir_projection()` emitted FHIR `Practitioner`/`PractitionerRole` for ordinary public members or unverified professional claims. | Run `42358276115`, `FAIL: public member incorrectly represented as FHIR Practitioner`. | Commit `9fd4bf8d94890ba56f9b9ab8b673ca5f0fb8ccf2`. FHIR professional output requires current doctor profile plus distinct `verified_doctor` badge and verified claim. Official Founder badge does not substitute. Non-active doctor remains inactive in FHIR. |
| D05 | F03-FR-011/013: indexer treated any `badge.verified=true` as `verified_doctor=true`, conflating Founder official verification with doctor verification. Historical stored Founder index still required click-time containment after source correction. | Run `42358431866`, `FAIL: Founder badge conflated with verified doctor in File26 search`; historical index RED run `42358575663`, `FAIL: previously indexed Founder false-doctor badge remained visible`. | Source fix `253e941f16751e110977063e33307039545abf2e` and historical click-time hardening `b4a364106e202fbfabd600d60a531f55816165e3`. Only `profile_type=doctor` + exact File 09 badge key + verified truth produces doctor index flag; all old false claims are denied. |

**Accounting:** D01–D05 = **5 discovered/corrected source-code defect families**, including D05's historical-index follow-through. A separate stale staging checklist release-heading mismatch was corrected to rc18 (documentation, not sixth source defect). **Zero production/live fixes verified.**

## Added permanent executable and integration regression paths

- `tests/file26-document-version-runtime.php` — profile version, missing/invalid/stale entry, provider-error/ID mismatch.
- `tests/file26-doctor-badge-revocation-runtime.php` — same native version, current File 09 verified badge revoke/reinstate, stale index suppression.
- `tests/file26-founder-badge-separation-runtime.php` — Founder vs verified doctor document plus historical False Verified Doctor stored index.
- `tests/activator-option-persistence.php` — absent false option, 0 migration cursor, idempotent string version, failed writes must not report success.
- `tests/fhir-verified-professional-runtime.php` — member, Founder and unverified doctor denial; verified doctor Practitioner; retired state disabled.
- `.github/workflows/fresh-eighty-round-review.yml` — mandatory exact-head permanent execution of all 5 new PHP gates before package job.
- `.github/workflows/file03-v12-wordpress701-mariadb114.yml` — real disposable WordPress 7.0.1/PHP8.3/MariaDB11.4 with pinned exact File00 1.2.44, actual File00 deferred administrator bootstrap, File03 fresh install, owned base/central/future schema postconditions, repeated repair and contract state. **This is not staging or live acceptance**.

## Release and external acceptance remaining

`STAGING-ACCEPTANCE.md` is now labeled rc18/v12 and has **72 unchecked** practical staging receipts (69 earlier + 3 current regressions). These remain required despite source CI success.

Before any 100%/Production-Ready claim: install exact reviewed package on staged WordPress; freeze current plugin/DB/schema/migration/Safe Mode/runtime/log/checksums; verify latest File 09 and File 26 real consumers and current File 02 source/deployed parity separately; browser/mobile/Urdu-Arabic RTL/WCAG and p75/p95; external File08/17/19/20/21/24/25 current assertions; patient-minor/guardian safety; full fresh/upgrade rollback/restore; Founder acceptance; controlled deployment; live re-test and checksum parity.

The candidate runtime still identifies as `1.2.0-rc18` while this PR remains unmerged; **before an approved promotion, choose a unique release identity/version and freeze the exact reviewed package checksum**. Do not conflate an rc18 branch snapshot with older rc18 main. No branch merge or deploy is authorized by this ledger.

## Five required live-truth fields

| Field | State |
|---|---|
| Repository HEAD | File03 main `636e3ef965423887f810718abec3cd1c11c3659d`; tested source candidate SHA is recorded separately in GitHub PR #40 exact-head CI, not main |
| Deployed Version | Unknown; no fresh live artifact acquired |
| DB Version | Live unknown; candidate declares `1.2.0` but that is not deployed proof |
| Migration State | Live unknown; disposable WordPress/MariaDB CI not production migration |
| Live Verification Status | Not performed; not Resolved/Production Ready |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Gate decision:** Keep PR #40 **draft/unmerged**, until exact-head CI, release identity, independent cross-file/staging evidence, and explicit promotion approval.
