# Changelog

## 1.2.0-rc14 — Fourth fresh twenty-round sequential corrective hardening

- Started from exact `main` `998571621bae0c33afa347e515be543cb3f4b4e9` (tree `dd8269834b86413e1394884eaab3da3d41ba57dd`).
- Defect-bearing rounds: `01, 04, 06, 14, 16, 18, 19, 20`.
- Clean rounds: `02, 03, 05, 07, 08, 09, 10, 11, 12, 13, 15, 17`.
- Preserves unauthenticated generic profile mutation as 401 separately from authenticated authorization denial.
- Makes delegation grant fail closed on profile-store, File00 provider/current-claim and File09 provider/current-projection uncertainty before applying genuine eligibility denials.
- Verifies initialization of `spd_safe_mode` / `spd_migration_cursor` and exact persistence of Repair evidence before reporting activation/repair success.
- Rejects unknown root JSON fields on core professional/report/moderation mutation routes with 400 before mutation.
- Requires exact Central delegation schema readiness before File08 emits an authoritative delegation `allowed` projection.
- Adds `FOURTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md` and `tests/fourth-twenty-round-sequential-review.py` to permanent CI/package gates.
- Advances source identity to `1.2.0-rc14`; DB schema remains `1.2.0`; contract remains `1.4.0`.
- Final exact candidate/PR/merge evidence remains pending until one post-metadata SHA passes every applicable automated gate.
- Staging, deployed-package parity, live DB/migration state, Founder acceptance and operational status remain unverified.

## 1.2.0-rc13 — Third fresh twenty-round sequential corrective hardening

- Started from exact `main` `1b887186a7097948b41aabd22122d84cd8b080e0` (tree `0113b2993a6def0a7a39f72749436bfd493fc836`).
- Defect-bearing rounds: `01, 05, 07, 09, 12, 13, 14, 15, 17, 18, 19, 20`.
- Clean rounds: `02, 03, 04, 06, 08, 10, 11, 16`.
- Preserves no-actor moderation as 401 separately from File00 provider/claim 503 and valid unauthorized 403.
- Makes Future lifecycle/federation/native translation/freshness/history and Central target reads DB-certain or explicitly degraded.
- Requires exact persistence/read-back of activation and normal runtime-upgrade version/contract/plan metadata.
- Adds original `spd_founder_profile` cleanup only inside explicit two-gate destructive uninstall.
- Enforces File09 minimum claim version and preserves every non-null upstream File26 answer, including errors.
- Keeps logged-in public profile GET side-effect free instead of ensuring/creating a missing profile.
- Adds exact review-branch coverage plus `THIRD-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md` and `tests/third-twenty-round-sequential-review.py`.
- Advances source identity to `1.2.0-rc13`; DB schema remains `1.2.0`; contract remains `1.4.0`.
- Exact reviewed candidate `febc19f1f4384de18d5f51073ad6a437ae6fb852` passed Corrective `31572754678`, Fresh `31572754676`, Future `31572754659`, PHP 8.1/8.3/8.4, permanent Third-Twenty, fresh post-correction gates and deterministic package/checksum/SBOM/source-package parity.
- PR #29 merged that exact head as code-bearing `main` `538ef9e1b5b380bd01417ccc0626625d7c151231`, preserving tree `07b5bdd96d4238430023f5adf963b5c0200ef232`; Baseline `31573348838`, Fresh `31573348862` and Future `31573348876` then passed on the merge commit.
- Staging, deployed-package parity, live DB/migration state, Founder acceptance and operational status remain unverified.

## 1.2.0-rc12 — Second fresh twenty-round sequential corrective hardening

