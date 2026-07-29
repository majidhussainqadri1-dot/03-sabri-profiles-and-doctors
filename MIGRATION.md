# File 03 Migration — 0.1.0 to 0.2.0

1. Keep the original baseline branch unchanged.
2. Back up the WordPress database and uploads before staging activation.
3. Activate File 00 before File 03.
4. Activate corrected File 03 on staging only.
5. File 03 removes its obsolete administrator capability but does not destructively change user roles.
6. Legacy Founder identity/contact keys in the File 03 option are discarded at runtime; canonical values come from File 00.
7. Existing File 03 pages are adopted only when exact ownership or exact shortcode content is proven. Unrelated pages are not overwritten.
8. Existing verified doctors receive an approved snapshot only when File 00 doctor approval and File 09 reviewer evidence are both present.
9. Doctors without a valid snapshot are excluded from the public directory until File 09 re-review.
10. Legacy File 03 audit rows are anonymized after 180 days and deleted after 365 days.
11. Roll back by deactivating `0.2.0` and restoring the staging backup. Do not install the original baseline on live production.
