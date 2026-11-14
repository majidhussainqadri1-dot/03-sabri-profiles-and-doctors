<?php
/**
 * D17 real MariaDB 11.4 / WordPress regression, invoked with wp eval-file.
 * Runs on the disposable GitHub integration installation ONLY, after File03
 * activation. It uses temporary wp_options rows and deletes them afterward.
 *
 * The test exercises the genuine SPD_DB::transaction and wpdb/InnoDB path.
 * It is NOT an acceptance receipt for the user's Hostinger staging/live site.
 */
defined('ABSPATH') || exit;

global $wpdb;
if (!class_exists('SPD_DB')) {
    throw new RuntimeException('D17 File03 transaction class is not loaded.');
}
$marker = 'f03_d17_ci_' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 16) . '_';
$table = $wpdb->options;
$names = [];
$insert = static function(string $label) use ($wpdb, $table, $marker, &$names): void {
    $name = $marker . $label;
    $names[] = $name;
    if (1 !== $wpdb->insert($table, [
        'option_name' => $name, 'option_value' => 'D17 temporary CI record',
        'autoload' => 'off',
    ])) {
        throw new RuntimeException('D17 could not insert disposable option: '.$label);
    }
};
$count = static function() use ($wpdb, $table, $marker): int {
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE option_name LIKE %s",
        $wpdb->esc_like($marker).'%'
    );
    $value = $wpdb->get_var($query);
    if ($value === null || !is_numeric($value) || $wpdb->last_error) {
        throw new RuntimeException('D17 DB count check uncertain');
    }
    return (int)$value;
};
$clear = static function() use ($wpdb, $table, $marker): void {
    $query = $wpdb->prepare(
        "DELETE FROM {$table} WHERE option_name LIKE %s",
        $wpdb->esc_like($marker).'%'
    );
    if (false === $wpdb->query($query)) {
        throw new RuntimeException('D17 failed to remove disposable options');
    }
};

try {
    $failed = SPD_DB::transaction(static function() use ($insert) {
        $insert('outer-before');
        $nested = SPD_DB::transaction(static function() use ($insert) {
            $insert('future-profile');
            $insert('outbox');
            return ['done' => true];
        });
        if (is_wp_error($nested)) return $nested;
        $insert('outer-after');
        return new WP_Error('spd_d17_failed_idempotency');
    });
    if (!is_wp_error($failed) || 0 !== $count()) {
        throw new RuntimeException('D17 outer failure did not roll back committed inner Future state');
    }

    $recovered = SPD_DB::transaction(static function() use ($insert) {
        $insert('surviving-parent');
        $nested = SPD_DB::transaction(static function() use ($insert) {
            $insert('should-roll-back');
            return new WP_Error('spd_d17_failed_event');
        });
        if (!is_wp_error($nested)) {
            return new WP_Error('spd_d17_expected_nested_error');
        }
        $insert('parent-after');
        return ['recovered' => true];
    });
    if (is_wp_error($recovered) || 2 !== $count()) {
        throw new RuntimeException('D17 inner savepoint rollback did not preserve parent-only writes');
    }
    $clear();

    $success = SPD_DB::transaction(static function() use ($insert) {
        $insert('healthy-parent');
        $nested = SPD_DB::transaction(static function() use ($insert) {
            $insert('healthy-child');
            return ['ok' => true];
        });
        return is_wp_error($nested) ? $nested : ['ok'=>true];
    });
    if (is_wp_error($success) || 2 !== $count()) {
        throw new RuntimeException('D17 nested healthy transaction did not commit atomically');
    }
    echo "PASS D17 real MariaDB nested transaction rollback, partial savepoint recovery and healthy commit\n";
} finally {
    $clear();
}
