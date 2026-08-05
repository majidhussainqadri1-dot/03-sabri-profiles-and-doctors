# Security and Privacy Boundary

File 03 never reads File 00 internal tables, identity documents, guardian evidence or raw private phone metadata. It accepts only current versioned assertions/projections and fails closed on missing, stale or malformed claims. File 09 is the sole doctor-verification authority. Private fields are never granted to moderators merely because they can review reports.

All mutations require current object authorization, optimistic version, idempotency key and durable event evidence. Public WordPress media ingestion requires exact-byte validation, metadata re-encoding and a current compatible safety scan. Non-public/revoked media is removed from profile references and placed in a leased deletion ledger. Destructive uninstall requires two independent controls and remains disabled by default.

Do not report sensitive vulnerabilities in public issues; use the project’s approved private security channel.
