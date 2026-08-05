=== Sabri Profiles and Doctors ===
Contributors: majidhussainqadri1-dot
Tags: profiles, doctors, privacy, founder, timeline
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0-rc2
License: GPLv2 or later

Canonical Founder, member and doctor profile domain for the Sabri Social Homeopathy Platform.

== Description ==

File 03 owns stable public profile identity, presentation fields, field visibility, profile media references, slug history, reporting and timeline slots. File 00 remains membership/identity authority; File 09 remains doctor-verification authority; File 07/26 own directory discovery/ranking; File 08 owns clinic truth; File 21 and media/learning modules own timeline content; File 20 owns the application shell and File 25 owns final platform-wide visual components.

This is a staging candidate. It is not production-operational until all documented staging, integration, accessibility, backup, rollback and Founder acceptance gates pass.

== Installation ==

1. Back up and verify restore capability.
2. Activate a compatible File 00 Membership Core.
3. Install and activate this plugin on staging.
4. Run Profile System Check.
5. Execute documented migration, integration and acceptance matrices.

== Changelog ==

= 1.0.0-rc2 =
* Hardens File 00 and File 09 versioned authority, moderator privacy, minor defaults, profile media scanning and deletion, report transitions, atomic outbox leases, migration quarantine, revocation-safe no-store responses and operator repair.
* Adds behavior-oriented authorization, verification, state, timeline, schema, source-regression and full-bootstrap tests.
* Source/package/automated-QA candidate only; staging, real provider integration, browser/WCAG/RTL, backup/restore, rollback and Founder acceptance remain mandatory.

= 1.0.0-rc1 =
* Initial plan-completion candidate.
