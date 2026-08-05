<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Cache {
	public function purge_profile_cache( array $profile ) {
		wp_cache_delete( 'profile:' . $profile['public_id'], 'spd' );
		delete_transient( 'spd_profile_' . md5( $profile['public_id'] ) );
		do_action( 'spd_profile_cache_purged', $profile['public_id'], $profile['user_id'], $profile['version'] );
		do_action( 'sabri_file26_profile_changed', $profile['public_id'], $profile['version'] );
	}
}
