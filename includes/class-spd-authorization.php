<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Authorization {
	public static function allowed_audiences() {
		return array( 'public', 'members', 'contacts', 'private' );
	}

	public static function normalize_audience( $audience ) {
		$audience = sanitize_key( (string) $audience );
		return in_array( $audience, self::allowed_audiences(), true ) ? $audience : 'private';
	}

	public static function is_contact( $owner_id, $viewer_id ) {
		if ( ! $owner_id || ! $viewer_id || $owner_id === $viewer_id ) {
			return $owner_id === $viewer_id;
		}
		return (bool) apply_filters( 'sabri_network_are_contacts', false, absint( $owner_id ), absint( $viewer_id ) );
	}

	public static function audience_allows( $audience, $owner_id, $viewer_id ) {
		$audience = self::normalize_audience( $audience );
		$owner_id = absint( $owner_id );
		$viewer_id = absint( $viewer_id );
		if ( $viewer_id && $viewer_id === $owner_id ) {
			return true;
		}
		if ( SPD_Membership_Adapter::can_moderate_profiles( $viewer_id ) ) {
			return true;
		}
		switch ( $audience ) {
			case 'public':
				return true;
			case 'members':
				return $viewer_id > 0;
			case 'contacts':
				return self::is_contact( $owner_id, $viewer_id );
			default:
				return false;
		}
	}

	public static function profile_visibility_allows( array $profile, $viewer_id ) {
		$owner_id = absint( $profile['user_id'] ?? 0 );
		if ( $owner_id && $owner_id === absint( $viewer_id ) ) {
			return true;
		}
		if ( SPD_Membership_Adapter::is_founder( $owner_id ) ) {
			return true;
		}
		if ( in_array( $profile['state'] ?? '', array( 'suspended', 'archived', 'tombstoned' ), true ) ) {
			return SPD_Membership_Adapter::can_moderate_profiles( $viewer_id );
		}
		$visibility = $profile['profile_visibility'] ?? 'private';
		return self::audience_allows( $visibility, $owner_id, $viewer_id );
	}

	public static function can_edit_profile( array $profile, $actor_id ) {
		$actor_id = absint( $actor_id );
		$owner_id = absint( $profile['user_id'] ?? 0 );
		if ( ! $actor_id || $actor_id !== $owner_id ) {
			return false;
		}
		if ( SPD_Membership_Adapter::is_founder( $owner_id ) ) {
			return SPD_Membership_Adapter::can_manage_founder( $actor_id );
		}
		$claims = SPD_Membership_Adapter::claims( $actor_id );
		if ( ! $claims || ! empty( $claims['suspended'] ) ) {
			return false;
		}
		if ( ! empty( $claims['guardian_required'] ) && empty( $claims['guardian_verified'] ) ) {
			return false;
		}
		return SPD_Membership_Adapter::is_approved( $actor_id );
	}

	public static function can_publish_audience( $user_id, $field_key, $audience ) {
		$user_id = absint( $user_id );
		$audience = self::normalize_audience( $audience );
		$field_key = sanitize_key( $field_key );
		if ( SPD_Membership_Adapter::is_minor( $user_id ) ) {
			if ( in_array( $field_key, array( 'phone', 'email', 'whatsapp', 'city' ), true ) ) {
				return 'private' === $audience;
			}
			if ( 'public' === $audience ) {
				return false;
			}
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
}
