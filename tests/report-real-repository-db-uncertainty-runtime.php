<?php
/**
 * File03 V12.3 D13: actual SPD_Profile_Repository path invoked by the central
 * REST endpoint, NOT merely SPD_Profile_Central trait fixture.
 * A null SQL report-count without last_error must fail closed as 503.
 * Isolated source contract test, not live/staging evidence.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    public function __construct(private string $code, string $msg = '', array $data = []) {}
    public function get_error_code() { return $this->code; }
}
function add_action(...$args) { return true; }
function is_wp_error($x) { return $x instanceof WP_Error; }
function __($s, $domain = '') { return $s; }
function absint($x) { return abs((int)$x); }
function sanitize_key($x) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$x)); }
function sanitize_text_field($x) { return (string)$x; }
trait SPD_Profile_Identity_Create {}
trait SPD_Profile_Identity_Read {
    public function find_by_public_id($id) {
        return ['id' => 4, 'public_id' => (string)$id, 'user_id' => 8, 'state' => 'active'];
    }
}
trait SPD_Profile_Public_DTO {}
trait SPD_Profile_Edit_Model {}
trait SPD_Profile_Update { public function update_profile() {} }
trait SPD_Profile_Professional {}
trait SPD_Profile_Media {}
trait SPD_Profile_Moderation {}
require __DIR__.'/../includes/trait-spd-profile-report-appeals.php';
trait SPD_Profile_Lifecycle { use SPD_Profile_Report_Appeals; }
trait SPD_Profile_Events {
    private function idempotency_begin(...$args) { return []; }
    private function idempotency_fail(...$args) { return true; }
    private function idempotency_complete(...$args) { return true; }
    public function event(...$args) { return true; }
}
trait SPD_Profile_Cache {}
trait SPD_Profile_Central {
    public function grant_delegate() {}
    public function central_edit_model() {}
    public function update_central_profile() {}
}
class SPD_Membership_Adapter {
    public static function health() { return ['status' => 'available']; }
    public static function claims($id) { return ['eligible' => true, 'suspended' => false]; }
}
class SPD_Observability { public static function safe_mode() { return false; } }
class SPD_Authorization {
    public static function profile_visibility_allows($profile, $viewer) { return true; }
}
class SPD_Central_Profile {
    public static function report_reasons() { return ['privacy']; }
    public static function extended_fields() { return []; }
}
class SPD_Helpers {
    public static function sanitize_multiline($v, $n=500) { return substr((string)$v, 0, $n); }
    public static function text_length($v) { return strlen((string)$v); }
    public static function json_encode($v) { return json_encode($v); }
    public static function consume_rate_limit(...$args) { return true; }
    public static function public_id() { return 'fixture-uuid'; }
    public static function now() { return '2026-11-14 09:00:00'; }
}
class SPD_DB {
    public static function table($name) { return 'wp_spd_'.$name; }
    public static function transaction($callback) { return $callback(); }
}
class FakeReportDb {
    public string $last_error = '';
    public $count = null;
    public int $writes = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return $this->count; }
    public function insert($table, $data) { $this->writes++; return 1; }
}
$GLOBALS['wpdb'] = new FakeReportDb();
require __DIR__.'/../includes/class-spd-profile-repository.php';
$repo = SPD_Profile_Repository::instance();
$source = (new ReflectionMethod($repo, 'create_safety_report'))->getDeclaringClass()->getName();
if ($source !== 'SPD_Profile_Repository') {
    fwrite(STDERR, "FAIL D13 test not exercising real overridden class method\n"); exit(1);
}
$args = ['123e4567-e89b-42d3-a456-426614174000', 11, 'privacy',
    'A detailed serious safety report with enough context.', 'idem-xyz'];
$failed = $repo->create_safety_report(...$args);
if (!is_wp_error($failed) || $failed->get_error_code() !== 'spd_report_store_unavailable'
    || $GLOBALS['wpdb']->writes !== 0) {
    fwrite(STDERR, "FAIL D13 actual repository accepted null DB count and/or inserted report\n"); exit(1);
}
$GLOBALS['wpdb']->count = '0';
$success = $repo->create_safety_report(...$args);
if (!is_array($success) || ($success['status'] ?? '') !== 'submitted'
    || $GLOBALS['wpdb']->writes !== 1) {
    fwrite(STDERR, "FAIL D13 known healthy zero count did not permit report\n"); exit(1);
}
$GLOBALS['wpdb']->count = null;
$beforeStrict = $GLOBALS['wpdb']->writes;
$strict = $repo->create_safety_report_strict(...$args);
if (!is_wp_error($strict) || $strict->get_error_code() !== 'spd_report_store_unavailable'
    || $GLOBALS['wpdb']->writes !== $beforeStrict) {
    fwrite(STDERR, "FAIL D13 actual frontend strict method accepted uncertain count\n"); exit(1);
}
$GLOBALS['wpdb']->count = '0';
$strictOk = $repo->create_safety_report_strict(...$args);
if (!is_array($strictOk) || ($strictOk['status'] ?? '') !== 'submitted'
    || $GLOBALS['wpdb']->writes !== $beforeStrict + 1) {
    fwrite(STDERR, "FAIL D13 actual frontend strict method rejected healthy count\n"); exit(1);
}
echo "PASS File03 D13 active REST repository count uncertainty and healthy-zero semantics\n";
