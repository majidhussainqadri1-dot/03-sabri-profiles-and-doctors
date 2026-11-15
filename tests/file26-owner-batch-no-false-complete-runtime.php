<?php
/**
 * Exact File03 native list_batch fail-closed source acceptance.
 * A missing canonical profile schema must NEVER emit done=true; that would
 * allow File26 to mistake a failed native read for a complete empty shadow.
 * Synthetic WP DB only; actual cross-plugin MariaDB acceptance separate.
 */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
class SPD_DB {
	public static $available = false;
	public static function tables_exist() { return self::$available; }
	public static function table( $name ) { return 'wp_spd_' . $name; }
}
class F03OwnerFakeDB {
	public $last_error = '';
	public $rows = array();
	public $broken = false;
	public function prepare( $sql ) { return $sql; }
	public function get_results( $sql, $mode = null ) {
		if ( $this->broken ) { $this->last_error = 'SOURCE_UNAVAILABLE'; return false; }
		return $this->rows;
	}
}
define( 'ARRAY_A', 1 );
$wpdb = new F03OwnerFakeDB();
require_once dirname( __DIR__ ) . '/includes/class-spd-contracts.php';
function f03_check( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: " . $label . "\n" ); exit( 1 ); }
	echo "PASS: " . $label . "\n";
}
$read = static function ( $cursor = '', $limit = 10, $scope = array( 'connector' => 'file03-profiles' ) ) {
	return SPD_Contracts::file26_list_batch( $cursor, $limit, $scope );
};
$denied = $read();
f03_check( is_wp_error( $denied ) && 'spd_file26_owner_schema_unavailable' === $denied->get_error_code(),
	'missing File03 canonical tables cannot falsely certify a complete empty index' );
SPD_DB::$available = true;
foreach ( array( 'bad-cursor', '01', '9999999999999999999', '1e2', '0', -1 ) as $cursor ) {
	$denied = $read( $cursor );
	f03_check( is_wp_error( $denied ) && 'spd_file26_owner_batch_cursor' === $denied->get_error_code(),
		'malformed/overflow native cursor cannot hide skipped owner rows' );
}
foreach ( array( '10', 0, -1, 101, 2.5 ) as $limit ) {
	$denied = $read( '', $limit );
	f03_check( is_wp_error( $denied ) && 'spd_file26_owner_batch_limit' === $denied->get_error_code(),
		'malformed or excessive native batch limit rejected' );
}
foreach ( array(
	array( 'object_id' => 'fake' ),
	array( 'connector' => 'other-owner' ),
	array( 'connector' => 'file03-profiles', 'object_id' => 'fake' )
) as $scope ) {
	$denied = $read( '', 10, $scope );
	f03_check( is_wp_error( $denied ) &&
		'spd_file26_owner_partial_scope_unavailable' === $denied->get_error_code(),
		'unsupported partial/foreign source scope cannot be presented as an owner snapshot' );
}
$wpdb->broken = true;
$denied = $read();
f03_check( is_wp_error( $denied ) && 'spd_file26_owner_batch_read_failed' === $denied->get_error_code(),
	'owner SQL query failure is a typed error, not successful done' );
$wpdb->broken = false;
$wpdb->last_error = '';
$complete = $read();
f03_check( is_array( $complete ) && $complete['done'] === true && $complete['items'] === array(),
	'only a genuinely empty readable source can attest done=true' );
echo "PASS: File03 canonical File26 batch completion integrity runtime\n";
