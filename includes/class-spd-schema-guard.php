<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exact owned-schema shape verification.
 * Table existence alone is not sufficient: a partial/deferred migration can
 * leave a table present while required columns or integrity indexes are absent.
 */
final class SPD_Schema_Guard {
	private static function index( $name, array $columns, $unique = false ) {
		return array( 'name' => (string) $name, 'columns' => array_values( $columns ), 'unique' => (bool) $unique );
	}

	private static function shape_ready( $table, array $columns, array $indexes = array() ) {
		global $wpdb;
		$table = (string) $table;
		if ( '' === $table ) { return false; }
		$wpdb->last_error = '';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $wpdb->last_error || $found !== $table ) { return false; }
		$wpdb->last_error = '';
		$column_rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $column_rows ) ) { return false; }
		$present_columns = array();
		foreach ( $column_rows as $row ) { if ( isset( $row['Field'] ) ) { $present_columns[] = (string) $row['Field']; } }
		if ( array_diff( $columns, $present_columns ) ) { return false; }
		if ( ! $indexes ) { return true; }
		$wpdb->last_error = '';
		$index_rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $index_rows ) ) { return false; }
		$present_indexes = array();
		foreach ( $index_rows as $row ) {
			if ( empty( $row['Key_name'] ) || empty( $row['Column_name'] ) ) { continue; }
			$name = (string) $row['Key_name'];
			if ( ! isset( $present_indexes[ $name ] ) ) {
				$present_indexes[ $name ] = array( 'unique' => ! absint( $row['Non_unique'] ?? 1 ), 'columns' => array() );
			}
			$seq = max( 1, absint( $row['Seq_in_index'] ?? 1 ) );
			$present_indexes[ $name ]['columns'][ $seq ] = (string) $row['Column_name'];
		}
		foreach ( $present_indexes as &$definition ) {
			ksort( $definition['columns'], SORT_NUMERIC );
			$definition['columns'] = array_values( $definition['columns'] );
		}
		unset( $definition );
		foreach ( $indexes as $required ) {
			if ( is_string( $required ) ) {
				if ( ! isset( $present_indexes[ $required ] ) ) { return false; }
				continue;
			}
			$name = (string) ( $required['name'] ?? '' );
			if ( '' === $name || ! isset( $present_indexes[ $name ] ) ) { return false; }
			$expected_columns = array_values( array_map( 'strval', (array) ( $required['columns'] ?? array() ) ) );
			if ( $expected_columns && $expected_columns !== $present_indexes[ $name ]['columns'] ) { return false; }
			if ( array_key_exists( 'unique', $required ) && (bool) $required['unique'] !== (bool) $present_indexes[ $name ]['unique'] ) { return false; }
		}
		return true;
	}

	public static function base_ready() {
		$requirements = array(
			'profiles' => array(
				array( 'id','user_id','public_id','slug','profile_type','state','locale','bio','country','city','languages','studied_books','avatar_id','cover_id','avatar_focal_x','avatar_focal_y','cover_focal_x','cover_focal_y','version','created_at','updated_at' ),
				array(
					self::index( 'PRIMARY', array( 'id' ), true ),
					self::index( 'user_id', array( 'user_id' ), true ),
					self::index( 'public_id', array( 'public_id' ), true ),
					self::index( 'slug', array( 'slug' ), false ),
					self::index( 'state_type', array( 'state','profile_type' ), false ),
				),
			),
			'fields' => array( array( 'id','profile_id','field_key','field_value','audience','state','source_owner','version','created_at','updated_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'profile_field', array( 'profile_id','field_key' ), true ), self::index( 'audience_state', array( 'audience','state' ), false ) ) ),
			'slugs' => array( array( 'id','profile_id','slug','is_current','created_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'slug', array( 'slug' ), true ), self::index( 'profile_current', array( 'profile_id','is_current' ), false ) ) ),
			'media' => array( array( 'id','profile_id','attachment_id','purpose','state','alt_text','focal_x','focal_y','scan_provider','scan_reference','version','created_at','updated_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'profile_purpose', array( 'profile_id','purpose' ), true ), self::index( 'attachment_id', array( 'attachment_id' ), false ), self::index( 'state', array( 'state' ), false ) ) ),
			'reports' => array( array( 'id','report_uuid','profile_id','reporter_user_id','reason','details','decision_note','dedupe_hash','status','severity','assigned_to','version','created_at','updated_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'report_uuid', array( 'report_uuid' ), true ), self::index( 'reporter_dedupe', array( 'reporter_user_id','dedupe_hash' ), true ), self::index( 'profile_status', array( 'profile_id','status' ), false ), self::index( 'reporter_created', array( 'reporter_user_id','created_at' ), false ) ) ),
			'events' => array( array( 'id','event_uuid','event_name','aggregate_type','aggregate_id','payload','status','attempts','available_at','lease_token','lease_expires','last_error_code','created_at','delivered_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'event_uuid', array( 'event_uuid' ), true ), self::index( 'delivery', array( 'status','available_at' ), false ), self::index( 'lease', array( 'status','lease_expires' ), false ), self::index( 'aggregate', array( 'aggregate_type','aggregate_id' ), false ) ) ),
			'idempotency' => array( array( 'id','idempotency_key','actor_id','command','request_hash','response_json','status','expires_at','created_at','updated_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'actor_command_key', array( 'actor_id','command','idempotency_key' ), true ), self::index( 'expiry', array( 'expires_at' ), false ) ) ),
			'deletions' => array( array( 'id','deletion_uuid','attachment_id','owner_user_id','purpose','status','attempts','available_at','lease_token','lease_expires','last_error_code','created_at','completed_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'deletion_uuid', array( 'deletion_uuid' ), true ), self::index( 'attachment_purpose', array( 'attachment_id','purpose' ), true ), self::index( 'delivery', array( 'status','available_at' ), false ) ) ),
			'migration_failures' => array( array( 'id','user_id','error_code','detail_hash','attempts','status','next_attempt_at','last_attempt_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'user_id', array( 'user_id' ), true ), self::index( 'retry', array( 'status','next_attempt_at' ), false ) ) ),
			'professional_submissions' => array( array( 'id','submission_uuid','profile_id','submitted_by','payload_json','payload_hash','status','owner_reference','version','created_at','updated_at' ), array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'submission_uuid', array( 'submission_uuid' ), true ), self::index( 'profile_state', array( 'profile_id','status' ), false ), self::index( 'submitter', array( 'submitted_by','created_at' ), false ) ) ),
		);
		foreach ( $requirements as $name => $shape ) {
			if ( ! self::shape_ready( SPD_DB::table( $name ), $shape[0], $shape[1] ) ) { return false; }
		}
		return true;
	}

	public static function central_ready() {
		return self::shape_ready(
			SPD_Central_Profile::delegation_table(),
			array( 'id','profile_id','owner_user_id','delegate_user_id','scopes','status','expires_at','version','created_at','updated_at' ),
			array(
				self::index( 'PRIMARY', array( 'id' ), true ),
				self::index( 'owner_delegate', array( 'owner_user_id','delegate_user_id' ), true ),
				self::index( 'profile_status', array( 'profile_id','status' ), false ),
				self::index( 'delegate_status', array( 'delegate_user_id','status' ), false ),
			)
		) && self::shape_ready(
			SPD_Central_Profile::appeals_table(),
			array( 'id','appeal_uuid','report_id','requested_by','reason','status','reviewer_id','decision_note','version','created_at','updated_at' ),
			array(
				self::index( 'PRIMARY', array( 'id' ), true ),
				self::index( 'appeal_uuid', array( 'appeal_uuid' ), true ),
				self::index( 'report_requester', array( 'report_id','requested_by' ), true ),
				self::index( 'status_updated', array( 'status','updated_at' ), false ),
			)
		);
	}

	public static function future_ready() {
		return self::shape_ready(
			SPD_Future_Profile::translations_table(),
			array( 'id','profile_id','locale','headline','bio','source','status','approved_by','version','created_at','updated_at' ),
			array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'profile_locale', array( 'profile_id','locale' ), true ), self::index( 'profile_status', array( 'profile_id','status' ), false ) )
		) && self::shape_ready(
			SPD_Future_Profile::attestations_table(),
			array( 'id','profile_id','field_key','confirmed_by','confirmed_at','expires_at','version' ),
			array( self::index( 'PRIMARY', array( 'id' ), true ), self::index( 'profile_field', array( 'profile_id','field_key' ), true ), self::index( 'expires_at', array( 'expires_at' ), false ) )
		) && self::shape_ready(
			SPD_Future_Profile::state_table(),
			array( 'profile_id','federation_opt_in','professional_lifecycle','lifecycle_reason','lifecycle_changed_at','version','updated_at' ),
			array( self::index( 'PRIMARY', array( 'profile_id' ), true ), self::index( 'lifecycle', array( 'professional_lifecycle' ), false ) )
		);
	}

	public static function all_ready() { return self::base_ready() && self::central_ready() && self::future_ready(); }
}
