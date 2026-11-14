<?php
/** F03 V12.3 D12 follow-up: delegation/appeal privacy reads must fail closed. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private $code;
    public function __construct($c, $message = '') { $this->code = $c; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->code; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function __($s, $d = '') { return $s; }
function absint($x) { return abs((int) $x); }
function get_user_by($key, $value) { return (object) ['ID' => 11]; }
function apply_filters($tag, $v, ...$args) { return false; }
class SPD_Schema_Guard { public static function central_ready() { return true; } }
class SPD_Central_Profile {
    public static function delegation_table() { return 'wp_spd_profile_delegations'; }
    public static function appeals_table() { return 'wp_spd_report_appeals'; }
}
class SPD_Helpers { public static function now() { return '2026-11-14 05:00:00'; } }
class SPD_DB { public static function transaction($callback) { return $callback(); } }
class FakeCentralWpdb {
    public string $last_error = '';
    public string $mode = '';
    public int $writes = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $format) {
        if ('null_delegations' === $this->mode && str_contains($sql, 'profile_delegations')) return null;
        if ('null_appeals' === $this->mode && str_contains($sql, 'report_appeals')) return null;
        return [];
    }
    public function get_var($sql) {
        if ('null_delegation_count' === $this->mode && str_contains($sql, 'profile_delegations')) return null;
        if ('null_requester_count' === $this->mode && str_contains($sql, 'requested_by')) return null;
        if ('null_reviewer_count' === $this->mode && str_contains($sql, 'reviewer_id')) return null;
        return '0';
    }
    public function query($sql) { $this->writes++; return 1; }
}
$GLOBALS['wpdb'] = new FakeCentralWpdb();
require __DIR__ . '/../includes/class-spd-central-privacy.php';
$privacy = new SPD_Central_Privacy();
foreach (['null_delegations', 'null_appeals'] as $mode) {
    $GLOBALS['wpdb']->mode = $mode;
    $result = $privacy->export('test@example.test');
    if (!is_wp_error($result) || $result->get_error_code() !== 'spd_central_privacy_export_failed') {
        fwrite(STDERR, "FAIL D12 central export false empty success on $mode\n"); exit(1);
    }
}
foreach (['null_delegation_count', 'null_requester_count', 'null_reviewer_count'] as $mode) {
    $GLOBALS['wpdb']->mode = $mode;
    $result = $privacy->erase('test@example.test');
    if (!is_array($result) || !empty($result['done']) || empty($result['items_retained'])) {
        fwrite(STDERR, "FAIL D12 central erasure false complete on $mode\n"); exit(1);
    }
}
$GLOBALS['wpdb']->mode = 'healthy_empty';
$export = $privacy->export('test@example.test');
$erase = $privacy->erase('test@example.test');
if (empty($export['done']) || empty($erase['done'])) {
    fwrite(STDERR, "FAIL D12 genuine empty central privacy result rejected\n"); exit(1);
}
echo "PASS D12 central profile delegation/appeal privacy DB-null fail-closed\n";