- Started from exact `main` `a34e4e2b808134237ae9945759745595685c8733` (tree `c0d41641c66cb897c1073dbb40943c5cf9093d44`).
- Defect-bearing rounds: `01, 02, 05, 06, 07, 09, 12, 15, 16, 17, 18, 19, 20`.
- Clean rounds: `03, 04, 08, 10, 11, 13, 14`.
- Revalidated File 00 provider/claim truth inside moderation, base report, central safety-report and report-appeal domain boundaries so dependency uncertainty remains 503 rather than fabricated account state.
- Removed undeclared internal WordPress `attachment_id` from anonymous/public profile-media DTOs; public media retains only presentation-safe fields.
- Preserved Future Ask Work profile-store failures as 503 instead of 404.
- Isolated privacy-reconciliation and media-deletion worker error clearing so one clean worker cannot erase another worker’s real failure evidence.
- Prevented legacy Founder option deletion until the read-only migration target is proven persisted.
- Extended explicit destructive uninstall to recover exact File03-owned usermeta independently of profile-table completeness, while keeping default uninstall non-destructive.
- Made public Founder rendering read-only so an anonymous GET cannot ensure/create a missing profile record.
- Added redacted active worker error code/timestamp evidence to System Check without exposing SQL/PII/secrets.
- Added exact review-branch Fresh/Future coverage; updated a stale historical media assertion to require the stronger error-family isolation invariant.
- Added `SECOND-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md` and `tests/second-twenty-round-sequential-review.py`.
- Advanced source identity to `1.2.0-rc12`; DB schema remains `1.2.0`; contract remains `1.4.0` because removal of undeclared internal `attachment_id` is privacy hardening under the existing opaque-public-ID/public-DTO-allowlist contract.
- Exact reviewed candidate `96276335b02fd42bea265648ae4a21c255db6d00` passed Corrective/Fresh/Future, PHP 8.1/8.3/8.4, permanent Second-Twenty, two fresh adversarial gates and deterministic package/checksum/SBOM/source-package parity; PR #27 merged it as code-bearing `main` `97cc579f706587490c2f4424efd593bbba9add29`, whose Baseline/Fresh/Future push gates also passed.
- Staging, deployed-package parity, live DB/migration state, Founder acceptance and operational status remain unverified.

## 1.2.0-rc11 — Fresh twenty-round sequential corrective hardening

- Started from exact `main` `60207107479c971cae4be379e427e1adb212ea92` (tree `1145214fbcff6afd9bf08289e9a112ffacfc4aaf`).
- Defect-bearing rounds: `01, 02, 03, 04, 05, 06, 09, 10, 12, 13, 14, 15, 17, 18, 19, 20`.
- Clean rounds: `07, 08, 11, 16`.
- Distinguished File 00 provider/claim uncertainty from genuine ineligibility on Central, Future and Core protected REST boundaries.
- Made personal-site, redirect, moderation/report, timeline and lifecycle-sensitive frontend reads DB-certain.
- Minimized delegation privacy export by removing internal/counterparty user/profile identifiers.
- Preserved media attachment-deletion failures as operational evidence and completed explicit destructive cleanup of File03-owned timeline circuit transients.
- Distinguished disclosure-store failure from verified revocation and made Future post-preflight mutations DB-certain.
- Prevented File 08 delegation projection from emitting authoritative denial when File 00, File 09 or delegation-store truth is unavailable.
- Prevented operational count-query failures from masquerading as healthy zero values; Repair refuses execution from uncertain database diagnostics.
- Added exact review-branch Fresh/Future workflow coverage and permanent first twenty-round QA.
- Staging, deployed-package parity, live DB/migration state, Founder acceptance and operational status remain unverified.

## Historical corrective releases

- `1.2.0-rc10`: tenth fresh ten-round review; all rounds `01–10` defect-bearing and corrected.
- `1.2.0-rc9`: ninth fresh ten-round review; defects `01,03,08,09,10`; clean `02,04,05,06,07`.
- `1.2.0-rc8`: eighth fresh ten-round review; defects `01,03,04,05,06,07,08,09,10`; clean `02`.
- `1.2.0-rc7`: seventh fresh ten-round review; defects `01,02,03,04,05,06,08,09,10`; clean `07`.
- `1.2.0-rc6`: sixth fresh ten-round review; defects `01,02,03,04,05,07,09,10`; clean `06,08`.
- `1.2.0-rc5`: fifth fresh ten-round review; defects `01,02,03,04,05,09,10`; clean `06,07,08`.
- `1.2.0-rc4`: fourth fresh ten-round review; all rounds `01–10` defect-bearing.
- `1.2.0-rc3`: third fresh ten-round review; defects `01,02,03,04,06,07,08,09,10`; clean `05`.
- `1.2.0-rc2`: original numbered 80-round and second independent fresh 80-round corrective hardening.
- `1.2.0-rc1`: approved 18-feature Future Professional Identity & Profile Superset.

All historical release records are regression/provenance evidence only and do not establish current staging or live state.
