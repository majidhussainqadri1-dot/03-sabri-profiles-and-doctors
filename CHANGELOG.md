# Changelog

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
