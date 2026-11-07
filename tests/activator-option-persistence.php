<?php
/**
 * F03-NFR-007: permanent fail-first regression for WordPress option storage.
 * WordPress treats an absent option's old value as false; update_option()
 * can skip inserting another false. On DB reads, bool/int scalar options may
 * be represented as empty, "1" or decimal strings. Missing writes must not
 * receive a false-success receipt.
 *
 * Isolated WordPress-semantics mock. Separate real WP7.0.1/MariaDB CI exists.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['option_store'] = [];
$GLOBALS['force_option_write_failure'] = false;
function sanitize_key($x) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$x)); }
function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['option_store']) ? $GLOBALS['option_store'][$key] : $default;
}
function stored_scalar($value) {
    if ($value === true) return '1';
    if ($value === false) return '';
    return is_int($value) || is_float($value) ? (string)$value : $value;
}
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if ($GLOBALS['force_option_write_failure'] || array_key_exists($key, $GLOBALS['option_store'])) return false;
    $GLOBALS['option_store'][$key] = stored_scalar($value);
    return true;
}
function update_option($key, $value, $autoload = false) {
    if ($GLOBALS['force_option_write_failure']) return false;
    if (get_option($key) === $value) return false;
    $GLOBALS['option_store'][$key] = stored_scalar($value);
    return true;
}
require_once __DIR__ . '/../includes/class-spd-activator.php';
$method = new ReflectionMethod('SPD_Activator', 'persist_exact_option');
$method->setAccessible(true);
function verify_option(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
verify_option($method->invoke(null, 'spd_safe_mode', false) === true, 'missing false-valued safe mode option not created');
verify_option(array_key_exists('spd_safe_mode', $GLOBALS['option_store']), 'safe mode still absent');
verify_option(get_option('spd_safe_mode', null) === '', 'safe-mode false readback was not modeled');
verify_option($method->invoke(null, 'spd_migration_cursor', 0) === true, 'integer zero cursor not safely persisted');
verify_option(get_option('spd_migration_cursor', null) === '0', 'numeric cursor not in DB scalar form');
verify_option($method->invoke(null, 'spd_version', '1.2.0-rc18') === true, 'version string marker not persisted');
verify_option($method->invoke(null, 'spd_version', '1.2.0-rc18') === true, 'idempotent write incorrectly failed');
$GLOBALS['option_store']['spd_safe_mode'] = '1';
verify_option($method->invoke(null, 'spd_safe_mode', false) === true, 'existing safe mode could not be set to false');
$GLOBALS['force_option_write_failure'] = true;
verify_option($method->invoke(null, 'spd_never_stored', false) === false, 'database write failure incorrectly reported success');
verify_option(!array_key_exists('spd_never_stored', $GLOBALS['option_store']), 'fixture unexpectedly persisted failed option');
echo "PASS File03 exact option persistence false/zero/idempotency/failure isolation\n";
