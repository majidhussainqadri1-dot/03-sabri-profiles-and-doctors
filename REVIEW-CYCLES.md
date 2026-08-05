# File 03 Review → Fix → Fresh Adversarial Review → Retest

## Round 1 — architecture and correctness review

The initial plan-completion implementation was reviewed against all File 03 FR/NFR requirements and the parent/directive ownership boundaries. The following defects were found and corrected:

1. Database transaction state could inherit an old `$wpdb->last_error`; it is reset before each transaction.
2. An unchanged field update could return zero rows and be mistaken for failure; update success now distinguishes `false` from `0`.
3. `incomplete` profile state lacked safe suspension/tombstone transitions.
4. Concurrent lazy profile creation could return a false failure; the unique-key race is re-read safely.
5. Audience maps lost field keys through `wp_list_pluck`; a keyed map is now built explicitly.
6. Logged-in personalized views of otherwise public profiles risked cache leakage; every authenticated profile view is now private/no-store.
7. Dynamic routes depended on a fixed page slug; rewrite targets now use File 03-owned page IDs.
8. Founder whole-profile visibility could be made private; canonical Founder visibility is forced public.
9. Founder mission, vision, objectives, methodology, experience, research, works and institutional links were missing; bounded Founder-owned fields were added.
10. Asynchronous custom cron scheduling could fail during activation; activation now uses a registered core schedule.
11. Pending media-scan completion lacked an update contract; a versioned scan-completion action was added.
12. Profile/report moderation lacked explicit versioned commands; moderation methods and REST endpoints were added.

All PHP syntax, architecture, plan-coverage and unit tests passed after fixes.

## Round 2 — attacker, concurrency, privacy and failure review

A fresh adversarial review then examined partial REST updates, cache variance, upload failure, migration interruption, provider data and privacy erasure. The following defects were found and corrected:

1. Partial REST edits could erase omitted fields; absent values now preserve current state.
2. File 08 clinic projection was trusted wholesale; output is now allowlisted and sanitized.
3. A pending scan could place a file in public WordPress uploads; pending scans now fail before media ingestion.
4. Image re-encoding could save to a different path; the sanitized result is copied back and temporary output deleted.
5. URL-safe base64 cursors could fail without padding; decoding now restores padding.
6. A missing table with a stale version option could bypass repair; runtime checks schema presence as well as version.
7. Founder subtitle ignored the controlled professional-title field; it now uses the Founder field when present.
8. Generic WordPress administrators could operate File 03 outside File 00 authority; operator access now uses File 00 moderator/Founder claims.
9. Profile save could partially mutate fields before discovering an invalid second image; all media is validated first and cleaned up on failure.
10. Migration used offsets and could skip users after deletion; it now advances by the highest processed user ID.
11. Legacy migration depended on profile version and could be skipped after benign synchronization; it now uses an explicit idempotent per-user marker.
12. Destructive uninstall could query a missing table; it now confirms table existence first.
13. Founder and moderation events were absent from the contract manifest; the manifest now includes them.

The complete local regression suite passed again after these corrections. Hostinger staging, real companion integrations, browser/accessibility evidence and backup/rollback drills remain external acceptance gates rather than hidden assumptions.

## Round 3 — bootstrap/runtime composition review

A final composition-focused review discovered that the refactored repository and frontend classes declared PHP traits before those trait files were required by the plugin bootstrap. Syntax-only testing could not detect this load-order fatal. The plugin loader now requires every trait before the classes that use it, the architecture test asserts key trait filenames, and a dedicated minimal WordPress bootstrap smoke test verifies all principal classes and traits load without a fatal error. The full lint, architecture, plan coverage, bootstrap and security/state-machine suites then passed again.

## Round 4 — CI evidence review and false-positive correction

The first exact-head GitHub Actions run passed PHP 8.1/8.3/8.4 lint, architecture, plan coverage, bootstrap and unit checks, but the secret scan correctly blocked the workflow because a security-unit example contained a Pakistan-format telephone number. Although synthetic, it was indistinguishable from committed personal contact data. The fixture was replaced with a non-project generic UK-format example; no production or Founder contact appears in source or tests. The complete local suite and package build passed again before the corrective commit.
