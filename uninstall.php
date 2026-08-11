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
delete_transient( 'spd_migration_lock' );

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
	foreach ( (array) $queued as $row ) {
		$attachment_refs[ absint( $row['attachment_id'] ) ] = array( 'owner' => absint( $row['owner_user_id'] ), 'purpose' => sanitize_key( $row['purpose'] ) );
	}
}

// Recover File-03-owned attachments from their immutable ownership/purpose
// markers too. This closes destructive-cleanup gaps when profile/deletion rows
// were lost in a partial schema failure. Both markers must be present and the
// purpose must remain one of File 03's two owned public-image purposes.
$owned_media_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND CAST(meta_value AS UNSIGNED)>0",
		'_spd_media_owner_user_id'
	)
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( (array) $owned_media_ids as $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$owner = absint( get_post_meta( $attachment_id, '_spd_media_owner_user_id', true ) );
	$purpose = sanitize_key( (string) get_post_meta( $attachment_id, '_spd_media_purpose', true ) );
	$post = get_post( $attachment_id );
	if ( $attachment_id && $owner && in_array( $purpose, array( 'avatar', 'cover' ), true ) && $post instanceof WP_Post && 'attachment' === $post->post_type ) {
		$attachment_refs[ $attachment_id ] = array( 'owner' => $owner, 'purpose' => $purpose );
	}
}

foreach ( $attachment_refs as $attachment_id => $reference ) {
	$owner          = absint( get_post_meta( $attachment_id, '_spd_media_owner_user_id', true ) );
	$stored_purpose = sanitize_key( (string) get_post_meta( $attachment_id, '_spd_media_purpose', true ) );
	if ( $owner === $reference['owner'] && $stored_purpose === $reference['purpose'] ) {
		wp_delete_attachment( absint( $attachment_id ), true );
	}
}

$managed_keys = array( 'founder', 'profile', 'account_profile', 'personal_site', 'private_preview' );
$managed_ids = array();
$map = (array) get_option( 'spd_page_map', array() );
foreach ( $managed_keys as $key ) {
	$page_id = absint( $map[ $key ] ?? 0 );
	if ( $page_id && $key === get_post_meta( $page_id, '_spd_managed_page_key', true ) ) { $managed_ids[ $page_id ] = true; }
}
// Recover File-03-owned pages by their ownership marker as well as the map. This
// makes destructive cleanup complete even when the page-map option was lost.
$placeholders = implode( ',', array_fill( 0, count( $managed_keys ), '%s' ) );
$sql = $wpdb->prepare(
	"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_spd_managed_page_key' AND meta_value IN ({$placeholders})",
	...$managed_keys
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$marked_ids = $wpdb->get_col( $sql );
foreach ( (array) $marked_ids as $page_id ) {
	$page_id = absint( $page_id );
	$key = sanitize_key( (string) get_post_meta( $page_id, '_spd_managed_page_key', true ) );
	$post = get_post( $page_id );
	if ( $page_id && in_array( $key, $managed_keys, true ) && $post instanceof WP_Post && 'page' === $post->post_type ) { $managed_ids[ $page_id ] = true; }
}
foreach ( array_keys( $managed_ids ) as $page_id ) { wp_delete_post( absint( $page_id ), true ); }

foreach ( array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency', 'deletions', 'migration_failures', 'professional_submissions', 'profile_delegations', 'report_appeals', 'profile_translations', 'profile_attestations', 'profile_future_state' ) as $name ) {
	$table = $wpdb->prefix . 'spd_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'spd_page_map', 'spd_version', 'spd_db_version', 'spd_contract_version', 'spd_central_schema_version', 'spd_future_schema_version', 'spd_plan_version',
	'spd_safe_mode', 'spd_safe_mode_reason', 'spd_safe_mode_changed_at',
	'spd_migration_cursor', 'spd_migration_completed_at', 'spd_migration_traversal_completed_at', 'spd_last_migration_integrity_error',
	'spd_last_outbox_run', 'spd_last_retention_run', 'spd_last_retention_error', 'spd_last_repair_at', 'spd_last_reconciliation',
	'spd_reconciliation_required', 'spd_profile_cache_generation',
	'spd_media_privacy_cursor', 'spd_media_privacy_cycle_completed_at', 'spd_last_media_queue_error',
	'spd_purge_on_uninstall', 'spd_founder_profile_legacy_read_only',
) as $option ) {
	delete_option( $option );
}

// Corrective synchronization/rate-limiter keys are dynamic. They are strictly
// File-03-owned prefixes and are removed only inside the explicit destructive
// uninstall gate above.
$lock_like = $wpdb->esc_like( 'spd_lock_' ) . '%';
$rate_like = $wpdb->esc_like( '_transient_spd_rate_' ) . '%';
$rate_timeout_like = $wpdb->esc_like( '_transient_timeout_spd_rate_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $lock_like, $rate_like, $rate_timeout_like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
