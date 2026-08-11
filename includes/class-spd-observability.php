<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Observability {
	const MIGRATION_MAX_ATTEMPTS = 8;
	const OUTBOX_MAX_ATTEMPTS    = 8;

	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'spd_dispatch_outbox', array( $this, 'dispatch_outbox' ) );
		add_action( 'spd_migrate_profiles_batch', array( $this, 'migrate_profiles_batch' ) );
		add_action( 'spd_retention_cleanup', array( $this, 'retention_cleanup' ) );
		add_action( 'spd_process_media_deletions', array( $this, 'process_media_deletions' ) );
	}

	public function cron_schedules( $schedules ) {
		$schedules['spd_five_minutes'] = array( 'interval' => 300, 'display' => __( 'Every five minutes', 'sabri-profiles-doctors' ) );
		return $schedules;
	}

	public static function safe_mode() { return (bool) get_option( 'spd_safe_mode', false ); }

	public static function set_safe_mode( $enabled, $reason = '' ) {
		$enabled = (bool) $enabled;
		$reason  = sanitize_text_field( $reason );
		if ( '' === $reason ) { return new WP_Error( 'spd_safe_mode_reason_required', __( 'A safe-mode reason is required.', 'sabri-profiles-doctors' ) ); }
		$changed_at = SPD_Helpers::now();
		update_option( 'spd_safe_mode', $enabled, false );
		update_option( 'spd_safe_mode_reason', $reason, false );
		update_option( 'spd_safe_mode_changed_at', $changed_at, false );
		if ( (bool) get_option( 'spd_safe_mode', false ) !== $enabled || (string) get_option( 'spd_safe_mode_reason', '' ) !== $reason ) {
			return new WP_Error( 'spd_safe_mode_persist_failed', __( 'The safe-mode state could not be persisted.', 'sabri-profiles-doctors' ) );
		}
		do_action( 'sabri_file24_security_state_changed', 'file03', $enabled ? 'safe_mode' : 'normal', $reason );
		return true;
	}

	private static function operational_count( $sql, &$query_error ) {
		global $wpdb;
		$wpdb->last_error = '';
		$value = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->last_error ) { $query_error = true; return null; }
		return absint( $value );
	}

	private static function redacted_error_record( $option ) {
		$record = get_option( $option, array() );
		if ( ! is_array( $record ) || empty( $record['code'] ) ) { return array(); }
		return array(
			'code' => sanitize_key( (string) $record['code'] ),
			'at'   => sanitize_text_field( (string) ( $record['at'] ?? '' ) ),
		);
	}

	public static function health_report() {
		global $wpdb;
		$exists    = SPD_DB::tables_exist();
		$events    = SPD_DB::table( 'events' );
		$reports   = SPD_DB::table( 'reports' );
		$deletions = SPD_DB::table( 'deletions' );
		$failures  = SPD_DB::table( 'migration_failures' );
		$map       = (array) get_option( 'spd_page_map', array() );
		$query_error = false;
		$counts = array(
			'outbox_pending' => null, 'outbox_dead' => null, 'open_reports' => null,
			'media_deletions_pending' => null, 'media_deletions_dead' => null,
			'migration_retry' => null, 'migration_dead' => null,
		);
		if ( $exists ) {
			$counts['outbox_pending'] = self::operational_count( "SELECT COUNT(*) FROM {$events} WHERE status IN ('pending','retry','processing')", $query_error );
			$counts['outbox_dead'] = self::operational_count( "SELECT COUNT(*) FROM {$events} WHERE status='dead'", $query_error );
			$counts['open_reports'] = self::operational_count( "SELECT COUNT(*) FROM {$reports} WHERE status NOT IN ('closed','rejected')", $query_error );
			$counts['media_deletions_pending'] = self::operational_count( "SELECT COUNT(*) FROM {$deletions} WHERE status IN ('pending','retry','processing')", $query_error );
			$counts['media_deletions_dead'] = self::operational_count( "SELECT COUNT(*) FROM {$deletions} WHERE status='dead'", $query_error );
			$counts['migration_retry'] = self::operational_count( "SELECT COUNT(*) FROM {$failures} WHERE status='retry'", $query_error );
			$counts['migration_dead'] = self::operational_count( "SELECT COUNT(*) FROM {$failures} WHERE status='dead'", $query_error );
		}
		return array(
			'plugin_version' => SPD_VERSION,
			'db_version' => get_option( 'spd_db_version', '' ),
			'contract_version' => SPD_CONTRACT_VERSION,
			'file00' => SPD_Membership_Adapter::health(),
			'file09' => SPD_Verification_Adapter::health(),
			'tables' => $exists ? 'available' : 'missing',
			'health_query_status' => ! $exists ? 'not_applicable' : ( $query_error ? 'degraded' : 'available' ),
			'pages' => array(
				'founder' => ! empty( $map['founder'] ) && get_post_status( $map['founder'] ) ? 'available' : 'missing',
				'profile' => ! empty( $map['profile'] ) && get_post_status( $map['profile'] ) ? 'available' : 'missing',
				'account_profile' => ! empty( $map['account_profile'] ) && get_post_status( $map['account_profile'] ) ? 'available' : 'missing',
			),
			'cron' => array(
				'outbox' => wp_next_scheduled( 'spd_dispatch_outbox' ) ? 'scheduled' : 'missing',
				'migration' => wp_next_scheduled( 'spd_migrate_profiles_batch' ) ? 'scheduled' : 'idle',
				'retention' => wp_next_scheduled( 'spd_retention_cleanup' ) ? 'scheduled' : 'missing',
				'media_deletions' => wp_next_scheduled( 'spd_process_media_deletions' ) ? 'scheduled' : 'missing',
			),
			'outbox_pending' => $counts['outbox_pending'],
			'outbox_dead' => $counts['outbox_dead'],
			'open_reports' => $counts['open_reports'],
			'media_deletions_pending' => $counts['media_deletions_pending'],
			'media_deletions_dead' => $counts['media_deletions_dead'],
			'migration_retry' => $counts['migration_retry'],
			'migration_dead' => $counts['migration_dead'],
			'provider_health' => self::provider_health(),
			'active_errors' => array_filter( array(
				'outbox' => self::redacted_error_record( 'spd_last_outbox_error' ),
				'media' => self::redacted_error_record( 'spd_last_media_queue_error' ),
				'retention' => self::redacted_error_record( 'spd_last_retention_error' ),
				'migration' => self::redacted_error_record( 'spd_last_migration_error' ),
				'migration_integrity' => self::redacted_error_record( 'spd_last_migration_integrity_error' ),
			) ),
			'safe_mode' => self::safe_mode(),
			'safe_mode_reason' => get_option( 'spd_safe_mode_reason', '' ),
			'migration_cursor' => absint( get_option( 'spd_migration_cursor', 0 ) ),
			'migration_traversed_at' => get_option( 'spd_migration_traversal_completed_at', '' ),
			'migration_completed_at' => get_option( 'spd_migration_completed_at', '' ),
			'cache_generation' => SPD_Profile_Repository::cache_generation(),
			'reconciliation_required' => (bool) get_option( 'spd_reconciliation_required', false ),
			'last_outbox_run' => get_option( 'spd_last_outbox_run', '' ),
			'last_retention_run' => get_option( 'spd_last_retention_run', '' ),
			'last_reconciliation' => get_option( 'spd_last_reconciliation', '' ),
		);
	}

	private static function provider_health() {
		$out = array();
		foreach ( SPD_Timeline::providers() as $key => $definition ) {
			$filter = is_array( $definition ) ? ( $definition['availability_filter'] ?? '' ) : '';
			$value = $filter ? apply_filters( $filter, null, 0, SPD_CONTRACT_VERSION ) : null;
			$out[ sanitize_key( $key ) ] = is_array( $value ) ? sanitize_key( (string) ( $value['status'] ?? 'invalid' ) ) : 'missing';
		}
		return $out;
	}

	public function dispatch_outbox() {
		global $wpdb;
		if ( ! SPD_DB::tables_exist() ) { return; }
		$table = SPD_DB::table( 'events' );
		$wpdb->query( "UPDATE {$table} SET status='retry',lease_token='',lease_expires=NULL,available_at=UTC_TIMESTAMP(),last_error_code='lease_expired' WHERE status='processing' AND lease_expires<UTC_TIMESTAMP()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $ids as $id ) {
			$token = hash( 'sha256', SPD_Helpers::trace_id() . ':' . absint( $id ) );
			$lease = gmdate( 'Y-m-d H:i:s', time() + 300 );
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='processing',lease_token=%s,lease_expires=%s WHERE id=%d AND status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP()", $token, $lease, absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $claimed ) { continue; }
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND lease_token=%s LIMIT 1", absint( $id ), $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) { continue; }
			$payload = json_decode( $row['payload'], true );
			$attempts = absint( $row['attempts'] ) + 1;
			if ( ! is_array( $payload ) ) { $this->fail_outbox_claim( $row, $token, $attempts, 'invalid_payload' ); continue; }
			try {
				do_action( 'spd_outbox_event_v1', $row['event_name'], $payload, $row );
				do_action( 'sabri_platform_event', $row['event_name'], $payload, array( 'owner' => 'file03', 'event_uuid' => $row['event_uuid'] ) );
				$wpdb->update( $table, array( 'status' => 'delivered', 'attempts' => $attempts, 'delivered_at' => SPD_Helpers::now(), 'lease_token' => '', 'lease_expires' => null, 'last_error_code' => '' ), array( 'id' => absint( $id ), 'lease_token' => $token ) );
			} catch ( Throwable $exception ) { $this->fail_outbox_claim( $row, $token, $attempts, sanitize_key( get_class( $exception ) ) ); }
		}
		update_option( 'spd_last_outbox_run', SPD_Helpers::now(), false );
	}

	private function fail_outbox_claim( array $row, $token, $attempts, $error_code ) {
		global $wpdb;
		$status = $attempts >= self::OUTBOX_MAX_ATTEMPTS ? 'dead' : 'retry';
		$delay = min( HOUR_IN_SECONDS, 30 * ( 2 ** min( $attempts, 6 ) ) );
		$wpdb->update( SPD_DB::table( 'events' ), array( 'status' => $status, 'attempts' => $attempts, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ), 'lease_token' => '', 'lease_expires' => null, 'last_error_code' => sanitize_key( $error_code ) ), array( 'id' => absint( $row['id'] ), 'lease_token' => $token ) );
	}

	public static function requeue_outbox( $event_uuid, $actor_id, $reason ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 500 );
		if ( ! SPD_Membership_Adapter::can_operate_profiles( $actor_id ) || ! $reason ) { return false; }
		$table = SPD_DB::table( 'events' );
		$event_uuid = sanitize_text_field( $event_uuid );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='retry',attempts=0,available_at=UTC_TIMESTAMP(),lease_token='',lease_expires=NULL,last_error_code='manual_requeue' WHERE event_uuid=%s AND status='dead'", $event_uuid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === $updated ) {
			do_action( 'spd_operational_recovery', array( 'queue' => 'outbox', 'reference' => $event_uuid, 'actor_id' => $actor_id, 'reason' => $reason, 'at' => SPD_Helpers::now() ) );
			return true;
		}
		return false;
	}

	public function migrate_profiles_batch() {
		if ( get_transient( 'spd_migration_lock' ) || ! SPD_DB::tables_exist() ) { return; }
		set_transient( 'spd_migration_lock', 1, 10 * MINUTE_IN_SECONDS );
		global $wpdb;
		try {
			$cursor = absint( get_option( 'spd_migration_cursor', 0 ) );
			$users = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT 100", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $users as $user_id ) {
				$user_id = absint( $user_id );
				$failure = $this->migration_failure( $user_id );
				if ( $failure && 'retry' === $failure['status'] && strtotime( $failure['next_attempt_at'] . ' UTC' ) > time() ) { break; }
				if ( $failure && 'dead' === $failure['status'] ) { $cursor = $user_id; update_option( 'spd_migration_cursor', $cursor, false ); continue; }
				$result = $this->migrate_one_user( $user_id );
				if ( is_wp_error( $result ) ) {
					$status = $this->record_migration_failure( $user_id, $result );
					if ( 'dead' === $status ) { $cursor = $user_id; update_option( 'spd_migration_cursor', $cursor, false ); continue; }
					break;
				}
				$this->clear_migration_failure( $user_id );
				$cursor = $user_id;
				update_option( 'spd_migration_cursor', $cursor, false );
			}
			if ( count( $users ) < 100 ) {
				update_option( 'spd_migration_traversal_completed_at', SPD_Helpers::now(), false );
				$retry = absint( $wpdb->get_var( "SELECT COUNT(*) FROM " . SPD_DB::table( 'migration_failures' ) . " WHERE status='retry'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dead = absint( $wpdb->get_var( "SELECT COUNT(*) FROM " . SPD_DB::table( 'migration_failures' ) . " WHERE status='dead'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( ! $retry ) { wp_clear_scheduled_hook( 'spd_migrate_profiles_batch' ); }
				if ( ! $retry && ! $dead ) { update_option( 'spd_migration_completed_at', SPD_Helpers::now(), false ); } else { delete_option( 'spd_migration_completed_at' ); }
			}
		} finally { delete_transient( 'spd_migration_lock' ); }
	}

	private function migrate_one_user( $user_id ) {
		$profile = SPD_Profile_Repository::instance()->ensure_for_user( $user_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		return $this->migrate_legacy_projection( $user_id );
	}

	private function migration_failure( $user_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SPD_DB::table( 'migration_failures' ) . " WHERE user_id=%d LIMIT 1", absint( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : array();
	}

	private function record_migration_failure( $user_id, WP_Error $error ) {
		global $wpdb;
		$table = SPD_DB::table( 'migration_failures' );
		$now = SPD_Helpers::now();
		$old = $this->migration_failure( $user_id );
		$count = absint( $old['attempts'] ?? 0 ) + 1;
		$status = $count >= self::MIGRATION_MAX_ATTEMPTS ? 'dead' : 'retry';
		$delay = min( DAY_IN_SECONDS, 300 * ( 2 ** min( $count - 1, 7 ) ) );
		$hash = hash( 'sha256', $error->get_error_code() . ':' . $error->get_error_message() );
		$query = $wpdb->prepare( "INSERT INTO {$table} (user_id,error_code,detail_hash,attempts,status,next_attempt_at,last_attempt_at) VALUES (%d,%s,%s,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE error_code=VALUES(error_code),detail_hash=VALUES(detail_hash),attempts=VALUES(attempts),status=VALUES(status),next_attempt_at=VALUES(next_attempt_at),last_attempt_at=VALUES(last_attempt_at)", absint( $user_id ), sanitize_key( $error->get_error_code() ), $hash, $count, $status, gmdate( 'Y-m-d H:i:s', time() + $delay ), $now );
		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'dead' === $status ) { do_action( 'sabri_file24_migration_quarantined', array( 'owner' => 'file03', 'user_id' => absint( $user_id ), 'error_code' => sanitize_key( $error->get_error_code() ), 'attempts' => $count ) ); }
		return $status;
	}

	private function clear_migration_failure( $user_id ) { global $wpdb; $wpdb->delete( SPD_DB::table( 'migration_failures' ), array( 'user_id' => absint( $user_id ) ) ); }

	public static function requeue_migration_user( $user_id, $actor_id, $reason ) {
		global $wpdb;
		$user_id = absint( $user_id ); $actor_id = absint( $actor_id ); $reason = SPD_Helpers::sanitize_multiline( $reason, 500 );
		if ( ! $user_id || ! $reason || ! SPD_Membership_Adapter::can_operate_profiles( $actor_id ) ) { return false; }
		$table = SPD_DB::table( 'migration_failures' );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='retry',attempts=0,next_attempt_at=UTC_TIMESTAMP(),last_attempt_at=UTC_TIMESTAMP() WHERE user_id=%d AND status='dead'", $user_id ) );
		if ( 1 !== $updated ) { return false; }
		$current = absint( get_option( 'spd_migration_cursor', 0 ) );
		if ( $current >= $user_id ) { update_option( 'spd_migration_cursor', max( 0, $user_id - 1 ), false ); }
		delete_option( 'spd_migration_completed_at' ); delete_option( 'spd_migration_traversal_completed_at' );
		if ( ! wp_next_scheduled( 'spd_migrate_profiles_batch' ) ) { wp_schedule_event( time() + 60, 'spd_five_minutes', 'spd_migrate_profiles_batch' ); }
		do_action( 'spd_operational_recovery', array( 'queue' => 'migration', 'reference' => $user_id, 'actor_id' => $actor_id, 'reason' => $reason, 'at' => SPD_Helpers::now() ) );
		return true;
	}

	private function migrate_legacy_projection( $user_id ) {
		global $wpdb;
		$repo = SPD_Profile_Repository::instance();
		$profile = $repo->find_by_user_id( $user_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_migration_profile_missing', __( 'The profile could not be loaded during migration.', 'sabri-profiles-doctors' ) ); }
		if ( get_user_meta( $user_id, '_spd_v1_migrated', true ) ) { return true; }
		$legacy = sanitize_key( (string) get_user_meta( $user_id, '_spd_profile_visibility', true ) );
		if ( in_array( $legacy, SPD_Authorization::allowed_audiences(), true ) ) {
			$claims = SPD_Membership_Adapter::claims( $user_id );
			$safe_audience = 'private';
			if ( 'founder' === $profile['profile_type'] && SPD_Membership_Adapter::is_founder( $user_id ) ) { $safe_audience = 'public'; }
			elseif ( $claims && ! empty( $claims['eligible'] ) && empty( $claims['suspended'] ) ) {
				if ( 'public' === $legacy && ! empty( $claims['public_profile_allowed'] ) && SPD_Membership_Adapter::public_profile_age_eligible( $user_id ) ) { $safe_audience = 'public'; }
				elseif ( 'members' === $legacy ) { $safe_audience = 'members'; }
				elseif ( 'contacts' === $legacy && empty( $claims['is_minor'] ) ) { $safe_audience = 'contacts'; }
			}
			$ok = $wpdb->update( SPD_DB::table( 'fields' ), array( 'audience' => $safe_audience, 'updated_at' => SPD_Helpers::now() ), array( 'profile_id' => $profile['id'], 'field_key' => 'profile_visibility' ) );
			if ( false === $ok ) { return new WP_Error( 'spd_migration_visibility_failed', __( 'Legacy visibility could not be migrated.', 'sabri-profiles-doctors' ) ); }
		}
		if ( '1' === (string) get_user_meta( $user_id, '_spd_public_contact', true ) && ! SPD_Membership_Adapter::is_minor( $user_id ) ) {
			foreach ( array( 'phone', 'whatsapp' ) as $key ) {
				$ok = $wpdb->update( SPD_DB::table( 'fields' ), array( 'audience' => 'public', 'updated_at' => SPD_Helpers::now() ), array( 'profile_id' => $profile['id'], 'field_key' => $key ) );
				if ( false === $ok ) { return new WP_Error( 'spd_migration_contact_failed', __( 'Legacy contact visibility could not be migrated.', 'sabri-profiles-doctors' ) ); }
			}
		}
		if ( false === update_user_meta( $user_id, '_spd_v1_migrated', SPD_Helpers::now() ) ) { return new WP_Error( 'spd_migration_marker_failed', __( 'Migration completion could not be recorded.', 'sabri-profiles-doctors' ) ); }
		return true;
	}

	public function process_media_deletions() { SPD_Media::reconcile_storage_privacy( 100 ); SPD_Media::process_deletion_queue( 50 ); }

	public function retention_cleanup() {
		global $wpdb;
		if ( ! SPD_DB::tables_exist() ) { return; }
		$events = SPD_DB::table( 'events' ); $idempotency = SPD_DB::table( 'idempotency' ); $reports = SPD_DB::table( 'reports' );
		$wpdb->query( "DELETE FROM {$idempotency} WHERE expires_at<UTC_TIMESTAMP()" );
		$wpdb->query( "UPDATE {$reports} SET reporter_user_id=0,details='',decision_note='',dedupe_hash=SHA2(CONCAT(report_uuid,':retained'),256) WHERE reporter_user_id<>0 AND status IN ('closed','rejected') AND updated_at<(UTC_TIMESTAMP()-INTERVAL 365 DAY)" );
		$wpdb->query( "DELETE FROM {$events} WHERE status='delivered' AND created_at<(UTC_TIMESTAMP()-INTERVAL 730 DAY) AND event_name NOT IN ('ProfileTombstoned.v1','ProfileReported.v1','ProfileReportReviewed.v1')" );
		update_option( 'spd_last_retention_run', SPD_Helpers::now(), false );
	}

	public static function repair( $execute = false ) {
		global $wpdb;
		$plan = array();
		if ( ! SPD_DB::tables_exist() ) { $plan[] = 'install_or_upgrade_module_tables'; }
		$map = (array) get_option( 'spd_page_map', array() );
		foreach ( array( 'founder', 'profile', 'account_profile' ) as $key ) { if ( empty( $map[ $key ] ) || ! get_post_status( absint( $map[ $key ] ) ) ) { $plan[] = 'restore_page:' . $key; } }
		foreach ( array( 'spd_dispatch_outbox' => 'schedule_outbox', 'spd_retention_cleanup' => 'schedule_retention', 'spd_process_media_deletions' => 'schedule_media_deletions', 'spd_migrate_profiles_batch' => 'schedule_migration' ) as $hook => $action ) { if ( ! wp_next_scheduled( $hook ) ) { $plan[] = $action; } }
		$diagnostic_error = false;
		if ( SPD_DB::tables_exist() ) {
			$events = SPD_DB::table( 'events' ); $deletions = SPD_DB::table( 'deletions' ); $failures = SPD_DB::table( 'migration_failures' );
			$outbox_dead = self::operational_count( "SELECT COUNT(*) FROM {$events} WHERE status='dead'", $diagnostic_error );
			$media_dead = self::operational_count( "SELECT COUNT(*) FROM {$deletions} WHERE status='dead'", $diagnostic_error );
			$migration_failed = self::operational_count( "SELECT COUNT(*) FROM {$failures} WHERE status IN ('retry','dead')", $diagnostic_error );
			if ( $diagnostic_error ) { $plan[] = 'diagnose_database_query_failure'; }
			if ( null !== $outbox_dead && $outbox_dead ) { $plan[] = 'inspect_dead_letter_outbox'; }
			if ( null !== $media_dead && $media_dead ) { $plan[] = 'inspect_dead_media_deletions'; }
			if ( null !== $migration_failed && $migration_failed ) { $plan[] = 'reconcile_migration_failures'; }
			if ( get_option( 'spd_reconciliation_required', false ) ) { $plan[] = 'purge_profile_dto_and_object_cache'; $plan[] = 'notify_file26_reindex_reconciliation'; }
		}
		$plan = array_values( array_unique( $plan ) );
		if ( $execute && $diagnostic_error ) { return new WP_Error( 'spd_repair_diagnosis_uncertain', __( 'Repair was not executed because File 03 operational database diagnostics are temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( $execute && $plan ) {
			$repair = SPD_Activator::repair_owned_resources();
			if ( is_wp_error( $repair ) ) { return $repair; }
			if ( get_option( 'spd_reconciliation_required', false ) ) {
				if ( function_exists( 'wp_cache_flush_group' ) ) { wp_cache_flush_group( 'spd' ); } else { update_option( 'spd_profile_cache_generation', SPD_Profile_Repository::cache_generation() + 1, false ); }
				do_action( 'sabri_file26_reconcile_profile_index_v1', array( 'owner' => 'file03', 'contract_version' => SPD_CONTRACT_VERSION ) );
				delete_option( 'spd_reconciliation_required' );
				update_option( 'spd_last_reconciliation', SPD_Helpers::now(), false );
			}
		}
		return array( 'execute' => (bool) $execute, 'actions' => $plan, 'companion_data_mutated' => false );
	}
}
