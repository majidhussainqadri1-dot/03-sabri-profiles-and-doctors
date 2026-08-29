<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Central {
	/**
	 * Tri-state delegated authorization. Genuine denial returns false; provider
	 * or delegation-store uncertainty returns an explicit 503 WP_Error.
	 */
	private function delegated_access_result( $owner_id, $delegate_id, $scope ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$delegate_id = absint( $delegate_id );
		$scope = sanitize_key( $scope );
		if ( ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::central_ready() ) {
			return new WP_Error( 'spd_delegation_store_unavailable', __( 'Delegated profile management is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$membership_health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $membership_health['status'] ?? '' ) ) {
			return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership authorization is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$owner_claims = SPD_Membership_Adapter::claims( $owner_id );
		$delegate_claims = SPD_Membership_Adapter::claims( $delegate_id );
		if ( ! $owner_claims || ! $delegate_claims ) {
			return new WP_Error( 'spd_membership_claim_unavailable', __( 'Current delegation eligibility could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$verification_health = SPD_Verification_Adapter::health();
		if ( 'available' !== ( $verification_health['status'] ?? '' ) ) {
			return new WP_Error( 'spd_verification_provider_unavailable', __( 'Doctor verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$owner_verification = SPD_Verification_Adapter::projection( $owner_id );
		if ( ! $owner_verification ) {
			return new WP_Error( 'spd_verification_claim_unavailable', __( 'Current doctor-verification evidence could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$wpdb->last_error = '';
		$allowed = $this->delegate_can_manage( $owner_id, $delegate_id, $scope );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'spd_delegation_store_unavailable', __( 'Delegated profile management is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return (bool) $allowed;
	}

	public function central_edit_model( $actor_id, $target_user_id = 0 ) {
		$actor_id = absint( $actor_id );
		$target_user_id = $target_user_id ? absint( $target_user_id ) : $actor_id;
		$profile = $this->find_by_user_id( $target_user_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$is_owner = $actor_id === absint( $profile['user_id'] );
		$delegated = false;
		if ( ! $is_owner ) {
			$delegated = $this->delegated_access_result( $profile['user_id'], $actor_id, 'profile_presentation' );
			if ( is_wp_error( $delegated ) ) { return $delegated; }
		}
		if ( ! $is_owner && ! $delegated ) { return new WP_Error( 'spd_forbidden', __( 'You cannot manage this personal-site profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		if ( ! $delegated ) {
			$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
			if ( is_wp_error( $guard ) ) { return $guard; }
		} elseif ( SPD_Observability::safe_mode() ) {
			return new WP_Error( 'spd_safe_mode', __( 'Delegated profile management is temporarily unavailable while the system is in safe mode.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$values = array();
		$audiences = array();
		foreach ( SPD_Central_Profile::extended_fields() as $key ) {
			$values[ $key ] = (string) ( $profile['fields'][ $key ]['field_value'] ?? '' );
			$audiences[ $key ] = (string) ( $profile['fields'][ $key ]['audience'] ?? 'private' );
		}
		return array(
			'public_id' => $profile['public_id'],
			'target_user_id' => absint( $profile['user_id'] ),
			'actor_user_id' => $actor_id,
			'profile_type' => $profile['profile_type'],
			'version' => absint( $profile['version'] ),
			'custom_slug' => $profile['slug'],
			'values' => $values,
			'audiences' => $audiences,
			'share_url' => SPD_Central_Profile::short_url( $profile ),
			'canonical_url' => SPD_Helpers::canonical_profile_url( $profile['public_id'] ),
			'delegated' => $delegated,
			'delegations' => $is_owner ? $this->list_delegates( $profile['user_id'] ) : array(),
			'analytics' => SPD_Central_Profile::analytics_projection( $profile['user_id'], $actor_id ),
		);
	}

	public function update_central_profile( $actor_id, array $input, $expected_version, $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$target_user_id = absint( $input['target_user_id'] ?? $actor_id );
		$profile = $this->find_by_user_id( $target_user_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$is_owner = $actor_id === $target_user_id;
		$delegated = false;
		if ( ! $is_owner ) {
			$delegated = $this->delegated_access_result( $target_user_id, $actor_id, 'profile_presentation' );
			if ( is_wp_error( $delegated ) ) { return $delegated; }
		}
		if ( $is_owner ) {
			$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
			if ( is_wp_error( $guard ) ) { return $guard; }
		} elseif ( ! $delegated ) {
			return new WP_Error( 'spd_forbidden', __( 'You are not authorized to change these profile fields.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		} elseif ( SPD_Observability::safe_mode() ) {
			return new WP_Error( 'spd_safe_mode', __( 'Delegated profile management is temporarily unavailable while the system is in safe mode.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$expected_version = absint( $expected_version );
		if ( $expected_version < 1 ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }
		$allowed = array_merge( array( 'target_user_id', 'custom_slug', 'audiences' ), SPD_Central_Profile::extended_fields() );
		if ( array_diff( array_keys( $input ), $allowed ) ) { return new WP_Error( 'spd_unknown_profile_field', __( 'One or more personal-site fields are not supported.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$clean = array();
		foreach ( SPD_Central_Profile::extended_fields() as $key ) {
			$current = (string) ( $profile['fields'][ $key ]['field_value'] ?? '' );
			$clean[ $key ] = array_key_exists( $key, $input ) ? SPD_Helpers::sanitize_multiline( $input[ $key ], 3000 ) : $current;
		}
		$safety = SPD_Central_Profile::validate_presentation_fields( $clean );
		if ( is_wp_error( $safety ) ) { return $safety; }
		$requested_audiences = isset( $input['audiences'] ) && is_array( $input['audiences'] ) ? $input['audiences'] : array();
		$audiences = array();
		foreach ( SPD_Central_Profile::extended_fields() as $key ) {
			$audience = SPD_Authorization::normalize_audience( $requested_audiences[ $key ] ?? ( $profile['fields'][ $key ]['audience'] ?? 'private' ) );
			if ( ! SPD_Authorization::can_publish_audience( $target_user_id, $key, $audience ) ) { return new WP_Error( 'spd_audience_not_allowed', __( 'A requested visibility setting is not allowed.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
			$audiences[ $key ] = $audience;
		}
		$slug = sanitize_title( (string) ( $input['custom_slug'] ?? $profile['slug'] ) );
		if ( ! $slug ) { $slug = $profile['slug']; }
		if ( strlen( $slug ) > 160 ) { $slug = substr( $slug, 0, 160 ); }
		if ( $slug !== $profile['slug'] ) {
			$existing = $this->find_by_slug( $slug );
			if ( $existing && absint( $existing['id'] ) !== absint( $profile['id'] ) ) { return new WP_Error( 'spd_slug_collision', __( 'That public profile address is already reserved.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		}
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $target_user_id, $clean, $audiences, $slug, $expected_version ) ) );
		$idem = $this->idempotency_begin( $actor_id, 'update_central_profile', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$new_version = $expected_version + 1;
		$response = array( 'public_id' => $profile['public_id'], 'version' => $new_version, 'share_url' => SPD_Central_Profile::short_url( $profile ) );
		$profiles = SPD_DB::table( 'profiles' );
		$before = array( 'slug' => $profile['slug'], 'extended' => SPD_Central_Profile::public_extended_fields( $profile, $target_user_id ) );
		$result = SPD_DB::transaction( function() use ( $wpdb, $profiles, $profile, $slug, $clean, $audiences, $expected_version, $new_version, $actor_id, $target_user_id, $response, $idempotency_key ) {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$profiles} SET slug=%s,version=%d,updated_at=%s WHERE id=%d AND version=%d", $slug, $new_version, SPD_Helpers::now(), $profile['id'], $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'This profile changed in another session. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( $slug !== $profile['slug'] ) {
				$record = $this->record_slug( $profile['id'], $slug, true );
				if ( is_wp_error( $record ) ) { return $record; }
				if ( ! $this->upsert_field( $profile['id'], 'custom_slug_locked', '1', 'private', 'approved', 'file03' ) ) { return new WP_Error( 'spd_slug_lock_failed', __( 'The custom profile address could not be locked.', 'sabri-profiles-doctors' ) ); }
			}
			foreach ( $clean as $key => $value ) {
				if ( ! $this->upsert_field( $profile['id'], $key, $value, $audiences[ $key ], 'approved', 'file03' ) ) { return new WP_Error( 'spd_field_update_failed', __( 'A personal-site field could not be saved.', 'sabri-profiles-doctors' ) ); }
			}
			$event = $this->event( 'PublicProfileUpdated.v1', 'profile', $profile['public_id'], array( 'user_id' => $target_user_id, 'changed_fields' => array_keys( $clean ), 'operator_user_id' => $actor_id, 'version' => $new_version ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $actor_id, 'update_central_profile', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'update_central_profile', $idempotency_key ); return $result; }
		$updated_profile = $this->find_by_public_id( $profile['public_id'] );
		$this->audit_diff( $updated_profile, $actor_id, $before, array( 'slug' => $slug, 'extended' => $clean ), 'central_profile_update' );
		$this->purge_profile_cache( $updated_profile );
		return $response;
	}

	public function rotate_share_link( $actor_id, $expected_version, $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$profile = $this->find_by_user_id( $actor_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$expected_version = absint( $expected_version );
		if ( $expected_version !== absint( $profile['version'] ) ) { return new WP_Error( 'spd_version_conflict', __( 'Reload your profile before rotating the share link.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		$epoch = SPD_Central_Profile::share_epoch( $profile ) + 1;
		$request_hash = hash( 'sha256', $profile['public_id'] . '|' . $epoch . '|' . $expected_version );
		$idem = $this->idempotency_begin( $actor_id, 'rotate_share_link', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$new_version = $expected_version + 1;
		$profiles = SPD_DB::table( 'profiles' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $profiles, $profile, $epoch, $expected_version, $new_version, $actor_id, $idempotency_key ) {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$profiles} SET version=%d,updated_at=%s WHERE id=%d AND version=%d", $new_version, SPD_Helpers::now(), $profile['id'], $expected_version ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'The profile changed while rotating its share link.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( ! $this->upsert_field( $profile['id'], 'share_epoch', (string) $epoch, 'private', 'approved', 'file03' ) ) { return new WP_Error( 'spd_share_rotation_failed', __( 'The share-link revision could not be recorded.', 'sabri-profiles-doctors' ) ); }
			$event = $this->event( 'ProfileShareLinkRotated.v1', 'profile', $profile['public_id'], array( 'version' => $new_version ) );
			if ( is_wp_error( $event ) ) { return $event; }
			$response = array( 'public_id' => $profile['public_id'], 'version' => $new_version );
			if ( ! $this->idempotency_complete( $actor_id, 'rotate_share_link', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return $response;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'rotate_share_link', $idempotency_key ); return $result; }
		$updated = $this->find_by_public_id( $profile['public_id'] );
		$this->purge_profile_cache( $updated );
		$result['share_url'] = SPD_Central_Profile::short_url( $updated );
		return $result;
	}

	public function grant_delegate( $owner_id, $delegate_id, array $scopes, $expires_at = '', $idempotency_key = '' ) {
		global $wpdb;
		$owner_id = absint( $owner_id ); $delegate_id = absint( $delegate_id );
		if ( ! $owner_id || ! $delegate_id || $owner_id === $delegate_id ) { return new WP_Error( 'spd_delegate_invalid', __( 'Choose a different eligible account as delegate.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$profile = $this->find_by_user_id( $owner_id, false );
		if ( ! $profile || 'doctor' !== $profile['profile_type'] || ! SPD_Verification_Adapter::is_verified( $owner_id ) || SPD_Membership_Adapter::is_minor( $owner_id ) ) { return new WP_Error( 'spd_delegate_owner_ineligible', __( 'Delegated profile management is available only to an eligible verified adult doctor.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $owner_id ); if ( is_wp_error( $guard ) ) { return $guard; }
		if ( ! SPD_Membership_Adapter::is_member_eligible( $delegate_id ) ) { return new WP_Error( 'spd_delegate_ineligible', __( 'The delegate account is not eligible.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$scopes = array_values( array_intersect( array_map( 'sanitize_key', $scopes ), SPD_Central_Profile::delegation_scopes() ) );
		if ( ! $scopes ) { return new WP_Error( 'spd_delegate_scope_required', __( 'Choose at least one allowed delegation scope.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$expires_at = trim( (string) $expires_at );
		$expires = '' !== $expires_at ? strtotime( $expires_at ) : false;
		if ( '' !== $expires_at && false === $expires ) { return new WP_Error( 'spd_delegate_expiry_invalid', __( 'Delegation expiry must be a valid date and time.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( false !== $expires && $expires <= time() ) { return new WP_Error( 'spd_delegate_expired', __( 'Delegation expiry must be in the future.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$expires_value = false !== $expires ? gmdate( 'Y-m-d H:i:s', $expires ) : null;
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $owner_id, $delegate_id, $scopes, $expires_value ) ) );
		$idem = $this->idempotency_begin( $owner_id, 'grant_profile_delegate', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'delegate_user_id' => $delegate_id, 'scopes' => $scopes, 'status' => 'active', 'expires_at' => $expires_value );
		$table = SPD_Central_Profile::delegation_table(); $now = SPD_Helpers::now();
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $profile, $owner_id, $delegate_id, $scopes, $expires_value, $now, $response, $idempotency_key ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$data = array( 'profile_id' => $profile['id'], 'scopes' => implode( ',', $scopes ), 'status' => 'active', 'expires_at' => $expires_value, 'updated_at' => $now );
			if ( $row ) { $data['version'] = absint( $row['version'] ) + 1; $ok = $wpdb->update( $table, $data, array( 'id' => absint( $row['id'] ), 'version' => absint( $row['version'] ) ) ); }
			else { $data += array( 'owner_user_id' => $owner_id, 'delegate_user_id' => $delegate_id, 'version' => 1, 'created_at' => $now ); $ok = $wpdb->insert( $table, $data ); }
			if ( false === $ok || 0 === $ok ) { return new WP_Error( 'spd_delegate_save_failed', __( 'The delegation could not be saved because it changed concurrently or persistence failed.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileDelegationChanged.v1', 'profile', $profile['public_id'], array( 'delegate_user_id' => $delegate_id, 'status' => 'active', 'scopes' => $scopes ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $owner_id, 'grant_profile_delegate', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $owner_id, 'grant_profile_delegate', $idempotency_key ); return $result; }
		return $response;
	}

	public function revoke_delegate( $owner_id, $delegate_id, $idempotency_key = '' ) {
		global $wpdb; $owner_id = absint( $owner_id ); $delegate_id = absint( $delegate_id );
		$profile = $this->find_by_user_id( $owner_id, false );
		if ( ! $profile ) { return new WP_Error( 'spd_forbidden', __( 'You cannot revoke this delegation.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $owner_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $owner_id, $delegate_id ) ) );
		$idem = $this->idempotency_begin( $owner_id, 'revoke_profile_delegate', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'delegate_user_id' => $delegate_id, 'status' => 'revoked' );
		$table = SPD_Central_Profile::delegation_table();
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $profile, $owner_id, $delegate_id, $response, $idempotency_key ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d AND status='active' LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) { return new WP_Error( 'spd_delegate_not_active', __( 'No active delegation exists for that account.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
			$ok = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='revoked',version=version+1,updated_at=%s WHERE id=%d AND version=%d AND status='active'", SPD_Helpers::now(), absint( $row['id'] ), absint( $row['version'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $ok ) { return new WP_Error( 'spd_delegate_revoke_failed', __( 'The delegation changed concurrently and could not be revoked.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileDelegationChanged.v1', 'profile', $profile['public_id'], array( 'delegate_user_id' => $delegate_id, 'status' => 'revoked' ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $owner_id, 'revoke_profile_delegate', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $owner_id, 'revoke_profile_delegate', $idempotency_key ); return $result; }
		return $response;
	}

	public function delegate_can_manage( $owner_id, $delegate_id, $scope ) {
		global $wpdb; $owner_id = absint( $owner_id ); $delegate_id = absint( $delegate_id ); $scope = sanitize_key( $scope );
		if ( ! $owner_id || ! $delegate_id || ! in_array( $scope, SPD_Central_Profile::delegation_scopes(), true ) || ! SPD_Central_Profile::schema_ready() ) { return false; }
		$table = SPD_Central_Profile::delegation_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT scopes,expires_at FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d AND status='active' LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ( $row['expires_at'] && strtotime( $row['expires_at'] ) <= time() ) ) { return false; }
		if ( ! SPD_Membership_Adapter::is_member_eligible( $owner_id ) || ! SPD_Membership_Adapter::is_member_eligible( $delegate_id ) || ! SPD_Verification_Adapter::is_verified( $owner_id ) ) { return false; }
		return in_array( $scope, array_filter( array_map( 'sanitize_key', explode( ',', $row['scopes'] ) ) ), true );
	}

	public function list_delegates( $owner_id ) {
		global $wpdb; $owner_id = absint( $owner_id ); if ( ! SPD_Central_Profile::schema_ready() ) { return array(); }
		$table = SPD_Central_Profile::delegation_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT delegate_user_id,scopes,status,expires_at,version FROM {$table} WHERE owner_user_id=%d AND status='active' ORDER BY updated_at DESC LIMIT 50", $owner_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out = array();
		foreach ( $rows as $row ) {
			if ( $row['expires_at'] && strtotime( $row['expires_at'] ) <= time() ) { continue; }
			$user = get_userdata( absint( $row['delegate_user_id'] ) );
			$out[] = array( 'user_id' => absint( $row['delegate_user_id'] ), 'display_name' => $user ? $user->display_name : __( 'Account', 'sabri-profiles-doctors' ), 'scopes' => array_filter( explode( ',', $row['scopes'] ) ), 'expires_at' => $row['expires_at'], 'version' => absint( $row['version'] ) );
		}
		return $out;
	}

	public function create_safety_report( $public_id, $reporter_user_id, $reason, $details, $idempotency_key = '' ) {
		global $wpdb; $reporter_user_id = absint( $reporter_user_id );
		if ( ! $reporter_user_id || ! SPD_Membership_Adapter::is_member_eligible( $reporter_user_id ) ) { return new WP_Error( 'spd_login_required', __( 'An eligible signed-in account is required to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$profile = $this->find_by_public_id( $public_id );
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $reporter_user_id ) ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$reason = sanitize_key( $reason ); $details = SPD_Helpers::sanitize_multiline( $details, 3000 );
		if ( ! in_array( $reason, SPD_Central_Profile::report_reasons(), true ) ) { return new WP_Error( 'spd_invalid_report_reason', __( 'Choose a valid report reason.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( SPD_Helpers::text_length( $details ) < 10 ) { return new WP_Error( 'spd_report_details_required', __( 'Provide enough detail for a fair review.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $public_id, $reason, $details ) ) );
		$idem = $this->idempotency_begin( $reporter_user_id, 'create_safety_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$reports = SPD_DB::table( 'reports' );
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE reporter_user_id=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)", $reporter_user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count >= 5 || ! SPD_Helpers::consume_rate_limit( 'profile_report_' . $reporter_user_id, 5, DAY_IN_SECONDS ) ) { $this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key ); return new WP_Error( 'spd_report_rate_limited', __( 'Too many reports were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) ); }
		$uuid = SPD_Helpers::public_id(); $now = SPD_Helpers::now();
		$critical = in_array( $reason, array( 'harm','child_safety','impersonation','privacy','privacy_breach','scam' ), true );
		$severity = $critical ? 'high' : 'normal';
		$dedupe = hash( 'sha256', gmdate( 'Y-m-d' ) . ':' . $profile['id'] . ':' . $reason . ':' . hash( 'sha256', $details ) );
		$response = array( 'report_uuid' => $uuid, 'status' => 'submitted' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $reports, $uuid, $profile, $reporter_user_id, $reason, $details, $severity, $now, $dedupe, $response, $idempotency_key ) {
			$ok = $wpdb->insert( $reports, array( 'report_uuid' => $uuid, 'profile_id' => $profile['id'], 'reporter_user_id' => $reporter_user_id, 'reason' => $reason, 'details' => $details, 'dedupe_hash' => $dedupe, 'status' => 'submitted', 'severity' => $severity, 'version' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			if ( ! $ok ) { return new WP_Error( 'spd_report_duplicate_or_failed', __( 'This report could not be recorded or may already exist.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileReported.v1', 'report', $uuid, array( 'profile_public_id' => $profile['public_id'], 'reason' => $reason, 'severity' => $severity ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $reporter_user_id, 'create_safety_report', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key ); return $result; }
		return $response;
	}

	public function request_report_appeal( $report_uuid, $requester_id, $reason, $idempotency_key = '' ) {
		global $wpdb; $requester_id = absint( $requester_id ); $reason = SPD_Helpers::sanitize_multiline( $reason, 2000 );
		if ( ! $requester_id || ! SPD_Membership_Adapter::is_member_eligible( $requester_id ) ) { return new WP_Error( 'spd_account_ineligible', __( 'An eligible account is required to appeal a report.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		if ( SPD_Helpers::text_length( $reason ) < 10 ) { return new WP_Error( 'spd_appeal_reason_required', __( 'Provide a clear reason for the appeal.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$reports = SPD_DB::table( 'reports' );
		$report_uuid = sanitize_text_field( $report_uuid );
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id,reporter_user_id,status FROM {$reports} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $report || absint( $report['reporter_user_id'] ) !== $requester_id || ! in_array( $report['status'], array( 'rejected','closed','actioned' ), true ) ) { return new WP_Error( 'spd_appeal_unavailable', __( 'This report is not eligible for appeal by this account.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $report_uuid, $requester_id, $reason ) ) );
		$idem = $this->idempotency_begin( $requester_id, 'request_report_appeal', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$table = SPD_Central_Profile::appeals_table(); $uuid = SPD_Helpers::public_id(); $now = SPD_Helpers::now();
		$response = array( 'appeal_uuid' => $uuid, 'status' => 'submitted' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $report, $report_uuid, $requester_id, $reason, $uuid, $now, $response, $idempotency_key ) {
			$ok = $wpdb->insert( $table, array( 'appeal_uuid' => $uuid, 'report_id' => absint( $report['id'] ), 'requested_by' => $requester_id, 'reason' => $reason, 'status' => 'submitted', 'version' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			if ( ! $ok ) { return new WP_Error( 'spd_appeal_duplicate_or_failed', __( 'An appeal already exists or could not be recorded.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileReportAppealed.v1', 'report', $report_uuid, array( 'appeal_uuid' => $uuid, 'requested_by' => $requester_id ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $requester_id, 'request_report_appeal', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $requester_id, 'request_report_appeal', $idempotency_key ); return $result; }
		return $response;
	}
}
