# فائل 03 — ماسٹر پلان v12: حقیقی مطابقت، قبولیت اور باقی رکاوٹوں کا ثبوتی رجسٹر
**تاریخ:** 2026-11-07. **دائرہ:** GitHub source / disposable WordPress CI؛ Hostinger staging/live نہیں۔

## قطعی تفریقِ حقیقت
- **File03 repository main HEAD:** `4b97fd450e86079897b9eb1a354719e0bc87e667` (PR #40 merged, independently verified by post-merge 3 passing workflows).
- **File03 integration candidate:** PR #41 `audit/f03-real-crossfile-seven-plugin-20261107`، غیر ضم شدہ؛ اس رجسٹر کو شامل کرنے کے بعد HEAD اور اصل CI artifact کا فیصلہ PR کی تازہ SHA سے کیا جائے۔
- **File09 repo main:** `13153e4bb5c98e449f638a2c24ec2f1c05083afc` (PR #14 merged; PHP 7.4/8.3 + package/postmerge CI passed).
- **File08:** PR #43 corrective candidate `acef00c953599e4336ad2f30824c0c9985fc207a`؛ main پر ضم نہیں، اس لیے File08 source correction کو deployed/merged تصور کرنا ممنوع۔
- **File26 main:** `1b064c6ea9f81195611ed001d5457e8ced65460a`.
- **Live active File03/package SHA/checksum:** نامعلوم۔ **Live DB version:** نامعلوم۔ **Live migration cursor/failed row/safe mode:** نامعلوم۔ **Live re-test:** نہیں ہوا۔
- **Site connector:** WPVibe sites = `[]`. `https://www.sabrihomeopathy.com` اور `https://sabrihomeopathy.com` کے WPVibe connection handshakes نے HTTP 530 دیا۔ HTTP 530 کا اصل server-side سبب غیر ثابت؛ Live error/DB/package نہ دیکھے گئے۔
- **قانون:** “Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔”

## اصل cross-file evidence — محدود مگر حقیقی
GitHub Actions **File03 7-plugin CI** Run `42383965235`, WordPress **7.0.1**, PHP **8.3**, MariaDB **11.4**, مکمل disposable WordPress DB میں اصل source-trees اور independent plugin activation:
- File00 `1.2.44` — deferred bootstrap/schema پاس؛
- File02 `1.3.4` — canonical professional reauth API پاس؛
- File03 `1.2.0-rc18` — base/central/future owned schema پاس؛
- File09 `1.3.0` **corrective candidate** — current File00/02/03 contract and private credential storage pass؛
- File07 `1.2.1` — directory plugin activation پاس؛
- File08 `1.2.16` **corrective candidate** — real MariaDB decimal schema/rollback gates, no silent runtime migration failure, current File03 event hooks pass؛
- File26 `1.3.0` — schema, canonical File03 owner connector, guarded status, unknown public profile denial pass؛
- Temporary CI secrets and credential directory outside public webroot only. No site Deployment or business-user end-to-end verified.

## جڑ سے ملنے والے نئے مسائل — Source correction ledger
| ID | سچا مسئلہ | Fail-first / اصل failure | ماخذ کی اصلاح | موجودہ حیثیت |
|---|---|---|---|---|
| CF-01 | File09 was bound to retired File00 1.2.0/CF01 1.0.0 and File00 MFA; current owner asserts 1.2.3/1.1.0 and transfers authentication to File02. | File03 Run `42381751908`; F09 negative test Run `42382327507`. | File09 PR #14, candidate `a93c7f1fd52eb58da0c8f47cf13fdf0abba2bce2`, merged `13153e4bb5c98e449f638a2c24ec2f1c05083afc`. Contract-major mismatch still fail closed. | **Merged/tested in repo; live unknown** |
| CF-02 | File08 required missing legacy `GDO_Helpers` while File09 supplies current `GDO_Membership_Adapter`. | File03 Run `42382571293`; File08 RED `42382828580`. | File08 PR #43 source code correction `7a70d5213b9a9d50bd4f2ea2c3e7d1b6c01bffc4`. | **Draft/unmerged; real CI fixed** |
| CF-03 | File08 SQL DECIMAL(24,6) `DEFAULT 0` compared literally to MariaDB `0.000000`; activation rollback. | File03 Run `42382921606`; exact location `wp_wca_metrics.sum_value`; File08 RED `42383355769`. | File08 PR #43 `dbe5795f84c3f7e135ece29fc188acfbc167cccb` with canonical decimal string compare; wrong/non-numeric defaults fail closed. | **Draft; PHP/real MariaDB verified** |
| CF-04 | File08 called `WCA_Routes::register()` from pre-init `plugins_loaded`; `$wp_rewrite` null and migration falsely logged failed. | Disposable WP Debug log in File03 Run `42383586582`; File08 negative regression `42383888694`. | File08 PR #43 `acef00c953599e4336ad2f30824c0c9985fc207a`, route registration exclusively on normal WordPress `init`. | **Draft; real WP no-log and CI verified** |
| CI-F01 | File09 private credential store not configured in disposable CI, correct fail closed (not a source defect). | Run `42382467552`. | CI configured private `/tmp/f03-gdo-private` outside webroot. | Fixture resolved |
| CI-F02 | File26 WP-CLI invocation escaped PHP namespaces twice, parse error (not a File26 plugin defect). | Run `42383586582`. | File03 CI commit `5bd0f8083f752bf5fc09d79c49838616c1f57df8`. | Fixture resolved |

One additional **investigation-only potential cross-owner logical gate:** File09 initial doctor application uses File00 `clinical_identity_link` result, but File00's clinical eligibility checks File09 professional verified truth. No real completed applicant-to-verified E2E proof. Do not claim this journey accepted without a dedicated actor/test and an owner-approved fix if it fails.

## 72 Staging Acceptance — exact accounting; no fictitious ticks
All listed in canonical `STAGING-ACCEPTANCE.md`; no stage-installed artifact, DB, logs, screenshots, migration state, independent backup/restore, real browser/device or Founder approval obtained. **0/72 items accepted in actual Staging; 72 unverified.** Some source-level analogs pass CI but cannot be promoted into staging receipts.

| Existing checklist group | Staging items | Staging accepted |
|---|---:|---:|
| A Install/upgrade/schema/routes | 7 | 0 |
| A2 Fresh install/owner-negative | 3 | 0 |
| B Identity/role/access | 5 | 0 |
| C Privacy/lifecycle | 6 | 0 |
| D Reports/appeals | 7 | 0 |
| E AI/Future | 8 | 0 |
| F Providers/timeline | 6 | 0 |
| G Browser/idempotency | 5 | 0 |
| H Media/storage | 4 | 0 |
| I Events/queues/observability | 7 | 0 |
| J UI/RTL/a11y | 5 | 0 |
| K Package/backup/rollback/approval | 9 | 0 |
| **Total** | **72** | **0** |

## لازمی آئندہ evidence and release disposition
Do **not** call this Production Ready or 95/100% approved. Next authoritative gate: site connector usable → live/stage freeze runtime & deployed checksums → staging replica/backup/restore → install exact pinned immutable package → DB/migration state → full 72 checklists and founder approval → deliberate deploy → live retest → deployed SHA parity. CI source correctness remains useful, never substitutes for those steps.

**Incident fields — actual live site:** Repository HEAD: `4b97fd450e86079897b9eb1a354719e0bc87e667`; Deployed Version: **unverified**; DB Version: **unverified**; Migration State: **unverified**; Live Verification Status: **not performed**.
