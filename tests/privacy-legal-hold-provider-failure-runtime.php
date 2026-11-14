<?php
/**
 * F03 V12.3 D14: uncertain legal-hold provider must never crash WordPress
 * privacy erasure, accidentally delete data, or claim completion.
 * Execute actual Future Privacy class, not a reimplemented branch.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public function __construct(public string $code, public string $message = '') {}
    public function get_error_message() { return $this->message; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function __($s, $domain = '') { return $s; }
function absint($x) { return abs((int)$x); }
function sanitize_key($x) { return strtolower((string)$x); }
function get_user_by($key, $email) { return (object) ['ID' => 11]; }
$GLOBALS['throw_on'] = 'spd_profile_legal_hold';
function apply_filters($filter, ...$args) {
    if ($filter === $GLOBALS['throw_on']) { throw new RuntimeException('synthetic legal hold provider failure'); }
    return false;
}
class SPD_Profile_Repository {
    public static function instance() { return new self(); }
    public function find_by_user_id($id, $ensure = false) {
        return ['id'=>55, 'public_id'=>'00000000-0000-4000-8000-000000000055', 'state'=>'tombstoned'];
    }
}
class SPD_Membership_Adapter { public static function is_founder($id) { return false; } }
class FakeWpdb { public string $last_error = ''; }
$GLOBALS['wpdb'] = new FakeWpdb();
require __DIR__.'/../includes/class-spd-future-privacy.php';
foreach (['spd_profile_legal_hold', 'spd_future_profile_legal_hold'] as $target) {
    $GLOBALS['throw_on'] = $target;
    try { $result = (new SPD_Future_Privacy())->erase('patient@example.org'); }
    catch (Throwable $e) {
        fwrite(STDERR, "FAIL D14 legal hold provider exception crashed eraser: ".$target."\n"); exit(1);
    }
    if (($result['done'] ?? true) !== false || empty($result['items_retained'])
        || !empty($result['items_removed'])) {
        fwrite(STDERR, "FAIL D14 uncertain legal-hold source did not retain with retry: ".$target."\n"); exit(1);
    }
}
echo "PASS File03 D14 future profile legal hold provider exceptions fail closed\n";
