<?php
defined( 'ABSPATH' ) || exit;

/**
 * Export coverage for File 03's permanent canonical-slug redirect history.
 * Slug aliases are retained for citation/redirect integrity after profile
 * erasure, so a data subject must also be able to receive that retained
 * history in the WordPress personal-data export.
 */
final class SPD_Slug_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profile-slug-history'] = array(
			'exporter_friendly_name' => __( 'Sabri profile address history', 'sabri-profiles-doctors' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }

		$wpdb->last_error = '';
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( absint( $user->ID ), false );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'spd_slug_privacy_profile_read_failed', __( 'Profile address history could not be resolved for export.', 'sabri-profiles-doctors' ) );
		}
		if ( ! $profile ) { return array( 'data' => array(), 'done' => true ); }

		$page = max( 1, absint( $page ) );
		$limit = 51;
		$offset = ( $page - 1 ) * 50;
		$table = SPD_DB::table( 'slugs' );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id,slug,is_current,created_at FROM {$table} WHERE profile_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", absint( $profile['id'] ), $limit, $offset ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			return new WP_Error( 'spd_slug_privacy_export_failed', __( 'Profile address history could not be read for export.', 'sabri-profiles-doctors' ) );
		}

		$data = array();
		foreach ( array_slice( (array) $rows, 0, 50 ) as $row ) {
			$data[] = array(
				'group_id'    => 'sabri-profile-slug-history',
				'group_label' => __( 'Profile address history', 'sabri-profiles-doctors' ),
				'item_id'     => 'profile-slug-' . absint( $row['id'] ),
				'data'        => array(
					array( 'name' => 'Slug', 'value' => (string) $row['slug'] ),
					array( 'name' => 'Current', 'value' => ! empty( $row['is_current'] ) ? 'yes' : 'no' ),
					array( 'name' => 'Created', 'value' => (string) $row['created_at'] ),
					array( 'name' => 'Retention', 'value' => 'Permanent redirect/citation integrity' ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( (array) $rows ) <= 50 );
	}
}
