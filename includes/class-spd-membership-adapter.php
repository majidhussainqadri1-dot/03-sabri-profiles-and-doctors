<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only bridge to File 00 — Sabri Membership Core.
 */
final class SPD_Membership_Adapter {
	public static function available() {
		return defined( 'SMC_VERSION' ) && function_exists( 'smc_get_profile' ) && function_exists( 'smc_user_status' );
	}

	public static function profile( $user_id ) {
		return self::available() ? (array) smc_get_profile( absint( $user_id ) ) : array();
	}

	public static function account_type( $user_id ) {
		$profile = self::profile( $user_id );
		$type    = isset( $profile['account_type'] ) ? sanitize_key( $profile['account_type'] ) : '';
		if ( ! $type ) {
			$type = sanitize_key( (string) get_user_meta( absint( $user_id ), '_smc_requested_role', true ) );
		}
		return $type;
	}

	public static function is_doctor( $user_id ) {
		return 'sabri_doctor' === self::account_type( $user_id );
	}

	public static function status( $user_id ) {
		return self::available() ? sanitize_key( smc_user_status( absint( $user_id ) ) ) : 'dependency_missing';
	}

	public static function is_membership_approved( $user_id ) {
		return in_array( self::status( $user_id ), array( 'approved', 'verified' ), true );
	}

	public static function is_doctor_identity_approved( $user_id ) {
		return self::is_doctor( $user_id )
			&& self::is_membership_approved( $user_id )
			&& (bool) get_user_meta( absint( $user_id ), '_smc_doctor_verified', true );
	}

	public static function is_founder( $user_id ) {
		return self::available() && function_exists( 'smc_is_founder' ) && smc_is_founder( absint( $user_id ) );
	}

	public static function founder_id() {
		$filtered = absint( apply_filters( 'spd_canonical_founder_user_id', 0 ) );
		if ( $filtered && self::is_founder( $filtered ) ) {
			return $filtered;
		}

		$stored = absint( get_option( 'spd_founder_user_id', 0 ) );
		if ( $stored && self::is_founder( $stored ) ) {
			return $stored;
		}

		$users = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 2,
				'meta_key'   => '_smc_official_founder',
				'meta_value' => '1',
			)
		);
		if ( 1 === count( $users ) && self::is_founder( $users[0] ) ) {
			return absint( $users[0] );
		}
		return 0;
	}

	public static function can_manage_founder( $user_id = 0 ) {
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		$founder_id = self::founder_id();
		$allowed    = $founder_id && $user_id === $founder_id && self::is_founder( $user_id );
		return (bool) apply_filters( 'spd_can_manage_founder', $allowed, $user_id, $founder_id );
	}

	public static function field( $user_id, $key, $default = '' ) {
		$user_id = absint( $user_id );
		$key     = sanitize_key( $key );
		$profile = self::profile( $user_id );
		$user    = get_userdata( $user_id );

		if ( 'display_name' === $key ) {
			return $user ? $user->display_name : $default;
		}
		if ( 'account_type' === $key ) {
			return self::is_doctor( $user_id ) ? 'doctor' : preg_replace( '/^sabri_/', '', self::account_type( $user_id ) );
		}

		$profile_map = array(
			'country'            => 'country',
			'city'               => 'city',
			'phone'              => 'phone',
			'bio'                => 'bio',
			'profile_visibility' => 'profile_visibility',
		);
		if ( isset( $profile_map[ $key ] ) && isset( $profile[ $profile_map[ $key ] ] ) && '' !== (string) $profile[ $profile_map[ $key ] ] ) {
			return $profile[ $profile_map[ $key ] ];
		}

		global $wpdb;
		$credential_map = array(
			'qualification'      => 'qualification',
			'licence_number'     => 'license_number',
			'licensing_authority'=> 'council',
			'experience_years'   => 'experience_years',
			'specialty'          => 'specialization',
			'languages'          => 'languages',
			'studied_books'      => 'books_studied',
			'consultation_modes' => 'consultation_mode',
		);
		if ( isset( $credential_map[ $key ] ) ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT {$credential_map[$key]} FROM {$wpdb->prefix}smc_professional_credentials WHERE user_id = %d LIMIT 1",
					$user_id
				)
			);
			if ( null !== $value && '' !== (string) $value ) {
				return $value;
			}
		}

		$clinic_map = array(
			'clinic'   => 'name',
			'whatsapp' => 'whatsapp',
		);
		if ( isset( $clinic_map[ $key ] ) ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT {$clinic_map[$key]} FROM {$wpdb->prefix}smc_clinics WHERE owner_user_id = %d ORDER BY id ASC LIMIT 1",
					$user_id
				)
			);
			if ( null !== $value && '' !== (string) $value ) {
				return $value;
			}
		}

		// Temporary read-only compatibility for File 09 v1.0.0 data. File 03 never writes these values.
		$legacy = get_user_meta( $user_id, '_spd_' . $key, true );
		return '' !== (string) $legacy ? $legacy : $default;
	}

	public static function public_visibility( $user_id ) {
		$stored = sanitize_key( (string) get_user_meta( absint( $user_id ), '_spd_profile_visibility', true ) );
		if ( in_array( $stored, array( 'private', 'members', 'public' ), true ) ) {
			return $stored;
		}
		$canonical = sanitize_key( (string) self::field( $user_id, 'profile_visibility', 'members' ) );
		return in_array( $canonical, array( 'private', 'members', 'public' ), true ) ? $canonical : 'members';
	}
}
