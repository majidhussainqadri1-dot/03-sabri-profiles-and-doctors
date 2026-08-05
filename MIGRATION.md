# Migration and Rollback

- Migration is idempotent, cursor-based by ascending WordPress user ID and protected by a lock.
- A record failure is stored with bounded exponential retry. The cursor does not pass a retryable failure; exhausted failures enter `dead` quarantine and remain release blockers.
- Operator requeue requires current File 00 authority, two-factor eligibility and a written reason; the cursor is rewound.
- Legacy visibility/contact values migrate only into File 03-owned audience controls. Contact truth remains File 00-owned.
- Media privacy reconciliation has its own resumable cursor and retryable physical-deletion ledger.
- Rollback must restore database and files together, reactivate the prior accepted package, flush routes/caches and reconcile File 26 projections. No destructive uninstall is part of rollback.
