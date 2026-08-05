<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Media {
	public function attach_media( $user_id, $purpose, array $prepared, $expected_version ) {
		global $wpdb;
		$profile = $this->find_by_user_id( $user_id );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		$guard = SPD_Authorization::mutation_guard( $profile, $user_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$purpose = sanitize_key( $purpose );
		if ( ! in_array( $purpose, array( 'avatar', 'cover' ), true ) ) {
			return new WP_Error( 'spd_invalid_media_purpose', __( 'The media purpose is invalid.', 'sabri-profiles-doctors' ) );
		}
		$column = 'avatar' === $purpose ? 'avatar_id' : 'cover_id';
		$x_column = $purpose . '_focal_x';
		$y_column = $purpose . '_focal_y';
		$old_id = absint( $profile[ $column ] );
		$profile_table = SPD_DB::table( 'profiles' );
		$media_table = SPD_DB::table( 'media' );
		$attachment_id = absint( $prepared['attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
			return new WP_Error( 'spd_media_missing', __( 'The uploaded media is unavailable.', 'sabri-profiles-doctors' ) );
		}
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $profile_table, $media_table, $profile, $purpose, $column, $x_column, $y_column, $prepared, $attachment_id, $expected_version ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$profile_table} SET {$column}=%d,{$x_column}=%f,{$y_column}=%f,version=version+1,updated_at=%s WHERE id=%d AND version=%d",
						$attachment_id, (float) $prepared['focal_x'], (float) $prepared['focal_y'], SPD_Helpers::now(), absint( $profile['id'] ), absint( $expected_version )
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $updated ) {
					return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
				}
				$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$media_table} WHERE profile_id=%d AND purpose=%s LIMIT 1", absint( $profile['id'] ), $purpose ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$data = array(
					'attachment_id' => $attachment_id,
					'state'         => sanitize_key( $prepared['state'] ),
					'alt_text'      => sanitize_text_field( $prepared['alt_text'] ),
					'focal_x'       => (float) $prepared['focal_x'],
					'focal_y'       => (float) $prepared['focal_y'],
					'scan_provider' => sanitize_text_field( $prepared['scan_provider'] ),
					'scan_reference'=> sanitize_text_field( $prepared['scan_reference'] ),
					'updated_at'    => SPD_Helpers::now(),
				);
				if ( $existing ) {
					$data['version'] = absint( $existing['version'] ) + 1;
					$wpdb->update( $media_table, $data, array( 'id' => absint( $existing['id'] ) ) );
				} else {
					$data['profile_id'] = absint( $profile['id'] );
					$data['purpose'] = $purpose;
					$data['version'] = 1;
					$data['created_at'] = SPD_Helpers::now();
					$wpdb->insert( $media_table, $data );
				}
				$this->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'purpose' => $purpose, 'attachment_id' => $attachment_id, 'state' => $prepared['state'], 'version' => absint( $expected_version ) + 1 ) );
				return true;
			}
		);
		if ( is_wp_error( $result ) ) {
			SPD_Media::delete_owned( $attachment_id, $user_id, $purpose );
			return $result;
		}
		if ( $old_id && $old_id !== $attachment_id ) {
			SPD_Media::delete_owned( $old_id, $user_id, $purpose );
		}
		$updated = $this->find_by_user_id( $user_id, false );
		$this->purge_profile_cache( $updated );
		return $updated;
	}

}
