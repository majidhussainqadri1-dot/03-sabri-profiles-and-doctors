<?php
/**
 * F03-CEN-01/02, F03-FR-013, F03-NFR-001/002.
 * Fail-first: File 26 stores its document version and presents stored
 * fields after calling the File 03 can_view callback. An old index entry
 * must never remain viewable after the canonical profile version changes:
 * the old index may contain a field whose audience has since become private.
 *
 * Bounded mock of File 03's projection contract; NOT a real WP/DB/live test.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error { public function __construct(public string $code = 'missing') {} }
function is_wp_error($x) { return $x instanceof WP_Error; }
function sanitize_text_field($x) { return trim(strip_tags((string) $x)); }
const F03_ID = 'a1111111-1111-4111-8111-111111111111';
$GLOBALS['f03_current'] = [
    'canonical_id' => F03_ID,
    'version' => 8,
    'profile_type' => 'member',
    'badge' => array(),
];
function spd_get_search_projection($identity) {
    return $identity === F03_ID ? $GLOBALS['f03_current'] : new WP_Error();
}
require_once __DIR__ . '/../includes/class-spd-contracts.php';
function check_file26(bool $actual, bool $expected, string $message): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
$document = [
    'connector_slug' => 'file03-profiles',
    'object_id' => F03_ID,
    'object_version' => 8,
    'entity_type' => 'member_profile',
    'visibility' => 'public',
    'state' => 'active',
];
check_file26(SPD_Contracts::file26_can_view($document), true, 'current public profile should remain discoverable');
$old = $document;
$old['object_version'] = 7;
check_file26(SPD_Contracts::file26_can_view($old), false, 'stale search document disclosed after profile version changed');
$absent = $document;
unset($absent['object_version']);
check_file26(SPD_Contracts::file26_can_view($absent), false, 'missing document version treated as current');
$zero = $document;
$zero['object_version'] = 0;
check_file26(SPD_Contracts::file26_can_view($zero), false, 'invalid zero version accepted');
$GLOBALS['f03_current'] = new WP_Error();
check_file26(SPD_Contracts::file26_can_view($document), false, 'unavailable canonical owner returned visible');
$GLOBALS['f03_current'] = ['canonical_id' => 'b2222222-2222-4222-8222-222222222222', 'version'=>8];
check_file26(SPD_Contracts::file26_can_view($document), false, 'mismatched canonical identity returned visible');
echo "PASS File 03/File 26 version-bound click-time visibility revalidation\n";
