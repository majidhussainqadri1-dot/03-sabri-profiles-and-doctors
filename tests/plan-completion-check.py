#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
php = "\n".join(p.read_text(encoding="utf-8") for p in root.rglob("*.php") if "tests" not in p.parts)
required = {
    "stable public id": "_spd_public_id",
    "canonical profile route": "^profile/([a-f0-9]{32})",
    "timeline providers": "spd_timeline_providers",
    "field privacy": "_spd_field_privacy",
    "optimistic version": "_spd_profile_version",
    "minor fail closed": "SPD_Profile_Policy::is_minor",
    "minor contact denial": "if ( SPD_Profile_Policy::is_minor( $user_id ) ) { return false; }",
    "contract health": "file00_contract_too_old",
    "safe mode": "spd_safe_mode",
    "owner dto": "spd_profile_owner_dto",
}
for label, token in required.items():
    if token not in php:
        raise SystemExit(f"Missing {label}: {token}")
forbidden = {
    "direct credentials table": r"smc_professional_credentials",
    "direct clinic table": r"smc_clinics",
    "legacy requested role authority": r"_smc_requested_role",
}
for label, pattern in forbidden.items():
    if re.search(pattern, php):
        raise SystemExit(f"Forbidden architecture detected: {label}")
print("Plan-completion architecture checks passed.")
