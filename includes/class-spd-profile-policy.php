<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Profile_Policy {
	const META_FIELD_PRIVACY = '_spd_field_privacy';
	const META_VERSION = '_spd_profile_version';

	public static function fields() {
		return array( 'display_name','country','city','bio','languages','studied_books','specialty','qualification','clinic','phone','whatsapp' );
	}

	public static function is_minor( $user_id ) {
		$profile = SPD_Membership_Adapter::profile( $user_id );
		if ( isset( $profile['is_minor'] ) ) { return (bool) $profile['is_minor']; }
		if ( isset( $profile['date_of_birth'] ) ) {
			$dob = strtotime( (string) $profile['date_of_birth'] );
			return $dob && (int) gmdate( 'Ymd' ) - (int) gmdate( 'Ymd', $dob ) < 180000;
		}
		return false;
	}

	public static function version( $user_id ) {
		return max( 1, absint( get_user_meta( absint( $user_id ), self::META_VERSION, true ) ) );
	}

	public static function bump_version( $user_id, $expected ) {
		$user_id = absint( $user_id );
		$current = self::version( $user_id );
		if ( absint( $expected ) !== $current ) {
			return new WP_Error( 'spd_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409, 'current_version' => $current ) );
		}
		update_user_meta( $user_id, self::META_VERSION, $current + 1 );
		return $current + 1;
	}

	public static function field_privacy( $user_id ) {
		$saved = get_user_meta( absint( $user_id ), self::META_FIELD_PRIVACY, true );
		$saved = is_array( $saved ) ? $saved : array();
		$defaults = array_fill_keys( self::fields(), 'members' );
		$defaults['display_name'] = 'public';
		$defaults['country'] = 'public';
		$defaults['specialty'] = 'public';
		if ( self::is_minor( $user_id ) ) {
			$defaults = array_fill_keys( self::fields(), 'private' );
			$defaults['display_name'] = 'members';
		}
		return array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
	}

	public static function sanitize_privacy( $user_id, $input ) {
		$allowed = array( 'public','members','contacts','private' );
		$out = array();
		foreach ( self::fields() as $field ) {
			$value = isset( $input[ $field ] ) ? sanitize_key( $input[ $field ] ) : 'private';
			$out[ $field ] = in_array( $value, $allowed, true ) ? $value : 'private';
		}
		if ( self::is_minor( $user_id ) ) {
			foreach ( array( 'phone','whatsapp','city','clinic' ) as $field ) { $out[ $field ] = 'private'; }
		}
		return $out;
	}

	public static function can_view_field( $viewer_id, $owner_id, $field ) {
		if ( absint( $viewer_id ) === absint( $owner_id ) || current_user_can( 'smc_manage_membership' ) ) { return true; }
		$privacy = self::field_privacy( $owner_id );
		$level = isset( $privacy[ $field ] ) ? $privacy[ $field ] : 'private';
		if ( 'public' === $level ) { return true; }
		if ( 'members' === $level ) { return is_user_logged_in(); }
		if ( 'contacts' === $level ) { return (bool) apply_filters( 'spd_are_contacts', false, $viewer_id, $owner_id ); }
		return false;
	}
}
