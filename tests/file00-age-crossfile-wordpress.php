<?php
/**
 * V12.3 disposable real-WordPress File00/File03 age contract acceptance.
 * This file must only run inside CI after both actual plugins activate.
 * It never reads source-owner private DOB from File03 application code.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'SPD_Membership_Adapter' ) || ! class_exists( 'SMC_Contracts' ) ) {
	fwrite( STDERR, "FAIL: required File00/File03 runtime unavailable\n" );
	exit( 2 );
}
global $wpdb;
$fail = static function( $condition, $why ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL File00/File03 age: {$why}\n" ); exit( 1 ); }
	echo "PASS File00/File03 age: {$why}\n";
};
$fail( (bool) has_filter( 'smc_profile_age_guardian_claim_v1' ), 'independent owner provider registered' );
$fixture = static function( $kind, $years = null ) use ( $wpdb, $fail ) {
	$nonce = strtolower( wp_generate_password( 9, false, false ) );
	$user_id = wp_insert_user( array(
		'user_login' => 'f03age_' . $kind . '_' . $nonce,
		'user_email' => 'f03age-' . $kind . '-' . $nonce . '@example.test',
		'user_pass' => wp_generate_password( 25, true, true ),
		'role' => 'subscriber',
	) );
	$fail( ! is_wp_error( $user_id ) && (bool) smc_application( $user_id ), $kind . ' owner registration' );
	if ( null !== $years ) {
		$dob = gmdate( 'Y-m-d', strtotime( '-' . (int) $years . ' years' ) );
		$cipher = SMC_Security::encrypt( $dob, 'date-of-birth', array( 'user_id' => (int) $user_id ) );
		$fail( ! is_wp_error( $cipher ), $kind . ' private DOB encrypted by owner' );
		$changed = $wpdb->update(
			$wpdb->prefix . 'smc_applications',
			array( 'date_of_birth_enc' => $cipher, 'guardian_required' => $years < 18 ? 1 : 0,
				'updated_at' => current_time( 'mysql', true ) ),
			array( 'user_id' => (int) $user_id )
		);
		$fail( 1 === $changed, $kind . ' owner fixture persisted' );
	}
	return (int) $user_id;
};
$adult = $fixture( 'adult', 35 );
$minor = $fixture( 'minor', 16 );
$unknown = $fixture( 'unknown' );
foreach ( array( 'adult' => $adult, 'minor' => $minor, 'unknown' => $unknown ) as $kind => $id ) {
	$claim = apply_filters( 'smc_profile_age_guardian_claim_v1', null, $id, SPD_CONTRACT_VERSION );
	$fail( is_array( $claim )
		&& (int) ( $claim['user_id'] ?? 0 ) === $id
		&& 'file00' === ( $claim['owner'] ?? '' )
		&& 'public_profile_age_assurance' === ( $claim['purpose'] ?? '' )
		&& ! array_key_exists( 'age', $claim )
		&& ! array_key_exists( 'date_of_birth', $claim )
		&& ! array_key_exists( 'dob', $claim ),
		$kind . ' subject and no private age/DOB leak' );
	$expected_known = 'unknown' !== $kind;
	$expected_minor = 'adult' !== $kind;
	$fail( $expected_known === ( $claim['known'] ?? null )
		&& $expected_minor === ( $claim['is_minor'] ?? null )
		&& $expected_minor === ( $claim['guardian_required'] ?? null ),
		$kind . ' owner age-band policy' );
	$fail( ( 'adult' === $kind ) === SPD_Membership_Adapter::public_profile_age_eligible( $id ),
		$kind . ' File03 independent public-profile decision' );
	$fail( ( 'adult' !== $kind ) === SPD_Membership_Adapter::is_minor( $id ),
		$kind . ' File03 inline current-age decision' );
}
update_user_meta( $adult, '_smc_privacy_erasure_lock', array( 'locked_at' => gmdate( 'c' ) ) );
$fail( ! SPD_Membership_Adapter::public_profile_age_eligible( $adult ), 'owner erasure lock revokes adult eligibility' );
$fail( ! SPD_Membership_Adapter::public_profile_age_eligible( 999999999 ), 'missing WordPress user is denied' );
echo "PASS real WordPress File00/File03 V12.3 age-contract acceptance\n";
