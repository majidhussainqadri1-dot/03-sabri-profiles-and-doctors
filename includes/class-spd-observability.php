<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Observability {
	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'spd_dispatch_outbox', array( $this, 'dispatch_outbox' ) );
		add_action( 'spd_migrate_profiles_batch', array( $this, 'migrate_profiles_batch' ) );
		add_action( 'spd_retention_cleanup', array( $this, 'retention_cleanup' ) );
	}

	public function cron_schedules( $schedules ) {
		$schedules['spd_five_minutes'] = array( 'interval' => 300, 'display' => __( 'Every five minutes', 'sabri-profiles-doctors' ) );
		return $schedules;
	}

	public static function safe_mode() {
		return (bool) get_option( 'spd_safe_mode', false );
	}

	public static function set_safe_mode( $enabled, $reason = '' ) {
		update_option( 'spd_safe_mode', (bool) $enabled, false );
		update_option( 'spd_safe_mode_reason', sanitize_text_field( $reason ), false );
		update_option( 'spd_safe_mode_changed_at', SPD_Helpers::now(), false );
		do_action( 'sabri_file24_security_state_changed', 'file03', $enabled ? 'safe_mode' : 'normal', sanitize_text_field( $reason ) );
	}

	public static function health_report() {
		global $wpdb;
		$events = SPD_DB::table( 'events' );
		$reports = SPD_DB::table( 'reports' );
		$pending = SPD_DB::tables_exist() ? absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$events} WHERE status IN ('pending','retry')" ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dead = SPD_DB::tables_exist() ? absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$events} WHERE status='dead'" ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$open_reports = SPD_DB::tables_exist() ? absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$reports} WHERE status NOT IN ('closed','rejected')" ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map = (array) get_option( 'spd_page_map', array() );
		return array(
			'plugin_version'    => SPD_VERSION,
			'db_version'        => get_option( 'spd_db_version', '' ),
			'contract_version'  => SPD_CONTRACT_VERSION,
			'file00'            => SPD_Membership_Adapter::health(),
			'file09'            => SPD_Verification_Adapter::health(),
			'tables'            => SPD_DB::tables_exist() ? 'available' : 'missing',
			'pages'             => array(
				'founder'         => ! empty( $map['founder'] ) && get_post_status( $map['founder'] ) ? 'available' : 'missing',
				'profile'         => ! empty( $map['profile'] ) && get_post_status( $map['profile'] ) ? 'available' : 'missing',
				'account_profile' => ! empty( $map['account_profile'] ) && get_post_status( $map['account_profile'] ) ? 'available' : 'missing',
			),
			'cron'              => array(
				'outbox'    => wp_next_scheduled( 'spd_dispatch_outbox' ) ? 'scheduled' : 'missing',
				'migration' => wp_next_scheduled( 'spd_migrate_profiles_batch' ) ? 'scheduled' : 'idle',
				'retention' => wp_next_scheduled( 'spd_retention_cleanup' ) ? 'scheduled' : 'missing',
			),
			'outbox_pending'    => $pending,
			'outbox_dead'       => $dead,
			'open_reports'      => $open_reports,
			'safe_mode'         => self::safe_mode(),
			'safe_mode_reason'  => get_option( 'spd_safe_mode_reason', '' ),
			'migration_cursor'  => absint( get_option( 'spd_migration_cursor', 0 ) ),
			'last_outbox_run'   => get_option( 'spd_last_outbox_run', '' ),
			'last_retention_run'=> get_option( 'spd_last_retention_run', '' ),
		);
	}

	public function dispatch_outbox() {
		global $wpdb;
		if ( ! SPD_DB::tables_exist() ) {
			return;
		}
		$table = SPD_DB::table( 'events' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status IN ('pending','retry') AND available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 50", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			try {
				do_action( 'spd_outbox_event_v1', $row['event_name'], $payload, $row );
				do_action( 'sabri_platform_event', $row['event_name'], $payload, array( 'owner' => 'file03', 'event_uuid' => $row['event_uuid'] ) );
				$wpdb->update( $table, array( 'status' => 'delivered', 'attempts' => absint( $row['attempts'] ) + 1, 'delivered_at' => SPD_Helpers::now() ), array( 'id' => absint( $row['id'] ) ) );
			} catch ( Throwable $exception ) {
				$attempts = absint( $row['attempts'] ) + 1;
				$status = $attempts >= 8 ? 'dead' : 'retry';
				$delay = min( HOUR_IN_SECONDS, 30 * ( 2 ** min( $attempts, 6 ) ) );
				$wpdb->update( $table, array( 'status' => $status, 'attempts' => $attempts, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ) ), array( 'id' => absint( $row['id'] ) ) );
			}
		}
		update_option( 'spd_last_outbox_run', SPD_Helpers::now(), false );
	}

	public function migrate_profiles_batch() {
		if ( get_transient( 'spd_migration_lock' ) ) {
			return;
		}
		set_transient( 'spd_migration_lock', 1, 10 * MINUTE_IN_SECONDS );
		global $wpdb;
		$cursor = absint( get_option( 'spd_migration_cursor', 0 ) );
		$users = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID ASC LIMIT 100", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $users as $user_id ) {
			SPD_Profile_Repository::instance()->ensure_for_user( $user_id );
			$this->migrate_legacy_projection( $user_id );
			$cursor = max( $cursor, absint( $user_id ) );
		}
		update_option( 'spd_migration_cursor', $cursor, false );
		delete_transient( 'spd_migration_lock' );
		if ( count( $users ) < 100 ) {
			wp_clear_scheduled_hook( 'spd_migrate_profiles_batch' );
			update_option( 'spd_migration_completed_at', SPD_Helpers::now(), false );
		}
	}

	private function migrate_legacy_projection( $user_id ) {
		global $wpdb;
		$repo = SPD_Profile_Repository::instance();
		$profile = $repo->find_by_user_id( $user_id, false );
		if ( ! $profile || get_user_meta( $user_id, '_spd_v1_migrated', true ) ) {
			return;
		}
		$legacy_visibility = sanitize_key( (string) get_user_meta( $user_id, '_spd_profile_visibility', true ) );
		if ( in_array( $legacy_visibility, SPD_Authorization::allowed_audiences(), true ) ) {
			$fields = SPD_DB::table( 'fields' );
			$wpdb->update( $fields, array( 'audience' => $legacy_visibility, 'updated_at' => SPD_Helpers::now() ), array( 'profile_id' => $profile['id'], 'field_key' => 'profile_visibility' ) );
		}
		if ( '1' === (string) get_user_meta( $user_id, '_spd_public_contact', true ) && ! SPD_Membership_Adapter::is_minor( $user_id ) ) {
			$fields = SPD_DB::table( 'fields' );
			foreach ( array( 'phone', 'whatsapp' ) as $key ) {
				$wpdb->update( $fields, array( 'audience' => 'public', 'updated_at' => SPD_Helpers::now() ), array( 'profile_id' => $profile['id'], 'field_key' => $key ) );
			}
		}
		update_user_meta( $user_id, '_spd_v1_migrated', SPD_Helpers::now() );
	}

	public function retention_cleanup() {
		global $wpdb;
		if ( ! SPD_DB::tables_exist() ) {
			return;
		}
		$events = SPD_DB::table( 'events' );
		$idempotency = SPD_DB::table( 'idempotency' );
		$reports = SPD_DB::table( 'reports' );
		$wpdb->query( "DELETE FROM {$idempotency} WHERE expires_at < UTC_TIMESTAMP()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$reports} SET reporter_user_id=0 WHERE status='closed' AND updated_at < (UTC_TIMESTAMP() - INTERVAL 365 DAY)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$events} WHERE status='delivered' AND created_at < (UTC_TIMESTAMP() - INTERVAL 730 DAY) AND event_name NOT IN ('ProfileTombstoned.v1','ProfileReported.v1')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		update_option( 'spd_last_retention_run', SPD_Helpers::now(), false );
	}

	public static function repair( $execute = false ) {
		$plan = array();
		if ( ! SPD_DB::tables_exist() ) {
			$plan[] = 'install_module_tables';
		}
		$map = (array) get_option( 'spd_page_map', array() );
		foreach ( array( 'founder', 'profile', 'account_profile' ) as $key ) {
			if ( empty( $map[ $key ] ) || ! get_post_status( absint( $map[ $key ] ) ) ) {
				$plan[] = 'restore_page:' . $key;
			}
		}
		if ( ! wp_next_scheduled( 'spd_dispatch_outbox' ) ) {
			$plan[] = 'schedule_outbox';
		}
		if ( ! wp_next_scheduled( 'spd_retention_cleanup' ) ) {
			$plan[] = 'schedule_retention';
		}
		if ( $execute && $plan ) {
			SPD_Activator::repair_owned_resources();
		}
		return array( 'execute' => (bool) $execute, 'actions' => $plan, 'companion_data_mutated' => false );
	}
}
