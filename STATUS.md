# File 03 Status — 1.2.0-rc18

| Status | Evidence / decision |
|---|---|
| Specified | File 03 amended plan + central governing plan + `FUTURE-SUPERSET-18.md` |
| Repository identity | Plugin `1.2.0-rc18` · DB schema `1.2.0` · public contract `1.4.0` |
| Coded | Repository-owned File 03 scope plus Future Superset 18 and retained corrective hardening through the ninth current-companion twenty-round cycle |
| Ninth 20-round source audit | **20/20 completed; defect-bearing rounds 04, 07, 20; all corrections applied; exact-head CI/package gates required and verified before merge** |
| Ninth-cycle starting main freeze | `695329cced81a1b2ee5c59e3b4a92c9809a2564b` |
| Ninth-cycle branch | `audit/file03-ninth-twenty-round-20260928` |
| Ninth-cycle reviewed parent HEAD | `117dbfac30fac35260947383abd86b11b5481461` |
| Ninth-cycle ledger | `NINTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-09-28.md` |
| Ninth-cycle defect-bearing rounds | `04, 07, 20` |
| Ninth-cycle clean rounds | `01, 02, 03, 05, 06, 08–19` |
| Ninth-cycle proven defect | rc17 did not subscribe to File 08 current `wca_outbox_event`; cached clinic/availability projection could survive current File 08 owner changes |
| Eighth 20-round source audit | **20/20 source gates passed; PR #37 merged; resulting main SHA `695329cced81a1b2ee5c59e3b4a92c9809a2564b` passed required exact-head workflows** |
| Eighth-cycle starting main freeze | `88da03fa4b92576384f4542ee7fc17312043bc0f` |
| Eighth-cycle branch | `audit/file03-eighth-twenty-round-20260927` |
| Eighth-cycle ledger | `EIGHTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-09-27.md` |
| Eighth-cycle defect-bearing review areas | external event invalidation · File 19 producer/intake · event-manifest parity · File 19 health evidence · stale CI artifact identity · release identity synchronization |
| Seventh 20-round review | **20/20 completed** using complete review → consolidated defect ledger → correction → retest → next round |
| Seventh-cycle defect-bearing | `03, 04, 05, 06, 07, 08, 11, 14, 15, 17, 19, 20` |
| Seventh-cycle clean | `01, 02, 09, 10, 12, 13, 16, 18` |
| Seventh-cycle totals | `12/20` defect-bearing · `8/20` clean |
| R20 pre-correction exact HEAD | `95c90da025d2157b578126d69559fc6bac733918` |
| Current seventh-review branch | `audit/file-03-seventh-twenty-round-20260813` |
| Permanent cycle ledger | `SEVENTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-29.md` |
| Automated review gate | `.github/workflows/fresh-eighty-round-review.yml` runs retained historical/fresh/sequential gates plus seventh-cycle, R20, eighth-cycle and ninth-cycle closure assertions |
| Exact package gate | Same exact-HEAD workflow builds twice, verifies deterministic ZIP/checksum/SBOM and source/package runtime parity, then uploads the exact artifact |
| PHP compatibility gate | Corrective Integrity covers PHP 8.1, 8.3 and 8.4 plus source-integrity/security checks |
| Contract decision | DB remains `1.2.0`; public contract remains `1.4.0`; rc18 is a source/release-candidate identity advance for the current File 08 event-contract correction, not a DB/public-contract version advance |
| Historical release inventories/checksums | Historical provenance only; not current rc18 package truth |
| Staging-Accepted | **Pending / unverified** |
| Live-Deployed | **Unverified** |
| Live DB / migration | **Unverified** |
| Deployment parity | **Unverified** |
| Operational | **Not established** |

## Repository closure boundary

The eighth source audit contains 20 completed review rounds recorded in `EIGHTH-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-09-27.md`; its corrected PR #37 was merged and the resulting main SHA passed the required exact-head workflows. The ninth cycle starts from that merge SHA and reopens repository review because current File 08 companion transport evidence exposed an additional integration defect. The ninth cycle completed 20/20 rounds; rounds 04, 07 and 20 were defect-bearing and were corrected together at their round boundaries. The final branch SHA and any resulting merge SHA must pass the required exact-head CI/package gates separately.

Repository and CI evidence do not establish Hostinger staging or live state. External acceptance remains: staging reality freeze → exact installed package/version/checksum → DB/schema/migration verification → current companion contracts → representative browser/mobile/RTL/WCAG journeys → backup/restore/rollback → Founder acceptance → controlled deployment → live re-test → parity confirmation.

**Exact deployed code remains unverified; repository-based diagnosis is provisional for any live incident.**
