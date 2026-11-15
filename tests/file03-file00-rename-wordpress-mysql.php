<?php
/** Real File00+File03 on disposable WordPress 7.0.1/MariaDB; no Live access. */
if ( 'yes' !== getenv( 'FILE03_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'example.test' ) ) {
	fwrite( STDERR, "REFUSED: File03 owner rename test requires ephemeral WordPress.\n" );
	exit( 2 );
}
function f03_real_name_check( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	echo "PASS: $label\n";
}
global $wpdb;
f03_real_name_check( SPD_Schema_Guard::base_ready(), 'File03 exact schema is ready before native File00 identity update' );
$profile_table = SPD_DB::table( 'profiles' );
$event_table = SPD_DB::table( 'events' );
$admin = get_user_by( 'login', 'admin' );
f03_real_name_check( $admin instanceof WP_User, 'Real disposable WordPress administrator exists' );
$id = (int) $admin->ID;
$repository = SPD_Profile_Repository::instance();
$profile = $repository->find_by_user_id( $id, false );
$now = SPD_Helpers::now();
if ( ! is_array( $profile ) || empty( $profile['id'] ) ) {
	$created = $wpdb->insert( $profile_table, array(
		'user_id' => $id,
		'public_id' => wp_generate_uuid4(),
		'slug' => 'file03-native-name-ci',
		'profile_type' => 'member',
		'state' => 'active', 'locale' => 'en-US',
		'version' => 1, 'created_at' => $now, 'updated_at' => $now,
	) );
	f03_real_name_check( 1 === (int) $created, 'Create owner profile only in disposable source DB' );
	$profile = $repository->find_by_user_id( $id, false );
} else {
	$wpdb->update( $profile_table,
		array( 'state' => 'active' ),
		array( 'id' => (int) $profile['id'] ) );
}
f03_real_name_check( is_array( $profile ) && ! empty( $profile['public_id'] ),
	'Current native File03 profile has a canonical ID' );
$initial = $wpdb->get_var( $wpdb->prepare(
	"SELECT version FROM $profile_table WHERE user_id=%d", $id ) );
$initial = (int) $initial;
f03_real_name_check( $initial >= 1, 'Native File03 owner version is a durable positive integer' );
$before_name = (string) get_userdata( $id )->display_name;
$next_name = 'File00 publicly approved CI name ' . substr( wp_generate_uuid4(), 0, 8 );
$changed = wp_update_user( array( 'ID' => $id, 'display_name' => $next_name ) );
f03_real_name_check( ! is_wp_error( $changed ), 'Real wp_update_user committed an updated native File00 display name' );
$after_version = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT version FROM $profile_table WHERE user_id=%d", $id ) );
f03_real_name_check( $after_version === $initial + 1,
	'Actual WordPress profile_update hook atomically advanced File03 search projection by exactly one version' );
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT event_name,aggregate_id,payload FROM $event_table WHERE event_name=%s AND aggregate_id=%s ORDER BY id DESC LIMIT 10",
	'PublicProfileUpdated.v1', (string) $profile['public_id']
), ARRAY_A );
$found = false;
foreach ( (array) $rows as $row ) {
	$data = json_decode( (string) $row['payload'], true );
	if ( is_array( $data ) && (int) ( $data['version'] ?? 0 ) === $after_version &&
		( $data['changed_fields'] ?? array() ) === array( 'file00_public_display_name' ) &&
		( $data['canonical_owner'] ?? '' ) === 'file03' && ( $data['identity_owner'] ?? '' ) === 'file00' ) {
		$found = true;
	}
}
f03_real_name_check( $found, 'Owner transition and monotonic event are in real MariaDB File03 outbox' );
$repeat = wp_update_user( array( 'ID' => $id, 'display_name' => $next_name ) );
f03_real_name_check( ! is_wp_error( $repeat ), 'Idempotent second native WP user update succeeds' );
f03_real_name_check( $after_version === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT version FROM $profile_table WHERE user_id=%d", $id ) ),
	'Same public name does not generate a duplicate File03 version' );
f03_real_name_check( ! function_exists( 'sabri_file26_upsert_document' ),
	'No File26 plugin is installed here, so this cannot falsely certify delivery or search reindex' );
echo "PASS: actual File00 -> WordPress name update -> File03 version+outbox, not deployed File26.\n";
