<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only, versioned projection of File 09 doctor-verification truth.
 */
final class SPD_Verification_Adapter {
	const SNAPSHOT_META = '_spd_approved_projection_snapshot_v2';
	const MIN_VERSION   = '1.0.0';

	public static function available() {
		if ( defined( 'GDO_VERSION' ) && version_compare( (string) GDO_VERSION, self::MIN_VERSION, '>=' ) ) {
			return true;
		}
		return has_filter( 'sabri_doctor_verification_public_projection_v1' );
	}

	public static function health() {
		if ( has_filter( 'sabri_doctor_verification_public_projection_v1' ) ) {
			return array( 'status' => 'available', 'version' => 'contract-v1', 'reason' => '' );
		}
		if ( ! defined( 'GDO_VERSION' ) ) {
			return array( 'status' => 'missing', 'version' => '', 'reason' => 'file09_missing' );
		}
		if ( version_compare( (string) GDO_VERSION, self::MIN_VERSION, '<' ) ) {
			return array( 'status' => 'incompatible', 'version' => (string) GDO_VERSION, 'reason' => 'file09_version_too_old' );
		}
		return array( 'status' => 'compatibility', 'version' => (string) GDO_VERSION, 'reason' => 'legacy_projection_adapter' );
	}

	public static function projection( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$projection = apply_filters( 'sabri_doctor_verification_public_projection_v1', null, $user_id, SPD_CONTRACT_VERSION );
		if ( is_array( $projection ) ) {
			return self::normalize( $projection, $user_id, true );
		}
		return self::legacy_projection( $user_id );
	}

