<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exact owned-schema shape verification.
 * Table existence alone is not sufficient: a partial/deferred migration can
 * leave a table present while required columns or integrity indexes are absent.
 */
final class SPD_Schema_Guard {
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
		foreach ( $index_rows as $row ) { if ( isset( $row['Key_name'] ) ) { $present_indexes[] = (string) $row['Key_name']; } }
		return ! array_diff( $indexes, array_values( array_unique( $present_indexes ) ) );
	}

	public static function base_ready() {
		$requirements = array(
			'profiles' => array(
				array( 'id','user_id','public_id','slug','profile_type','state','locale','bio','country','city','languages','studied_books','avatar_id','cover_id','avatar_focal_x','avatar_focal_y','cover_focal_x','cover_focal_y','version','created_at','updated_at' ),
				array( 'PRIMARY','user_id','public_id','slug','state_type' ),
			),
			'fields' => array( array( 'id','profile_id','field_key','field_value','audience','state','source_owner','version','created_at','updated_at' ), array( 'PRIMARY','profile_field','audience_state' ) ),
			'slugs' => array( array( 'id','profile_id','slug','is_current','created_at' ), array( 'PRIMARY','slug','profile_current' ) ),
			'media' => array( array( 'id','profile_id','attachment_id','purpose','state','alt_text','focal_x','focal_y','scan_provider','scan_reference','version','created_at','updated_at' ), array( 'PRIMARY','profile_purpose','attachment_id','state' ) ),
			'reports' => array( array( 'id','report_uuid','profile_id','reporter_user_id','reason','details','decision_note','dedupe_hash','status','severity','assigned_to','version','created_at','updated_at' ), array( 'PRIMARY','report_uuid','reporter_dedupe','profile_status','reporter_created' ) ),
			'events' => array( array( 'id','event_uuid','event_name','aggregate_type','aggregate_id','payload','status','attempts','available_at','lease_token','lease_expires','last_error_code','created_at','delivered_at' ), array( 'PRIMARY','event_uuid','delivery','lease','aggregate' ) ),
			'idempotency' => array( array( 'id','idempotency_key','actor_id','command','request_hash','response_json','status','expires_at','created_at','updated_at' ), array( 'PRIMARY','actor_command_key','expiry' ) ),
			'deletions' => array( array( 'id','deletion_uuid','attachment_id','owner_user_id','purpose','status','attempts','available_at','lease_token','lease_expires','last_error_code','created_at','completed_at' ), array( 'PRIMARY','deletion_uuid','attachment_purpose','delivery' ) ),
			'migration_failures' => array( array( 'id','user_id','error_code','detail_hash','attempts','status','next_attempt_at','last_attempt_at' ), array( 'PRIMARY','user_id','retry' ) ),
			'professional_submissions' => array( array( 'id','submission_uuid','profile_id','submitted_by','payload_json','payload_hash','status','owner_reference','version','created_at','updated_at' ), array( 'PRIMARY','submission_uuid','profile_state','submitter' ) ),
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
			array( 'PRIMARY','owner_delegate','profile_status','delegate_status' )
		) && self::shape_ready(
			SPD_Central_Profile::appeals_table(),
			array( 'id','appeal_uuid','report_id','requested_by','reason','status','reviewer_id','decision_note','version','created_at','updated_at' ),
			array( 'PRIMARY','appeal_uuid','report_requester','status_updated' )
		);
	}

	public static function future_ready() {
		return self::shape_ready(
			SPD_Future_Profile::translations_table(),
			array( 'id','profile_id','locale','headline','bio','source','status','approved_by','version','created_at','updated_at' ),
			array( 'PRIMARY','profile_locale','profile_status' )
		) && self::shape_ready(
			SPD_Future_Profile::attestations_table(),
			array( 'id','profile_id','field_key','confirmed_by','confirmed_at','expires_at','version' ),
			array( 'PRIMARY','profile_field','expires_at' )
		) && self::shape_ready(
			SPD_Future_Profile::state_table(),
			array( 'profile_id','federation_opt_in','professional_lifecycle','lifecycle_reason','lifecycle_changed_at','version','updated_at' ),
			array( 'PRIMARY','lifecycle' )
		);
	}

	public static function all_ready() { return self::base_ready() && self::central_ready() && self::future_ready(); }
}
