# Corrective Review Cycles

## Rounds 1–4 — prior candidate

The prior branch established canonical profiles, audiences, timeline contracts, media/report foundations, migration/operations scaffolding and deterministic packaging. Its exact-head CI was green, but a subsequent independent source review found that token-presence tests and limited unit checks did not prove behavioral completeness.

## Round 5 — fresh adversarial source review and correction

Corrected release-blocking defects:

- current File 00 contract/version and fail-closed assertions;
- Founder authority filter changed from grant-capable to restriction-only;
- moderator private-field bypass and login-only “members” access removed;
- unknown age made minor-safe and guardian/contact claims made current/versioned;
- legacy File 09 metadata authority removed; current validated projection required;
- fake media scan default removed; exact re-encoded bytes must receive a fresh compatible scan;
- privacy tightening and replacement media now commit a durable deletion ledger atomically;
- report state transitions, notes, dedupe and idempotency enforced;
- outbox and deletion queues use leases, bounded retry, dead-letter and audited requeue;
- migration failures block/retry or quarantine instead of silently advancing;
- provider health, author ownership, same-origin URL and cursor integrity enforced;
- cross-owner profile/timeline REST caching disabled until accepted invalidation contracts exist;
- safe-mode and repair persistence failures now surface explicitly;
- privacy erasure made retry-aware and event-backed.

## Round 6 — post-correction adversarial review and retest

The corrected source was re-read for authority bypasses, direct companion-table coupling, stale projections, privacy leakage, unsafe media ingestion, replay/concurrency gaps, migration skipping, queue duplication, destructive uninstall and misleading completion claims. Tests were expanded from token-only checks to behavior-oriented authorization, verification, state/current-contract, timeline and bootstrap suites, plus schema and source-regression gates.

The repository may be called source/package/automated-QA candidate-complete only after the exact final commit passes GitHub Actions. Staging, live and operational statuses remain separate.
