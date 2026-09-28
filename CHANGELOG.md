# Changelog

## 1.2.0-rc18 — ninth current-companion twenty-round corrective review

Fresh comparison against the amended File 03 plan, central plan and current companion repository heads found a concrete File 08 integration gap: the current File 08 implementation publishes clinic/appointment owner facts through `wca_outbox_event`, while rc17 subscribed only to legacy/generic event surfaces. Because File 03 caches anonymous public profile DTOs containing File 08 clinic/availability projections, that mismatch could leave a stale projection after a legitimate File 08 owner change.

Corrections applied in this cycle:
- subscribe to File 08's exact current `wca_outbox_event` integration surface;
- validate the supported File 08 `1.x` event-contract range and fail closed below `1.0.0` or at future incompatible major `2.0.0+`;
- recognize current clinic/appointment lifecycle event names and invalidate File 03 cache/reconciliation state without copying File 08 truth;
- preserve opaque subject-UUID safety by using global generation/reconciliation invalidation when no canonical local profile mapping is available;
- add runtime regression coverage, a permanent ninth 20-round exact-head gate and a human-readable 20-round ledger; and
- correct stale eighth-cycle repository evidence after PR #37 merged and its resulting main SHA passed required exact-head workflows.

Release identity is `1.2.0-rc18`; DB schema remains `1.2.0`; public contract remains `1.4.0`. The ninth audit completed 20/20 rounds; rounds 04, 07 and 20 were defect-bearing and corrected. Closure requires the final branch and resulting main SHAs to pass their exact-head CI/package gates. Exact deployed code, live DB/migration state and deployment parity remain unverified.

## 1.2.0-rc17 — eighth cross-file twenty-round corrective review

The eighth source audit checks File 03 against its amended master plan, the central governing plan and current companion-owner contracts. It starts from exact main SHA `88da03fa4b92576384f4542ee7fc17312043bc0f`.

Corrections applied:
- consume required File 09/File 21/File 08 event families as projection invalidation only, preserving canonical ownership;
- register File 03 as the `file03-profiles` File 19 producer and use the canonical `sun.event.v1` intake path for eligible user-facing profile/report/moderation notifications;
- preserve durable retry semantics when File 19 is present but does not acknowledge delivery, while avoiding a duplicate notification backend when File 19 is absent;
- publish File 19 dependency/notification contract and System Check health evidence;
- add emitted appeal-review/reopen events to the machine-readable contract manifest;
- remove stale latest-plan rc1 artifact naming and add the permanent eighth 20-round exact-head gate; and
- advance the materially changed source candidate to `1.2.0-rc17` while retaining DB `1.2.0` and public contract `1.4.0`.

Exact deployed code, live DB/migration state and deployment parity remain unverified.


## 1.2.0-rc16 — seventh fresh 20-round sequential corrective review

The seventh cycle completed **20/20** rounds under the required sequence: complete review → consolidated defect list → correction → exact-state retest → next round.

Defect-bearing rounds: **03, 04, 05, 06, 07, 08, 11, 14, 15, 17, 19, 20**. Clean rounds: **01, 02, 09, 10, 12, 13, 16, 18**. Total: **12/20 defect-bearing; 8/20 clean**.

Key seventh-cycle corrections:
- contain File 17 contact/message provider Throwables and preserve fail-closed public-profile rendering;
- centralize File 00 provider exception containment and preserve dependency evidence;
- contain Future projection provider failures and stale/malformed claims;
- distinguish delegated and profile mutation dependency/store uncertainty from genuine authorization denial;
- keep legacy contact migration fail-closed when age/guardian state is unknown;
- enforce current viewer audience authorization on profile timeline items;
- prevent an established official Founder profile from silent identity demotion;
- protect persisted Founder identity and legal-hold uncertainty during erasure;
- surface retention schema failure as explicit operational/File24 evidence instead of a silent worker return; and
- reconcile repository/release identity in R20 by advancing the materially changed source candidate from rc15 to `1.2.0-rc16`, adding sixth/seventh plan-lineage markers, synchronizing current repository truth documents, and adding a permanent seventh-cycle R20 closure gate.

Release identity is `1.2.0-rc16`; DB schema remains `1.2.0`; public contract remains `1.4.0`. Historical release inventories/checksums remain evidence for their recorded older candidates and are not current rc16 artifact truth.

No staging/live/operational claim is made by this entry. Exact deployed code remains unverified.

