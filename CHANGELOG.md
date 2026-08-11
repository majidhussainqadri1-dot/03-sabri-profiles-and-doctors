# Changelog

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
- Distinguished disclosure-store failure from verified revocation and made Future post-preflight mutation helpers DB-certain.
- Prevented File 08 delegation projection from emitting authoritative denial when File 00, File 09 or delegation-store truth is unavailable.
- Prevented operational count-query failures from masquerading as healthy zero values; Repair now refuses execution from uncertain database diagnostics.
- Added exact review-branch Fresh/Future workflow coverage; CI then exposed and drove correction of an isolated timeline runtime composition failure.
- Added `TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md` and `tests/twenty-round-sequential-review.py`; advanced source identity to `1.2.0-rc11` while DB schema stays `1.2.0` and contract stays `1.4.0`.
- Final exact candidate, PR/merge and exact-main evidence remain pending until one post-documentation SHA passes every applicable automated gate.
- Staging, deployed-package parity, live DB/migration state, Founder acceptance and operational status remain unverified.

## Historical corrective releases

- `1.2.0-rc10`: tenth fresh ten-round review; all rounds `01–10` defect-bearing and corrected; bootstrap parity restoration and permanent tenth-review QA.
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
