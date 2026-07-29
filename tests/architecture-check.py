#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
php = "\n".join(p.read_text(encoding="utf-8") for p in root.rglob("*.php") if "tests" not in p.parts)
forbidden = {
    "role creation": r"\badd_role\s*\(",
    "role mutation": r"->\s*(?:set_role|add_role|remove_role)\s*\(",
    "legacy File 01 page map": r"spf_page_map",
    "doctor approval endpoint": r"spd_verify_doctor",
    "hardcoded personal phone": r"\+923[0-9]{9}",
    "legacy auth account type": r"_sa_account_type",
}
for label, pattern in forbidden.items():
    if re.search(pattern, php):
        raise SystemExit(f"Forbidden {label} detected")
required = [
    "SPD_Membership_Adapter::is_doctor_identity_approved",
    "SPD_Verification_Adapter::directory_eligible",
    "_spd_managed_page_key",
    "X-Robots-Tag: noindex, nofollow, noarchive",
    "SPD_ALLOW_DESTRUCTIVE_UNINSTALL",
]
for token in required:
    if token not in php:
        raise SystemExit(f"Required architecture token missing: {token}")
print("Architecture checks passed.")
