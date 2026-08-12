<?php
defined( 'ABSPATH' ) || exit;

/** Read-only, current and versioned projection of File 09 doctor-verification truth. */
final class SPD_Verification_Adapter {
	const MIN_VERSION = '1.0.0';
	const MIN_CONTRACT_VERSION = '1.0.0';

	public static function available() { return has_filter( 'sabri_doctor_verification_public_projection_v1' ) && function_exists( 'gdo_validate_public_projection' ); }
	public static function health() {
		if ( ! self::available() ) { return array( 'status' => 'missing', 'version' => '', 'reason' => 'file09_versioned_projection_missing' ); }
		return array( 'status' => 'available', 'version' => 'contract-v1', 'reason' => '' );
	}

	public static function projection( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! self::available() ) { return array(); }
		$data = apply_filters( 'sabri_doctor_verification_public_projection_v1', null, $user_id, SPD_CONTRACT_VERSION );
		if ( ! is_array( $data ) || true !== gdo_validate_public_projection( $data, $user_id, SPD_CONTRACT_VERSION ) ) { return array(); }
		$normalized = self::normalize( $data, $user_id );
		return self::is_current_projection( $normalized, $user_id ) ? $normalized : array();
	}

	private static function normalize( array $data, $user_id ) {
		$fields = isset( $data['approved_fields'] ) && is_array( $data['approved_fields'] ) ? $data['approved_fields'] : array();
		$allowed = array(
			'professional_title','qualification','degree','institution','licence_number','licensing_authority','jurisdiction',
			'credential_issued_at','credential_expires_at','experience_years','specialty','languages','studied_books','consultation_modes',
			'clinic_name','clinic_url','country','city','bio','profile_photo_id','cover_photo_id',
		);
		$clean = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) { continue; }
			$clean[ $key ] = in_array( $key, array( 'profile_photo_id', 'cover_photo_id', 'experience_years' ), true ) ? absint( $fields[ $key ] ) : sanitize_textarea_field( (string) $fields[ $key ] );
		}
		$status = sanitize_key( (string) ( $data['status'] ?? 'pending' ) );
		if ( ! in_array( $status, array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended', 'expired' ), true ) ) { $status = 'pending'; }
		return array(
			'user_id'          => absint( $data['user_id'] ?? $user_id ),
			'status'           => $status,
			'approved_fields'  => $clean,
			'reviewer_id'      => absint( $data['reviewer_id'] ?? 0 ),
			'reviewed_at'      => sanitize_text_field( (string) ( $data['reviewed_at'] ?? '' ) ),
			'generated_at'     => sanitize_text_field( (string) ( $data['generated_at'] ?? '' ) ),
			'valid_until'      => sanitize_text_field( (string) ( $data['valid_until'] ?? '' ) ),
			'claim_version'    => sanitize_text_field( (string) ( $data['claim_version'] ?? $data['version'] ?? '' ) ),
			'contract_version' => sanitize_text_field( (string) ( $data['contract_version'] ?? '' ) ),
			'issuer'           => sanitize_key( (string) ( $data['issuer'] ?? 'file09' ) ),
			'trusted_contract' => true,
		);
	}

	private static function is_current_projection( array $projection, $user_id ) {
		if ( absint( $projection['user_id'] ?? 0 ) !== absint( $user_id ) ) { return false; }
		if ( empty( $projection['contract_version'] ) || version_compare( $projection['contract_version'], self::MIN_CONTRACT_VERSION, '<' ) ) { return false; }
		if ( empty( $projection['claim_version'] ) || version_compare( (string) $projection['claim_version'], self::MIN_VERSION, '<' ) ) { return false; }
		$reviewed_at = strtotime( (string) $projection['reviewed_at'] );
		$generated_at = strtotime( (string) ( $projection['generated_at'] ?? '' ) );
		$valid_until = strtotime( (string) ( $projection['valid_until'] ?? '' ) );
		if ( empty( $projection['reviewer_id'] ) || false === $reviewed_at || false === $generated_at || false === $valid_until ) { return false; }
		if ( $reviewed_at > time() + 300 || abs( time() - $generated_at ) > 600 || $valid_until <= time() || $valid_until <= $reviewed_at ) { return false; }
		return true;
	}

	public static function fingerprint( array $fields ) { ksort( $fields ); return hash( 'sha256', wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); }
	public static function status( $user_id ) {
		$membership = SPD_Membership_Adapter::status( $user_id );
		if ( in_array( $membership, array( 'suspended', 'rejected', 'erased' ), true ) ) { return 'suspended' === $membership ? 'suspended' : 'rejected'; }
		$projection = self::projection( $user_id );
		return $projection ? $projection['status'] : 'verification_unavailable';
	}
	public static function approved_fields( $user_id ) { $p = self::projection( $user_id ); return $p && 'verified' === $p['status'] ? $p['approved_fields'] : array(); }
	public static function is_verified( $user_id ) {
		$p = self::projection( $user_id );
		return SPD_Membership_Adapter::is_doctor( $user_id ) && SPD_Membership_Adapter::is_approved( $user_id ) && $p && ! empty( $p['trusted_contract'] ) && 'verified' === $p['status'];
	}
	public static function status_label( $status ) {
		$labels = array( 'pending'=>__( 'Pending','sabri-profiles-doctors' ), 'under_review'=>__( 'Under review','sabri-profiles-doctors' ), 'verified'=>__( 'Verified','sabri-profiles-doctors' ), 'more_info'=>__( 'More information required','sabri-profiles-doctors' ), 'rejected'=>__( 'Not approved','sabri-profiles-doctors' ), 'suspended'=>__( 'Suspended','sabri-profiles-doctors' ), 'expired'=>__( 'Verification expired','sabri-profiles-doctors' ), 'verification_unavailable'=>__( 'Verification service unavailable','sabri-profiles-doctors' ) );
		return $labels[ $status ] ?? $labels['pending'];
	}
	public static function directory_eligible( $user_id ) { return self::is_verified( $user_id ) && ! SPD_Membership_Adapter::is_minor( $user_id ); }
	public static function maybe_refresh_from_meta() { /* Legacy metadata is deliberately non-authoritative. */ }
}
