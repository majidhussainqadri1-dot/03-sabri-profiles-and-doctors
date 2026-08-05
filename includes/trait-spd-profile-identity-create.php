<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Identity_Create {
	public function ensure_for_user( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$user = get_userdata( $user_id );
		if ( ! $user || ! SPD_Membership_Adapter::available() ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'The profile identity is unavailable.', 'sabri-profiles-doctors' ) );
		}
		$table = SPD_DB::table( 'profiles' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$claims = SPD_Membership_Adapter::claims( $user_id );
		$type = ! empty( $claims['is_founder'] ) ? 'founder' : ( SPD_Membership_Adapter::is_doctor( $user_id ) ? 'doctor' : 'member' );
		$state = $this->runtime_state( $user_id, $row['state'] ?? 'incomplete' );
		$locale = SPD_Helpers::normalize_locale( $claims['locale'] ?? get_user_locale( $user_id ) );
		$slug = $this->unique_slug( SPD_Helpers::slug_base( $claims['display_name'] ?? $user->display_name, $user_id ), $row['id'] ?? 0 );
		$now = SPD_Helpers::now();

		if ( ! $row ) {
			$public_id = SPD_Helpers::public_id();
			$inserted = $wpdb->insert(
				$table,
				array(
					'user_id'      => $user_id,
					'public_id'    => $public_id,
					'slug'         => $slug,
					'profile_type' => $type,
					'state'        => $state,
					'locale'       => $locale,
					'country'      => sanitize_text_field( $claims['country'] ?? '' ),
					'city'         => sanitize_text_field( $claims['city'] ?? '' ),
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				$race = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $race ) {
					return $this->hydrate( $race );
				}
				return new WP_Error( 'spd_profile_create_failed', __( 'The profile could not be created.', 'sabri-profiles-doctors' ) );
			}
			$profile_id = absint( $wpdb->insert_id );
			$this->record_slug( $profile_id, $slug, true );
			$this->initialize_visibility( $profile_id, $user_id, $type );
			$this->event( 'PublicProfileUpdated.v1', 'profile', $public_id, array( 'user_id' => $user_id, 'change' => 'created', 'version' => 1 ) );
			$row = $this->find_by_id( $profile_id );
		} else {
			$changes = array();
			if ( $row['slug'] !== $slug ) {
				$changes['slug'] = $slug;
			}
			if ( $row['profile_type'] !== $type ) {
				$changes['profile_type'] = $type;
			}
			if ( $row['state'] !== $state && SPD_Helpers::state_transition_allowed( $row['state'], $state, 'profile' ) ) {
				$changes['state'] = $state;
			}
			if ( $row['locale'] !== $locale ) {
				$changes['locale'] = $locale;
			}
			if ( $changes ) {
				$changes['version'] = absint( $row['version'] ) + 1;
				$changes['updated_at'] = $now;
				$wpdb->update( $table, $changes, array( 'id' => absint( $row['id'] ) ) );
				if ( isset( $changes['slug'] ) ) {
					$this->record_slug( absint( $row['id'] ), $slug, true );
				}
				$row = $this->find_by_id( absint( $row['id'] ) );
			}
		}
		return $this->hydrate( $row );
	}

	private function runtime_state( $user_id, $stored_state ) {
		if ( SPD_Membership_Adapter::is_founder( $user_id ) ) {
			return 'active';
		}
		$status = SPD_Membership_Adapter::status( $user_id );
		if ( 'suspended' === $status ) {
			return 'suspended';
		}
		if ( in_array( $status, array( 'rejected', 'erased' ), true ) ) {
			return 'archived';
		}
		if ( SPD_Membership_Adapter::is_minor( $user_id ) && ! SPD_Membership_Adapter::guardian_verified( $user_id ) ) {
			return 'limited';
		}
		if ( SPD_Membership_Adapter::is_approved( $user_id ) ) {
			return 'active';
		}
		return in_array( $stored_state, array( 'archived', 'tombstoned' ), true ) ? $stored_state : 'incomplete';
	}

	private function unique_slug( $base, $profile_id = 0 ) {
		global $wpdb;
		$table = SPD_DB::table( 'slugs' );
		$candidate = $base;
		$counter = 2;
		while ( true ) {
			$owner = $wpdb->get_var( $wpdb->prepare( "SELECT profile_id FROM {$table} WHERE slug = %s LIMIT 1", $candidate ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $owner || absint( $owner ) === absint( $profile_id ) ) {
				return $candidate;
			}
			$candidate = substr( $base, 0, 150 ) . '-' . $counter;
			$counter++;
		}
	}

	private function record_slug( $profile_id, $slug, $current ) {
		global $wpdb;
		$table = SPD_DB::table( 'slugs' );
		if ( $current ) {
			$wpdb->update( $table, array( 'is_current' => 0 ), array( 'profile_id' => absint( $profile_id ) ), array( '%d' ), array( '%d' ) );
		}
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) {
			$wpdb->update( $table, array( 'profile_id' => absint( $profile_id ), 'is_current' => $current ? 1 : 0 ), array( 'id' => absint( $existing ) ) );
			return;
		}
		$wpdb->insert(
			$table,
			array( 'profile_id' => absint( $profile_id ), 'slug' => sanitize_title( $slug ), 'is_current' => $current ? 1 : 0, 'created_at' => SPD_Helpers::now() ),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	private function initialize_visibility( $profile_id, $user_id, $type ) {
		$minor = SPD_Membership_Adapter::is_minor( $user_id );
		$defaults = array(
			'profile_visibility' => 'founder' === $type ? 'public' : 'private',
			'bio'                => $minor ? 'members' : 'public',
			'country'            => $minor ? 'private' : 'public',
			'city'               => 'private',
			'languages'          => $minor ? 'members' : 'public',
			'studied_books'      => $minor ? 'members' : 'public',
			'phone'              => 'private',
			'email'              => 'private',
			'whatsapp'           => 'private',
			'internal_message'   => $minor ? 'private' : 'members',
		);
		foreach ( $defaults as $key => $audience ) {
			$this->upsert_field( $profile_id, $key, 'internal_message' === $key ? '1' : '', $audience, 'approved', 'file03' );
		}
		if ( 'founder' === $type ) {
			foreach ( self::founder_fields() as $key ) {
				$this->upsert_field( $profile_id, $key, '', 'public', 'approved', 'file03' );
			}
		}
	}

}
