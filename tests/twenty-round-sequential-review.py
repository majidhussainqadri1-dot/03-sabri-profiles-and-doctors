#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding="utf-8")

def require(path, *tokens):
    data = text(path)
    missing = [token for token in tokens if token not in data]
    if missing:
        raise AssertionError(f"{path}: missing invariants: {missing}")

bootstrap = text("sabri-profiles-doctors.php")
version = re.search(r"define\( 'SPD_VERSION', '1\.2\.0-rc(\d+)' \);", bootstrap)
if not version or int(version.group(1)) < 11:
    raise AssertionError("first twenty-round guarantees require rc11 or a later corrective release")
if "TWENTY-ROUND-SEQUENTIAL-CORRECTIVE-REVIEW" not in bootstrap:
    raise AssertionError("twenty-round governing marker is missing")
if "define( 'SPD_DB_VERSION', '1.2.0' );" not in bootstrap or "define( 'SPD_CONTRACT_VERSION', '1.4.0' );" not in bootstrap:
    raise AssertionError("DB/contract identity drifted unexpectedly")

for path in (
    "includes/class-spd-rest.php",
    "includes/class-spd-central-rest.php",
    "includes/class-spd-future-rest.php",
):
    require(path, "SPD_Membership_Adapter::health()", "spd_membership_provider_unavailable", "'status' => 503", "spd_account_ineligible")

require("includes/class-spd-central-profile.php", "find_by_public_id_strict( $dto['public_id'] )")
require("includes/class-spd-routes.php", "find_by_slug_strict", "find_by_public_id_strict", "status_header( 503 )")
require("includes/trait-spd-profile-moderation.php", "find_by_public_id_strict( $public_id )", "spd_report_store_unavailable")
require("includes/class-spd-timeline.php", "find_by_public_id_strict", "spd_timeline_profile_store_unavailable", "is_object( $wpdb )")
require("includes/trait-spd-frontend-profile.php", "find_by_public_id_strict( $public_id )")

central_privacy = text("includes/class-spd-central-privacy.php")
if "Relationship role" not in central_privacy:
    raise AssertionError("delegation relationship export minimization is missing")
for forbidden in ("'name' => 'Owner user ID'", "'name' => 'Delegate user ID'", "'name' => 'Profile ID'"):
    if forbidden in central_privacy:
        raise AssertionError(f"delegation export leaks internal identifier: {forbidden}")

require("includes/class-spd-media.php", "attachment_delete_failed", "$had_error=true", "spd_last_media_queue_error")
require("uninstall.php", "_transient_spd_timeline_circuit_", "_transient_timeout_spd_timeline_circuit_")
require("includes/class-spd-future-rest.php", "spd_disclosure_store_unavailable", "db_certain_future_result", "spd_future_store_unavailable")
require("includes/class-spd-plugin.php", "SPD_Membership_Adapter::health()", "SPD_Verification_Adapter::projection", "$wpdb->last_error", "return array();")
require("includes/class-spd-observability.php", "operational_count", "health_query_status", "diagnose_database_query_failure", "spd_repair_diagnosis_uncertain")

require(".github/workflows/fresh-eighty-round-review.yml", "tests/twenty-round-sequential-review.py", "audit/file-03-twentieth-round-sequential-20260812")
require(".github/workflows/future-superset-18.yml", "tests/twenty-round-sequential-review.py", "Deterministic package, checksum, SBOM and corrective parity")
require("TWENTY-ROUND-SEQUENTIAL-REVIEW-2026-08-12.md", "Defect-bearing rounds", "07, 08, 11, 16", "1.2.0-rc11")

print("File 03 first twenty-round sequential corrective invariants: PASS")
