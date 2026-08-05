# File 03 Plan-to-Code Traceability

| Requirement | Principal implementation | Evidence family |
|---|---|---|
| F03-FR-001 Founder official profile | canonical File 00 Founder; Founder fields; File 21 timeline slot | founder invariant, route, content and timeline tests |
| F03-FR-002 Member public profile | profiles/fields tables; public DTO; profile renderer | owner/guest/audience tests |
| F03-FR-003 Doctor professional profile | File 09 versioned projection; File 08 clinic allowlist | verified/suspended/malformed-contract tests |
| F03-FR-004 Avatar and cover | strict media class; focal points; re-encoding; scan hook | malicious upload and erasure tests |
| F03-FR-005 Field visibility | public/members/contacts/private per-field policy | audience/cache/IDOR tests |
| F03-FR-006 Contact consent | File 00 contact values plus File 03 audience choices | revocation and cache purge tests |
| F03-FR-007 Canonical URLs | UUIDv4, slug history, `/u/` redirect, stable UUID route | collision/redirect/role-change tests |
| F03-FR-008 Profile editing | server allowlist, expected version, transaction and idempotency | concurrency/replay/mass-assignment tests |
| F03-FR-009 Completeness | role-specific non-manipulative checklist | member/doctor/founder completeness tests |
| F03-FR-010 Timeline | federated providers, normalization, visibility and cursor | owner-provider outage/pagination tests |
| F03-FR-011 Badges | File 00 Founder and File 09 doctor claims only | spoof/stale/suspension tests |
| F03-FR-012 Reporting | report table, rate limits, File 24/support events and review API | abuse/escalation/version tests |
| F03-FR-013 Search metadata | public DTO providers, schema.org and private noindex | corpus/index/cache tests |
| F03-NFR-001 Authorization | `SPD_Authorization`, owner checks and moderator contracts | IDOR/object/field/state tests |
| F03-NFR-002 Privacy | audience DTOs, WordPress export/erase and tombstones | privacy lifecycle tests |
| F03-NFR-003 Reliability | outbox, retry, dead-letter, transactions and safe mode | outage/retry tests |
| F03-NFR-004 Performance | bounded provider/queue queries and cursor pagination | load/query budget tests |
| F03-NFR-005 Accessibility | semantic HTML, focus, reflow, RTL and reduced-motion CSS | WCAG/manual/automated tests |
| F03-NFR-006 Observability | trace IDs, health report, counters and audit events | diagnostics/alert tests |
| F03-NFR-007 Migration/rollback | additive schema, ID cursor, lock, marker and runbook | fresh/upgrade/resume/restore tests |
| F03-NFR-008 Operability | System Check, Safe Mode and File 03-only repair | operator journey tests |
| F03-NFR-009 Compatibility | version gates and CI PHP matrix | contract/environment tests |
| F03-NFR-010 Localization | translatable strings, locale normalization and RTL | Urdu/Arabic/date/number tests |
