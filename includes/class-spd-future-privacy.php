<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Future_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profile-future-domain'] = array( 'exporter_friendly_name' => __( 'Sabri future professional profile data', 'sabri-profiles-doctors' ), 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-profile-future-domain'] = array( 'eraser_friendly_name' => __( 'Sabri future professional profile data', 'sabri-profiles-doctors' ), 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		if ( absint( $page ) > 1 ) { return array( 'data' => array(), 'done' => true ); }
		$user = get_user_by( 'email', $email ); if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user->ID, false ); if ( ! $profile ) { return array( 'data' => array(), 'done' => true ); }
		$data = self::export_profile_data( $profile['id'] );
		if ( is_wp_error( $data ) ) { return $data; }
		return array( 'data' => $data ? array( array( 'group_id' => 'sabri-profile-future-domain', 'group_label' => __( 'Sabri Future Professional Profile', 'sabri-profiles-doctors' ), 'item_id' => 'future-profile-' . $profile['public_id'], 'data' => $data ) ) : array(), 'done' => true );
	}

	public function erase( $email, $page = 1 ) {
		if ( absint( $page ) > 1 ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$user = get_user_by( 'email', $email ); if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user->ID, false ); if ( ! $profile ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		if ( SPD_Membership_Adapter::is_founder( $user->ID ) ) { return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'The official Founder future-profile record requires an authorized governance decision before removal.', 'sabri-profiles-doctors' ) ), 'done' => true ); }
		$base_hold = apply_filters( 'spd_profile_legal_hold', false, absint( $user->ID ), $profile );
		$future_hold = apply_filters( 'spd_future_profile_legal_hold', false, absint( $user->ID ), $profile );
		if ( $base_hold || $future_hold ) { return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'Future professional profile data is retained under an active legal or governance hold.', 'sabri-profiles-doctors' ) ), 'done' => true ); }
		$result = self::erase_profile_data( $profile['id'] );
		return array( 'items_removed' => ! empty( $result['removed'] ), 'items_retained' => ! empty( $result['retry'] ), 'messages' => ! empty( $result['retry'] ) ? array( __( 'Future professional profile data could not yet be erased and requires a retry.', 'sabri-profiles-doctors' ) ) : array(), 'done' => empty( $result['retry'] ) );
	}

	public static function export_profile_data( $profile_id ) {
		global $wpdb; $profile_id = absint( $profile_id ); if ( ! $profile_id || ! SPD_Future_Profile::schema_ready() ) { return array(); }
		$wpdb->last_error = '';
		$translations = $wpdb->get_results( $wpdb->prepare( 'SELECT locale,headline,bio,source,status,version,created_at,updated_at FROM ' . SPD_Future_Profile::translations_table() . ' WHERE profile_id=%d ORDER BY id ASC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_future_privacy_export_failed', __( 'Future-profile translation data could not be read for export.', 'sabri-profiles-doctors' ) ); }
		$wpdb->last_error = '';
		$attestations = $wpdb->get_results( $wpdb->prepare( 'SELECT field_key,confirmed_at,expires_at,version FROM ' . SPD_Future_Profile::attestations_table() . ' WHERE profile_id=%d ORDER BY id ASC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_future_privacy_export_failed', __( 'Future-profile freshness data could not be read for export.', 'sabri-profiles-doctors' ) ); }
		$wpdb->last_error = '';
		$state = $wpdb->get_row( $wpdb->prepare( 'SELECT federation_opt_in,professional_lifecycle,lifecycle_reason,lifecycle_changed_at,version,updated_at FROM ' . SPD_Future_Profile::state_table() . ' WHERE profile_id=%d LIMIT 1', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_future_privacy_export_failed', __( 'Future-profile lifecycle data could not be read for export.', 'sabri-profiles-doctors' ) ); }
		$data = array();
		foreach ( (array) $translations as $row ) { $data[] = array( 'name' => 'Approved translation ' . $row['locale'], 'value' => wp_json_encode( $row ) ); }
		foreach ( (array) $attestations as $row ) { $data[] = array( 'name' => 'Field freshness ' . $row['field_key'], 'value' => wp_json_encode( $row ) ); }
		if ( $state ) { $data[] = array( 'name' => 'Future professional state', 'value' => wp_json_encode( $state ) ); }
		return $data;
	}

	public static function erase_profile_data( $profile_id ) {
		global $wpdb; $profile_id = absint( $profile_id );
		if ( ! $profile_id || ! SPD_Future_Profile::schema_ready() ) { return array( 'removed' => false, 'retry' => false ); }
		$result = SPD_DB::transaction( function() use ( $wpdb, $profile_id ) {
			foreach ( array( SPD_Future_Profile::translations_table(), SPD_Future_Profile::attestations_table(), SPD_Future_Profile::state_table() ) as $table ) {
				$deleted = $wpdb->delete( $table, array( 'profile_id' => $profile_id ) );
				if ( false === $deleted ) { return new WP_Error( 'spd_future_privacy_erasure_failed', __( 'Future-profile privacy data could not be erased.', 'sabri-profiles-doctors' ) ); }
			}
			return true;
		} );
		return is_wp_error( $result ) ? array( 'removed' => false, 'retry' => true, 'error' => $result ) : array( 'removed' => true, 'retry' => false );
	}
}
