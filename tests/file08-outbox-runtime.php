<?php
require __DIR__ . '/test-bootstrap.php';

final class SPD_Helpers {
	public static function now() { return gmdate( 'Y-m-d H:i:s' ); }
	public static function valid_uuid( $value ) { return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value ); }
}
final class SPD_Profile_Repository {
	private static $instance;
	public static function instance() { return self::$instance ?: self::$instance = new self(); }
	public static function cache_generation() { return absint( get_option( 'spd_profile_cache_generation', 0 ) ); }
	public function find_by_public_id( $id ) { return array(); }
	public function find_by_user_id( $id, $ensure = false ) { unset( $id, $ensure ); return array(); }
	public function purge_profile_cache( $profile ) { unset( $profile ); $GLOBALS['purged'] = true; }
}
require dirname(__DIR__) . '/includes/class-spd-cross-file-events.php';

SPD_Cross_File_Events::register();
test_assert( has_filter( 'wca_outbox_event' ), 'File 03 must subscribe to File 08 current outbox event surface.' );

$GLOBALS['options']['spd_profile_cache_generation'] = 0;
SPD_Cross_File_Events::consume_file08_outbox_event( array(
	'topic' => 'ClinicAvailabilityChanged.v1',
	'contract' => '1.0.0',
	'aggregate_ref' => 'clinic-1',
	'payload' => array( 'doctor_subject_uuid' => 'subject-opaque', 'version' => 4 ),
) );
test_assert( 1 === absint( get_option( 'spd_profile_cache_generation', 0 ) ), 'A current File 08 projection-changing fact must invalidate the cache generation when user mapping is unavailable.' );
test_assert( ! empty( get_option( 'spd_reconciliation_required', array() ) ), 'Unmapped File 08 facts must create reconciliation evidence.' );

$GLOBALS['options']['spd_profile_cache_generation'] = 7;
SPD_Cross_File_Events::consume_file08_outbox_event( array(
	'topic' => 'ClinicAvailabilityChanged.v1',
	'contract' => '0.9.0',
	'payload' => array( 'doctor_subject_uuid' => 'subject-opaque' ),
) );
test_assert( 7 === absint( get_option( 'spd_profile_cache_generation', 0 ) ), 'Incompatible File 08 contract versions must fail closed.' );

$GLOBALS['options']['spd_profile_cache_generation'] = 9;
SPD_Cross_File_Events::consume_file08_outbox_event( array(
	'topic' => 'ClinicAvailabilityChanged.v1',
	'contract' => '2.0.0',
	'payload' => array( 'doctor_subject_uuid' => 'subject-opaque' ),
) );
test_assert( 9 === absint( get_option( 'spd_profile_cache_generation', 0 ) ), 'Unsupported future File 08 major contract versions must fail closed.' );

$GLOBALS['options']['spd_profile_cache_generation'] = 11;
SPD_Cross_File_Events::consume_file08_outbox_event( array(
	'topic' => 'UnrelatedOwnerFact.v1',
	'contract' => '1.0.0',
	'payload' => array(),
) );
test_assert( 11 === absint( get_option( 'spd_profile_cache_generation', 0 ) ), 'Unknown File 08 event topics must not mutate File 03 cache state.' );

echo "File 08 outbox runtime contract checks passed.\n";
