<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Identity_Create {
	public function ensure_for_user( $user_id ) {
		global $wpdb; $user_id = absint( $user_id ); $user = get_userdata( $user_id );
		if ( ! $user || ! SPD_Membership_Adapter::available() ) { return new WP_Error( 'spd_profile_unavailable', __( 'The profile identity is unavailable.', 'sabri-profiles-doctors' ) ); }
		$claims = SPD_Membership_Adapter::claims( $user_id ); if ( ! $claims ) { return new WP_Error( 'spd_identity_claims_invalid', __( 'Current File 00 identity claims are unavailable or invalid.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$table = SPD_DB::table( 'profiles' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id=%d LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_profile_identity_read_failed', __( 'The profile identity store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
		$type = ! empty( $claims['is_founder'] ) ? 'founder' : ( SPD_Membership_Adapter::is_doctor( $user_id ) ? 'doctor' : 'member' );
		if ( 'founder' === $type && SPD_Membership_Adapter::founder_id() !== $user_id ) { return new WP_Error( 'spd_founder_identity_conflict', __( 'The Founder identity did not match File 00.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		$state = $this->runtime_state( $user_id, $row['state'] ?? 'incomplete' ); $locale = SPD_Helpers::normalize_locale( $claims['locale'] ?? get_user_locale( $user_id ) );
		$custom_slug_locked = false;
		if ( $row ) {
			$fields_table = SPD_DB::table( 'fields' );
			$wpdb->last_error = '';
			$lock_value = $wpdb->get_var( $wpdb->prepare( "SELECT field_value FROM {$fields_table} WHERE profile_id=%d AND field_key='custom_slug_locked' LIMIT 1", absint( $row['id'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { return new WP_Error( 'spd_profile_slug_lock_read_failed', __( 'The profile address state is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
			$custom_slug_locked = '1' === (string) $lock_value;
		}
		$slug = $row && $custom_slug_locked ? sanitize_title( $row['slug'] ) : $this->unique_slug( SPD_Helpers::slug_base( $claims['display_name'] ?? $user->display_name, $user_id ), $row['id'] ?? 0 );
		if ( is_wp_error( $slug ) ) { return $slug; }
		$now = SPD_Helpers::now();
		if ( ! $row ) {
			$public_id = SPD_Helpers::public_id();
			$result = SPD_DB::transaction( function() use ( $wpdb, $table, $user_id, $public_id, $slug, $type, $state, $locale, $claims, $now ) {
				if ( 'founder' === $type ) {
					$wpdb->last_error = '';
					$existing = $wpdb->get_var( "SELECT id FROM {$table} WHERE profile_type='founder' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( $wpdb->last_error ) { return new WP_Error( 'spd_founder_lookup_failed', __( 'Founder profile state could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
					if ( $existing ) { return new WP_Error( 'spd_founder_profile_exists', __( 'An official Founder profile already exists.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				}
				$ok = $wpdb->insert( $table, array( 'user_id' => $user_id, 'public_id' => $public_id, 'slug' => $slug, 'profile_type' => $type, 'state' => $state, 'locale' => $locale, 'country' => sanitize_text_field( $claims['country'] ?? '' ), 'city' => sanitize_text_field( $claims['city'] ?? '' ), 'created_at' => $now, 'updated_at' => $now ) );
				if ( ! $ok ) {
					$wpdb->last_error = '';
					$race = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id=%d LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( $wpdb->last_error ) { return new WP_Error( 'spd_profile_create_race_read_failed', __( 'The profile create result could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
					return $race ? $race : new WP_Error( 'spd_profile_create_failed', __( 'The profile could not be created.', 'sabri-profiles-doctors' ) );
				}
				$profile_id = absint( $wpdb->insert_id ); $slug_result = $this->record_slug( $profile_id, $slug, true ); if ( is_wp_error( $slug_result ) ) { return $slug_result; }
				$visibility = $this->initialize_visibility( $profile_id, $user_id, $type ); if ( is_wp_error( $visibility ) ) { return $visibility; }
				$event = $this->event( 'PublicProfileUpdated.v1', 'profile', $public_id, array( 'user_id' => $user_id, 'change' => 'created', 'version' => 1 ) ); if ( is_wp_error( $event ) ) { return $event; }
				return array( 'profile_id' => $profile_id );
			} );
			if ( is_wp_error( $result ) ) { return $result; } if ( isset( $result['id'] ) ) { return $this->hydrate( $result ); } return $this->find_by_id( absint( $result['profile_id'] ) );
		}
		$changes = array(); if ( $row['slug'] !== $slug ) { $changes['slug'] = $slug; } if ( $row['profile_type'] !== $type ) { $changes['profile_type'] = $type; } if ( $row['state'] !== $state && SPD_Helpers::state_transition_allowed( $row['state'], $state, 'profile' ) ) { $changes['state'] = $state; } if ( $row['locale'] !== $locale ) { $changes['locale'] = $locale; }
		if ( $changes ) {
			$result = SPD_DB::transaction( function() use ( $wpdb, $table, $row, $changes, $slug, $user_id ) {
				if ( isset( $changes['profile_type'] ) && 'founder' === $changes['profile_type'] ) {
					$wpdb->last_error = '';
					$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE profile_type='founder' AND id<>%d LIMIT 1", absint( $row['id'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( $wpdb->last_error ) { return new WP_Error( 'spd_founder_lookup_failed', __( 'Founder profile state could not be verified.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
					if ( $existing ) { return new WP_Error( 'spd_founder_profile_exists', __( 'An official Founder profile already exists.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				}
				$changes['version'] = absint( $row['version'] ) + 1; $changes['updated_at'] = SPD_Helpers::now();
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET slug=%s,profile_type=%s,state=%s,locale=%s,version=%d,updated_at=%s WHERE id=%d AND version=%d", $changes['slug'] ?? $row['slug'], $changes['profile_type'] ?? $row['profile_type'], $changes['state'] ?? $row['state'], $changes['locale'] ?? $row['locale'], $changes['version'], $changes['updated_at'], $row['id'], $row['version'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( 1 !== $updated ) { return new WP_Error( 'spd_profile_refresh_conflict', __( 'The profile changed while identity assertions were refreshed.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
				if ( isset( $changes['slug'] ) ) { $s = $this->record_slug( $row['id'], $slug, true ); if ( is_wp_error( $s ) ) { return $s; } }
				if ( isset( $changes['profile_type'] ) && 'founder' === $changes['profile_type'] ) {
					$founder = $this->ensure_founder_invariants( absint( $row['id'] ) );
					if ( is_wp_error( $founder ) ) { return $founder; }
				}
				$event = $this->event( 'PublicProfileUpdated.v1', 'profile', $row['public_id'], array( 'user_id' => $user_id, 'change' => 'identity_refresh', 'changed_fields' => array_keys( $changes ), 'version' => absint( $changes['version'] ) ) );
				return is_wp_error( $event ) ? $event : true;
			} );
			if ( is_wp_error( $result ) ) { return $result; }
			$row = $this->find_by_id( $row['id'] );
			if ( is_wp_error( $row ) || ! is_array( $row ) ) { return is_wp_error( $row ) ? $row : new WP_Error( 'spd_profile_refresh_read_failed', __( 'The refreshed profile could not be read safely.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
			$this->purge_profile_cache( $row );
			update_option( 'spd_reconciliation_required', 1, false );
		}
		return $this->hydrate( $row );
	}

	private function runtime_state( $user_id, $stored ) { if ( SPD_Membership_Adapter::is_founder( $user_id ) ) { return 'active'; } $status = SPD_Membership_Adapter::status( $user_id ); if ( in_array( $status, array( 'suspended','appeal_review' ), true ) ) { return 'suspended'; } if ( in_array( $status, array( 'rejected','expired','erasure_pending','invalid_application','erased' ), true ) ) { return 'archived'; } if ( SPD_Membership_Adapter::is_minor( $user_id ) && ! SPD_Membership_Adapter::guardian_verified( $user_id ) ) { return 'limited'; } if ( SPD_Membership_Adapter::is_member_eligible( $user_id ) ) { return 'active'; } if ( SPD_Membership_Adapter::is_approved( $user_id ) ) { return 'limited'; } return in_array( $stored, array( 'archived','tombstoned' ), true ) ? $stored : 'incomplete'; }

	private function unique_slug( $base, $profile_id = 0 ) {
		global $wpdb;
		$table = SPD_DB::table( 'slugs' ); $candidate = sanitize_title( $base ); $counter = 2;
		while ( true ) {
			$wpdb->last_error = '';
			$owner = $wpdb->get_var( $wpdb->prepare( "SELECT profile_id FROM {$table} WHERE slug=%s LIMIT 1", $candidate ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { return new WP_Error( 'spd_slug_lookup_failed', __( 'The profile address registry is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) ); }
			if ( ! $owner || absint( $owner ) === absint( $profile_id ) ) { return $candidate; }
			$candidate = substr( $base, 0, 150 ) . '-' . $counter; $counter++;
			if ( $counter > 10000 ) { return new WP_Error( 'spd_slug_space_exhausted', __( 'A unique profile address could not be allocated safely.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		}
	}

	private function record_slug( $profile_id, $slug, $current ) {
		global $wpdb; $table = SPD_DB::table( 'slugs' ); $slug = sanitize_title( $slug );
		$wpdb->last_error = '';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,profile_id FROM {$table} WHERE slug=%s LIMIT 1", $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_slug_lookup_failed', __( 'The profile address registry is temporarily unavailable.', 'sabri-profiles-doctors' ) ); }
		if ( $existing && absint( $existing['profile_id'] ) !== absint( $profile_id ) ) { return new WP_Error( 'spd_slug_collision', __( 'The requested profile address is already reserved.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) ); }
		if ( $current && false === $wpdb->update( $table, array( 'is_current' => 0 ), array( 'profile_id' => absint( $profile_id ) ) ) ) { return new WP_Error( 'spd_slug_history_failed', __( 'Profile address history could not be updated.', 'sabri-profiles-doctors' ) ); }
		if ( $existing ) { $ok = $wpdb->update( $table, array( 'is_current' => $current ? 1 : 0 ), array( 'id' => absint( $existing['id'] ) ) ); return false === $ok ? new WP_Error( 'spd_slug_update_failed', __( 'The profile address could not be updated.', 'sabri-profiles-doctors' ) ) : true; }
		$ok = $wpdb->insert( $table, array( 'profile_id' => absint( $profile_id ), 'slug' => $slug, 'is_current' => $current ? 1 : 0, 'created_at' => SPD_Helpers::now() ) ); return $ok ? true : new WP_Error( 'spd_slug_insert_failed', __( 'The profile address could not be recorded.', 'sabri-profiles-doctors' ) );
	}

	private function initialize_visibility( $profile_id, $user_id, $type ) {
		$minor = SPD_Membership_Adapter::is_minor( $user_id );
		$defaults = array( 'profile_visibility' => 'founder' === $type ? 'public' : 'private', 'bio' => $minor ? 'members' : 'public', 'country' => $minor ? 'private' : 'public', 'city' => 'private', 'languages' => $minor ? 'members' : 'public', 'studied_books' => $minor ? 'members' : 'public', 'phone' => 'private', 'email' => 'private', 'whatsapp' => 'private', 'internal_message' => $minor ? 'private' : 'members' );
		foreach ( SPD_Central_Profile::extended_fields() as $key ) { $defaults[ $key ] = $minor ? 'private' : ( 'doctor' === $type ? 'public' : 'private' ); }
		foreach ( $defaults as $key => $audience ) { if ( ! $this->upsert_field( $profile_id, $key, 'internal_message' === $key ? '1' : '', $audience, 'approved', 'file03' ) ) { return new WP_Error( 'spd_visibility_initialize_failed', __( 'Profile privacy defaults could not be initialized.', 'sabri-profiles-doctors' ) ); } }
		if ( ! $this->upsert_field( $profile_id, 'share_epoch', '1', 'private', 'approved', 'file03' ) ) { return new WP_Error( 'spd_share_initialize_failed', __( 'Profile share-link state could not be initialized.', 'sabri-profiles-doctors' ) ); }
		if ( 'founder' === $type ) { foreach ( self::founder_fields() as $key ) { if ( ! $this->upsert_field( $profile_id, $key, '', 'public', 'approved', 'file03' ) ) { return new WP_Error( 'spd_founder_fields_initialize_failed', __( 'Founder profile fields could not be initialized.', 'sabri-profiles-doctors' ) ); } } }
		return true;
	}

	private function ensure_founder_invariants( $profile_id ) {
		global $wpdb;
		$profile_id = absint( $profile_id );
		$fields = SPD_DB::table( 'fields' );
		$wpdb->last_error = '';
		$current_visibility = $wpdb->get_row( $wpdb->prepare( "SELECT field_value FROM {$fields} WHERE profile_id=%d AND field_key='profile_visibility' LIMIT 1", $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return new WP_Error( 'spd_founder_visibility_read_failed', __( 'Founder profile visibility could not be verified.', 'sabri-profiles-doctors' ) ); }
		if ( ! $this->upsert_field( $profile_id, 'profile_visibility', (string) ( $current_visibility['field_value'] ?? '' ), 'public', 'approved', 'file03' ) ) { return new WP_Error( 'spd_founder_visibility_failed', __( 'Founder public visibility could not be established.', 'sabri-profiles-doctors' ) ); }
		foreach ( self::founder_fields() as $key ) {
			$wpdb->last_error = '';
			$current = $wpdb->get_row( $wpdb->prepare( "SELECT field_value FROM {$fields} WHERE profile_id=%d AND field_key=%s LIMIT 1", $profile_id, $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { return new WP_Error( 'spd_founder_field_read_failed', __( 'Founder profile fields could not be verified.', 'sabri-profiles-doctors' ) ); }
			if ( ! $this->upsert_field( $profile_id, $key, (string) ( $current['field_value'] ?? '' ), 'public', 'approved', 'file03' ) ) { return new WP_Error( 'spd_founder_fields_initialize_failed', __( 'Founder profile fields could not be initialized.', 'sabri-profiles-doctors' ) ); }
		}
		return true;
	}
}
