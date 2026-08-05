<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Authorization {
	public static function allowed_audiences() { return array( 'public', 'members', 'contacts', 'private' ); }
	public static function normalize_audience( $audience ) { $audience = sanitize_key( (string) $audience ); return in_array( $audience, self::allowed_audiences(), true ) ? $audience : 'private'; }

	public static function is_contact( $owner_id, $viewer_id ) {
		$owner_id = absint( $owner_id );
		$viewer_id = absint( $viewer_id );
		if ( ! $owner_id || ! $viewer_id ) { return false; }
		if ( $owner_id === $viewer_id ) { return true; }
		if ( ! SPD_Membership_Adapter::is_member_eligible( $viewer_id ) ) { return false; }
		$claim = apply_filters( 'sabri_network_contact_claim_v1', null, $owner_id, $viewer_id, SPD_CONTRACT_VERSION );
		return SPD_Helpers::current_contract_claim( $claim, '1.0.0', 300 )
			&& ! empty( $claim['connected'] )
			&& absint( $claim['owner_user_id'] ?? 0 ) === $owner_id
			&& absint( $claim['viewer_user_id'] ?? 0 ) === $viewer_id;
	}

	public static function audience_allows( $audience, $owner_id, $viewer_id ) {
		$audience = self::normalize_audience( $audience );
		$owner_id = absint( $owner_id );
		$viewer_id = absint( $viewer_id );
		if ( $viewer_id && $viewer_id === $owner_id ) { return true; }
		switch ( $audience ) {
			case 'public': return true;
			case 'members': return $viewer_id > 0 && SPD_Membership_Adapter::is_member_eligible( $viewer_id );
			case 'contacts': return self::is_contact( $owner_id, $viewer_id );
			default: return false;
		}
	}

	public static function profile_visibility_allows( array $profile, $viewer_id ) {
		$owner_id = absint( $profile['user_id'] ?? 0 );
		$viewer_id = absint( $viewer_id );
		if ( $owner_id && $owner_id === $viewer_id ) { return true; }
		if ( in_array( $profile['state'] ?? '', array( 'suspended', 'archived', 'tombstoned' ), true ) ) { return false; }
		if ( 'founder' === ( $profile['profile_type'] ?? '' ) ) {
			return SPD_Membership_Adapter::is_founder( $owner_id );
		}
		$claims = SPD_Membership_Adapter::claims( $owner_id );
		if ( ! $claims || empty( $claims['eligible'] ) || ! empty( $claims['suspended'] ) ) { return false; }
		$visibility = self::normalize_audience( $profile['profile_visibility'] ?? 'private' );
		if ( 'public' === $visibility && ( ! empty( $claims['is_minor'] ) || empty( $claims['public_profile_allowed'] ) ) ) { return false; }
		return self::audience_allows( $visibility, $owner_id, $viewer_id );
	}

	public static function can_edit_profile( array $profile, $actor_id ) {
		$actor_id = absint( $actor_id );
		$owner_id = absint( $profile['user_id'] ?? 0 );
		if ( ! $actor_id || ! $owner_id ) { return false; }
		if ( 'founder' === ( $profile['profile_type'] ?? '' ) ) {
			return $actor_id === $owner_id && SPD_Membership_Adapter::can_manage_founder( $actor_id );
		}
		$is_owner = $actor_id === $owner_id;
		$is_guardian = ! $is_owner && SPD_Membership_Adapter::guardian_can_manage( $actor_id, $owner_id );
		if ( ! $is_owner && ! $is_guardian ) { return false; }
		$claims = SPD_Membership_Adapter::claims( $owner_id );
		if ( ! $claims || ! empty( $claims['suspended'] ) ) { return false; }
		if ( ! empty( $claims['guardian_required'] ) && empty( $claims['guardian_verified'] ) ) { return false; }
		return SPD_Membership_Adapter::is_approved( $owner_id );
	}

	public static function can_publish_audience( $user_id, $field_key, $audience ) {
		$user_id = absint( $user_id );
		$audience = self::normalize_audience( $audience );
		$field_key = sanitize_key( $field_key );
		if ( SPD_Membership_Adapter::is_minor( $user_id ) ) {
			if ( in_array( $field_key, array( 'phone', 'email', 'whatsapp', 'city', 'internal_message' ), true ) ) { return 'private' === $audience; }
			if ( 'public' === $audience || 'contacts' === $audience ) { return false; }
		}
		return true;
	}

	public static function mutation_guard( array $profile, $actor_id ) {
		if ( SPD_Observability::safe_mode() ) {
			return new WP_Error( 'spd_safe_mode', __( 'Profile changes are temporarily unavailable while the system is in safe mode.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( ! self::can_edit_profile( $profile, $actor_id ) ) {
			return new WP_Error( 'spd_forbidden', __( 'You are not authorized to change this profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function moderation_guard( $actor_id ) {
		if ( SPD_Observability::safe_mode() ) {
			return new WP_Error( 'spd_safe_mode', __( 'Profile moderation is temporarily unavailable while the system is in safe mode.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return SPD_Membership_Adapter::can_moderate_profiles( $actor_id ) ? true : new WP_Error( 'spd_forbidden', __( 'Profile moderation permission is required.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
	}
}
