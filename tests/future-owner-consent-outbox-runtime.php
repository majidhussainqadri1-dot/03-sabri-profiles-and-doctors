<?php
/**
 * V12.3 D14: File03 Future owner opt-in and transactional outbox.
 * Executes the actual SPD_Future_Profile mutation methods against an
 * isolated fake WordPress DB; not a substitute for staging/real SQL.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    private string $code;
    public function __construct(string $code, string $message = '', array $data = []) { $this->code = $code; }
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error($v): bool { return $v instanceof WP_Error; }
function __($s, $domain = '') { return $s; }
function absint($v): int { return abs((int)$v); }
function sanitize_key($v): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_text_field($v): string { return (string)$v; }
function wp_json_encode($v) { return json_encode($v); }
function wp_generate_uuid4(): string { return '123e4567-e89b-42d3-a456-426614174000'; }
function spd_read_future_profile_state($id) {
    return ['federation_opt_in'=>0, 'professional_lifecycle'=>'active', 'lifecycle_reason'=>'',
        'lifecycle_changed_at'=>'', 'version'=>0];
}
class SPD_Helpers {
    public static function normalize_locale($v): string { return $v === 'en-US' ? 'en-US' : ''; }
    public static function sanitize_multiline($v, $limit = 1000): string { return substr(trim((string)$v), 0, $limit); }
    public static function now(): string { return '2026-11-14 12:00:00'; }
}
class SPD_Central_Profile {
    public static function extended_fields(): array { return []; }
}
class SPD_Membership_Adapter {
    public static function can_manage_founder($id): bool { return false; }
    public static function can_operate_profiles($id): bool { return (int)$id === 22; }
}
class SPD_Authorization {
    public static function mutation_guard($p, $actor) { return true; }
}
class SPD_Profile_Repository {
    public static function instance() { return new self(); }
    public function find_by_public_id($id): array {
        return ['id'=>4,'public_id'=>(string)$id,'user_id'=>11,'profile_type'=>($id === 'founder-profile' ? 'founder' : 'doctor'),'version'=>3];
    }
}
class FakeDb {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public bool $fail_event = false;
    public array $rows = [];
    public array $queries = [];
    private ?array $snapshot = null;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $format) { return null; }
    public function get_var($sql) { return null; }
    public function insert($table, $data) {
        if ($this->fail_event && $table === 'wp_spd_events') return false;
        $this->rows[] = ['table'=>$table,'data'=>$data];
        return 1;
    }
    public function update($table, $data, $where) {
        $this->rows[] = ['table'=>$table,'data'=>$data,'where'=>$where];
        return 1;
    }
    public function query($sql) {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION') $this->snapshot = $this->rows;
        if ($sql === 'ROLLBACK' && $this->snapshot !== null) $this->rows = $this->snapshot;
        if ($sql === 'COMMIT') $this->snapshot = null;
        return 1;
    }
    public function reset($fail = false) {
        $this->last_error = '';
        $this->rows = [];
        $this->queries = [];
        $this->snapshot = null;
        $this->fail_event = $fail;
    }
}
$GLOBALS['wpdb'] = new FakeDb();
require __DIR__ . '/../includes/class-spd-db.php';
require __DIR__ . '/../includes/class-spd-future-profile.php';
function assert_case($condition, string $message) {
    if (!$condition) { fwrite(STDERR, "FAIL D14: $message\n"); exit(1); }
}
$db = $GLOBALS['wpdb']; $pid = '123e4567-e89b-42d3-a456-426614174000';

// Delegated profile operators can govern lifecycle; they may not opt an
// individual's profile into federation without the individual's consent.
$db->reset();
$r = SPD_Future_Profile::set_future_state(22, $pid, ['federation_opt_in'=>1]);
assert_case(is_wp_error($r) && $r->get_error_code() === 'spd_federation_owner_consent_required',
    'an operator activated federation for an unconsenting owner');
assert_case(count($db->rows) === 0, 'denied opt-in wrote database state');

// D15: a general operator must not change the Founder's official record.
// The Founder profile is governed only by the canonical Founder authority.
$db->reset();
$r = SPD_Future_Profile::set_future_state(22, 'founder-profile', [
    'professional_lifecycle'=>'legacy', 'lifecycle_reason'=>'unauthorized general moderation'
]);
assert_case(is_wp_error($r) && $r->get_error_code() === 'spd_founder_governance_required',
    'ordinary profile operator changed Founder future-state lifecycle');
assert_case(count($db->rows) === 0, 'unauthorized Founder lifecycle write occurred');

// Any transaction with a failed outbox insert MUST roll back its state change.
$db->reset(true);
$r = SPD_Future_Profile::save_translation(11, $pid, 'en-US', 'Published title', 'Biography');
assert_case(is_wp_error($r) && count($db->rows) === 0,
    'failed translation event left a published translation without an outbox event');
assert_case(in_array('ROLLBACK', $db->queries, true),
    'translation failure did not roll back');

$db->reset(true);
$r = SPD_Future_Profile::reconfirm_field(11, $pid, 'bio');
assert_case(is_wp_error($r) && count($db->rows) === 0,
    'failed reconfirm event left an unattested eventless mutation');
assert_case(in_array('ROLLBACK', $db->queries, true),
    'reconfirmation failure did not roll back');

$db->reset(true);
$r = SPD_Future_Profile::set_future_state(11, $pid, ['federation_opt_in'=>1]);
assert_case(is_wp_error($r) && count($db->rows) === 0,
    'failed future-state event left federation enabled without an outbox event');
assert_case(in_array('ROLLBACK', $db->queries, true),
    'future-state failure did not roll back');

// Healthy paths still save both mutation and event exactly once.
$db->reset();
$r = SPD_Future_Profile::save_translation(11, $pid, 'en-US', 'Title', 'Bio');
assert_case(is_array($r) && count($db->rows) === 2 &&
    in_array('COMMIT', $db->queries, true), 'healthy translation+event not committed');

$db->reset();
$r = SPD_Future_Profile::reconfirm_field(11, $pid, 'bio');
assert_case(is_array($r) && count($db->rows) === 2 &&
    in_array('COMMIT', $db->queries, true), 'healthy confirmation+event not committed');

$db->reset();
$r = SPD_Future_Profile::set_future_state(11, $pid, ['federation_opt_in'=>1]);
assert_case(is_array($r) && !empty($r['federation_opt_in']) &&
    count($db->rows) === 2 && in_array('COMMIT', $db->queries, true),
    'owner opt-in and event not atomically committed');
echo "PASS D14/D15 owner consent, Founder governance and transactional outbox\n";
