<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Public_Identity {
	const META_ID = '_spd_public_id';
	const META_SLUG_HISTORY = '_spd_slug_history';

	public static function ensure( $user_id ) {
		$user_id = absint( $user_id );
		$id = sanitize_key( (string) get_user_meta( $user_id, self::META_ID, true ) );
		if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			return $id;
		}
		$id = strtolower( wp_generate_uuid4() );
		$id = str_replace( '-', '', $id );
		add_user_meta( $user_id, self::META_ID, $id, true );
		return sanitize_key( (string) get_user_meta( $user_id, self::META_ID, true ) );
	}

	public static function resolve( $public_id ) {
		$public_id = sanitize_key( $public_id );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $public_id ) ) {
			return 0;
		}
		$users = get_users( array( 'fields' => 'ids', 'number' => 2, 'meta_key' => self::META_ID, 'meta_value' => $public_id ) );
		return 1 === count( $users ) ? absint( $users[0] ) : 0;
	}

	public static function profile_url( $user_id, $timeline = false ) {
		$id = self::ensure( $user_id );
		$path = $timeline ? 'profile/' . $id . '/timeline/' : 'profile/' . $id . '/';
		return home_url( user_trailingslashit( $path ) );
	}

	public static function register_routes() {
		add_rewrite_rule( '^profile/([a-f0-9]{32})/?$', 'index.php?spd_public_id=$matches[1]', 'top' );
		add_rewrite_rule( '^profile/([a-f0-9]{32})/timeline/?$', 'index.php?spd_public_id=$matches[1]&spd_timeline=1', 'top' );
		add_rewrite_rule( '^account/profile/?$', 'index.php?spd_account_profile=1', 'top' );
		add_filter( 'query_vars', static function( $vars ) {
			$vars[] = 'spd_public_id'; $vars[] = 'spd_timeline'; $vars[] = 'spd_account_profile';
			return $vars;
		} );
	}
}
