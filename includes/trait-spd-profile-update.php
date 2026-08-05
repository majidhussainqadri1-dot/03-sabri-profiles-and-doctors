<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Update {
	public function update_profile( $user_id, array $input, $expected_version, $idempotency_key = '' ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$profile = $this->find_by_user_id( $user_id );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		$guard = SPD_Authorization::mutation_guard( $profile, $user_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$expected_version = absint( $expected_version );
		if ( $expected_version < 1 ) {
			return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) );
		}
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $input, $expected_version ) ) );
		$idempotent = $this->idempotency_begin( $user_id, 'update_profile', $idempotency_key, $request_hash );
		if ( is_wp_error( $idempotent ) ) {
			return $idempotent;
		}
		if ( is_array( $idempotent ) && isset( $idempotent['replay'] ) ) {
			return $idempotent['response'];
		}

		$clean = array();
		$clean['bio'] = array_key_exists( 'bio', $input ) ? SPD_Helpers::sanitize_multiline( $input['bio'], 5000 ) : (string) $profile['bio'];
		$clean['country'] = array_key_exists( 'country', $input ) ? sanitize_text_field( (string) $input['country'] ) : (string) $profile['country'];
		$clean['city'] = array_key_exists( 'city', $input ) ? sanitize_text_field( (string) $input['city'] ) : (string) $profile['city'];
		$clean['languages'] = array_key_exists( 'languages', $input ) ? SPD_Helpers::sanitize_multiline( $input['languages'], 1000 ) : (string) $profile['languages'];
		$clean['studied_books'] = array_key_exists( 'studied_books', $input ) ? SPD_Helpers::sanitize_multiline( $input['studied_books'], 5000 ) : (string) $profile['studied_books'];
		$clean['locale'] = SPD_Helpers::normalize_locale( $input['locale'] ?? $profile['locale'] );
		$founder_values = array();
		if ( 'founder' === $profile['profile_type'] ) {
			foreach ( self::founder_fields() as $key ) {
				$current = (string) ( $profile['fields'][ $key ]['field_value'] ?? '' );
				$founder_values[ $key ] = array_key_exists( $key, $input ) ? SPD_Helpers::sanitize_multiline( $input[ $key ], 8000 ) : $current;
			}
		}
		$audiences = isset( $input['audiences'] ) && is_array( $input['audiences'] ) ? $input['audiences'] : array();
		foreach ( self::visibility_fields() as $field_key ) {
			$audience = SPD_Authorization::normalize_audience( $audiences[ $field_key ] ?? ( $profile['fields'][ $field_key ]['audience'] ?? 'private' ) );
			if ( 'founder' === $profile['profile_type'] && 'profile_visibility' === $field_key ) {
				$audience = 'public';
			}
			if ( ! SPD_Authorization::can_publish_audience( $user_id, $field_key, $audience ) ) {
				$this->idempotency_fail( $user_id, 'update_profile', $idempotency_key );
				return new WP_Error( 'spd_audience_not_allowed', __( 'One or more visibility choices are not permitted for this account.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
			}
			$audiences[ $field_key ] = $audience;
		}
		$internal_message = ! empty( $input['internal_message'] ) ? '1' : '0';
		$profile_table = SPD_DB::table( 'profiles' );
		$before = $this->public_dto( $profile['public_id'], $user_id );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $profile_table, $profile, $clean, $founder_values, $audiences, $internal_message, $expected_version, $user_id ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$profile_table} SET bio=%s,country=%s,city=%s,languages=%s,studied_books=%s,locale=%s,version=version+1,updated_at=%s WHERE id=%d AND version=%d",
						$clean['bio'], $clean['country'], $clean['city'], $clean['languages'], $clean['studied_books'], $clean['locale'], SPD_Helpers::now(), absint( $profile['id'] ), $expected_version
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $updated ) {
					return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
				}
				foreach ( $audiences as $key => $audience ) {
					$value = 'internal_message' === $key ? $internal_message : ( $profile['fields'][ $key ]['field_value'] ?? '' );
					if ( ! $this->upsert_field( $profile['id'], $key, $value, $audience ) ) {
						return new WP_Error( 'spd_field_update_failed', __( 'A profile field could not be updated.', 'sabri-profiles-doctors' ) );
					}
				}
				foreach ( $founder_values as $key => $value ) {
					if ( ! $this->upsert_field( $profile['id'], $key, $value, 'public' ) ) {
						return new WP_Error( 'spd_founder_field_update_failed', __( 'An official Founder field could not be updated.', 'sabri-profiles-doctors' ) );
					}
				}
				$this->event(
					'PublicProfileUpdated.v1',
					'profile',
					$profile['public_id'],
					array( 'user_id' => $user_id, 'previous_version' => $expected_version, 'version' => $expected_version + 1, 'changed_fields' => array_keys( $clean ) )
				);
				$this->event(
					'ProfileVisibilityChanged.v1',
					'profile',
					$profile['public_id'],
					array( 'user_id' => $user_id, 'audiences' => $audiences, 'version' => $expected_version + 1 )
				);
				return true;
			}
		);
		if ( is_wp_error( $result ) ) {
			$this->idempotency_fail( $user_id, 'update_profile', $idempotency_key );
			return $result;
		}
		$updated_profile = $this->find_by_user_id( $user_id, false );
		$after = $this->public_dto( $updated_profile['public_id'], $user_id );
		$this->audit_diff( $profile, $user_id, $before, $after, 'profile_update' );
		$this->purge_profile_cache( $updated_profile );
		$response = array( 'profile' => $after, 'version' => $updated_profile['version'] );
		$this->idempotency_complete( $user_id, 'update_profile', $idempotency_key, $response );
		return $response;
	}

}
