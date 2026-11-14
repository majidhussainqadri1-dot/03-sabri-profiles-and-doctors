<?php
/**
 * File03 <-> File26 full-snapshot integrity. This is a source-level
 * regression fixture, not evidence of staging or a deployed WordPress site.
 * Root cause: unknown nonempty scopes were ignored by the File03 batch owner,
 * allowing File26 to label an unsupported partial enumeration complete.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    private string $code;
    private $data;
    public function __construct(string $code = 'unavailable', string $message = '', $data = null) { $this->code = $code; $this->data = $data; }
    public function get_error_code(): string { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($v): bool { return $v instanceof WP_Error; }
function absint($v): int { return abs((int)$v); }
function sanitize_key($v): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_text_field($v): string { return trim(strip_tags((string)$v)); }
function esc_url_raw($v): string { return (string)$v; }
class SPD_DB {
    public static bool $ready = true;
    public static function tables_exist(): bool { return self::$ready; }
    public static function table($name): string { return 'wp_spd_'.(string)$name; }
}
class SPD_Helpers {
    public static function normalize_locale($v): string { return (string)$v; }
    public static function sanitize_multiline($v, $limit = 600): string { return substr((string)$v, 0, (int)$limit); }
}
class FakeWpdb {
    public string $last_error = '';
    public array $rows = [];
    public bool $fail = false;
    public int $queries = 0;
    public function prepare($sql, ...$values): string { return (string)$sql; }
    public function get_results($sql, $format) {
        $this->queries++;
        if ($this->fail) { $this->last_error = 'synthetic read error'; return null; }
        return $this->rows;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();
const PROFILE_ID = 'a1111111-1111-4111-8111-111111111111';
$GLOBALS['profile'] = [
    'canonical_id' => PROFILE_ID,
    'canonical_url' => 'https://example.test/profile/'.PROFILE_ID.'/',
    'version' => 4,
    'profile_type' => 'member',
    'locale' => 'en-US',
    'display_name' => 'Public member',
    'fields' => ['bio' => 'Visible biography'],
    'professional' => [],
    'badge' => [],
    'generated_at' => '2026-11-01T12:00:00+00:00',
];
function spd_get_search_projection($id) {
    if (!empty($GLOBALS['projection_error'])) { return $GLOBALS['projection_error']; }
    return $id === PROFILE_ID ? $GLOBALS['profile'] : new WP_Error('not_found', '', ['status' => 404]);
}
require_once __DIR__.'/../includes/class-spd-contracts.php';
function check($test, string $message): void {
    if (!$test) { fwrite(STDERR, "FAIL File26 owner scope: {$message}\n"); exit(1); }
}
function err($value, string $code): bool {
    return $value instanceof WP_Error && $value->get_error_code() === $code;
}
SPD_DB::$ready = false;
check(err(SPD_Contracts::file26_list_batch('', 10, []), 'spd_file26_owner_schema_unavailable'), 'missing schema must never emit an empty completed snapshot');
SPD_DB::$ready = true;
// File26's actual Indexer stores the routing connector inside scope_json and
// sends it back on EVERY full-source list_batch; it is not a partial filter.
$GLOBALS['wpdb']->rows = [];
$transport = SPD_Contracts::file26_list_batch('', 10, ['connector' => 'file03-profiles']);
check(is_array($transport) && ($transport['done'] ?? null) === true, 'valid File26 connector-only routing metadata must preserve full-source snapshots');
check(err(SPD_Contracts::file26_list_batch('', 10, ['connector' => 'file07-doctors']), 'spd_file26_partial_scope_unverified'), 'wrong connector identity must be rejected');
check(err(SPD_Contracts::file26_list_batch('', 10, ['connector' => 'file03-profiles', 'mode' => 'partial']), 'spd_file26_partial_scope_unverified'), 'connector plus unknown partial filter must be rejected');
check(err(SPD_Contracts::file26_list_batch('', 10, ['object_id' => PROFILE_ID]), 'spd_file26_partial_scope_unverified'), 'unsupported object scope must fail closed');
check(err(SPD_Contracts::file26_list_batch('', 10, ['unrecognized' => 'partial']), 'spd_file26_partial_scope_unverified'), 'unknown nonempty scope must never silently become full snapshot');
check(err(SPD_Contracts::file26_list_batch('', 10, ['mode' => 'partial']), 'spd_file26_partial_scope_unverified'), 'partial mode must not become a complete source snapshot');
check(err(SPD_Contracts::file26_list_batch('1abc', 10, []), 'spd_file26_invalid_cursor'), 'malformed cursor rejected');
check(err(SPD_Contracts::file26_list_batch(-3, 10, []), 'spd_file26_invalid_cursor'), 'non-string cursor rejected');
$GLOBALS['wpdb']->fail = true;
check(err(SPD_Contracts::file26_list_batch('', 10, []), 'spd_file26_owner_batch_unreadable'), 'SQL read failure must not look like successful empty results');
$GLOBALS['wpdb']->fail = false;
$GLOBALS['wpdb']->rows = [];
$empty = SPD_Contracts::file26_list_batch('', 10, []);
check(is_array($empty) && ($empty['items'] ?? null) === [] && ($empty['done'] ?? false) === true, 'true healthy empty snapshot remains successful');
$GLOBALS['wpdb']->rows = [['id' => 6, 'public_id' => PROFILE_ID]];
$batch = SPD_Contracts::file26_list_batch('', 10, []);
check(is_array($batch) && count($batch['items'] ?? []) === 1 && ($batch['done'] ?? false) === true, 'current public owner row remains enumerable');
// A public-row projection can fail after SQL succeeds. Never promote a
// truncated index generation as complete when the owner returns 503/unknown.
$GLOBALS['projection_error'] = new WP_Error('spd_profile_store_unavailable', '', ['status' => 503]);
check(err(SPD_Contracts::file26_list_batch('', 10, []), 'spd_file26_owner_projection_unavailable'), 'a 503 while projecting an active row must fail the entire source snapshot');
$GLOBALS['projection_error'] = new WP_Error('spd_unknown_read_fault');
check(err(SPD_Contracts::file26_list_batch('', 10, []), 'spd_file26_owner_projection_unavailable'), 'unknown projection errors must fail closed');
// An intentionally unavailable/private profile is a normal exclusion.
$GLOBALS['projection_error'] = new WP_Error('spd_profile_unavailable', '', ['status' => 404]);
$hidden = SPD_Contracts::file26_list_batch('', 10, []);
check(is_array($hidden) && ($hidden['items'] ?? null) === [] && ($hidden['done'] ?? false) === true, 'explicit 404 private projection must remain excluded without a false 503');
$GLOBALS['projection_error'] = null;
$good_profile = $GLOBALS['profile'];
$GLOBALS['profile'] = ['canonical_id' => PROFILE_ID, 'version' => 4];
check(err(SPD_Contracts::file26_list_batch('', 10, []), 'spd_file26_owner_projection_unavailable'), 'malformed canonical owner projection must not be silently omitted');
$GLOBALS['profile'] = $good_profile;
$first = SPD_Contracts::file26_fetch_object(PROFILE_ID);
$GLOBALS['profile']['generated_at'] = '2026-11-13T14:00:00+00:00';
$second = SPD_Contracts::file26_fetch_object(PROFILE_ID);
check($first === $second, 'same canonical owner version must produce stable checksum-bearing document across requests');
check(!array_key_exists('freshness_at', $second), 'request-time generated_at cannot be indexed as persistent owner fact');
echo "PASS File03 File26 owner full-snapshot fail-closed scope/cursor/read and stable document\n";
