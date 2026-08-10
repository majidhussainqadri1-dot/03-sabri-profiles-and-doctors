<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Non-destructive by default. Destructive removal requires both controls.
if ( ! defined( 'SPD_ALLOW_DESTRUCTIVE_UNINSTALL' ) || true !== SPD_ALLOW_DESTRUCTIVE_UNINSTALL || ! get_option( 'spd_purge_on_uninstall', false ) ) {
	return;
}

global $wpdb;
foreach ( array( 'spd_dispatch_outbox', 'spd_migrate_profiles_batch', 'spd_retention_cleanup', 'spd_process_media_deletions' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$attachment_refs = array();
$profiles_table  = $wpdb->prefix . 'spd_profiles';
$profiles_exist  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profiles_table ) ) === $profiles_table;
$profiles        = $profiles_exist ? $wpdb->get_results( "SELECT user_id,avatar_id,cover_id FROM {$profiles_table}", ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( (array) $profiles as $profile ) {
	foreach ( array( 'avatar' => absint( $profile['avatar_id'] ), 'cover' => absint( $profile['cover_id'] ) ) as $purpose => $attachment_id ) {
		if ( $attachment_id ) { $attachment_refs[ $attachment_id ] = array( 'owner' => absint( $profile['user_id'] ), 'purpose' => $purpose ); }
	}
	delete_user_meta( absint( $profile['user_id'] ), '_spd_approved_projection_snapshot_v2' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_profile_visibility' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_public_contact' );
	delete_user_meta( absint( $profile['user_id'] ), '_spd_v1_migrated' );
}

$deletions_table = $wpdb->prefix . 'spd_deletions';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $deletions_table ) ) === $deletions_table ) {
	$queued = $wpdb->get_results( "SELECT attachment_id,owner_user_id,purpose FROM {$deletions_table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $queued as $row ) {
		$attachment_refs[ absint( $row['attachment_id'] ) ] = array( 'owner' => absint( $row['owner_user_id'] ), 'purpose' => sanitize_key( $row['purpose'] ) );
	}
}

foreach ( $attachment_refs as $attachment_id => $reference ) {
	$owner          = absint( get_post_meta( $attachment_id, '_spd_media_owner_user_id', true ) );
	$stored_purpose = sanitize_key( (string) get_post_meta( $attachment_id, '_spd_media_purpose', true ) );
	if ( $owner === $reference['owner'] && $stored_purpose === $reference['purpose'] ) {
		wp_delete_attachment( absint( $attachment_id ), true );
	}
}

$map = (array) get_option( 'spd_page_map', array() );
foreach ( array( 'founder', 'profile', 'account_profile', 'personal_site', 'private_preview' ) as $key ) {
	$page_id = absint( $map[ $key ] ?? 0 );
	if ( $page_id && $key === get_post_meta( $page_id, '_spd_managed_page_key', true ) ) { wp_delete_post( $page_id, true ); }
}
foreach ( array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency', 'deletions', 'migration_failures', 'professional_submissions', 'profile_delegations', 'report_appeals', 'profile_translations', 'profile_attestations', 'profile_future_state' ) as $name ) {
	$table = $wpdb->prefix . 'spd_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'spd_page_map', 'spd_version', 'spd_db_version', 'spd_contract_version', 'spd_central_schema_version', 'spd_future_schema_version', 'spd_plan_version',
	'spd_safe_mode', 'spd_safe_mode_reason', 'spd_safe_mode_changed_at',
	'spd_migration_cursor', 'spd_migration_completed_at', 'spd_migration_traversal_completed_at',
	'spd_last_outbox_run', 'spd_last_retention_run', 'spd_last_repair_at', 'spd_last_reconciliation',
	'spd_reconciliation_required', 'spd_profile_cache_generation',
	'spd_media_privacy_cursor', 'spd_media_privacy_cycle_completed_at',
	'spd_purge_on_uninstall', 'spd_founder_profile_legacy_read_only',
) as $option ) {
	delete_option( $option );
}
