<?php
/**
 * V12.3 D17 red-first regression: recursive SPD_DB::transaction must never
 * independently commit an inner future-profile mutation while the REST-level
 * idempotency transaction is still pending. MySQL START TRANSACTION implicitly
 * commits any active transaction; a nested START is not a savepoint.
 *
 * This fake SQL driver implements that MySQL rule. It exercises the ACTUAL
 * SPD_DB::transaction function, not a duplicate local implementation.
 * Disposable fake-DB evidence; real MariaDB stage check remains required.
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
    private string $code;
    public function __construct( string $code, string $message = '' ) { $this->code = $code; }
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function __( $message, $domain = '' ) { return $message; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }

class FakeMySQLTransactionDB {
    public string $last_error = '';
    public string $prefix = 'wp_';
    public array $durable = [];
    public ?array $working = null;
    public array $savepoints = [];
    public array $statements = [];
    public function write( string $value ): void {
        if ( $this->working !== null ) { $this->working[] = $value; }
        else { $this->durable[] = $value; }
    }
    public function query( string $sql ) {
        $this->statements[] = $sql;
        if ( 'START TRANSACTION' === $sql ) {
            // MySQL/MariaDB: BEGIN or START TRANSACTION implicitly commits
            // an existing transaction; this is the behavior being tested.
            if ( $this->working !== null ) { $this->durable = $this->working; }
            $this->working = $this->durable;
            $this->savepoints = [];
            return 1;
        }
        if ( 'COMMIT' === $sql ) {
            if ( $this->working !== null ) { $this->durable = $this->working; }
            $this->working = null; $this->savepoints = [];
            return 1;
        }
        if ( 'ROLLBACK' === $sql ) {
            $this->working = null; $this->savepoints = [];
            return 1;
        }
        if ( preg_match('/^SAVEPOINT (spd_[a-z0-9_]+)$/', $sql, $m) ) {
            if ($this->working === null) { $this->last_error = 'no transaction for savepoint'; return false; }
            $this->savepoints[$m[1]] = $this->working;
            return 1;
        }
        if ( preg_match('/^ROLLBACK TO SAVEPOINT (spd_[a-z0-9_]+)$/', $sql, $m) ) {
            if (!isset($this->savepoints[$m[1]])) { $this->last_error='unknown savepoint'; return false; }
            $this->working = $this->savepoints[$m[1]];
            return 1;
        }
        if ( preg_match('/^RELEASE SAVEPOINT (spd_[a-z0-9_]+)$/', $sql, $m) ) {
            if (!isset($this->savepoints[$m[1]])) { $this->last_error='unknown savepoint'; return false; }
            unset($this->savepoints[$m[1]]);
            return 1;
        }
        throw new RuntimeException('Unrecognized SQL in D17 fixture: '.$sql);
    }
}
$GLOBALS['wpdb'] = new FakeMySQLTransactionDB();
require __DIR__.'/../includes/class-spd-db.php';
function demand($valid, string $message): void {
    if (!$valid) { fwrite(STDERR, "FAIL D17 $message\n"); exit(1); }
}
$db=$GLOBALS['wpdb'];

// Exactly the REST -> Future method shape: inner Future state+event succeeds,
// later REST idempotency persistence fails. No state must be durable.
$out=SPD_DB::transaction(static function() use ($db) {
    $db->write('outer-before');
    $inner=SPD_DB::transaction(static function() use ($db) {
        $db->write('future-translation');
        $db->write('future-event');
        return ['status'=>'submitted'];
    });
    if (is_wp_error($inner)) return $inner;
    $db->write('outer-after');
    return new WP_Error('spd_idempotency_finalize_failed');
});
demand(is_wp_error($out), 'outer idempotency error did not propagate');
demand($db->durable === [], 'inner/outer state was committed before REST idempotency failure');

// A failed inner Future step may be caught, but its modifications must not
// survive a subsequent successful parent commit.
$out=SPD_DB::transaction(static function() use ($db) {
    $db->write('parent-allowed');
    $child=SPD_DB::transaction(static function() use ($db) {
        $db->write('child-before-error');
        return new WP_Error('spd_event_insert_failed');
    });
    if (!is_wp_error($child)) return new WP_Error('expected-child-failure');
    $db->write('parent-after-recovery');
    return ['ok'=>true];
});
demand(is_array($out), 'outer transaction did not recover');
demand($db->durable === ['parent-allowed','parent-after-recovery'], 'child failed state was not rolled back to savepoint');

// Healthy nested success is one durable commit and does not duplicate rows.
$db->durable=[]; $db->statements=[];
$out=SPD_DB::transaction(static function() use ($db) {
    $db->write('outer');
    $child=SPD_DB::transaction(static function() use ($db) {
        $db->write('inner');
        return ['saved'=>true];
    });
    if(is_wp_error($child)) return $child;
    return ['ok'=>true];
});
demand(is_array($out) && $db->durable === ['outer','inner'], 'healthy nested commit failed');
demand(count(array_filter($db->statements, static fn($s)=>$s==='START TRANSACTION'))===1,
    'inner scope used another START TRANSACTION instead of a savepoint');
demand(count(array_filter($db->statements, static fn($s)=>$s==='COMMIT'))===1,
    'child scope committed separately from outer scope');
echo "PASS D17 recursive SPD_DB transactions preserve MySQL atomicity\n";
