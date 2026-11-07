<?php
/**
 * F03-FR-011/013, F03-CEN-01/02: verified-doctor facts belong to File 09.
 * File 26 Search returns a persisted row/payload after File 03's can_view
 * callback, so owner revocation must suppress a stale index badge even when
 * File 03's native presentation version has not changed.
 *
 * Bounded behavior test (WordPress/File09 transport mocked, not production).
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error { public function __construct(public string $code = 'unavailable') {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
const DOCTOR_ID = 'c3333333-3333-4333-8333-333333333333';
$GLOBALS['f03_current_projection'] = [
    'canonical_id'=>DOCTOR_ID,
    'version'=>12,
    'profile_type'=>'doctor',
    'badge'=>['verified'=>true],
];
function spd_get_search_projection($identity) {
    return $identity === DOCTOR_ID ? $GLOBALS['f03_current_projection'] : new WP_Error();
}
require_once __DIR__ . '/../includes/class-spd-contracts.php';
function verify(bool $condition, string $description): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$description}\n"); exit(1); }
}
$indexed=[
    'connector_slug'=>'file03-profiles',
    'object_id'=>DOCTOR_ID,
    'object_version'=>12,
    'visibility'=>'public',
    'state'=>'active',
    'payload'=>['verified_doctor'=>true],
];
verify(SPD_Contracts::file26_can_view($indexed) === true, 'matching approved verified doctor must be visible');
$GLOBALS['f03_current_projection']['badge']['verified'] = false;
verify(SPD_Contracts::file26_can_view($indexed) === false, 'revoked File09 verified-doctor fact remains visible through stale File26 index');
$indexed['payload']['verified_doctor'] = false;
verify(SPD_Contracts::file26_can_view($indexed) === true, 'refreshed no-longer-verified doctor index should be visible without a false badge');
$GLOBALS['f03_current_projection']['badge']['verified'] = true;
verify(SPD_Contracts::file26_can_view($indexed) === false, 'new current File09 verification does not match old unverified index');
echo "PASS File 03/File 09/File 26 current doctor-badge projection and index revocation\n";
