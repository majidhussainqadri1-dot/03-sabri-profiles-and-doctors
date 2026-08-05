<?php
defined( 'ABSPATH' ) || exit;

final class SPD_DB {
	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'profiles', 'fields', 'slugs', 'media', 'reports', 'events', 'idempotency', 'deletions', 'migration_failures', 'professional_submissions' );
		$name = sanitize_key( $name );
		return in_array( $name, $allowed, true ) ? $wpdb->prefix . 'spd_' . $name : '';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$profiles=self::table('profiles'); $fields=self::table('fields'); $slugs=self::table('slugs'); $media=self::table('media');
		$reports=self::table('reports'); $events=self::table('events'); $idempotency=self::table('idempotency');
		$deletions=self::table('deletions'); $migration_failures=self::table('migration_failures'); $professional_submissions=self::table('professional_submissions');
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
			PRIMARY KEY (id), UNIQUE KEY user_id (user_id), UNIQUE KEY public_id (public_id), KEY slug (slug), KEY state_type (state,profile_type)
		) {$c};";
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
			PRIMARY KEY (id), UNIQUE KEY profile_field (profile_id,field_key), KEY audience_state (audience,state)
		) {$c};";
		$sql[] = "CREATE TABLE {$slugs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			slug varchar(200) NOT NULL,
			is_current tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY slug (slug), KEY profile_current (profile_id,is_current)
		) {$c};";
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
			PRIMARY KEY (id), UNIQUE KEY profile_purpose (profile_id,purpose), KEY attachment_id (attachment_id), KEY state (state)
		) {$c};";
		$sql[] = "CREATE TABLE {$reports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_uuid char(36) NOT NULL,
			profile_id bigint(20) unsigned NOT NULL,
			reporter_user_id bigint(20) unsigned NOT NULL,
			reason varchar(40) NOT NULL,
			details text NULL,
			decision_note text NULL,
			dedupe_hash char(64) NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'submitted',
			severity varchar(16) NOT NULL DEFAULT 'normal',
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY report_uuid (report_uuid), UNIQUE KEY reporter_dedupe (reporter_user_id,dedupe_hash), KEY profile_status (profile_id,status), KEY reporter_created (reporter_user_id,created_at)
		) {$c};";
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
			lease_token char(64) NOT NULL DEFAULT '',
			lease_expires datetime NULL,
			last_error_code varchar(80) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			delivered_at datetime NULL,
			PRIMARY KEY (id), UNIQUE KEY event_uuid (event_uuid), KEY delivery (status,available_at), KEY lease (status,lease_expires), KEY aggregate (aggregate_type,aggregate_id)
		) {$c};";
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
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY actor_command_key (actor_id,command,idempotency_key), KEY expiry (expires_at)
		) {$c};";
		$sql[] = "CREATE TABLE {$deletions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			deletion_uuid char(36) NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			owner_user_id bigint(20) unsigned NOT NULL,
			purpose varchar(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			lease_token char(64) NOT NULL DEFAULT '',
			lease_expires datetime NULL,
			last_error_code varchar(80) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY (id), UNIQUE KEY deletion_uuid (deletion_uuid), UNIQUE KEY attachment_purpose (attachment_id,purpose), KEY delivery (status,available_at)
		) {$c};";
		$sql[] = "CREATE TABLE {$migration_failures} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			error_code varchar(80) NOT NULL,
			detail_hash char(64) NOT NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'retry',
			next_attempt_at datetime NOT NULL,
			last_attempt_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY user_id (user_id), KEY retry (status,next_attempt_at)
		) {$c};";
		$sql[] = "CREATE TABLE {$professional_submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_uuid char(36) NOT NULL,
			profile_id bigint(20) unsigned NOT NULL,
			submitted_by bigint(20) unsigned NOT NULL,
			payload_json longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'draft',
			owner_reference varchar(191) NOT NULL DEFAULT '',
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY submission_uuid (submission_uuid), KEY profile_state (profile_id,status), KEY submitter (submitted_by,created_at)
		) {$c};";
		foreach ( $sql as $statement ) { dbDelta( $statement ); }
		if ( ! self::tables_exist() ) {
			return new WP_Error( 'spd_schema_install_failed', __( 'One or more File 03 database tables could not be installed or upgraded.', 'sabri-profiles-doctors' ) );
		}
		if ( false === update_option( 'spd_db_version', SPD_DB_VERSION, false ) && SPD_DB_VERSION !== (string) get_option( 'spd_db_version', '' ) ) {
			return new WP_Error( 'spd_schema_version_failed', __( 'The File 03 database version could not be recorded.', 'sabri-profiles-doctors' ) );
		}
		return true;
	}

	public static function tables_exist() {
		global $wpdb;
		foreach ( array( 'profiles','fields','slugs','media','reports','events','idempotency','deletions','migration_failures','professional_submissions' ) as $name ) {
			$table=self::table($name); $found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
			if ( $found !== $table ) { return false; }
		}
		return true;
	}

	public static function transaction( callable $callback ) {
		global $wpdb;
		$wpdb->last_error='';
		if ( false === $wpdb->query('START TRANSACTION') ) { return new WP_Error('spd_transaction_unavailable',__( 'The database transaction could not be started.','sabri-profiles-doctors')); }
		try {
			$result=$callback();
			if ( is_wp_error($result) || $wpdb->last_error ) { $wpdb->query('ROLLBACK'); return is_wp_error($result)?$result:new WP_Error('spd_database_error',__( 'The profile update could not be completed.','sabri-profiles-doctors')); }
			if ( false === $wpdb->query('COMMIT') ) { $wpdb->query('ROLLBACK'); return new WP_Error('spd_commit_failed',__( 'The profile update could not be committed.','sabri-profiles-doctors')); }
			return $result;
		} catch ( Throwable $e ) { $wpdb->query('ROLLBACK'); return new WP_Error('spd_database_exception',__( 'The profile update could not be completed.','sabri-profiles-doctors')); }
	}
}
