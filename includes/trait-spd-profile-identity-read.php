<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Identity_Read {
	public function find_by_id( $profile_id ) {
		global $wpdb;
		$table = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $profile_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : array();
	}

	public function find_by_user_id( $user_id, $ensure = true ) {
		global $wpdb;
		$table = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", absint( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			return $ensure ? new WP_Error( 'spd_profile_read_failed', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ) : array();
		}
		if ( ! $row && $ensure ) {
			return $this->ensure_for_user( $user_id );
		}
		return $row ? $this->hydrate( $row ) : array();
	}

	public function find_by_public_id( $public_id ) {
		if ( ! SPD_Helpers::valid_uuid( (string) $public_id ) ) { return array(); }
		global $wpdb;
		$table = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", sanitize_text_field( $public_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : array();
	}

	public function find_by_slug( $slug ) {
		global $wpdb;
		$slugs = SPD_DB::table( 'slugs' );
		$profiles = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.* FROM {$profiles} p INNER JOIN {$slugs} s ON s.profile_id = p.id WHERE s.slug = %s LIMIT 1",
				sanitize_title( $slug )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : array();
	}

	private function hydrate( array $row ) {
		$row['id'] = absint( $row['id'] );
		$row['user_id'] = absint( $row['user_id'] );
		$row['version'] = absint( $row['version'] );
		$row['avatar_id'] = absint( $row['avatar_id'] );
		$row['cover_id'] = absint( $row['cover_id'] );
		$row['fields'] = $this->field_map( $row['id'] );
		$row['profile_visibility'] = $row['fields']['profile_visibility']['audience'] ?? 'private';
		return $row;
	}

	public function field_map( $profile_id ) {
		global $wpdb;
		$table = SPD_DB::table( 'fields' );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE profile_id = %d", absint( $profile_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return array(); }
		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row['field_key'] ] = $row;
		}
		return $map;
	}

	private function upsert_field( $profile_id, $key, $value, $audience, $state = 'approved', $source_owner = 'file03' ) {
		global $wpdb;
		$table = SPD_DB::table( 'fields' );
		$now = SPD_Helpers::now();
		$key = sanitize_key( $key );
		$audience = SPD_Authorization::normalize_audience( $audience );
		$wpdb->last_error = '';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, version FROM {$table} WHERE profile_id = %d AND field_key = %s LIMIT 1", absint( $profile_id ), $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return false; }
		$data = array(
			'field_value'  => (string) $value,
			'audience'     => $audience,
			'state'        => sanitize_key( $state ),
			'source_owner' => sanitize_key( $source_owner ),
			'updated_at'   => $now,
		);
		if ( $existing ) {
			$data['version'] = absint( $existing['version'] ) + 1;
			return false !== $wpdb->update( $table, $data, array( 'id' => absint( $existing['id'] ) ) );
		}
		$data['profile_id'] = absint( $profile_id );
		$data['field_key'] = $key;
		$data['version'] = 1;
		$data['created_at'] = $now;
		return false !== $wpdb->insert( $table, $data );
	}

}