	private static function normalize( array $data, $user_id, $trusted_contract ) {
		$fields = isset( $data['approved_fields'] ) && is_array( $data['approved_fields'] ) ? $data['approved_fields'] : $data;
		$allowed = array(
			'professional_title', 'qualification', 'licence_number', 'licensing_authority',
			'experience_years', 'specialty', 'languages', 'studied_books', 'consultation_modes',
			'clinic_name', 'clinic_url', 'country', 'city', 'bio', 'profile_photo_id', 'cover_photo_id',
		);
		$clean = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}
			$clean[ $key ] = in_array( $key, array( 'profile_photo_id', 'cover_photo_id', 'experience_years' ), true )
				? absint( $fields[ $key ] )
				: sanitize_textarea_field( (string) $fields[ $key ] );
		}
		$status = sanitize_key( (string) ( $data['status'] ?? 'pending' ) );
		$allowed_statuses = array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended', 'expired' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'pending';
		}
		return array(
			'user_id'          => absint( $user_id ),
			'status'           => $status,
			'approved_fields'  => $clean,
			'reviewer_id'      => absint( $data['reviewer_id'] ?? 0 ),
			'reviewed_at'      => sanitize_text_field( (string) ( $data['reviewed_at'] ?? '' ) ),
			'claim_version'    => sanitize_text_field( (string) ( $data['claim_version'] ?? $data['version'] ?? '1' ) ),
			'contract_version' => sanitize_text_field( (string) ( $data['contract_version'] ?? ( $trusted_contract ? '1.0.0' : 'legacy' ) ) ),
			'trusted_contract' => (bool) $trusted_contract,
		);
	}

	private static function legacy_projection( $user_id ) {
		if ( ! defined( 'GDO_VERSION' ) ) {
			return array();
		}
		$status = sanitize_key( (string) get_user_meta( $user_id, '_spd_verification_status', true ) );
		$reviewed_at = sanitize_text_field( (string) get_user_meta( $user_id, '_gdo_reviewed_at', true ) );
		$reviewer_id = absint( get_user_meta( $user_id, '_gdo_reviewer_id', true ) );
		if ( ! $reviewed_at || ! $reviewer_id ) {
			$status = 'pending';
		}
		$legacy = array(
			'status'          => $status ?: 'pending',
			'reviewer_id'     => $reviewer_id,
			'reviewed_at'     => $reviewed_at,
			'approved_fields' => array(),
			'contract_version'=> 'legacy-compat',
		);
		$snapshot = get_user_meta( $user_id, self::SNAPSHOT_META, true );
		if ( is_array( $snapshot ) && ! empty( $snapshot['fields'] ) ) {
			$legacy['approved_fields'] = $snapshot['fields'];
		}
		return self::normalize( $legacy, $user_id, false );
	}

	public static function fingerprint( array $fields ) {
		ksort( $fields );
		return hash( 'sha256', wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	public static function capture_compatibility_snapshot( $user_id ) {
		$projection = self::projection( $user_id );
		if ( empty( $projection ) || 'verified' !== $projection['status'] || empty( $projection['reviewer_id'] ) || empty( $projection['reviewed_at'] ) ) {
			return false;
		}
		if ( ! empty( $projection['trusted_contract'] ) ) {
			return true;
		}
		$fields = self::current_legacy_fields( $user_id );
		if ( ! $fields ) {
			return false;
		}
		update_user_meta(
			$user_id,
			self::SNAPSHOT_META,
			array(
				'schema'      => 2,
				'fields'      => $fields,
				'fingerprint' => self::fingerprint( $fields ),
				'reviewed_at' => $projection['reviewed_at'],
				'reviewer_id' => $projection['reviewer_id'],
				'captured_at' => current_time( 'mysql', true ),
			)
		);
		return true;
	}

	private static function current_legacy_fields( $user_id ) {
		$fields = apply_filters( 'spd_legacy_doctor_public_fields', array(), absint( $user_id ) );
		return is_array( $fields ) ? $fields : array();
	}

	public static function status( $user_id ) {
		$membership = SPD_Membership_Adapter::status( $user_id );
		if ( in_array( $membership, array( 'suspended', 'rejected', 'erased' ), true ) ) {
			return 'suspended' === $membership ? 'suspended' : 'rejected';
		}
		$projection = self::projection( $user_id );
		if ( ! $projection ) {
			return 'verification_unavailable';
		}
		return $projection['status'];
	}

	public static function approved_fields( $user_id ) {
		$projection = self::projection( $user_id );
		return $projection['approved_fields'] ?? array();
	}

	public static function is_verified( $user_id ) {
		$projection = self::projection( $user_id );
		return SPD_Membership_Adapter::is_doctor( $user_id )
			&& SPD_Membership_Adapter::is_approved( $user_id )
			&& ! empty( $projection )
			&& 'verified' === $projection['status']
			&& ! empty( $projection['reviewed_at'] )
			&& ! empty( $projection['reviewer_id'] );
	}

	public static function status_label( $status ) {
		$labels = array(
			'pending'                  => __( 'Pending', 'sabri-profiles-doctors' ),
			'under_review'             => __( 'Under review', 'sabri-profiles-doctors' ),
			'verified'                 => __( 'Verified', 'sabri-profiles-doctors' ),
			'more_info'                => __( 'More information required', 'sabri-profiles-doctors' ),
			'rejected'                 => __( 'Not approved', 'sabri-profiles-doctors' ),
			'suspended'                => __( 'Suspended', 'sabri-profiles-doctors' ),
			'expired'                  => __( 'Verification expired', 'sabri-profiles-doctors' ),
			'verification_unavailable' => __( 'Verification service unavailable', 'sabri-profiles-doctors' ),
		);
		return $labels[ $status ] ?? $labels['pending'];
	}

	public static function directory_eligible( $user_id ) {
		return self::is_verified( $user_id ) && ! SPD_Membership_Adapter::is_minor( $user_id );
	}

	public static function maybe_refresh_from_meta( $meta_id, $user_id, $meta_key ) {
		unset( $meta_id );
		if ( in_array( $meta_key, array( '_gdo_reviewed_at', '_gdo_reviewer_id', '_spd_verification_status' ), true ) ) {
			self::capture_compatibility_snapshot( $user_id );
		}
	}
}
