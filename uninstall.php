<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Non-destructive by default. Destructive removal requires both controls.
if ( ! defined( 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' ) || true !== SPD_ALLOW_DESTRUCTIVE_UNINSTALL || ! get_option( 'spd_purge_on_uninstall', false ) ) {
	return;
}

global $wpdb;
foreach ( array( 'spd_dispatch_outbox', 'spd_migrate_profiles_batch', 'spd_retention_cleanup' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$profiles_table = $wpdb->prefix . 'spd_profiles';
$profiles_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profiles_table ) ) === $profiles_table;
$profiles = $profiles_exists ? $wpdb->get_results( "SELECT user_id,avatar_id,cover_id FROM {$profiles_table}", ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( (array) $profiles as $profile ) {
	foreach ( array( 'avatar' => absint( $profile['avatar_id'] ), 'cover' => absint( $profile['cover_id'] ) ) as $purpose => $attachment_id ) {
		$owner = absint( get_post_meta( $attachment_id, '_spd_media_owner_user_id', true ) );
		$stored_purpose = sanitize_key( (string) get_post_meta( $attachment_id, '_spd_media_purpose', true ) );
		if ( $attachment_id && $owner === absint( $profile['user_id'] ) && $purpose === $stored_purpose ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
	delete_user_meta( absint( $profile['user_id'] ), '_spd_approved_projection_snapshot_v2' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_profile_visibility' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_public_contact' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_v1_migrated' );
}

$map = (array) get_option( 'spd_page_map', array() );
foreach ( array( 'founder', 'profile', 'account_profile' ) as $key ) {
	$page_id = absint( $map[ $key ] ?? 0 );
	if ( $page_id && $key === get_post_meta( $page_id, '_spd_managed_page_key', true ) ) {
		wp_delete_post( $page_id, true );
	}
}
foreach ( array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency' ) as $name ) {
	$table = $wpdb->prefix . 'spd_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array( 'spd_page_map', 'spd_version', 'spd_db_version', 'spd_contract_version', 'spd_safe_mode', 'spd_safe_mode_reason', 'spd_safe_mode_changed_at', 'spd_migration_cursor', 'spd_migration_completed_at', 'spd_last_outbox_run', 'spd_last_retention_run', 'spd_last_repair_at', 'spd_purge_on_uninstall', 'spd_founder_profile_legacy_read_only' ) as $option ) {
	delete_option( $option );
}
