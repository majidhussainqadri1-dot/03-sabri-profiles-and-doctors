<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Profile_Repository {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public static function founder_fields() {
		return array( 'professional_title', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications', 'institutional_links' );
	}

	public static function editable_fields() {
		return array_merge( array( 'bio', 'country', 'city', 'languages', 'studied_books', 'locale' ), SPD_Central_Profile::extended_fields(), self::founder_fields() );
	}

	public static function professional_fields() {
		return array( 'professional_title', 'qualification', 'degree', 'institution', 'licence_number', 'licensing_authority', 'jurisdiction', 'credential_issued_at', 'credential_expires_at', 'experience_years', 'specialty', 'consultation_modes' );
	}

	public static function visibility_fields() {
		return array_merge( array( 'profile_visibility', 'bio', 'country', 'city', 'languages', 'studied_books', 'phone', 'email', 'whatsapp', 'internal_message' ), SPD_Central_Profile::extended_fields(), self::founder_fields() );
	}

	use SPD_Profile_Identity_Create;
	use SPD_Profile_Identity_Read;
	use SPD_Profile_Public_DTO;
	use SPD_Profile_Edit_Model;
	use SPD_Profile_Update { update_profile as private base_update_profile; }
	use SPD_Profile_Professional;
	use SPD_Profile_Media;
	use SPD_Profile_Moderation;
	use SPD_Profile_Lifecycle;
	use SPD_Profile_Events;
	use SPD_Profile_Cache;
	use SPD_Profile_Central {
		grant_delegate as private central_grant_delegate;
		central_edit_model as private central_edit_model_impl;
		update_central_profile as private central_update_profile;
	}

	public function find_by_public_id_strict( $public_id ) {
		global $wpdb;
		$wpdb->last_error = '';
		$result = $this->find_by_public_id( $public_id );
		if ( $wpdb->last_error || ( is_array( $result ) && ! empty( $result['_fields_read_failed'] ) ) ) {
			return new WP_Error( 'spd_profile_read_failed', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return $result;
	}

	public function find_by_slug_strict( $slug ) {
		global $wpdb;
		$wpdb->last_error = '';
		$result = $this->find_by_slug( $slug );
		if ( $wpdb->last_error || ( is_array( $result ) && ! empty( $result['_fields_read_failed'] ) ) ) {
			return new WP_Error( 'spd_slug_lookup_failed', __( 'The profile address registry is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return $result;
	}

	private function central_target_preflight( $target_user_id ) {
		global $wpdb;
		$target_user_id = absint( $target_user_id );
		$wpdb->last_error = '';
		$profile = $this->find_by_user_id( $target_user_id, false );
		if ( $wpdb->last_error || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) {
			return new WP_Error( 'spd_profile_store_unavailable', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		return $profile;
	}

	/** Return a committed replay record without creating a new reservation. */
	private function completed_idempotency_response( $actor_id, $command, $key ) {
		global $wpdb;
		$key = trim( (string) $key );
		if ( '' === $key ) { return null; }
		$table = SPD_DB::table( 'idempotency' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status,response_json FROM {$table} WHERE actor_id=%d AND command=%s AND idempotency_key=%s LIMIT 1",
				absint( $actor_id ),
				sanitize_key( $command ),
				hash( 'sha256', $key )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) {
			return new WP_Error( 'spd_idempotency_store_unavailable', __( 'Replay protection is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( ! $row || 'completed' !== ( $row['status'] ?? '' ) ) { return null; }
		$response = json_decode( (string) ( $row['response_json'] ?? '' ), true );
		return is_array( $response ) ? $response : new WP_Error( 'spd_idempotency_response_invalid', __( 'The stored replay result is invalid and requires operator review.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
	}

	private function delegation_store_error() {
		return new WP_Error( 'spd_delegation_store_unavailable', __( 'Delegated profile management is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
	}

	public function central_edit_model( $actor_id, $target_user_id = 0 ) {
		$actor_id = absint( $actor_id );
		$target_user_id = $target_user_id ? absint( $target_user_id ) : $actor_id;
		$profile = $this->central_target_preflight( $target_user_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$result = $this->central_edit_model_impl( $actor_id, $target_user_id );
		if ( is_wp_error( $result ) && 'spd_profile_unavailable' === $result->get_error_code() ) {
			$confirm = $this->central_target_preflight( $target_user_id );
			if ( is_wp_error( $confirm ) ) { return $confirm; }
		}
		if ( is_array( $result ) && isset( $result['delegations'] ) && is_wp_error( $result['delegations'] ) ) { return $result['delegations']; }
		return $result;
	}

	public function update_profile( $actor_id, array $input, $expected_version, $idempotency_key = '', array $prepared_media = array() ) {
		if ( array_key_exists( 'audiences', $input ) ) {
			$guard = SPD_Authorization::validate_audience_payload( $input['audiences'], self::visibility_fields() );
			if ( is_wp_error( $guard ) ) { return $guard; }
		}
		return $this->base_update_profile( $actor_id, $input, $expected_version, $idempotency_key, $prepared_media );
	}

	public function update_central_profile( $actor_id, array $input, $expected_version, $idempotency_key = '' ) {
		$target_user_id = absint( $input['target_user_id'] ?? $actor_id );
		$profile = $this->central_target_preflight( $target_user_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		if ( array_key_exists( 'audiences', $input ) ) {
			$guard = SPD_Authorization::validate_audience_payload( $input['audiences'], SPD_Central_Profile::extended_fields() );
			if ( is_wp_error( $guard ) ) { return $guard; }
		}
		if ( array_key_exists( 'custom_slug', $input ) ) {
			$slug = sanitize_title( (string) $input['custom_slug'] );
			if ( '' !== $slug ) {
				$registry = $this->find_by_slug_strict( $slug );
				if ( is_wp_error( $registry ) ) { return $registry; }
			}
		}
		try {
			$result = $this->central_update_profile( $actor_id, $input, $expected_version, $idempotency_key );
		} catch ( Throwable $exception ) {
			$replay = $this->completed_idempotency_response( $actor_id, 'update_central_profile', $idempotency_key );
			if ( is_wp_error( $replay ) ) { return $replay; }
			if ( is_array( $replay ) ) {
				try { $this->purge_profile_cache( $profile ); } catch ( Throwable $ignored ) {}
				try {
					do_action( 'sabri_file24_profile_post_commit_recovery', array(
						'owner'           => 'file03',
						'command'         => 'update_central_profile',
						'exception_class' => sanitize_key( get_class( $exception ) ),
						'public_id_hash'  => hash( 'sha256', (string) $profile['public_id'] ),
						'at'              => SPD_Helpers::now(),
					) );
				} catch ( Throwable $ignored ) {}
				return $replay;
			}
			$this->idempotency_fail( $actor_id, 'update_central_profile', $idempotency_key );
			return new WP_Error( 'spd_central_update_failed', __( 'The profile update could not be completed safely.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( is_wp_error( $result ) && 'spd_profile_unavailable' === $result->get_error_code() ) {
			$confirm = $this->central_target_preflight( $target_user_id );
			if ( is_wp_error( $confirm ) ) { return $confirm; }
		}
		return $result;
	}

	public function rotate_share_link( $actor_id, $expected_version, $idempotency_key = '' ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$profile = $this->find_by_user_id( $actor_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $actor_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$expected_version = absint( $expected_version );
		if ( $expected_version < 1 ) { return new WP_Error( 'spd_version_required', __( 'A current profile version is required.', 'sabri-profiles-doctors' ), array( 'status' => 428 ) ); }

		// A committed response may have been lost after the server completed the
		// rotation. Check that exact actor/command/key before rejecting the old
		// version; the response version binds the replay to this requested version.
		$prior = $this->completed_idempotency_response( $actor_id, 'rotate_share_link', $idempotency_key );
		if ( is_wp_error( $prior ) ) { return $prior; }
		if ( is_array( $prior ) && absint( $prior['version'] ?? 0 ) === $expected_version + 1 && (string) ( $prior['public_id'] ?? '' ) === (string) $profile['public_id'] ) { return $prior; }
		if ( $expected_version !== absint( $profile['version'] ) ) { return new WP_Error( 'spd_version_conflict', __( 'Reload your profile before rotating the share link.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }

		$epoch = SPD_Central_Profile::share_epoch( $profile ) + 1;
		$request_hash = hash( 'sha256', $profile['public_id'] . '|' . $epoch . '|' . $expected_version );
		$idem = $this->idempotency_begin( $actor_id, 'rotate_share_link', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$new_version = $expected_version + 1;
		$future_profile = $profile;
		$future_profile['fields']['share_epoch'] = array( 'field_value' => (string) $epoch, 'audience' => 'private' );
		$response = array( 'public_id' => $profile['public_id'], 'version' => $new_version, 'share_url' => SPD_Central_Profile::short_url( $future_profile ) );
		$profiles = SPD_DB::table( 'profiles' );
		$result = SPD_DB::transaction( function() use ( $wpdb, $profiles, $profile, $epoch, $expected_version, $new_version, $actor_id, $idempotency_key, $response ) {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$profiles} SET version=%d,updated_at=%s WHERE id=%d AND version=%d", $new_version, SPD_Helpers::now(), $profile['id'], $expected_version ) );
			if ( 1 !== $updated ) { return new WP_Error( 'spd_version_conflict', __( 'The profile changed while rotating its share link.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			if ( ! $this->upsert_field( $profile['id'], 'share_epoch', (string) $epoch, 'private', 'approved', 'file03' ) ) { return new WP_Error( 'spd_share_rotation_failed', __( 'The share-link revision could not be recorded.', 'sabri-profiles-doctors' ) ); }
			$event = $this->event( 'ProfileShareLinkRotated.v1', 'profile', $profile['public_id'], array( 'version' => $new_version ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $actor_id, 'rotate_share_link', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $actor_id, 'rotate_share_link', $idempotency_key ); return $result; }
		$this->purge_profile_cache( $profile );
		return $response;
	}

	public function grant_delegate( $owner_id, $delegate_id, array $scopes, $expires_at = '', $idempotency_key = '' ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$delegate_id = absint( $delegate_id );
		if ( ! $owner_id || ! $delegate_id || $owner_id === $delegate_id ) { return new WP_Error( 'spd_delegate_invalid', __( 'Choose a different eligible account as delegate.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$profile = $this->central_target_preflight( $owner_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $owner_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		if ( 'doctor' !== ( $profile['profile_type'] ?? '' ) ) { return new WP_Error( 'spd_delegate_owner_ineligible', __( 'Delegated profile management is available only to a currently verified doctor.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }

		$membership_health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $membership_health['status'] ?? '' ) ) { return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership authorization is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$owner_claims = SPD_Membership_Adapter::claims( $owner_id );
		$delegate_claims = SPD_Membership_Adapter::claims( $delegate_id );
		if ( ! $owner_claims || ! $delegate_claims ) { return new WP_Error( 'spd_membership_claim_unavailable', __( 'Current delegation eligibility could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( empty( $owner_claims['eligible'] ) || ! empty( $owner_claims['suspended'] ) || empty( $delegate_claims['eligible'] ) || ! empty( $delegate_claims['suspended'] ) ) { return new WP_Error( 'spd_delegate_ineligible', __( 'The owner or delegate account is not currently eligible.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		if ( ! empty( $delegate_claims['is_minor'] ) ) { return new WP_Error( 'spd_delegate_minor_forbidden', __( 'A minor account cannot receive delegated profile-management authority.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }

		$verification_health = SPD_Verification_Adapter::health();
		if ( 'available' !== ( $verification_health['status'] ?? '' ) ) { return new WP_Error( 'spd_verification_provider_unavailable', __( 'Doctor verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$owner_verification = SPD_Verification_Adapter::projection( $owner_id );
		if ( ! $owner_verification ) { return new WP_Error( 'spd_verification_claim_unavailable', __( 'Current doctor-verification evidence could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( 'verified' !== sanitize_key( (string) ( $owner_verification['status'] ?? '' ) ) ) { return new WP_Error( 'spd_delegate_owner_ineligible', __( 'Delegated profile management is available only to a currently verified doctor.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }

		$scopes = array_values( array_unique( array_intersect( array_map( 'sanitize_key', $scopes ), SPD_Central_Profile::delegation_scopes() ) ) );
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
		$table = SPD_Central_Profile::delegation_table();
		$now = SPD_Helpers::now();
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $profile, $owner_id, $delegate_id, $scopes, $expires_value, $now, $response, $idempotency_key ) {
			$wpdb->last_error = '';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { return $this->delegation_store_error(); }
			$data = array( 'profile_id' => $profile['id'], 'scopes' => implode( ',', $scopes ), 'status' => 'active', 'expires_at' => $expires_value, 'updated_at' => $now );
			$wpdb->last_error = '';
			if ( $row ) {
				$data['version'] = absint( $row['version'] ) + 1;
				$ok = $wpdb->update( $table, $data, array( 'id' => absint( $row['id'] ), 'version' => absint( $row['version'] ) ) );
			} else {
				$data += array( 'owner_user_id' => $owner_id, 'delegate_user_id' => $delegate_id, 'version' => 1, 'created_at' => $now );
				$ok = $wpdb->insert( $table, $data );
			}
			if ( $wpdb->last_error || false === $ok ) { return $this->delegation_store_error(); }
			if ( 0 === $ok ) { return new WP_Error( 'spd_delegate_save_conflict', __( 'The delegation changed concurrently. Reload and try again.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileDelegationChanged.v1', 'profile', $profile['public_id'], array( 'delegate_user_id' => $delegate_id, 'status' => 'active', 'scopes' => $scopes ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $owner_id, 'grant_profile_delegate', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $owner_id, 'grant_profile_delegate', $idempotency_key ); return $result; }
		return $response;
	}

	public function revoke_delegate( $owner_id, $delegate_id, $idempotency_key = '' ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$delegate_id = absint( $delegate_id );
		if ( ! $owner_id || ! $delegate_id ) { return new WP_Error( 'spd_delegate_invalid', __( 'Choose a valid delegated account.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$profile = $this->central_target_preflight( $owner_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$guard = SPD_Authorization::mutation_guard( $profile, $owner_id );
		if ( is_wp_error( $guard ) ) { return $guard; }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $owner_id, $delegate_id ) ) );
		$idem = $this->idempotency_begin( $owner_id, 'revoke_profile_delegate', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$response = array( 'delegate_user_id' => $delegate_id, 'status' => 'revoked' );
		$table = SPD_Central_Profile::delegation_table();
		$result = SPD_DB::transaction( function() use ( $wpdb, $table, $profile, $owner_id, $delegate_id, $response, $idempotency_key ) {
			$wpdb->last_error = '';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d AND status='active' LIMIT 1", $owner_id, $delegate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { return $this->delegation_store_error(); }
			if ( ! $row ) { return new WP_Error( 'spd_delegate_not_active', __( 'No active delegation exists for that account.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
			$wpdb->last_error = '';
			$ok = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='revoked',version=version+1,updated_at=%s WHERE id=%d AND version=%d AND status='active'", SPD_Helpers::now(), absint( $row['id'] ), absint( $row['version'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error || false === $ok ) { return $this->delegation_store_error(); }
			if ( 1 !== $ok ) { return new WP_Error( 'spd_delegate_revoke_failed', __( 'The delegation changed concurrently and could not be revoked.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
			$event = $this->event( 'ProfileDelegationChanged.v1', 'profile', $profile['public_id'], array( 'delegate_user_id' => $delegate_id, 'status' => 'revoked' ) );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! $this->idempotency_complete( $owner_id, 'revoke_profile_delegate', $idempotency_key, $response ) ) { return new WP_Error( 'spd_idempotency_finalize_failed', __( 'The replay-protection result could not be committed.', 'sabri-profiles-doctors' ) ); }
			return true;
		} );
		if ( is_wp_error( $result ) ) { $this->idempotency_fail( $owner_id, 'revoke_profile_delegate', $idempotency_key ); return $result; }
		return $response;
	}

	public function list_delegates( $owner_id ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		if ( ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::central_ready() ) { return $this->delegation_store_error(); }
		$table = SPD_Central_Profile::delegation_table();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT delegate_user_id,scopes,status,expires_at,version FROM {$table} WHERE owner_user_id=%d AND status='active' ORDER BY updated_at DESC LIMIT 50", $owner_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $rows ) ) { return $this->delegation_store_error(); }
		$out = array();
		foreach ( $rows as $row ) {
			if ( $row['expires_at'] && strtotime( $row['expires_at'] ) <= time() ) { continue; }
			$user = get_userdata( absint( $row['delegate_user_id'] ) );
			$out[] = array( 'user_id' => absint( $row['delegate_user_id'] ), 'display_name' => $user ? $user->display_name : __( 'Account', 'sabri-profiles-doctors' ), 'scopes' => array_values( array_filter( array_map( 'sanitize_key', explode( ',', $row['scopes'] ) ) ) ), 'expires_at' => $row['expires_at'], 'version' => absint( $row['version'] ) );
		}
		return $out;
	}

	public function delegate_can_manage( $owner_id, $delegate_id, $scope ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$delegate_id = absint( $delegate_id );
		$scope = sanitize_key( $scope );
		if ( ! $owner_id || ! $delegate_id || ! in_array( $scope, SPD_Central_Profile::delegation_scopes(), true ) || ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::central_ready() ) { return false; }
		$profile = $this->find_by_user_id( $owner_id, false );
		if ( ! $profile || ! empty( $profile['_fields_read_failed'] ) || ! SPD_Authorization::profile_mutation_state_allows( $profile ) ) { return false; }
		if ( 'doctor' !== ( $profile['profile_type'] ?? '' ) || SPD_Membership_Adapter::is_minor( $owner_id ) || SPD_Membership_Adapter::is_minor( $delegate_id ) ) { return false; }
		$table = SPD_Central_Profile::delegation_table();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT scopes,expires_at FROM {$table} WHERE owner_user_id=%d AND delegate_user_id=%d AND status='active' LIMIT 1", $owner_id, $delegate_id ), ARRAY_A );
		if ( $wpdb->last_error || ! $row || ( $row['expires_at'] && strtotime( $row['expires_at'] ) <= time() ) ) { return false; }
		if ( ! SPD_Membership_Adapter::is_member_eligible( $owner_id ) || ! SPD_Membership_Adapter::is_member_eligible( $delegate_id ) || ! SPD_Verification_Adapter::is_verified( $owner_id ) ) { return false; }
		return in_array( $scope, array_filter( array_map( 'sanitize_key', explode( ',', $row['scopes'] ) ) ), true );
	}

	public function create_safety_report( $public_id, $reporter_user_id, $reason, $details, $idempotency_key = '' ) {
		global $wpdb;
		$reporter_user_id = absint( $reporter_user_id );
		if ( ! $reporter_user_id ) { return new WP_Error( 'spd_login_required', __( 'A signed-in account is required to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$membership_health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $membership_health['status'] ?? '' ) ) { return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$reporter_claims = SPD_Membership_Adapter::claims( $reporter_user_id );
		if ( ! $reporter_claims ) { return new WP_Error( 'spd_membership_claim_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( empty( $reporter_claims['eligible'] ) || ! empty( $reporter_claims['suspended'] ) ) { return new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible to report a profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$profile = $this->find_by_public_id_strict( $public_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $reporter_user_id ) ) { return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$reason = sanitize_key( $reason );
		$details = SPD_Helpers::sanitize_multiline( $details, 3000 );
		if ( ! in_array( $reason, SPD_Central_Profile::report_reasons(), true ) ) { return new WP_Error( 'spd_invalid_report_reason', __( 'Choose a valid report reason.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( SPD_Helpers::text_length( $details ) < 10 ) { return new WP_Error( 'spd_report_details_required', __( 'Provide enough detail for a fair review.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $public_id, $reason, $details ) ) );
		$idem = $this->idempotency_begin( $reporter_user_id, 'create_safety_report', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$reports = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE reporter_user_id=%d AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)", $reporter_user_id ) );
		if ( $wpdb->last_error ) { $this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key ); return new WP_Error( 'spd_report_store_unavailable', __( 'Profile reporting is temporarily unavailable because report-rate evidence could not be read safely.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$count = absint( $count_raw );
		if ( $count >= 5 || ! SPD_Helpers::consume_rate_limit( 'profile_report_' . $reporter_user_id, 5, DAY_IN_SECONDS ) ) { $this->idempotency_fail( $reporter_user_id, 'create_safety_report', $idempotency_key ); return new WP_Error( 'spd_report_rate_limited', __( 'Too many reports were submitted. Try again later.', 'sabri-profiles-doctors' ), array( 'status' => 429 ) ); }
		$uuid = SPD_Helpers::public_id();
		$now = SPD_Helpers::now();
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
		global $wpdb;
		$requester_id = absint( $requester_id );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 2000 );
		if ( ! $requester_id ) { return new WP_Error( 'spd_login_required', __( 'A signed-in account is required to appeal a report.', 'sabri-profiles-doctors' ), array( 'status' => 401 ) ); }
		$membership_health = SPD_Membership_Adapter::health();
		if ( 'available' !== ( $membership_health['status'] ?? '' ) ) { return new WP_Error( 'spd_membership_provider_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$requester_claims = SPD_Membership_Adapter::claims( $requester_id );
		if ( ! $requester_claims ) { return new WP_Error( 'spd_membership_claim_unavailable', __( 'Membership verification is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( empty( $requester_claims['eligible'] ) || ! empty( $requester_claims['suspended'] ) ) { return new WP_Error( 'spd_account_ineligible', __( 'This account is not currently eligible to appeal a report.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		if ( SPD_Helpers::text_length( $reason ) < 10 ) { return new WP_Error( 'spd_appeal_reason_required', __( 'Provide a clear reason for the appeal.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$reports = SPD_DB::table( 'reports' );
		$report_uuid = sanitize_text_field( $report_uuid );
		$wpdb->last_error = '';
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT id,reporter_user_id,status FROM {$reports} WHERE report_uuid=%s LIMIT 1", $report_uuid ), ARRAY_A );
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_report_store_unavailable', __( 'The profile-report store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		if ( ! $report || absint( $report['reporter_user_id'] ) !== $requester_id || ! in_array( $report['status'], array( 'rejected','closed','actioned' ), true ) ) { return new WP_Error( 'spd_appeal_unavailable', __( 'This report is not eligible for appeal by this account.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) ); }
		$request_hash = hash( 'sha256', SPD_Helpers::json_encode( array( $report_uuid, $requester_id, $reason ) ) );
		$idem = $this->idempotency_begin( $requester_id, 'request_report_appeal', $idempotency_key, $request_hash, true );
		if ( is_wp_error( $idem ) ) { return $idem; }
		if ( is_array( $idem ) && isset( $idem['replay'] ) ) { return $idem['response']; }
		$table = SPD_Central_Profile::appeals_table();
		$uuid = SPD_Helpers::public_id();
		$now = SPD_Helpers::now();
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

	public function future_idempotency_begin( $actor_id, $command, $key, $request_hash ) { return $this->idempotency_begin( $actor_id, $command, $key, $request_hash, true ); }
	public function future_idempotency_complete( $actor_id, $command, $key, array $response ) { return $this->idempotency_complete( $actor_id, $command, $key, $response ); }
	public function future_idempotency_fail( $actor_id, $command, $key ) { $this->idempotency_fail( $actor_id, $command, $key ); }
}
