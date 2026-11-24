# File 03 — V12.3 exact-deployed-state recovery and acceptance runbook (15 November 2026)

**Status: OPERATOR INSTRUCTIONS ONLY — not executed on Hostinger.** This is a safe handoff for recovering Staging/Live read access and running the 96-case mandatory release gate. It never substitutes repository contents for installed files and must not be mistaken for an acceptance receipt.

## 0. Preconditions and permission
- Hostinger/WordPress operations require authorized administrator access and a maintenance/backout plan. Do not paste credentials, raw patient rows, identity documents, secret options, wp-config.php, or unredacted error logs into GitHub/ChatGPT.
- Freeze **Live** and **Staging** separately with date/time and a named authorized operator. First gather read-only facts. WordPress bootstrap via WP-CLI may cause plugin-init side effects; use a safe staging snapshot or verified admin operator and do not assume running WP-CLI is perfectly side-effect free.
- Current connected WordPress/Jetpack status is `site_disconnected`. WPVibe initial/direct/relay attempts returned HTTP 530; the failing layer is **unknown**. Preserve exact timestamp, 1xxx diagnostic code/headers if available, and contact Hostinger/WPVibe support as appropriate. Do not disable security/WAF or reinstall plugins solely on this indication.
- Freeze all active plugin versions (especially Files 00, 02, 03, 07, 08, 09, 16, 17, 19, 20, 21, 24, 25, 26), PHP version, WordPress version, MariaDB version and active dependent contracts.

## 1. Evidence manifest — required fields
| Key | How to obtain | Data handling |
|---|---|---|
| Host / environment / UTC capture time | Operator administrative record | No exact home/private personal information |
| Plugin File03 installed directory/version | WP Plugins admin panel or approved WP-CLI | Keep installed version separate from Github |
| Installed File03 SHA-256 | SFTP/SSH offline hash of complete installed plugin file set and exact reviewed ZIP; record *both* file tree and ZIP | Never hash an unrelated GitHub file and call it deployed |
| File00 installed version and source checksum | Same method | Do not assume source PR #61 is live |
| WP/PHP/DB runtime versions | Trusted host admin dashboard / approved queries | No credentials |
| `spd_version`, `spd_db_version`, `spd_contract_version` | Selected non-secret `wp_options` | Read-only, no secret options dump |
| Central and Future File03 schema versions | Selected `wp_options` | Include actual DB column/index readback |
| Migration status, cursor, safe mode | File03 System Check and benign option names | Capture genuine failures and dead-letter counts |
| File00 `smc_db_version` | Controlled `wp_options` query | Not Source `SMC_DB_VERSION` alone |
| Relevant tables/columns/indexes, counts | `information_schema` + read-only aggregate queries | Aggregate counts only; avoid names/emails/patient records |
| Runtime errors and owner/provider health | Site-local sanitized logs | Redact tokens, user IDs where sensitive, keys and payloads |
| Deployment/package lineage | Host deploy receipt, artifact hash, backup ID | Establish cryptographic parity |

## 2. Host operator commands (illustrative; do not run until authorized)
Use the host’s installed WP-CLI from the **actual selected site path**, not a guessed directory. Record results privately.
```bash
wp core version
wp plugin list --fields=name,status,version --format=table
wp db prefix
wp option get spd_version
wp option get spd_db_version
wp option get spd_contract_version
wp option get smc_db_version
```
For exact installed file hashes, the authorized operator must separately hash **each** installed File 03/00 runtime file and compare the complete manifest against the reviewed ZIP. A ZIP SHA-256 is not equal to a directory hash, and a GitHub HEAD SHA-1 is not a ZIP SHA-256. Do not print or commit raw private database exports.

## 3. Parity decision
1. Establish **Repository main HEAD**, **PR source candidate SHA**, **Staging installed artifact checksum**, and **Live installed artifact checksum** separately.
2. If the installed source differs, STOP ordinary debugging and perform Deployment-Parity Audit; determine exact installed version, inactive/partial upload, old schema, blocked migration, or stale cache *before* attributing an application-code root cause.
3. A current PR is not automatically deployed. Proposed source identities: File00 `1.2.46` and File03 `1.2.0-rc19`. Verify current exact PR SHA; never promote an outdated digest.
4. If any mandatory owner provider (File09 badge, File08 appointment, File26 search, File20 shell, File24 security) is stale/missing, fail closed and document `BLOCKED`.

## 4. Staging release gates
- Capture real Stage A–K **72** items in `STAGING-ACCEPTANCE.md` and the V12.3 distinct **24** items in `V12.3-DELTA-ACCEPTANCE-2026-11-14.md`. All mandatory cases need actual installed SHA/DB/actor/browser evidence; no source-only test is a Staging PASS.
- Reproduce with Founder, adult member, verified and pending doctor, revoked doctor, guardian-linked minor, delegated assistant, anonymous visitor and operator; test missing/stale/throwing owner.
- Check public/protected routing, private field revocation, File09 current badge scope, File26 indexed document invalidation, File08 real timezone/slot, File16 grounded AI boundaries, File17 contact relay, File20 navigation, File25 calm green tokens and File24 privacy evidence.
- Browser acceptance in English LTR, Urdu and Arabic RTL; mobile 320/360/390 width, 200%/400% zoom, keyboard-only, tested screen reader and reduced-motion. Distinguish manual WCAG 2.2 AA acceptance from automated lint.
- Record each case: `case_id, requirement, exact_SHA, installed_ZIP_SHA256, DB/migration, actor_fixture, expected, actual, PASS/FAIL/BLOCKED/NOT_RUN, log/screenshot, reviewer, UTC timestamp`.
- Fix/retest every proven Critical/High source/runtime defect before release. An unexecuted case is `NOT_RUN` or `BLOCKED`, not success.

## 5. Backup, rollback and promotion
- Take an encrypted, access-limited database/file backup and **actually perform a verified restore** in a disposable environment. Check profile IDs, privacy audiences, queues, cache, migration cursors, credentials and idempotency invariants.
- Rehearse actual predecessor-version rollback against captured Staging/Live predecessor artifact and database versions. An invented rc18→rc19 rollback is not a substitute for identifying the real installed predecessor.
- Founder approval must explicitly cite exact ZIP hash, required acceptance PASS evidence, independent reviewer and rollback receipt. No source CI automatically authorizes production.
- Controlled Live deployment must verify installed file hashes, plugin version, WP options, SQL columns/indexes, migration and critical browser smoke; recheck File09 revocation, File26 stale search and private data in cache and print/QR.
- Only after live retest and parity may an issue be marked `RESOLVED`.

## 6. Mandatory final status (five independent realities)
```text
Repository HEAD: <exact SHA from main>
Reviewed Source PR SHA: <exact SHA>
Deployed Version: UNKNOWN until proven from installed host
DB Version: UNKNOWN until proven from actual DB
Migration State: UNKNOWN until actual reads
Live Verification Status: NOT EXECUTED until actual real-website retest
Defects Found / Source Fixed / Staging Fixed / Live Verified / Outstanding: independently counted
```
**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
