=== Sabri Profiles and Doctors ===
Contributors: sabrihomeopathy
Tags: profiles, doctors, directory, privacy, founder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Privacy-controlled profile and verified-doctor directory projection for the Sabri Social Homeopathy Platform.

== Description ==

File 03 is a projection layer. It does not create membership roles, approve doctors, or own identity records.

Authority boundaries:
* File 00 — Sabri Membership Core owns identity, account type, roles, membership approval, and canonical profile data.
* File 09 — Global Doctor Onboarding and Verification owns credential evidence and doctor-verification decisions.
* File 20 — Unified Application Shell may consume File 03 navigation destinations.
* File 03 displays approved snapshots, public profile visibility, public contact consent, and presentation media.

A doctor appears in the directory only when File 00 approves doctor identity, File 09 records a verified decision with reviewer evidence, an approved snapshot exists, the snapshot still matches current material data, and profile visibility is Public.

== Installation ==

1. Activate File 00 — Sabri Membership Core.
2. Upload and activate File 03.
3. Activate File 09 before expecting verified doctors in the public directory.
4. The canonical Founder account must carry File 00's `_smc_official_founder` marker.
5. Review profile visibility and public-contact consent in Edit Profile Presentation.

== Privacy ==

General profiles default to the visibility supplied by File 00 and are never assumed public. File 03 stores only presentation visibility, contact consent, and plugin-owned profile/cover attachment references. Its privacy eraser deletes plugin-owned media when ownership can be proven. Identity, credentials, verification decisions, and audit records remain under Files 00 and 09.

== Security and governance ==

* No role or verification-status mutation.
* No File 01 page-map dependency.
* Exact managed-page ownership.
* Private account pages receive noindex/noarchive/no-store/private headers.
* Verified doctor material changes invalidate the approved projection until re-review.
* Destructive uninstall requires `SPD_ALLOW_DESTRUCTIVE_UNINSTALL` and `spd_purge_on_uninstall`.

== Changelog ==

= 0.2.0 =
* Made File 00 the mandatory identity and role authority.
* Made File 09 the doctor-verification authority and fail-closed when unavailable.
* Removed File 03 doctor-approval actions and role creation.
* Added approved projection snapshots and change invalidation.
* Bound Founder presentation to the canonical Founder account.
* Removed hardcoded personal Founder contact and biography defaults.
* Added exact page ownership, visibility controls, private headers, media lifecycle cleanup, and controlled purge.

= 0.1.0 =
* Original baseline release preserved on the baseline branch.
