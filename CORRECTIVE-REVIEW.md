# Corrective Review Record

## Defects addressed

1. Removed File 03 role creation and doctor-verification mutations.
2. Made File 00 mandatory and authoritative for identity, roles, account type, canonical profile data, and membership approval.
3. Made File 09 authoritative for doctor verification and fail-closed when File 09 evidence is unavailable.
4. Added approved public-projection snapshots and fingerprint comparison so material changes suspend public directory eligibility until re-review.
5. Bound the Founder profile to one canonical File 00 Founder account and removed administrator-wide Founder editing.
6. Removed hardcoded personal Founder contact, location, biography, and publication defaults from the corrected source.
7. Removed the legacy File 01 `spf_page_map` dependency and added exact File 03 page ownership.
8. Added explicit private/member/public profile visibility and explicit public-contact consent.
9. Added private-page `noindex`, `nofollow`, `noarchive`, `no-store`, private-cache, referrer, frame, MIME, and permissions headers.
10. Added image MIME/dimension/size validation, plugin media ownership, replacement cleanup, and physical media erasure when ownership is proven.
11. Stopped File 03 from editing professional identity and credential fields.
12. Routed projection audit events into File 00's canonical audit system.
13. Added legacy File 03 audit anonymization after 180 days and deletion after 365 days.
14. Added guarded destructive uninstall requiring both a constant and an administrator option.
15. Added immutable corrective inventory, action SHA pins, architecture tests, credential scanning, and PHP compatibility lint.

## Deliberate boundary

File 09 version `1.0.0` still contains legacy role assumptions and direct `_spd_*` writes. File 03 supplies a read-only compatibility facade but does not recreate those roles or take ownership of verification. File 09 must be corrected in its own repository before production integration can be accepted.

## Current decision

- Source-level correction: implemented.
- Automated QA: must pass in GitHub after upload.
- Runtime and staging acceptance: not yet granted.
- Merge authorization: no.
