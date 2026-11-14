<?php
/**
 * F03 V12.3 D12: real File03 privacy/exporter methods under WordPress DB
 * uncertain read results. Database null must never become empty success,
 * exception or irreversible erase; staging DB testing remains separate.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private string $code;
    public function __construct($code, $message = '') { $this->code = (string) $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function __($v, $domain = '') { return $v; }
function absint($v) { return abs((int) $v); }
function get_user_by($field, $email) { return (object) ['ID' => 11]; }
function apply_filters($name, $default, ...$args) { return false; }
class SPD_Helpers { public static function now() { return '2026-11-14 05:00:00'; } }
class SPD_DB {
    public static function table($name) { return 'wp_spd_' . $name; }
    public static function transaction($callback) { return $callback(); }
}
class SPD_Profile_Repository {
    public static function instance() { return new self(); }
    public function find_by_user_id($id, $ensure = false) { return []; }
    public function erase_profile($id) { return ['removed' => false, 'retained' => false, 'messages' => []]; }
    public function event() { return true; }
}
class FakePrivacyWpdb {
    public string $last_error = '';
    public string $mode = '';
    public int $deletes = 0;
    public function prepare($query, ...$args) { return $query; }
    public function get_results($query, $format) {
        if (str_contains($query, 'spd_reports')) {
            return in_array($this->mode, ['null_reports', 'null_reports_erase'], true) ? null : [];
        }
        if (str_contains($query, 'spd_professional_submissions')) {
            return 'null_professional' === $this->mode ? null : [];
        }
        return [];
    }
    public function get_var($query) { return 'null_count' === $this->mode ? null : '0'; }
    public function delete($table, $where) { $this->deletes++; return 0; }
}
$GLOBALS['wpdb'] = new FakePrivacyWpdb();
require __DIR__ . '/../includes/class-spd-privacy.php';
$privacy = new SPD_Privacy();
foreach (['null_reports', 'null_professional'] as $mode) {
    $GLOBALS['wpdb']->mode = $mode;
    try { $result = $privacy->export('privacy@example.test'); }
    catch (Throwable $e) { fwrite(STDERR, "FAIL D12 export crashed on $mode\n"); exit(1); }
    if (!is_wp_error($result) || 'spd_privacy_export_failed' !== $result->get_error_code()) {
        fwrite(STDERR, "FAIL D12 export reported success on $mode\n"); exit(1);
    }
}
foreach (['null_reports_erase', 'null_count'] as $mode) {
    $GLOBALS['wpdb']->mode = $mode;
    $beforeDeletes = $GLOBALS['wpdb']->deletes;
    $result = $privacy->erase('privacy@example.test');
    if (!empty($result['done']) || empty($result['items_retained'])
        || $beforeDeletes !== $GLOBALS['wpdb']->deletes) {
        fwrite(STDERR, "FAIL D12 erasure completed or deleted after uncertain $mode\n"); exit(1);
    }
}
$GLOBALS['wpdb']->mode = 'healthy_empty';
$export = $privacy->export('privacy@example.test');
$erase = $privacy->erase('privacy@example.test');
if (!is_array($export) || empty($export['done']) || !is_array($erase) || empty($erase['done'])) {
    fwrite(STDERR, "FAIL D12 genuine empty source result should succeed\n"); exit(1);
}
echo "PASS File03 privacy export/erasure uncertain null DB reads fail closed and retryable\n";
