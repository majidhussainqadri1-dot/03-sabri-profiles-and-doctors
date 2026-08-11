<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Profile_Repository {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public static function founder_fields() {
		return array( 'professional_title', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications', 'institutional_links' );
	}

	public static function editable_fields() {
		return array_merge( array( 'bio', 'country', 'city', 'languages', 'studied_books', 'locale' ), SPD_Central_Profile::extended_fields(), self::founder_fields() );
	}

	public static function professional_fields() {
		return array( 'professional_title', 'qualification', 'degree', 'institution', 'licence_number', 'licensing_authority', 'jurisdiction', 'credential_issued_at', 'credential_expires_at', 'experience_years', 'specialty', 'consultation_modes' );
	}

	public static function visibility_fields() {
		return array_merge( array( 'profile_visibility', 'bio', 'country', 'city', 'languages', 'studied_books', 'phone', 'email', 'whatsapp', 'internal_message' ), SPD_Central_Profile::extended_fields(), self::founder_fields() );
	}

	use SPD_Profile_Identity_Create;
	use SPD_Profile_Identity_Read;
	use SPD_Profile_Public_DTO;
	use SPD_Profile_Edit_Model;
	use SPD_Profile_Update;
	use SPD_Profile_Professional;
	use SPD_Profile_Media;
	use SPD_Profile_Moderation;
	use SPD_Profile_Lifecycle;
	use SPD_Profile_Events;
	use SPD_Profile_Cache;
	use SPD_Profile_Central { grant_delegate as private central_grant_delegate; }

	/**
	 * Grant-time delegation authority must be enforced below the REST layer too,
	 * because repository methods are a reusable integration surface for other
	 * File 03 adapters and companion modules.
	 */
	public function grant_delegate( $owner_id, $delegate_id, array $scopes, $expires_at = '', $idempotency_key = '' ) {
		$delegate_id = absint( $delegate_id );
		if ( $delegate_id && SPD_Membership_Adapter::is_minor( $delegate_id ) ) {
			return new WP_Error( 'spd_delegate_minor_forbidden', __( 'A minor account cannot receive delegated profile-management authority.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		return $this->central_grant_delegate( $owner_id, $delegate_id, $scopes, $expires_at, $idempotency_key );
	}

	/**
	 * Use-time delegation authority. The class-level owner command deliberately
	 * overrides the trait helper so a delegation cannot mutate a suspended,
	 * archived or tombstoned profile, nor proceed after a failed field-store
	 * read. Current File 00 and File 09 authority is revalidated every time.
	 */
	public function delegate_can_manage( $owner_id, $delegate_id, $scope ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$delegate_id = absint( $delegate_id );
		$scope = sanitize_key( $scope );
		if ( ! $owner_id || ! $delegate_id || ! in_array( $scope, SPD_Central_Profile::delegation_scopes(), true ) || ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::central_ready() ) { return false; }
		$profile = $this->find_by_user_id( $owner_id, false );
		if ( ! $profile || ! empty( $profile['_fields_read_failed'] ) || ! SPD_Authorization::profile_mutation_state_allows( $profile ) ) { return false; }
		if ( 'doctor' !== ( $profile['profile_type'] ?? '' ) || SPD_Membership_Adapter::is_minor( $owner_id ) || SPD_Membership_Adapter::is_minor( $delegate_id ) ) { return false; }
		$table = SPD_Central_Profile::delegation_table();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT scopes,expires_at FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d AND status='active' LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! $row || ( $row['expires_at'] && strtotime( $row['expires_at'] ) <= time() ) ) { return false; }
		if ( ! SPD_Membership_Adapter::is_member_eligible( $owner_id ) || ! SPD_Membership_Adapter::is_member_eligible( $delegate_id ) || ! SPD_Verification_Adapter::is_verified( $owner_id ) ) { return false; }
		return in_array( $scope, array_filter( array_map( 'sanitize_key', explode( ',', $row['scopes'] ) ) ), true );
	}

	/** Narrow public wrappers let additive File 03 owner commands reuse the canonical replay-protection store. */
	public function future_idempotency_begin( $actor_id, $command, $key, $request_hash ) { return $this->idempotency_begin( $actor_id, $command, $key, $request_hash, true ); }
	public function future_idempotency_complete( $actor_id, $command, $key, array $response ) { return $this->idempotency_complete( $actor_id, $command, $key, $response ); }
	public function future_idempotency_fail( $actor_id, $command, $key ) { $this->idempotency_fail( $actor_id, $command, $key ); }
}
