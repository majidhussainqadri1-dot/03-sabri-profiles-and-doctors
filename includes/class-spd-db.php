<?php
defined( 'ABSPATH' ) || exit;

final class SPD_DB {
	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency' );
		$name    = sanitize_key( $name );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'spd_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$profiles = self::table( 'profiles' );
		$fields = self::table( 'fields' );
		$slugs = self::table( 'slugs' );
		$media = self::table( 'media' );
		$reports = self::table( 'reports' );
		$events = self::table( 'events' );
		$idempotency = self::table( 'idempotency' );

		$sql = array();
		$sql[] = "CREATE TABLE {$profiles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			public_id char(36) NOT NULL,
			slug varchar(200) NOT NULL,
			profile_type varchar(32) NOT NULL DEFAULT 'member',
			state varchar(32) NOT NULL DEFAULT 'incomplete',
			locale varchar(20) NOT NULL DEFAULT 'en-US',
			bio longtext NULL,
			country varchar(120) NOT NULL DEFAULT '',
			city varchar(120) NOT NULL DEFAULT '',
			languages text NULL,
			studied_books longtext NULL,
			avatar_id bigint(20) unsigned NOT NULL DEFAULT 0,
			cover_id bigint(20) unsigned NOT NULL DEFAULT 0,
			avatar_focal_x decimal(5,2) NOT NULL DEFAULT 50.00,
			avatar_focal_y decimal(5,2) NOT NULL DEFAULT 50.00,
			cover_focal_x decimal(5,2) NOT NULL DEFAULT 50.00,
			cover_focal_y decimal(5,2) NOT NULL DEFAULT 50.00,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			UNIQUE KEY public_id (public_id),
			KEY slug (slug),
			KEY state_type (state,profile_type)
		) {$charset};";
		$sql[] = "CREATE TABLE {$fields} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			field_key varchar(64) NOT NULL,
			field_value longtext NULL,
			audience varchar(20) NOT NULL DEFAULT 'private',
			state varchar(24) NOT NULL DEFAULT 'approved',
			source_owner varchar(40) NOT NULL DEFAULT 'file03',
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY profile_field (profile_id,field_key),
			KEY audience_state (audience,state)
		) {$charset};";
		$sql[] = "CREATE TABLE {$slugs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			slug varchar(200) NOT NULL,
			is_current tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY profile_current (profile_id,is_current)
		) {$charset};";
		$sql[] = "CREATE TABLE {$media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			purpose varchar(20) NOT NULL,
			state varchar(24) NOT NULL DEFAULT 'pending_scan',
			alt_text varchar(255) NOT NULL DEFAULT '',
			focal_x decimal(5,2) NOT NULL DEFAULT 50.00,
			focal_y decimal(5,2) NOT NULL DEFAULT 50.00,
			scan_provider varchar(80) NOT NULL DEFAULT '',
			scan_reference varchar(191) NOT NULL DEFAULT '',
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY profile_purpose (profile_id,purpose),
			KEY attachment_id (attachment_id),
			KEY state (state)
		) {$charset};";
		$sql[] = "CREATE TABLE {$reports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_uuid char(36) NOT NULL,
			profile_id bigint(20) unsigned NOT NULL,
			reporter_user_id bigint(20) unsigned NOT NULL,
			reason varchar(40) NOT NULL,
			details text NULL,
			status varchar(24) NOT NULL DEFAULT 'submitted',
			severity varchar(16) NOT NULL DEFAULT 'normal',
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY report_uuid (report_uuid),
			KEY profile_status (profile_id,status),
			KEY reporter_created (reporter_user_id,created_at)
		) {$charset};";
		$sql[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			event_name varchar(100) NOT NULL,
			aggregate_type varchar(40) NOT NULL,
			aggregate_id varchar(80) NOT NULL,
			payload longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			created_at datetime NOT NULL,
			delivered_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			KEY delivery (status,available_at),
			KEY aggregate (aggregate_type,aggregate_id)
		) {$charset};";
		$sql[] = "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			idempotency_key char(64) NOT NULL,
			actor_id bigint(20) unsigned NOT NULL,
			command varchar(80) NOT NULL,
			request_hash char(64) NOT NULL,
			response_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'started',
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY actor_command_key (actor_id,command,idempotency_key),
			KEY expiry (expires_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		update_option( 'spd_db_version', SPD_DB_VERSION, false );
	}

	public static function tables_exist() {
		global $wpdb;
		foreach ( array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency' ) as $name ) {
			$table = self::table( $name );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				return false;
			}
		}
		return true;
	}

	public static function transaction( callable $callback ) {
		global $wpdb;
		$wpdb->last_error = '';
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = $callback();
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
			if ( $wpdb->last_error ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'spd_database_error', __( 'The profile update could not be completed.', 'sabri-profiles-doctors' ) );
			}
			$wpdb->query( 'COMMIT' );
			return $result;
		} catch ( Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'spd_database_exception', __( 'The profile update could not be completed.', 'sabri-profiles-doctors' ) );
		}
	}
}
