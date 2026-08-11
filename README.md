# File 03 — Profiles and Doctors

Canonical profile-domain source for the Sabri Social Homeopathy Platform.

## Governing scope

This repository implements the profile-owned requirements of the Definitive Integrated Master Plan v3.0, later Founder-approved amendments, the File 03 amended master plan and `FUTURE-SUPERSET-18.md`.

File 03 owns stable public profile identity, profile fields and audiences, Founder official profile, member/doctor presentation records, slug history, profile-media references and deletion ledger, profile reports, private professional proposals, profile timeline federation, privacy export/erasure and operational evidence. It does not own membership truth, doctor verification decisions, doctor search/ranking, clinic truth, publication records, communication graph, global shell or final visual-system ownership.

## Current repository candidate

- Plugin: `1.2.0-rc12`
- Database schema: `1.2.0`
- Contract: `1.4.0`
- PHP target matrix: `8.1`, `8.3`, `8.4`
- WordPress baseline: `7.0+`
- Second twenty-round starting `main`: `a34e4e2b808134237ae9945759745595685c8733`
- Starting tree: `c0d41641c66cb897c1073dbb40943c5cf9093d44`
- Review branch: `audit/file-03-second-twenty-round-20260812`
- Ledger: `SECOND-TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md`
- Defect-bearing rounds: `01, 02, 05, 06, 07, 09, 12, 15, 16, 17, 18, 19, 20`
- Clean rounds: `03, 04, 08, 10, 11, 13, 14`
- Exact reviewed candidate: `96276335b02fd42bea265648ae4a21c255db6d00`
- Code-bearing merge: `97cc579f706587490c2f4424efd593bbba9add29`

## Second twenty-round correction boundary

The new sequential cycle strengthens current File 00 authorization truth at moderation/report/appeal domain boundaries; removes an undeclared internal WordPress attachment primary key from the public media DTO; preserves Ask Work profile-store degradation as 503; isolates media-worker error evidence; prevents source-option deletion before legacy migration persistence is proven; recovers File03-owned orphan usermeta only inside explicit destructive uninstall; keeps public Founder rendering read-only; and surfaces redacted active worker-error reasons in System Check.

Round 19 also corrected missing exact-branch CI coverage. The first newly enabled run exposed a historical Eighth-review textual assertion that required the older unsafe global media-error clearing behavior; the test was updated to require the stronger per-worker error-family isolation invariant, after which exact-head Fresh and Future gates passed.

Removing `media.attachment_id` is privacy hardening of an undeclared internal primary key, not a documented public-contract field removal. The machine-readable contract already requires opaque public identity and public DTO allowlists, so the contract remains `1.4.0`.

## Exact candidate closure status

The exact reviewed candidate `96276335b02fd42bea265648ae4a21c255db6d00` passed Corrective Integrity, Fresh Eighty-Round and Future Superset 18 on the same SHA, including PHP 8.1/8.3/8.4, the permanent second-twenty-round gate, two fresh post-correction adversarial gates and deterministic package/checksum/SBOM/source-package parity. PR #27 merged that exact head into code-bearing `main` `97cc579f706587490c2f4424efd593bbba9add29`, whose Baseline, Fresh and Future push gates also passed. This documentation-only evidence closure does not establish staging or live state; its final merge creates a new repository HEAD that must itself be re-tested.

## Prior review history

The repository retains the original 80-round corrective review, the independent fresh 80-round reviews, the third through tenth fresh ten-round review ledgers, the first twenty-round ledger and this second twenty-round ledger. Historical records are regression evidence only; none substitutes for current exact-HEAD verification.

## Truthful status

Repository source, CI and deterministic package evidence do **not** authorize production. Hostinger staging, exact deployed-package parity, live database/schema/migration state, real File 00/07/08/09/16/17/20/21/24/25/26 provider integration, representative role journeys, browser/device and Urdu/Arabic RTL, WCAG 2.2 AA, backup/restore, migration/rollback rehearsal and Founder acceptance remain separate mandatory gates.

**Exact deployed code is currently unverified; repository evidence must not be described as live verification.**
