<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Destructive removal requires both an explicit constant and an administrator option.
if ( ! defined( 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' ) || true !== SPD_ALLOW_DESTRUCTIVE_UNINSTALL || ! get_option( 'spd_purge_on_uninstall', false ) ) {
	return;
}

global $wpdb;
wp_clear_scheduled_hook( 'spd_legacy_audit_retention' );
$ids = get_users( array( 'fields' => 'ids', 'number' => -1 ) );
foreach ( $ids as $user_id ) {
	foreach ( array( 'profile_photo_id'=>'profile', 'cover_photo_id'=>'cover' ) as $key=>$purpose ) {
		$attachment_id = absint( get_user_meta( $user_id, '_spd_' . $key, true ) );
		$owner = absint( get_post_meta( $attachment_id, '_spd_media_owner_user_id', true ) );
		$stored_purpose = sanitize_key( (string) get_post_meta( $attachment_id, '_spd_media_purpose', true ) );
		if ( $attachment_id && $owner === absint( $user_id ) && $purpose === $stored_purpose ) { wp_delete_attachment( $attachment_id, true ); }
		delete_user_meta( $user_id, '_spd_' . $key );
	}
	foreach ( array( '_spd_profile_visibility', '_spd_public_contact', '_spd_approved_projection_snapshot' ) as $key ) { delete_user_meta( $user_id, $key ); }
}
$map = (array) get_option( 'spd_page_map', array() );
foreach ( $map as $key=>$page_id ) { if ( sanitize_key($key) === get_post_meta(absint($page_id),'_spd_managed_page_key',true) ) { wp_delete_post(absint($page_id),true); } }
foreach ( array( 'spd_page_map','spd_founder_profile','spd_founder_user_id','spd_version','spd_db_version','spd_purge_on_uninstall' ) as $option ) { delete_option( $option ); }
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}spd_audit_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$admin=get_role('administrator');if($admin){$admin->remove_cap('manage_sabri_doctors');}
