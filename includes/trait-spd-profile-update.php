<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Update {
	/**
	 * Atomically update presentation fields, audiences and any already-scanned
	 * avatar/cover replacements. The browser may upload two images, but File 03
	 * commits either the whole profile bundle or none of it.
	 */
	public function update_profile( $actor_id, array $input, $expected_version, $idempotency_key = '', array $prepared_media = array() ) {
		global $wpdb;
		$actor_id      = absint( $actor_id );
		$target_user_id= absint( $input['target_user_id'] ?? $actor_id );
		$profile       = $this->find_by_user_id( $target_user_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$expected_version = absint( $expected_version );
		if ( $expected_version < 1 ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }

		$allowed_input = array_merge( array( 'target_user_id', 'bio', 'country', 'city', 'languages', 'studied_books', 'locale', 'audiences', 'internal_message' ), self::founder_fields() );
		$unknown = array_diff( array_keys( $input ), $allowed_input );
		if ( $unknown ) { return new WP_Error( 'spd_unknown_profile_field', __( 'One or more submitted profile fields are not supported.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( array_key_exists( 'locale', $input ) && ! SPD_Helpers::valid_locale( $input['locale'] ) ) { return new WP_Error( 'spd_profile_locale_invalid', __( 'Choose a valid profile locale.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$media_validation = $this->validate_prepared_media_bundle( $target_user_id, $prepared_media );
		if ( is_wp_error( $media_validation ) ) { return $media_validation; }

		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $input, $expected_version, $target_user_id, $this->prepared_media_hashes( $prepared_media ) ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'update_profile', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }

		$clean = array(
			'bio'           => array_key_exists( 'bio', $input ) ? SPD_Helpers::sanitize_multiline( $input['bio'], 5000 ) : (string) $profile['bio'],
			'country'       => array_key_exists( 'country', $input ) ? sanitize_text_field( (string) $input['country'] ) : (string) $profile['country'],
			'city'          => array_key_exists( 'city', $input ) ? sanitize_text_field( (string) $input['city'] ) : (string) $profile['city'],
			'languages'     => array_key_exists( 'languages', $input ) ? SPD_Helpers::sanitize_multiline( $input['languages'], 1000 ) : (string) $profile['languages'],
			'studied_books' => array_key_exists( 'studied_books', $input ) ? SPD_Helpers::sanitize_multiline( $input['studied_books'], 5000 ) : (string) $profile['studied_books'],
			'locale'        => SPD_Helpers::normalize_locale( $input['locale'] ?? $profile['locale'] ),
		);
		$founder_values = array();
		if ( 'founder' === $profile['profile_type'] ) {
			foreach ( self::founder_fields() as $key ) {
				$current = (string) ( $profile['fields'][ $key ]['field_value'] ?? '' );
				$founder_values[ $key ] = array_key_exists( $key, $input ) ? SPD_Helpers::sanitize_multiline( $input[ $key ], 8000 ) : $current;
			}
		}
		$requested_audiences = isset( $input['audiences'] ) && is_array( $input['audiences'] ) ? $input['audiences'] : array();
		$audiences = array();
		foreach ( self::visibility_fields() as $field_key ) {
			$audience = SPD_Authorization::normalize_audience( $requested_audiences[ $field_key ] ?? ( $profile['fields'][ $field_key ]['audience'] ?? 'private' ) );
			if ( 'founder' === $profile['profile_type'] && 'profile_visibility' === $field_key ) { $audience = 'public'; }
			if ( ! SPD_Authorization::can_publish_audience( $target_user_id, $field_key, $audience ) ) {
				$this->idempotency_fail( $actor_id, 'update_profile', $idempotency_key );
				return new WP_Error( 'spd_audience_not_allowed', __( 'One or more visibility choices are not permitted for this account.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
			}
			$audiences[ $field_key ] = $audience;
		}
		$remove_media_for_privacy = 'public' !== ( $audiences['profile_visibility'] ?? 'private' );
		if ( $prepared_media && $remove_media_for_privacy ) {
			$this->idempotency_fail( $actor_id, 'update_profile', $idempotency_key );
			return new WP_Error( 'spd_media_secure_delivery_required', __( 'Avatar and cover images cannot be retained on a non-public profile without the approved secure media-delivery contract.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		$current_internal = (string) ( $profile['fields']['internal_message']['field_value'] ?? '0' );
		$internal_message = array_key_exists( 'internal_message', $input ) ? ( ! empty( $input['internal_message'] ) ? '1' : '0' ) : $current_internal;
		$old_media = array( 'avatar' => absint( $profile['avatar_id'] ), 'cover' => absint( $profile['cover_id'] ) );
		$new_version = $expected_version + 1;
		$committed_response = array( 'public_id' => $profile['public_id'], 'version' => $new_version, 'committed' => true );
		$profile_table = SPD_DB::table( 'profiles' );
		$media_table   = SPD_DB::table( 'media' );
		$before = $this->public_dto( $profile['public_id'], $target_user_id );
		if ( is_wp_error( $before ) ) { $before = array( 'error' => $before->get_error_code(), 'version' => $profile['version'] ); }

		$result = SPD_DB::transaction(
			function () use ( $wpdb, $profile_table, $media_table, $profile, $clean, $founder_values, $audiences, $internal_message, $expected_version, $new_version, $target_user_id, $prepared_media, $actor_id, $idempotency_key, $committed_response, $remove_media_for_privacy, $old_media ) {
				$profile_data = array(
					'bio'           => $clean['bio'],
					'country'       => $clean['country'],
					'city'          => $clean['city'],
					'languages'     => $clean['languages'],
					'studied_books' => $clean['studied_books'],
					'locale'        => $clean['locale'],
					'version'       => $new_version,
					'updated_at'    => SPD_Helpers::now(),
				);
				if ( $remove_media_for_privacy ) {
					$profile_data['avatar_id'] = 0;
					$profile_data['cover_id']  = 0;
				}
				foreach ( $prepared_media as $purpose => $prepared ) {
					$profile_data[ $purpose . '_id' ]      = absint( $prepared['attachment_id'] );
					$profile_data[ $purpose . '_focal_x' ] = SPD_Helpers::normalize_focal( $prepared['focal_x'] ?? 50 );
					$profile_data[ $purpose . '_focal_y' ] = SPD_Helpers::normalize_focal( $prepared['focal_y'] ?? 50 );
				}
				$updated = $wpdb->update( $profile_table, $profile_data, array( 'id' => $profile['id'], 'version' => $expected_version ) );
				if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				foreach ( $audiences as $key => $audience ) {
					$value = 'internal_message' === $key ? $internal_message : ( $profile['fields'][ $key ]['field_value'] ?? '' );
					if ( ! $this->upsert_field( $profile['id'], $key, $value, $audience ) ) { return new WP_Error( 'spd_field_update_failed', __( 'A profile field could not be updated.', 'sabri-profiles-doctors' ) ); }
				}
				foreach ( $founder_values as $key => $value ) {
					if ( ! $this->upsert_field( $profile['id'], $key, $value, 'public' ) ) { return new WP_Error( 'spd_founder_field_update_failed', __( 'An official Founder field could not be updated.', 'sabri-profiles-doctors' ) ); }
				}
				if ( $remove_media_for_privacy ) {
					$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$media_table} WHERE profile_id=%d", $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( false === $deleted ) { return new WP_Error( 'spd_media_privacy_cleanup_failed', __( 'Profile media references could not be removed while privacy was tightened.', 'sabri-profiles-doctors' ) ); }
					foreach ( $old_media as $purpose => $old_id ) {
						if ( $old_id ) {
							$queued = SPD_Media::queue_owned_deletion( $old_id, $target_user_id, $purpose );
							if ( is_wp_error( $queued ) ) { return $queued; }
							$privacy_event = $this->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'purpose' => $purpose, 'attachment_id' => $old_id, 'state' => 'removed_for_privacy', 'version' => $new_version ) );
							if ( is_wp_error( $privacy_event ) ) { return $privacy_event; }
						}
					}
				}
				foreach ( $prepared_media as $purpose => $prepared ) {
					$old_id = absint( $old_media[ $purpose ] ?? 0 );
					$new_id = absint( $prepared['attachment_id'] ?? 0 );
					if ( $old_id && $new_id && $old_id !== $new_id ) {
						$queued = SPD_Media::queue_owned_deletion( $old_id, $target_user_id, $purpose );
						if ( is_wp_error( $queued ) ) { return $queued; }
					}
					$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$media_table} WHERE profile_id=%d AND purpose=%s LIMIT 1", $profile['id'], $purpose ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$data = array(
						'attachment_id' => absint( $prepared['attachment_id'] ),
						'state'         => 'active',
						'alt_text'      => sanitize_text_field( $prepared['alt_text'] ?? '' ),
						'focal_x'       => SPD_Helpers::normalize_focal( $prepared['focal_x'] ?? 50 ),
						'focal_y'       => SPD_Helpers::normalize_focal( $prepared['focal_y'] ?? 50 ),
						'scan_provider' => sanitize_text_field( $prepared['scan_provider'] ),
						'scan_reference'=> sanitize_text_field( $prepared['scan_reference'] ),
						'updated_at'    => SPD_Helpers::now(),
					);
					if ( $existing ) { $data['version'] = absint( $existing['version'] ) + 1; $ok = $wpdb->update( $media_table, $data, array( 'id' => absint( $existing['id'] ) ) ); }
					else { $data['profile_id'] = $profile['id']; $data['purpose'] = $purpose; $data['version'] = 1; $data['created_at'] = SPD_Helpers::now(); $ok = $wpdb->insert( $media_table, $data ); }
					if ( false === $ok ) { return new WP_Error( 'spd_media_record_failed', __( 'A profile media record could not be saved.', 'sabri-profiles-doctors' ) ); }
					$media_event = $this->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'purpose' => $purpose, 'attachment_id' => absint( $prepared['attachment_id'] ), 'state' => 'active', 'version' => $new_version ) );
					if ( is_wp_error( $media_event ) ) { return $media_event; }
				}
				$e1 = $this->event( 'PublicProfileUpdated.v1', 'profile', $profile['public_id'], array( 'user_id' => $target_user_id, 'previous_version' => $expected_version, 'version' => $new_version, 'changed_fields' => array_keys( $clean ) ) );
				if ( is_wp_error( $e1 ) ) { return $e1; }
				$e2 = $this->event( 'ProfileVisibilityChanged.v1', 'profile', $profile['public_id'], array( 'user_id' => $target_user_id, 'audiences' => $audiences, 'version' => $new_version ) );
				if ( is_wp_error( $e2 ) ) { return $e2; }
				if ( ! $this->idempotency_complete( $actor_id, 'update_profile', $idempotency_key, $committed_response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
				return true;
			}
		);
		if ( is_wp_error( $result ) ) {
			$this->idempotency_fail( $actor_id, 'update_profile', $idempotency_key );
			foreach ( $prepared_media as $purpose => $prepared ) {
				$queued = SPD_Media::queue_owned_deletion( absint( $prepared['attachment_id'] ), $target_user_id, $purpose );
				if ( is_wp_error( $queued ) ) { update_post_meta( absint( $prepared['attachment_id'] ), SPD_Media::STATE_META, 'rejected' ); do_action( 'sabri_file24_profile_media_cleanup_failed', array( 'attachment_id' => absint( $prepared['attachment_id'] ), 'owner_user_id' => $target_user_id, 'purpose' => sanitize_key( $purpose ), 'error_code' => $queued->get_error_code() ) ); }
			}
			return $result;
		}

		// The transaction and the replay record are already committed here. Use
		// the known pre-commit identity for invalidation so a transient reread
		// failure cannot turn a successful first execution into a different client
		// result than its replay.
		$this->purge_profile_cache( $profile );
		$updated_profile = $this->find_by_public_id_strict( $profile['public_id'] );
		if ( is_wp_error( $updated_profile ) || ! $updated_profile ) {
			$error_code = is_wp_error( $updated_profile ) ? $updated_profile->get_error_code() : 'spd_post_commit_profile_missing';
			$after = array( 'error' => $error_code, 'version' => $new_version, 'post_commit_reload' => 'degraded' );
			update_option( 'spd_last_post_commit_reload_error', array( 'code' => $error_code, 'public_id_hash' => hash( 'sha256', (string) $profile['public_id'] ), 'at' => SPD_Helpers::now() ), false );
			do_action( 'sabri_file24_profile_post_commit_reload_failure', array( 'owner' => 'file03', 'code' => $error_code, 'public_id_hash' => hash( 'sha256', (string) $profile['public_id'] ), 'at' => SPD_Helpers::now() ) );
		} else {
			delete_option( 'spd_last_post_commit_reload_error' );
			$after = $this->public_dto( $updated_profile['public_id'], $target_user_id );
			if ( is_wp_error( $after ) ) { $after = array( 'error' => $after->get_error_code(), 'version' => $updated_profile['version'] ); }
		}
		$this->audit_diff( $profile, $actor_id, $before, $after, 'profile_update' );
		SPD_Media::process_deletion_queue( 5 );
		return $committed_response;
	}

	private function validate_prepared_media_bundle( $target_user_id, array $prepared_media ) {
		foreach ( $prepared_media as $purpose => $prepared ) {
			$purpose = sanitize_key( $purpose );
			if ( ! in_array( $purpose, array( 'avatar', 'cover' ), true ) || ! is_array( $prepared ) ) { return new WP_Error( 'spd_invalid_media_bundle', __( 'The prepared media bundle is invalid.', 'sabri-profiles-doctors' ) ); }
			$attachment_id = absint( $prepared['attachment_id'] ?? 0 );
			if ( ! $attachment_id || 'active' !== sanitize_key( (string) ( $prepared['state'] ?? '' ) ) || 'active' !== sanitize_key( (string) get_post_meta( $attachment_id, SPD_Media::STATE_META, true ) ) || absint( get_post_meta( $attachment_id, SPD_Media::OWNER_META, true ) ) !== absint( $target_user_id ) || sanitize_key( (string) get_post_meta( $attachment_id, SPD_Media::PURPOSE_META, true ) ) !== $purpose ) {
				return new WP_Error( 'spd_media_ownership_invalid', __( 'The prepared media ownership, purpose, or scan state is invalid.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
			}
			if ( empty( $prepared['scan_provider'] ) || empty( $prepared['scan_reference'] ) || empty( $prepared['scan_contract_version'] ) || version_compare( (string) $prepared['scan_contract_version'], SPD_Media::SCAN_CONTRACT_MIN, '<' ) || ! preg_match( '/^[0-9a-f]{64}$/', (string) ( $prepared['scan_sha256'] ?? '' ) ) || ! hash_equals( strtolower( (string) get_post_meta( $attachment_id, SPD_Media::SCAN_SHA_META, true ) ), strtolower( (string) $prepared['scan_sha256'] ) ) ) {
				return new WP_Error( 'spd_media_scan_evidence_missing', __( 'The prepared media is missing valid scan evidence.', 'sabri-profiles-doctors' ) );
			}
		}
		return true;
	}

	private function prepared_media_hashes( array $prepared_media ) {
		$out = array();
		foreach ( $prepared_media as $purpose => $prepared ) {
			$out[ sanitize_key( $purpose ) ] = array(
				'attachment_id' => absint( $prepared['attachment_id'] ?? 0 ),
				'scan_reference'=> sanitize_text_field( (string) ( $prepared['scan_reference'] ?? '' ) ),
				'scan_sha256'  => strtolower( sanitize_text_field( (string) ( $prepared['scan_sha256'] ?? '' ) ) ),
				'focal_x'       => SPD_Helpers::normalize_focal( $prepared['focal_x'] ?? 50 ),
				'focal_y'       => SPD_Helpers::normalize_focal( $prepared['focal_y'] ?? 50 ),
			);
		}
		ksort( $out );
		return $out;
	}
}
