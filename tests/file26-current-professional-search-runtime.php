<?php
/**
 * F03-V12.3 D10: external File09 specialty changes need not bump File03's
 * local row version. File26 stores normalized_body as persisted search truth.
 * The canonical owner visibility callback must reject stale owner text.
 * Isolated source contract fixture, not an installed WordPress acceptance.
 */
namespace Sabri\File26 {
    final class Normalizer {
        public function normalize($v) { return preg_replace('/\s+/', ' ', strtolower(trim((string) $v))); }
    }
}
namespace {
    define('ABSPATH', __DIR__ . '/');
    class WP_Error {}
    function is_wp_error($v) { return $v instanceof WP_Error; }
    function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
    function wp_strip_all_tags($v, $remove_breaks = false) { return strip_tags((string)$v); }
    const PROFILE_ID = 'b4444444-4444-4444-8444-444444444444';
    $GLOBALS['f03_current'] = [
        'canonical_id' => PROFILE_ID,
        'profile_type' => 'doctor',
        'version' => 12,
        'badge' => ['key' => 'verified_doctor', 'verified' => true],
        'display_name' => 'Doctor A',
        'fields' => ['country' => 'Pakistan', 'city' => 'Lahore'],
        'professional' => ['professional_title' => 'Doctor', 'specialty' => 'Homeopathy A'],
    ];
    function spd_get_search_projection($identity) { return $GLOBALS['f03_current']; }
    require_once __DIR__ . '/../includes/class-spd-contracts.php';
    function test_check($condition, $why) { if (!$condition) { fwrite(STDERR, "FAIL D10: $why\n"); exit(1); } }
    $doc = [
        'object_id' => PROFILE_ID, 'object_version' => 12, 'entity_type' => 'doctor',
        'visibility' => 'public', 'state' => 'active', 'payload' => ['verified_doctor' => true],
        'normalized_body' => 'doctor a doctor homeopathy a pakistan lahore',
    ];
    test_check(\SPD_Contracts::file26_can_view($doc), 'current indexed specialty unexpectedly hidden');
    // The canonical File09 claim changes without mutating File03 version.
    $GLOBALS['f03_current']['professional']['specialty'] = 'Homeopathy B';
    test_check(!\SPD_Contracts::file26_can_view($doc), 'stale indexed File09 specialty still visible');
    $doc['normalized_body'] = 'doctor a doctor homeopathy b pakistan lahore';
    test_check(\SPD_Contracts::file26_can_view($doc), 'fresh File09 specialty not discoverable');
    unset($doc['normalized_body']);
    test_check(!\SPD_Contracts::file26_can_view($doc), 'unverifiable missing indexed body accepted');
    echo "PASS D10 File03 File09 File26 external search text and native version revalidation\n";
}
