<?php
/**
 * File03 V12.3 D14: actual base + central WordPress Privacy erasers.
 * Provider exception is uncertain retention decision, not permission to
 * erase or falsely close an operation. Source contract test, not live proof.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    public function __construct(public string $code, public string $message = '') {}
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text, $domain='') { return $text; }
function absint($value) { return abs((int)$value); }
function get_user_by($field, $email) { return (object)['ID' => 11]; }
function apply_filters($name, ...$args) {
    if ($name === $GLOBALS['throw_on']) { throw new RuntimeException('Synthetic legal hold provider outage'); }
    return false;
}
class SPD_Profile_Repository {
    public static function instance() { return new self(); }
    public function erase_profile($id) {
        return ['removed'=>false, 'retained'=>false, 'retry'=>false, 'messages'=>[]];
    }
}
class SPD_DB {
    public static function table($name) { return 'wp_spd_'.$name; }
    public static function transaction($callback) { return $callback(); }
}
class SPD_Schema_Guard { public static function central_ready() { return true; } }
class SPD_Central_Profile {
    public static function delegation_table() { return 'wp_spd_profile_delegations'; }
    public static function appeals_table() { return 'wp_spd_report_appeals'; }
}
class FakeWpdb {
    public string $last_error = '';
    public int $writes = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $format) { return []; }
    public function get_var($sql) { return '0'; }
    public function query($sql) { $this->writes++; return true; }
    public function delete($table, $where) { $this->writes++; return 0; }
}
$GLOBALS['wpdb'] = new FakeWpdb();
require __DIR__.'/../includes/class-spd-privacy.php';
require __DIR__.'/../includes/class-spd-central-privacy.php';
$cases = [
    ['spd_profile_report_legal_hold', SPD_Privacy::class],
    ['spd_professional_submission_legal_hold', SPD_Privacy::class],
    ['spd_profile_delegation_legal_hold', SPD_Central_Privacy::class],
    ['spd_profile_appeal_legal_hold', SPD_Central_Privacy::class],
];
foreach ($cases as [$hold, $class]) {
    $GLOBALS['throw_on'] = $hold;
    try { $result = (new $class())->erase('privacy@example.test'); }
    catch (Throwable $error) {
        fwrite(STDERR, "FAIL D14 legal-hold provider exception crashed ".$class.": ".$hold."\n");
        exit(1);
    }
    if (($result['done'] ?? true) !== false || empty($result['items_retained'])
        || $GLOBALS['wpdb']->writes !== 0) {
        fwrite(STDERR, "FAIL D14 provider outage incorrectly completed or wrote records: ".$hold."\n");
        exit(1);
    }
}
$GLOBALS['throw_on'] = 'none';
foreach ([SPD_Privacy::class, SPD_Central_Privacy::class] as $class) {
    $result = (new $class())->erase('privacy@example.test');
    if (empty($result['done'])) {
        fwrite(STDERR, "FAIL D14 true healthy empty privacy source did not complete\n"); exit(1);
    }
}
echo "PASS File03 D14 base/central legal-hold exceptions retained, retried and healthy-empty unaffected\n";
