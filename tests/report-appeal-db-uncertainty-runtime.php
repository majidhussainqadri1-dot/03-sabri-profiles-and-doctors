<?php
/**
 * F03 V12.3 D13: source-level no-false-success test for report quota
 * uncertainty and no-false-403 for appeal lookup DB failures.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    private $code; private $data;
    public function __construct($code, $message = '', $data = []) { $this->code = $code; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text, $domain = '') { return $text; }
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
class SPD_Membership_Adapter { public static function is_member_eligible($id) { return true; } }
class SPD_Authorization { public static function profile_visibility_allows($profile, $id) { return true; } }
class SPD_Helpers {
    public static function sanitize_multiline($v, $n) { return substr(trim($v), 0, $n); }
    public static function text_length($v) { return strlen($v); }
    public static function json_encode($v) { return json_encode($v); }
    public static function consume_rate_limit(...$args) { return true; }
    public static function public_id() { return 'fixture-uuid'; }
    public static function now() { return '2026-11-14 05:00:00'; }
}
class SPD_Central_Profile {
    public static function report_reasons() { return ['privacy']; }
    public static function appeals_table() { return 'wp_spd_report_appeals'; }
}
class SPD_DB {
    public static function table($name) { return 'wp_spd_' . $name; }
    public static function transaction($callback) { return $callback(); }
}
class FakeReportWpdb {
    public string $last_error = '';
    public string $mode = '';
    public int $writes = 0;
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return 'null_report_count' === $this->mode ? null : '0'; }
    public function get_row($query, $format) {
        if ('error_appeal_lookup' === $this->mode) {
            $this->last_error = 'simulated failed SQL read';
            return null;
        }
        return ['id' => 2, 'reporter_user_id' => 11, 'status' => 'closed'];
    }
    public function insert($table, $data) { $this->writes++; return 1; }
}
$GLOBALS['wpdb'] = new FakeReportWpdb();
require __DIR__ . '/../includes/trait-spd-profile-central.php';
class ReportProbe {
    use SPD_Profile_Central;
    public function find_by_public_id($id) { return ['id' => 3, 'public_id' => $id]; }
    public function idempotency_begin(...$args) { return []; }
    public function idempotency_fail(...$args) { return true; }
    public function idempotency_complete(...$args) { return true; }
    public function event(...$args) { return true; }
}
$repo = new ReportProbe();
$GLOBALS['wpdb']->mode = 'null_report_count';
$before = $GLOBALS['wpdb']->writes;
$result = $repo->create_safety_report('profile-id', 11, 'privacy', 'This is a serious and important safety report.', 'request-1');
if (!is_wp_error($result) || 'spd_report_store_unavailable' !== $result->get_error_code()
    || $before !== $GLOBALS['wpdb']->writes) {
    fwrite(STDERR, "FAIL D13 DB-null report count accepted or wrote a report\n"); exit(1);
}
$GLOBALS['wpdb']->mode = 'error_appeal_lookup';
$result = $repo->request_report_appeal('report-id', 11, 'I would like to appeal this incorrect decision.', 'request-2');
if (!is_wp_error($result) || 'spd_appeal_report_store_unavailable' !== $result->get_error_code()) {
    fwrite(STDERR, "FAIL D13 appeal store outage misclassified as user ineligible\n"); exit(1);
}
echo "PASS D13 report-rate-limit DB uncertainty and appeal read outage fail closed\n";