## 1.2.0-rc15 — fifth fresh 20-round sequential corrective review

Repository candidate review cycle started from frozen baseline `157cfca2ed985ac8025b71a9373e974fca72f1a4` and completed **20/20** rounds under the required sequence: complete review → consolidated defect list → correction → exact-state retest → next round.

Defect-bearing rounds: **01, 05, 06, 11, 13, 14, 15, 16, 17, 18, 19, 20**. Clean rounds: **02, 03, 04, 07, 08, 09, 10, 12**. Total: **12/20 defect-bearing; 8/20 clean**.

Key rc15 corrections:
- strict REST transport version normalization and unknown-field rejection;
- fail-closed migration failure-ledger reads/writes/cleanup/completion evidence;
- required managed-page restoration to `publish`;
- strict safety-report/appeal DB certainty plus executable appeal review/outcome/reopen events;
- multilingual patient-specific clinical-intent refusal for profile-work AI;
- fail-closed Future/browser/timeline provider exception handling and provider-health degradation;
- atomic rejection of unencodable outbox/audit event payloads;
- lifecycle-safe canonical browser profile projection and structured-data provider containment;
- replay-safe non-JavaScript delegation grant/revoke browser mutations;
- permanent canonicalization of legacy `/profile/?public_id=...` public links;
- System Check/repair truth for unpublished pages, stale/throwing providers, Safe Mode timestamp and admin DB uncertainty;
- exact-head deterministic ZIP + checksum + SBOM + source/package byte parity;
- staging acceptance matrix expanded for the new critical paths;
- published PHP integration contracts wrapped in a fail-closed Throwable boundary with explicit 503 semantics;
- appeal review/reopen routes/events made discoverable in the rc15 contract manifest extension; and
- final fifth-cycle closure ledger plus permanent R18/R19/R20 regression gates.

Release identity is `1.2.0-rc15`; DB schema remains `1.2.0`; public contract remains `1.4.0`. Historical release inventories/checksums remain evidence for their recorded older candidates and are not current rc15 artifact truth.

No staging/live/operational claim is made by this entry. Exact deployed code remains unverified.

## 1.2.0-rc14 — fourth fresh 20-round sequential corrective review

- Fourth fresh sequential corrective cycle completed from its recorded baseline.
- Added stricter authorization/provider uncertainty handling, request-shape guards, activation persistence verification and delegation/schema fail-closed behavior.
- Preserved DB 1.2.0 / contract 1.4.0 and repository/staging/live status separation.

## 1.2.0-rc13 — third fresh 20-round sequential corrective review

- Third fresh sequential corrective cycle completed with DB-certain central/Future reads, lifecycle/federation guards, File09 minimum-claim validation, bootstrap parity, and privacy/uninstall hardening.

## 1.2.0-rc12 — second fresh 20-round sequential corrective review

- Second fresh sequential corrective cycle completed with File00/File09 trust revalidation, public media minimization, migration/source cleanup, privacy/user-meta recovery and operational error evidence.

## 1.2.0-rc11 — first fresh 20-round sequential corrective review

- First fresh sequential corrective cycle completed with provider/claim outage distinction, DB-certain profile reads, privacy/deletion hardening, disclosure-store certainty, File08 delegation uncertainty and System Check DB certainty.

## 1.2.0-rc10

- Tenth fresh ten-round corrective review completed.
- Added DB-certain central/public/Future reads, full field-value privacy export, migration-completion truth, media reconciliation, hardened outbox failure latching and bootstrap parity.

## 1.2.0-rc9

- Ninth fresh ten-round corrective review completed.

## 1.2.0-rc8

- Eighth fresh ten-round corrective review completed.

## 1.2.0-rc7

- Seventh fresh ten-round corrective review completed.

## 1.2.0-rc6

- Sixth fresh ten-round corrective review completed.

## 1.2.0-rc5

- Fifth fresh ten-round corrective review completed.

## 1.2.0-rc4

- Fourth fresh ten-round corrective review completed.

## 1.2.0-rc3

- Third fresh ten-round corrective review completed.

## 1.2.0-rc2

- Original numbered 80-round and second fresh 80-round corrective hardening completed.

## 1.2.0-rc1

- Implemented approved Future Professional Identity & Profile Superset 18 while preserving canonical owner boundaries.

## 1.1.x / earlier

- Historical File 03 profile-domain implementation, schema, privacy, media, route and integration hardening. See repository history and frozen release evidence for exact older candidate details.
