<?php
/**
 * F03-V12.3 D12 future privacy: translation/attestation SELECT null cannot be
 * exported as success while the canonical store's result is uncertain.
 * Uses actual owner code with fake DB; stage proof remains mandatory.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int) $v); }
function __($v, $domain = '') { return $v; }
function wp_json_encode($v) { return json_encode($v); }
class SPD_Schema_Guard { public static function future_ready() { return true; } }
class SPD_Future_Profile {
    public static function translations_table() { return 'wp_spd_profile_translations'; }
    public static function attestations_table() { return 'wp_spd_profile_attestations'; }
    public static function state_table() { return 'wp_spd_profile_future_state'; }
}
class FutureWpdb {
    public string $last_error = '';
    public string $mode = '';
    public function prepare($sql,...$values) { return $sql; }
    public function get_results($sql,$format) {
        if ($this->mode === 'null_translations' && str_contains($sql,'profile_translations')) return null;
        if ($this->mode === 'null_attestations' && str_contains($sql,'profile_attestations')) return null;
        return [];
    }
    public function get_row($sql,$format) { return null; }
}
$GLOBALS['wpdb'] = new FutureWpdb();
require __DIR__.'/../includes/class-spd-future-privacy.php';
foreach (['null_translations','null_attestations'] as $mode) {
    $GLOBALS['wpdb']->mode = $mode;
    $result = SPD_Future_Privacy::export_profile_data(12);
    if (!is_wp_error($result) || $result->get_error_code() !== 'spd_future_privacy_export_failed') {
        fwrite(STDERR, "FAIL D12 future privacy accepted uncertain $mode\n"); exit(1);
    }
}
$GLOBALS['wpdb']->mode = 'healthy_empty';
$empty = SPD_Future_Privacy::export_profile_data(12);
if (!is_array($empty) || $empty !== []) {
    fwrite(STDERR,"FAIL D12 future privacy genuine empty export rejected\n"); exit(1);
}
echo "PASS D12 future profile translation/attestation DB-null export fail-closed\n";
