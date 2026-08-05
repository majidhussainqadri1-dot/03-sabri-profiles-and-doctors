<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned, fail-closed bridge to File 00 — Sabri Membership Core.
 *
 * File 03 consumes only File 00's published assertion functions. It never reads
 * File 00 tables, private identity evidence or legacy requested-role metadata.
 */
final class SPD_Membership_Adapter {
	const MIN_VERSION          = '1.2.11';
	const MIN_CONTRACT_VERSION = '1.2.0';

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& defined( 'SMC_CONTRACT_VERSION' )
			&& version_compare( (string) SMC_VERSION, self::MIN_VERSION, '>=' )
			&& version_compare( (string) SMC_CONTRACT_VERSION, self::MIN_CONTRACT_VERSION, '>=' )
			&& function_exists( 'smc_membership_assertions' )
			&& function_exists( 'smc_user_status' )
			&& function_exists( 'smc_founder_user_id' )
			&& function_exists( 'smc_is_founder' );
	}

	public static function health() {
		if ( ! defined( 'SMC_VERSION' ) || ! defined( 'SMC_CONTRACT_VERSION' ) ) {
			return array( 'status' => 'missing', 'reason' => 'file00_identity_or_contract_missing', 'version' => '', 'contract_version' => '' );
		}
		if ( version_compare( (string) SMC_VERSION, self::MIN_VERSION, '<' ) ) {
			return array( 'status' => 'incompatible', 'reason' => 'file00_version_too_old', 'version' => (string) SMC_VERSION, 'contract_version' => (string) SMC_CONTRACT_VERSION );
		}
		if ( version_compare( (string) SMC_CONTRACT_VERSION, self::MIN_CONTRACT_VERSION, '<' ) ) {
			return array( 'status' => 'incompatible', 'reason' => 'file00_contract_too_old', 'version' => (string) SMC_VERSION, 'contract_version' => (string) SMC_CONTRACT_VERSION );
		}
		$required = array( 'smc_membership_assertions', 'smc_user_status', 'smc_founder_user_id', 'smc_is_founder' );
		foreach ( $required as $function ) {
			if ( ! function_exists( $function ) ) {
				return array( 'status' => 'incompatible', 'reason' => 'file00_contract_functions_missing', 'version' => (string) SMC_VERSION, 'contract_version' => (string) SMC_CONTRACT_VERSION );
			}
		}
		return array( 'status' => 'available', 'reason' => '', 'version' => (string) SMC_VERSION, 'contract_version' => (string) SMC_CONTRACT_VERSION );
	}

	/**
	 * Return current File 00 assertions normalized for File 03.
	 *
	 * The function is called for every protected decision; no persistent File 03
	 * role/eligibility cache is authoritative.
	 */
	public static function claims( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! self::available() || ! $user_id ) {
			return array();
		}

		$raw = smc_membership_assertions( $user_id );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( absint( $raw['user_id'] ?? 0 ) !== $user_id ) {
			return array();
		}
		$contract = sanitize_text_field( (string) ( $raw['contract_version'] ?? '' ) );
		if ( ! $contract || version_compare( $contract, self::MIN_CONTRACT_VERSION, '<' ) ) {
			return array();
		}

		return self::normalize_claims( $raw, $user_id, $contract );
	}

	private static function normalize_claims( array $raw, $user_id, $contract ) {
		$user = get_userdata( $user_id );
		$approved_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $raw['approved_membership_types'] ?? array() ) ) ) ) );
		$membership_type = sanitize_key( (string) ( $raw['membership_type'] ?? '' ) );
		$account_class = sanitize_key( (string) ( $raw['account_class'] ?? '' ) );
		$is_founder = function_exists( 'smc_is_founder' ) && smc_is_founder( $user_id );

		if ( $is_founder ) {
			$account_type = 'founder';
		} elseif ( 'administrator' === $account_class ) {
			$account_type = 'administrator';
		} elseif ( in_array( 'doctor', $approved_types, true ) ) {
			$account_type = 'doctor';
		} elseif ( in_array( $membership_type, array( 'member', 'patient', 'student', 'guardian', 'teacher', 'researcher', 'pharmacy', 'clinic', 'publisher' ), true ) ) {
			$account_type = $membership_type;
		} else {
			$account_type = 'member';
		}

		$status = sanitize_key( (string) ( $raw['status'] ?? smc_user_status( $user_id ) ) );
		$hard_blocked = ! empty( $raw['suspended'] ) || in_array( $status, array( 'rejected', 'suspended', 'expired', 'appeal_review', 'erasure_pending', 'invalid_application', 'erased' ), true );
		$eligible = ! $hard_blocked && ! empty( $raw['eligible'] );

		$age = self::age_guardian_claim( $user_id );
		$age_known = ! empty( $age['age_known'] );
		$is_minor = $age_known ? ! empty( $age['is_minor'] ) : ! in_array( $account_type, array( 'founder', 'doctor' ), true );
		$guardian_verified = ! empty( $raw['guardian_verified'] );
		if ( $age_known && ! empty( $age['guardian_verified'] ) ) {
			$guardian_verified = true;
		}

		return array(
			'user_id'                => $user_id,
			'uuid'                   => sanitize_text_field( (string) ( $raw['user_uuid'] ?? '' ) ),
			'display_name'           => sanitize_text_field( (string) ( $user ? $user->display_name : '' ) ),
			'account_type'           => $account_type,
			'account_class'          => $account_class,
			'membership_type'        => $membership_type,
			'approved_types'         => $approved_types,
			'status'                 => $status,
			'approved'               => ! empty( $raw['approved'] ),
			'eligible'               => $eligible,
			'session_two_factor'     => ! empty( $raw['session_two_factor'] ),
			'is_founder'             => $is_founder,
			'is_minor'               => $is_minor,
			'age_known'              => $age_known,
			'age'                    => $age_known ? absint( $age['age'] ?? 0 ) : 0,
			'guardian_required'      => $age_known ? $is_minor : ! $guardian_verified,
			'guardian_verified'      => $guardian_verified,
			'suspended'              => $hard_blocked,
			'email_verified'         => ! empty( $raw['email_verified'] ),
			'phone_verified'         => ! empty( $raw['phone_verified'] ),
			'professional_verified'  => ! empty( $raw['professional_verified'] ),
			'public_profile_allowed' => ! empty( $raw['public_profile_allowed'] ),
			'locale'                 => sanitize_text_field( (string) get_user_locale( $user_id ) ),
			'contract_version'       => $contract,
			'claims_retrieved_at'    => gmdate( 'c' ),
		);
	}

	/**
	 * Optional File 00 age/guardian projection. Absence never makes a person
	 * public: non-professional profiles remain minor-safe until the current
	 * age-assurance contract is available.
	 */
	private static function age_guardian_claim( $user_id ) {
		$claim = apply_filters( 'smc_profile_age_guardian_claim_v1', null, absint( $user_id ), SPD_CONTRACT_VERSION );
		if ( ! is_array( $claim ) ) {
			return array( 'age_known' => false );
		}
		if ( absint( $claim['user_id'] ?? 0 ) !== absint( $user_id ) ) {
			return array( 'age_known' => false );
		}
		if ( ! SPD_Helpers::current_contract_claim( $claim, self::MIN_CONTRACT_VERSION, 600 ) ) {
			return array( 'age_known' => false );
		}
		$age = absint( $claim['age'] ?? 0 );
		if ( ! $age || $age > 120 ) {
			return array( 'age_known' => false );
		}
		return array(
			'age_known'        => true,
			'age'              => $age,
			'is_minor'         => isset( $claim['is_minor'] ) ? (bool) $claim['is_minor'] : $age < 18,
			'guardian_verified'=> ! empty( $claim['guardian_verified'] ),
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
		$claims = self::claims( $user_id );
		return $claims && ! empty( $claims['approved'] ) && empty( $claims['suspended'] );
	}

	public static function is_member_eligible( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims && ! empty( $claims['eligible'] ) && empty( $claims['suspended'] );
	}

	public static function is_doctor( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims && 'doctor' === $claims['account_type'] && ! empty( $claims['professional_verified'] );
	}

	public static function is_founder( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['is_founder'] );
	}

	public static function is_minor( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['is_minor'] );
	}

	public static function age_known( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['age_known'] );
	}

	public static function public_profile_age_eligible( $user_id ) {
		$claim = self::age_guardian_claim( absint( $user_id ) );
		return ! empty( $claim['age_known'] ) && empty( $claim['is_minor'] );
	}

	public static function guardian_verified( $user_id ) {
		$claims = self::claims( $user_id );
		return ! empty( $claims['guardian_verified'] );
	}

	public static function founder_id() {
		if ( ! self::available() ) {
			return 0;
		}
		$founder_id = absint( smc_founder_user_id() );
		return $founder_id && self::is_founder( $founder_id ) ? $founder_id : 0;
	}

	public static function can_manage_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$claims = self::claims( $user_id );
		$founder_id = self::founder_id();
		$allowed = $claims && $user_id === $founder_id && ! empty( $claims['eligible'] ) && ! empty( $claims['session_two_factor'] ) && ! empty( $claims['is_founder'] );
		// This filter can only narrow a valid File 00 decision; it cannot grant.
		return $allowed && (bool) apply_filters( 'spd_restrict_founder_management', true, $user_id );
	}

	public static function can_moderate_profiles( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$claims = self::claims( $user_id );
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $claims && $user && ! empty( $claims['eligible'] ) && ! empty( $claims['session_two_factor'] )
			&& ( user_can( $user, 'smc_review_verification' ) || user_can( $user, 'smc_manage_membership' ) );
	}

	public static function can_operate_profiles( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( self::can_manage_founder( $user_id ) ) {
			return true;
		}
		$claims = self::claims( $user_id );
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $claims && $user && ! empty( $claims['eligible'] ) && ! empty( $claims['session_two_factor'] ) && user_can( $user, 'smc_manage_membership' );
	}

	public static function guardian_can_manage( $guardian_id, $child_id ) {
		$guardian_id = absint( $guardian_id );
		$child_id = absint( $child_id );
		if ( ! $guardian_id || ! $child_id || ! self::is_member_eligible( $guardian_id ) || ! self::is_minor( $child_id ) ) {
			return false;
		}
		$assertion = apply_filters( 'smc_guardian_relationship_claim_v1', null, $guardian_id, $child_id, SPD_CONTRACT_VERSION );
		return SPD_Helpers::current_contract_claim( $assertion, self::MIN_CONTRACT_VERSION, 600 )
			&& ! empty( $assertion['verified'] )
			&& absint( $assertion['guardian_user_id'] ?? 0 ) === $guardian_id
			&& absint( $assertion['minor_user_id'] ?? 0 ) === $child_id;
	}

	public static function display_name( $user_id ) {
		$claims = self::claims( $user_id );
		return $claims['display_name'] ?? '';
	}

	/**
	 * Return only a consent-capable contact channel from a versioned File 00
	 * projection. Email may use the current verified WordPress address because
	 * File 00 explicitly asserts that channel ownership; phone/WhatsApp never
	 * fall back to user meta or File 00 internal tables.
	 */
	public static function contact( $user_id, $key ) {
		$user_id = absint( $user_id );
		$key = sanitize_key( $key );
		if ( ! in_array( $key, array( 'email', 'phone', 'whatsapp' ), true ) ) {
			return '';
		}
		$claims = self::claims( $user_id );
		if ( ! $claims ) {
			return '';
		}
		$projection = apply_filters( 'smc_profile_contact_projection_v1', null, $user_id, $key, SPD_CONTRACT_VERSION );
		if ( SPD_Helpers::current_contract_claim( $projection, self::MIN_CONTRACT_VERSION, 300 )
			&& absint( $projection['user_id'] ?? 0 ) === $user_id
			&& sanitize_key( (string) ( $projection['channel'] ?? '' ) ) === $key
			&& ! empty( $projection['verified'] ) ) {
			return sanitize_text_field( (string) ( $projection['value'] ?? '' ) );
		}
		if ( 'email' === $key && ! empty( $claims['email_verified'] ) ) {
			$user = get_userdata( $user_id );
			return $user ? sanitize_email( $user->user_email ) : '';
		}
		return '';
	}
}
