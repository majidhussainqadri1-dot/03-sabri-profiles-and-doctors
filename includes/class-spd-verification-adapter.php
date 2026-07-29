<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only projection of File 00 and File 09 verification state.
 */
final class SPD_Verification_Adapter {
	const SNAPSHOT_META = '_spd_approved_projection_snapshot';

	public static function gdo_available() {
		return defined( 'GDO_VERSION' ) || class_exists( 'GDO_Helpers', false );
	}

	public static function gdo_status( $user_id ) {
		$status  = sanitize_key( (string) get_user_meta( absint( $user_id ), '_spd_verification_status', true ) );
		$allowed = array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended' );
		return in_array( $status, $allowed, true ) ? $status : 'pending';
	}

	public static function has_review_evidence( $user_id ) {
		return (bool) get_user_meta( absint( $user_id ), '_gdo_reviewed_at', true )
			&& absint( get_user_meta( absint( $user_id ), '_gdo_reviewer_id', true ) ) > 0;
	}

	public static function material_fields() {
		return array(
			'display_name', 'country', 'city', 'clinic', 'qualification', 'licence_number',
			'licensing_authority', 'experience_years', 'specialty', 'languages', 'studied_books',
			'consultation_modes', 'phone', 'whatsapp', 'bio', 'profile_photo_id', 'cover_photo_id',
		);
	}

	public static function current_projection( $user_id ) {
		$data = array();
		foreach ( self::material_fields() as $key ) {
			if ( 'profile_photo_id' === $key || 'cover_photo_id' === $key ) {
				$data[ $key ] = absint( get_user_meta( absint( $user_id ), '_spd_' . $key, true ) );
			} else {
				$data[ $key ] = (string) SPD_Membership_Adapter::field( $user_id, $key, '' );
			}
		}
		return $data;
	}

	public static function fingerprint( array $data ) {
		ksort( $data );
		return hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	public static function snapshot( $user_id ) {
		$value = get_user_meta( absint( $user_id ), self::SNAPSHOT_META, true );
		return is_array( $value ) ? $value : array();
	}

	public static function capture_snapshot( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! SPD_Membership_Adapter::is_doctor_identity_approved( $user_id ) || 'verified' !== self::gdo_status( $user_id ) || ! self::has_review_evidence( $user_id ) ) {
			return false;
		}
		$data = self::current_projection( $user_id );
		$snapshot = array(
			'schema'      => 1,
			'fields'      => $data,
			'fingerprint' => self::fingerprint( $data ),
			'reviewed_at' => sanitize_text_field( (string) get_user_meta( $user_id, '_gdo_reviewed_at', true ) ),
			'reviewer_id' => absint( get_user_meta( $user_id, '_gdo_reviewer_id', true ) ),
			'captured_at' => current_time( 'mysql', true ),
		);
		update_user_meta( $user_id, self::SNAPSHOT_META, $snapshot );
		return true;
	}

	public static function snapshot_matches( $user_id ) {
		$snapshot = self::snapshot( $user_id );
		return ! empty( $snapshot['fingerprint'] )
			&& hash_equals( (string) $snapshot['fingerprint'], self::fingerprint( self::current_projection( $user_id ) ) );
	}

	public static function approved_fields( $user_id ) {
		$snapshot = self::snapshot( $user_id );
		return ! empty( $snapshot['fields'] ) && is_array( $snapshot['fields'] ) ? $snapshot['fields'] : array();
	}

	public static function is_verified( $user_id ) {
		return SPD_Membership_Adapter::is_doctor_identity_approved( $user_id )
			&& self::gdo_available()
			&& 'verified' === self::gdo_status( $user_id )
			&& self::has_review_evidence( $user_id )
			&& self::snapshot_matches( $user_id );
	}

	public static function status( $user_id ) {
		$user_id = absint( $user_id );
		$smc     = SPD_Membership_Adapter::status( $user_id );
		$gdo     = self::gdo_status( $user_id );
		if ( in_array( $smc, array( 'suspended', 'rejected' ), true ) || in_array( $gdo, array( 'suspended', 'rejected' ), true ) ) {
			return 'suspended' === $smc || 'suspended' === $gdo ? 'suspended' : 'rejected';
		}
		if ( ! SPD_Membership_Adapter::is_doctor_identity_approved( $user_id ) ) {
			return in_array( $smc, array( 'under_review', 'submitted', 'resubmitted', 'more_information' ), true ) ? 'under_review' : 'pending';
		}
		if ( ! self::gdo_available() ) {
			return 'verification_unavailable';
		}
		if ( 'verified' === $gdo && ! self::snapshot_matches( $user_id ) ) {
			return 'changes_pending';
		}
		return self::is_verified( $user_id ) ? 'verified' : $gdo;
	}

	public static function status_label( $status ) {
		$labels = array(
			'pending'                  => 'Pending',
			'under_review'             => 'Under review',
			'verified'                 => 'Verified',
			'more_info'                => 'More information required',
			'rejected'                 => 'Not approved',
			'suspended'                => 'Suspended',
			'changes_pending'          => 'Profile changes awaiting re-review',
			'verification_unavailable' => 'Verification service unavailable',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
	}

	public static function directory_eligible( $user_id ) {
		return self::is_verified( $user_id ) && 'public' === SPD_Membership_Adapter::public_visibility( $user_id );
	}

	public static function maybe_capture_from_meta( $meta_id, $user_id, $meta_key ) {
		unset( $meta_id );
		if ( in_array( $meta_key, array( '_gdo_reviewed_at', '_gdo_reviewer_id', '_spd_verification_status' ), true ) ) {
			self::capture_snapshot( $user_id );
		}
	}
}
