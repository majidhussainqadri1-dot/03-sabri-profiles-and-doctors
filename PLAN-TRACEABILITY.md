# Three-Plan Traceability — File 03

## Canonical ownership

File 03 owns profile identity, profile fields/audiences, Founder official profile, member/doctor presentation record, slug history, profile-media references/deletion ledger, profile reports, private professional proposals and federated profile-timeline slots. File 00 owns membership/identity assertions; File 09 owns doctor verification; Files 07/26 own directory/search/ranking; File 08 owns clinic truth; Files 21/10/11/05 own timeline content; File 17 owns contacts/messages; File 20 owns the shell; File 25 owns final visual components.

## Functional requirements

| Requirement | Principal implementation | Behavioral/static evidence |
|---|---|---|
| F03-FR-001 Founder official profile | identity repository, Founder invariant, Founder fields | authorization runtime, source regression |
| F03-FR-002 Member profile | public/private DTO and canonical routes | authorization runtime, bootstrap |
| F03-FR-003 Doctor presentation | private proposal + trusted File 09 projection | verification runtime |
| F03-FR-004 Avatar/cover | exact-byte scan, re-encoding, ownership, deletion ledger | source regression, schema |
| F03-FR-005 Per-field privacy | public/members/contacts/private authorization | authorization runtime |
| F03-FR-006 Contacts | current File 00/File 17 projections, no raw metadata | source regression |
| F03-FR-007 Stable URLs | UUID, slug history, aliases/redirects | schema, architecture |
| F03-FR-008 Versioned edit | If-Match, CAS, idempotency, audit/outbox | source regression |
| F03-FR-009 Completion | owner-only completeness model | state runtime |
| F03-FR-010 Timeline | versioned providers, author/URL/status checks, cursor | timeline runtime |
| F03-FR-011 Verification | current validated File 09 projection only | verification runtime |
| F03-FR-012 Reporting | reasons, dedupe, transitions, notes, outbox | source regression |
| F03-FR-013 SEO | canonical public route, safe structured data, private noindex | source regression |

## Non-functional requirements

Security, privacy erasure, leased outbox/deletion queues, migration quarantine, no-store revocation safety, accessibility CSS, trace IDs, system check, safe mode, repair, PHP/WordPress compatibility and RTL/locale behavior are mapped by `tests/plan-coverage.py`. Staging-only and operational evidence is explicitly excluded from repository completion claims.
