# File 03 — Forty-Round Review, Correction and Retest Register

**Candidate:** 1.0.0-rc3  
**Contract:** 1.2.1  
**Schema:** 1.2.0  
**Scope:** repository source, canonical boundaries, security/privacy, concurrency, migration, timeline, media, packaging and truthful release evidence.  
**Boundary:** this register proves source/package/automated-QA work only; Hostinger staging, real companion-provider acceptance, browser/RTL/WCAG, backup/restore, rollback, Founder acceptance and live operations remain separate gates.

| Round | Review focus | Finding | Correction completed before next round |
|---:|---|---|---|
| 1 | Release identity | rc2 could not distinguish the new audit | Bumped plugin to rc3 and contract to 1.2.1; schema unchanged |
| 2 | Canonical ownership | No direct companion writes found | Preserved adapter-only ownership boundary |
| 3 | Unknown age | Unknown-age doctor was treated as adult | All non-Founder unknown-age accounts are now minor-safe |
| 4 | Guardian claims | Current/versioned check present | Retained fail-closed guardian validation |
| 5 | Founder invariant | Generic moderation correctly blocked | Retained immutable Founder path |
| 6 | Public identifiers | Route regex permitted malformed UUID shapes | Added canonical UUID validation in REST and repository |
| 7 | Optimistic locking | Malformed If-Match could fall back to body version | Added strict single integer ETag parsing; malformed header fails |
| 8 | IDOR | Double authorization present | Retained route and native command checks |
| 9 | Idempotency input | Unbounded keys accepted | Limited Idempotency-Key to 200 characters |
| 10 | Replay equality | First profile-update response differed from replay | Return exactly the transaction-committed replay response |
| 11 | Scanner availability | Fail-closed provider requirement present | Retained scanner-required gate |
| 12 | Scan provenance | SHA-256 was optional | Made exact 64-hex SHA-256 mandatory and byte-bound |
| 13 | Metadata | Re-encoding occurred before scan | Retained exact-byte order |
| 14 | Public media | DTO trusted DB reference without rechecking attachment metadata | Revalidates owner, purpose, state and same origin |
| 15 | Privacy tightening | Existing atomic deletion path present | Retained and strengthened cleanup-failure evidence |
| 16 | Moderation revocation | Suspension/archive could leave raw public media until cron | Atomically detaches media and queues deletion during moderation |
| 17 | Deletion concurrency | Lease/retry/dead-letter present | Retained bounded queue semantics |
| 18 | Origin validation | Host-only comparison allowed scheme/port mismatch | Require exact scheme, host and effective port |
| 19 | URL credentials | Credential-bearing same-host URLs were not rejected | Reject user/password URL components |
| 20 | Provider health | Current/versioned health required | Retained five-minute freshness gate |
| 21 | Provider failure | Throwable could terminate timeline rendering | Catch provider exceptions and open circuit |
| 22 | Provider volume | Provider result was unbounded | Added 250-item hard cap and degradation state |
| 23 | Timeline authorship | Owner binding present | Retained strict author_user_id equality |
| 24 | Timeline time | Far-future items were accepted | Reject timestamps beyond five-minute skew |
| 25 | Timeline thumbnail | External thumbnail could create tracking request | Drop non-same-origin thumbnail URLs |
| 26 | Cursor | Cursor input/decoded ID were unbounded | Added length, strict base64 and timestamp checks |
| 27 | Timeline UI | Public DTO error could be accessed as array | Added explicit WP_Error rendering path |
| 28 | Clinic projection | Clinic filter ran for non-doctor profiles | Query clinic projection only for current verified doctors |
| 29 | Verification consistency | DTO fetched verification multiple times | Derive badge/professional/clinic from one current snapshot |
| 30 | Minor contacts | Multiple current calls could disagree | Use one File 00 claims snapshot for contact suppression |
| 31 | Legacy visibility | Public audience was copied without current eligibility | Migrate through private-by-default minimization |
| 32 | Legacy contact | Minor safety gate present | Retained current minor gate |
| 33 | Repair | Migration schedule was absent from repair plan | Added migration schedule repair action |
| 34 | Unicode reports | Byte length could treat short Urdu as ten characters | Use multibyte-aware character length |
| 35 | Audit hashing | WP_Error could enter diff hashing as opaque object | Normalize before/after errors to stable codes/versions |
| 36 | JSON failure | Encoding failure was not explicit | Helper returns sentinel; idempotency completion rejects it |
| 37 | Secrets/artifacts | No committed archive/secret found | Added automated source hygiene check |
| 38 | Uninstall | Destructive purge already had dual gate | Retained non-destructive default |
| 39 | Package identity | Software/contract/schema needed explicit separation | rc3 / 1.2.1 / 1.2.0 recorded independently |
| 40 | Truth status | Risk of equating QA with production completion | Readme and status explicitly preserve staging/live gates |

## Automated evidence

- `tests/forty-round-review.py` executes exactly forty source/governance assertions.
- `tests/forty-round-runtime.php` exercises UUID, exact-origin, credential rejection, multibyte length and JSON helpers.
- PHP syntax is checked across PHP 8.1, 8.3 and 8.4 in GitHub Actions.
- Existing architecture, security, schema, plan, bootstrap, authorization, verification, state and timeline suites remain mandatory.
- Package is built twice and must be byte-identical; checksum, source parity and SBOM are verified.
