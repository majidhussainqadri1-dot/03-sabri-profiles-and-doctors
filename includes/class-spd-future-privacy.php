<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Future_Privacy {
	public static function export_profile_data( $profile_id ) {
		global $wpdb; $profile_id = absint( $profile_id ); if ( ! $profile_id || ! SPD_Future_Profile::schema_ready() ) { return array(); }
		$translations = $wpdb->get_results( $wpdb->prepare( 'SELECT locale,headline,bio,source,status,version,created_at,updated_at FROM ' . SPD_Future_Profile::translations_table() . ' WHERE profile_id=%d ORDER BY id ASC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$attestations = $wpdb->get_results( $wpdb->prepare( 'SELECT field_key,confirmed_at,expires_at,version FROM ' . SPD_Future_Profile::attestations_table() . ' WHERE profile_id=%d ORDER BY id ASC', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$state = $wpdb->get_row( $wpdb->prepare( 'SELECT federation_opt_in,professional_lifecycle,lifecycle_reason,lifecycle_changed_at,version,updated_at FROM ' . SPD_Future_Profile::state_table() . ' WHERE profile_id=%d LIMIT 1', $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
