<?php
defined( 'ABSPATH' ) || exit;

/** Read-only bridge to File 00 and owner-supplied profile DTO contracts. */
final class SPD_Membership_Adapter {
	public static function available() {
		return defined( 'SMC_VERSION' ) && function_exists( 'smc_get_profile' ) && function_exists( 'smc_user_status' );
	}

	public static function profile( $user_id ) {
		return self::available() ? (array) smc_get_profile( absint( $user_id ) ) : array();
	}

	public static function account_type( $user_id ) {
		$profile = self::profile( $user_id );
		return isset( $profile['account_type'] ) ? sanitize_key( $profile['account_type'] ) : '';
	}

	public static function is_doctor( $user_id ) { return 'sabri_doctor' === self::account_type( $user_id ); }
	public static function status( $user_id ) { return self::available() ? sanitize_key( smc_user_status( absint( $user_id ) ) ) : 'dependency_missing'; }
	public static function is_membership_approved( $user_id ) { return in_array( self::status( $user_id ), array( 'approved', 'verified' ), true ); }

	public static function is_doctor_identity_approved( $user_id ) {
		$assertion = apply_filters( 'spd_doctor_identity_assertion', null, absint( $user_id ), SPD_CONTRACT_VERSION );
		if ( is_array( $assertion ) && isset( $assertion['approved'], $assertion['version'] ) ) {
			return (bool) $assertion['approved'];
		}
		return self::is_doctor( $user_id ) && self::is_membership_approved( $user_id ) && (bool) get_user_meta( absint( $user_id ), '_smc_doctor_verified', true );
	}

	public static function is_founder( $user_id ) {
		return self::available() && function_exists( 'smc_is_founder' ) && smc_is_founder( absint( $user_id ) );
	}

	public static function founder_id() {
		$filtered = absint( apply_filters( 'spd_canonical_founder_user_id', 0 ) );
		if ( $filtered && self::is_founder( $filtered ) ) { return $filtered; }
		$users = get_users( array( 'fields' => 'ids', 'number' => 2, 'meta_key' => '_smc_official_founder', 'meta_value' => '1' ) );
		return 1 === count( $users ) && self::is_founder( $users[0] ) ? absint( $users[0] ) : 0;
	}

	public static function can_manage_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$founder_id = self::founder_id();
		return (bool) apply_filters( 'spd_can_manage_founder', $founder_id && $user_id === $founder_id && self::is_founder( $user_id ), $user_id, $founder_id );
	}

	public static function owner_dto( $user_id ) {
		$dto = apply_filters( 'spd_profile_owner_dto', array(), absint( $user_id ), SPD_CONTRACT_VERSION );
		return is_array( $dto ) ? $dto : array();
	}

	public static function field( $user_id, $key, $default = '' ) {
		$user_id = absint( $user_id );
		$key = sanitize_key( $key );
		$user = get_userdata( $user_id );
		if ( 'display_name' === $key ) { return $user ? $user->display_name : $default; }
		if ( 'account_type' === $key ) { return self::is_doctor( $user_id ) ? 'doctor' : preg_replace( '/^sabri_/', '', self::account_type( $user_id ) ); }

		$dto = self::owner_dto( $user_id );
		if ( array_key_exists( $key, $dto ) && '' !== (string) $dto[ $key ] ) { return $dto[ $key ]; }

		$profile = self::profile( $user_id );
		$profile_map = array( 'country'=>'country', 'city'=>'city', 'phone'=>'phone', 'bio'=>'bio', 'profile_visibility'=>'profile_visibility' );
		if ( isset( $profile_map[ $key ], $profile[ $profile_map[ $key ] ] ) && '' !== (string) $profile[ $profile_map[ $key ] ] ) { return $profile[ $profile_map[ $key ] ]; }

		$legacy = get_user_meta( $user_id, '_spd_' . $key, true );
		return ( defined( 'SPD_ALLOW_LEGACY_READS' ) && SPD_ALLOW_LEGACY_READS && '' !== (string) $legacy ) ? $legacy : $default;
	}

	public static function public_visibility( $user_id ) {
		if ( SPD_Profile_Policy::is_minor( $user_id ) ) { return 'private'; }
		$stored = sanitize_key( (string) get_user_meta( absint( $user_id ), '_spd_profile_visibility', true ) );
		if ( in_array( $stored, array( 'private', 'members', 'public' ), true ) ) { return $stored; }
		$canonical = sanitize_key( (string) self::field( $user_id, 'profile_visibility', 'members' ) );
		return in_array( $canonical, array( 'private', 'members', 'public' ), true ) ? $canonical : 'members';
	}
}
