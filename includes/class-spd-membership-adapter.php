<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned, read-only bridge to File 00 — Sabri Membership Core.
 */
final class SPD_Membership_Adapter {
	const MIN_VERSION = '1.2.3';

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, self::MIN_VERSION, '>=' )
			&& function_exists( 'smc_get_profile' )
			&& function_exists( 'smc_user_status' );
	}

	public static function health() {
		if ( ! defined( 'SMC_VERSION' ) ) {
			return array( 'status' => 'missing', 'reason' => 'file00_missing', 'version' => '' );
		}
		if ( version_compare( (string) SMC_VERSION, self::MIN_VERSION, '<' ) ) {
			return array( 'status' => 'incompatible', 'reason' => 'file00_version_too_old', 'version' => (string) SMC_VERSION );
		}
		if ( ! function_exists( 'smc_get_profile' ) || ! function_exists( 'smc_user_status' ) ) {
			return array( 'status' => 'incompatible', 'reason' => 'file00_contract_missing', 'version' => (string) SMC_VERSION );
		}
		return array( 'status' => 'available', 'reason' => '', 'version' => (string) SMC_VERSION );
	}

	public static function claims( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! self::available() || ! $user_id ) {
			return array();
		}

		$filtered = apply_filters( 'smc_profile_claims_v1', null, $user_id, SPD_CONTRACT_VERSION );
		if ( is_array( $filtered ) ) {
			return self::normalize_claims( $filtered, $user_id );
		}

		$profile = (array) smc_get_profile( $user_id );
		$profile['status'] = sanitize_key( (string) smc_user_status( $user_id ) );
		if ( function_exists( 'smc_is_founder' ) ) {
			$profile['is_founder'] = (bool) smc_is_founder( $user_id );
		}
		return self::normalize_claims( $profile, $user_id );
	}

	private static function normalize_claims( array $claims, $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		$account_type = isset( $claims['account_type'] ) ? sanitize_key( $claims['account_type'] ) : '';
		if ( '' === $account_type && isset( $claims['role_class'] ) ) {
			$account_type = sanitize_key( $claims['role_class'] );
		}
		if ( ! in_array( $account_type, array( 'sabri_doctor', 'doctor', 'member', 'patient', 'student', 'guardian', 'founder', 'administrator' ), true ) ) {
			$account_type = 'member';
		}
		$age = isset( $claims['age'] ) ? absint( $claims['age'] ) : 0;
		$is_minor = isset( $claims['is_minor'] ) ? (bool) $claims['is_minor'] : ( $age > 0 && $age < 18 );
		$guardian_verified = ! empty( $claims['guardian_verified'] ) || 'verified' === ( $claims['guardian_status'] ?? '' );

		return array(
			'user_id'             => absint( $user_id ),
			'uuid'                => sanitize_text_field( (string) ( $claims['uuid'] ?? $claims['user_uuid'] ?? '' ) ),
			'display_name'        => sanitize_text_field( (string) ( $claims['display_name'] ?? ( $user ? $user->display_name : '' ) ) ),
			'email'               => sanitize_email( (string) ( $claims['email'] ?? ( $user ? $user->user_email : '' ) ) ),
			'phone'               => sanitize_text_field( (string) ( $claims['phone'] ?? '' ) ),
			'whatsapp'            => sanitize_text_field( (string) ( $claims['whatsapp'] ?? '' ) ),
			'account_type'        => $account_type,
			'status'              => sanitize_key( (string) ( $claims['status'] ?? 'pending' ) ),
			'is_founder'          => ! empty( $claims['is_founder'] ) || 'founder' === $account_type,
			'is_minor'            => $is_minor,
			'age'                 => $age,
			'guardian_required'   => ! empty( $claims['guardian_required'] ) || $is_minor,
			'guardian_verified'   => $guardian_verified,
			'suspended'           => ! empty( $claims['suspended'] ) || 'suspended' === ( $claims['status'] ?? '' ),
			'locale'              => sanitize_text_field( (string) ( $claims['locale'] ?? get_user_locale( $user_id ) ) ),
			'country'             => sanitize_text_field( (string) ( $claims['country'] ?? '' ) ),
			'city'                => sanitize_text_field( (string) ( $claims['city'] ?? '' ) ),
			'contract_version'    => sanitize_text_field( (string) ( $claims['contract_version'] ?? 'compat-1' ) ),
			'claims_generated_at' => sanitize_text_field( (string) ( $claims['generated_at'] ?? '' ) ),
		);
	}

	public static function profile( $user_id ) {
		return self::claims( $user_id );
	}

	public static function status( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims ? $claims['status'] : 'dependency_missing';
	}

	public static function is_approved( $user_id ) {
		return in_array( self::status( $user_id ), array( 'approved', 'verified', 'active' ), true );
	}

	public static function is_doctor( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims && in_array( $claims['account_type'], array( 'sabri_doctor', 'doctor' ), true );
	}

	public static function is_founder( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['is_founder'] );
	}

	public static function is_minor( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['is_minor'] );
	}

	public static function guardian_verified( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['guardian_verified'] );
	}

	public static function founder_id() {
		$filtered = absint( apply_filters( 'spd_canonical_founder_user_id', 0 ) );
		if ( $filtered && self::is_founder( $filtered ) ) {
			return $filtered;
		}
		if ( function_exists( 'smc_get_founder_user_id' ) ) {
			$founder_id = absint( smc_get_founder_user_id() );
			if ( $founder_id && self::is_founder( $founder_id ) ) {
				return $founder_id;
			}
		}
		$users = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 2,
				'meta_key'   => '_smc_official_founder',
				'meta_value' => '1',
			)
		);
		return 1 === count( $users ) && self::is_founder( $users[0] ) ? absint( $users[0] ) : 0;
	}

	public static function can_manage_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$allowed = $user_id && $user_id === self::founder_id() && self::is_founder( $user_id );
		return (bool) apply_filters( 'spd_can_manage_founder', $allowed, $user_id );
	}

	public static function can_moderate_profiles( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( function_exists( 'smc_user_can' ) ) {
			return (bool) smc_user_can( $user_id, 'moderate_profiles' );
		}
		return $user_id === get_current_user_id() && current_user_can( 'smc_manage_membership' );
	}

	public static function display_name( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims['display_name'] ?? '';
	}

	public static function contact( $user_id, $key ) {
		$key = sanitize_key( $key );
		if ( ! in_array( $key, array( 'email', 'phone', 'whatsapp' ), true ) ) {
			return '';
		}
		$claims = self::claims( $user_id );
		return isset( $claims[ $key ] ) ? (string) $claims[ $key ] : '';
	}
}
