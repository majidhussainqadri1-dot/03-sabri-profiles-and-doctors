<?php
/** Source-owner display_name change must atomically advance File03 search revision. */
define( 'ABSPATH', __DIR__ . '/' );
function absint( $x ) { return abs( (int) $x ); }
function sanitize_key( $x ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $x ) ); }
function sanitize_text_field( $x ) { return trim( strip_tags( (string) $x ) ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_userdata( $id ) { return isset( $GLOBALS['native_name'] ) && 12 === (int) $id ? (object) array( 'display_name' => $GLOBALS['native_name'] ) : null; }
function update_option( $k, $v, $autoload = false ) { $GLOBALS['options'][$k] = $v; return true; }
function do_action( $name, $payload = null ) { $GLOBALS['hooks'][] = $name; }
class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
class SPD_Helpers {
	public static function now() { return '2026-11-15 13:00:00'; }
}
class SPD_Schema_Guard {
	public static $ready = true;
	public static function base_ready() { return self::$ready; }
}
class SPD_DB {
	public static function table( $name ) { return 'wp_spd_' . $name; }
	public static function transaction( callable $callback ) {
		$prior = $GLOBALS['profile_version'];
		$result = $callback();
		if ( is_wp_error( $result ) ) { $GLOBALS['profile_version'] = $prior; }
		return $result;
	}
}
class F03NameWpDB {
	public $last_error = '';
	public function prepare( $sql ) { return $sql; }
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'UPDATE wp_spd_profiles SET version=version+1' ) ) {
			if ( ! empty( $GLOBALS['force_cas_failure'] ) ) { return 0; }
			$GLOBALS['profile_version']++;
			return 1;
		}
		throw new RuntimeException( 'Unexpected SQL: ' . $sql );
	}
}
class SPD_Profile_Repository {
	public static $instance;
	public $events = array();
	public $purges = array();
	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	public function find_by_user_id( $id, $create = false ) {
		if ( empty( $GLOBALS['profile_exists'] ) ) { return array(); }
		return array(
			'id' => 102, 'user_id' => 12,
			'public_id' => 'f1111111-1111-4111-8111-111111111111',
			'version' => $GLOBALS['profile_version'],
			'state' => 'active',
		);
	}
	public function event( $type, $aggregate_type, $id, array $payload ) {
		if ( ! empty( $GLOBALS['force_event_failure'] ) ) {
			return new WP_Error( 'spd_event_persist_failed' );
		}
		$this->events[] = array( $type, $aggregate_type, $id, $payload );
		return 'e1111111-1111-4111-8111-111111111111';
	}
	public function purge_profile_cache( array $profile ) {
		$this->purges[] = $profile;
		$GLOBALS['hooks'][] = 'sabri_file26_profile_changed';
	}
}
function f03_name_check( $test, $label ) {
	if ( ! $test ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	echo "PASS: $label\n";
}
$wpdb = new F03NameWpDB();
$GLOBALS['profile_exists'] = true;
$GLOBALS['profile_version'] = 14;
$GLOBALS['native_name'] = 'Old Public Name';
$GLOBALS['options'] = array();
$GLOBALS['hooks'] = array();
require_once dirname( __DIR__ ) . '/includes/class-spd-plugin.php';
$plugin = new SPD_Plugin();
$owner = SPD_Profile_Repository::instance();
$old = (object) array( 'display_name' => 'Old Public Name' );
$plugin->file26_versioned_file00_name_change( 12, $old );
f03_name_check( 14 === $GLOBALS['profile_version'] && count( $owner->events ) === 0,
	'No File00 name change never invents a File03 version or search event' );
$GLOBALS['native_name'] = 'New Approved Name';
$plugin->file26_versioned_file00_name_change( 12, $old );
f03_name_check( 15 === $GLOBALS['profile_version'] && 1 === count( $owner->events ),
	'Current canonical File00 name change advances exactly one File03 version and owner outbox event' );
$event = $owner->events[0];
f03_name_check( $event[0] === 'PublicProfileUpdated.v1' &&
	$event[3]['previous_version'] === 14 && $event[3]['version'] === 15 &&
	$event[3]['changed_fields'] === array( 'file00_public_display_name' ),
	'Current owner event identifies external File00 identity revision without claiming ownership of File00 data' );
f03_name_check( 1 === count( $owner->purges ) &&
	$owner->purges[0]['version'] === 15,
	'Only after committed monotonic revision does File03 invalidate search cache with new version' );
$plugin->file26_versioned_file00_name_change( 12, (object) array( 'display_name' => 'New Approved Name' ) );
f03_name_check( 15 === $GLOBALS['profile_version'] && count( $owner->events ) === 1,
	'Repeating same approved public name cannot create duplicate versions/events' );
$GLOBALS['native_name'] = 'Newer Name, CAS denied';
$GLOBALS['force_cas_failure'] = true;
$plugin->file26_versioned_file00_name_change( 12, (object) array( 'display_name' => 'New Approved Name' ) );
f03_name_check( 15 === $GLOBALS['profile_version'] && count( $owner->events ) === 1 &&
	isset( $GLOBALS['options']['spd_file26_identity_sync_error'] ),
	'Concurrent name revision failure is observable and refuses unsigned event' );
$GLOBALS['force_cas_failure'] = false;
$GLOBALS['native_name'] = 'Newest Name, outbox persist fails';
$GLOBALS['force_event_failure'] = true;
$plugin->file26_versioned_file00_name_change( 12, (object) array( 'display_name' => 'New Approved Name' ) );
f03_name_check( 15 === $GLOBALS['profile_version'] && count( $owner->purges ) === 1,
	'Atomic outbox persistence failure rolls back the derived profile revision' );
$GLOBALS['force_event_failure'] = false;
SPD_Schema_Guard::$ready = false;
$plugin->file26_versioned_file00_name_change( 12, (object) array( 'display_name' => 'New Approved Name' ) );
f03_name_check( 15 === $GLOBALS['profile_version'] &&
	'schema_unverified' === $GLOBALS['options']['spd_file26_identity_sync_error']['code'],
	'Unverified schema fails closed and raises an explicit reconcile alarm' );
SPD_Schema_Guard::$ready = true;
$GLOBALS['profile_exists'] = false;
$plugin->file26_versioned_file00_name_change( 12, (object) array( 'display_name' => 'New Approved Name' ) );
f03_name_check( 15 === $GLOBALS['profile_version'],
	'A user without eligible File03 profile cannot manufacture a public search owner revision' );
echo "PASS: File03 native File00 display-name monotonic revision and transactional outbox source contract; NOT a live test.\n";
