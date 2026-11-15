<?php
/**
 * File03 owns the monotonically versioned public-profile projection. File00
 * retains authority over WordPress user identity and its approved display name.
 *
 * A WordPress user display-name change MUST NOT silently leave File26 with
 * permanently hidden stale identity. We update only File03's source projection
 * version, write the File03 outbox record in the same InnoDB transaction, and
 * let the existing bounded outbox worker obtain File26's explicit ACK.
 *
 * No new table, copied identity store, untrusted webhook or File26 ranking.
 */
defined( 'ABSPATH' ) || exit;

final class SPD_File26_Identity_Name_Sync {
	const SOURCE_KIND = 'file00_wordpress_display_name';
	const EVENT_NAME = 'PublicProfileUpdated.v1';

	/** WordPress core profile_update($user_id, $old_user_data). */
	public static function on_profile_update( $user_id, $old_user_data ) {
		if ( ! is_object( $old_user_data ) || ! isset( $old_user_data->display_name )
			|| ! is_scalar( $old_user_data->display_name ) ) {
			return;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! function_exists( 'get_userdata' ) ) { return; }
		$current_user = get_userdata( $user_id );
		if ( ! $current_user || ! isset( $current_user->display_name )
			|| ! is_scalar( $current_user->display_name ) ) {
			return;
		}
		$before = sanitize_text_field( (string) $old_user_data->display_name );
		$after = sanitize_text_field( (string) $current_user->display_name );
		if ( hash_equals( $before, $after ) ) { return; } // Not an identity change.
		// If File00, File03 or the database is not ready we never invent
		// approval or silently force a stale derived index into public search.
		if ( ! class_exists( 'SPD_Membership_Adapter' ) || ! SPD_Membership_Adapter::available()
			|| ! class_exists( 'SPD_Schema_Guard' ) || ! SPD_Schema_Guard::base_ready() ) {
			self::record_failure( 'identity_owner_schema_unavailable', $user_id );
			return;
		}
		global $wpdb;
		$profiles = SPD_DB::table( 'profiles' );
		$old_profile = SPD_Profile_Repository::instance()->find_by_user_id( $user_id, false );
		if ( is_wp_error( $old_profile ) || ! is_array( $old_profile )
			|| empty( $old_profile['id'] ) ) {
			// File03 profile might not yet exist: File00 still owns user creation.
			return;
		}
		// Existing File03 transaction helper enforces commit/rollback on any
		// failed source/outbox operation. No best-effort version bump is allowed.
		$committed = SPD_DB::transaction( static function () use ( $wpdb, $profiles, $user_id ) {
			$wpdb->last_error = '';
			$profile = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,public_id,version,state FROM {$profiles} WHERE user_id=%d LIMIT 1 FOR UPDATE",
					$user_id
				),
				ARRAY_A
			);
			if ( $wpdb->last_error || ! is_array( $profile ) ) {
				return new WP_Error( 'spd_identity_profile_lock_failed', 'Cannot lock canonical profile for identity version update.' );
			}
			$raw_version = (string) $profile['version'];
			$version = preg_match( '/^[1-9][0-9]*$/D', $raw_version )
				? filter_var( $raw_version, FILTER_VALIDATE_INT ) : false;
			if ( false === $version || $version >= PHP_INT_MAX - 1 ) {
				return new WP_Error( 'spd_identity_profile_version_invalid', 'Canonical profile version cannot be safely advanced.' );
			}
			$new_version = $version + 1;
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$profiles} SET version=%d,updated_at=%s WHERE id=%d AND version=%d",
					$new_version, SPD_Helpers::now(), absint( $profile['id'] ), $version
				)
			);
			if ( 1 !== $written || $wpdb->last_error ) {
				return new WP_Error( 'spd_identity_projection_version_write_failed', 'Canonical profile projection did not advance atomically.' );
			}
			$event = SPD_Profile_Repository::instance()->event(
				self::EVENT_NAME,
				'profile',
				(string) $profile['public_id'],
				array(
					'user_id' => $user_id,
					'public_id' => (string) $profile['public_id'],
					'previous_version' => $version,
					'version' => $new_version,
					'changed_fields' => array( 'display_name' ),
					'source_kind' => self::SOURCE_KIND,
					'source_owner' => 'file00',
				)
			);
			if ( is_wp_error( $event ) ) { return $event; }
			return array(
				'public_id' => (string) $profile['public_id'],
				'user_id' => $user_id,
				'version' => $new_version,
				'event_uuid' => (string) $event,
			);
		} );
		if ( is_wp_error( $committed ) ) {
			self::record_failure( $committed->get_error_code(), $user_id );
			return;
		}
		// On the request immediately after WordPress identity mutation, old
		// File26 hits are already denied by File03's current can_view check.
		// The durable outbox remains recoverable even if the following cache
		// invalidation or WP-Cron scheduling fails.
		try {
			SPD_Profile_Repository::instance()->purge_profile_cache( $committed );
		} catch ( Throwable $ignored ) {
			self::record_failure( 'identity_post_commit_invalidation_failure', $user_id );
		}
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( time() + 30, 'spd_dispatch_outbox' );
		}
	}

	/**
	 * Native File03 outbox delivery. Return typed result, NEVER a fire-and-forget
	 * do_action callback whose ignored return could falsely ACK the source.
	 *
	 * Dispatcher calls this before setting the outbox status to delivered;
	 * WP_Error causes existing retry/backoff/dead-letter, not false success.
	 */
	public static function deliver_file26( $event_name, array $payload, array $row ) {
		if ( self::EVENT_NAME !== $event_name
			|| self::SOURCE_KIND !== (string) ( $payload['source_kind'] ?? '' ) ) {
			return true; // Other existing File03 events keep their own transport.
		}
		$public_id = (string) ( $payload['public_id'] ?? '' );
		$version_raw = $payload['version'] ?? null;
		if ( ! SPD_Helpers::valid_uuid( $public_id )
			|| ! ( is_string( $version_raw ) || is_int( $version_raw ) )
			|| ! preg_match( '/^[1-9][0-9]*$/D', (string) $version_raw ) ) {
			return new WP_Error( 'spd_file26_identity_event_invalid', 'Invalid canonical identity source event.' );
		}
		$event_version = filter_var( (string) $version_raw, FILTER_VALIDATE_INT );
		if ( false === $event_version || $event_version < 1 ) {
			return new WP_Error( 'spd_file26_identity_event_version_invalid', 'Invalid canonical identity source version.' );
		}
		if ( (string) ( $row['aggregate_id'] ?? '' ) !== $public_id
			|| (string) ( $row['aggregate_type'] ?? '' ) !== 'profile' ) {
			return new WP_Error( 'spd_file26_identity_event_owner_mismatch', 'Outbox source does not match canonical profile.' );
		}
		if ( ! SPD_Membership_Adapter::available() || ! SPD_Schema_Guard::base_ready() ) {
			return new WP_Error( 'spd_file26_identity_owner_unavailable', 'Canonical File03/File00 owner is unavailable.' );
		}
		$profile = SPD_Profile_Repository::instance()->find_by_public_id_strict( $public_id );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( ! $profile ) {
			// A deleted profile cannot safely be reconstructed from an old
			// outbox payload. Its dedicated erasure/tombstone owner path wins.
			return new WP_Error( 'spd_file26_identity_owner_deleted', 'Identity owner no longer has an attestable profile.' );
		}
		$current_version = isset( $profile['version'] ) ? absint( $profile['version'] ) : 0;
		if ( $current_version < $event_version ) {
			return new WP_Error( 'spd_file26_identity_event_ahead', 'Owner state has not reached the committed event version.' );
		}
		// A known restricted current profile must not be indexed. Use current
		// source version for versioned revocation; never echo hidden data.
		if ( 'active' !== (string) ( $profile['state'] ?? '' )
			|| 'public' !== (string) ( $profile['profile_visibility'] ?? 'private' ) ) {
			if ( ! function_exists( 'sabri_file26_tombstone_document' ) ) {
				return new WP_Error( 'spd_file26_transport_unavailable', 'File26 revocation consumer unavailable.' );
			}
			return sabri_file26_tombstone_document(
				'file03-profiles', 'profiles', $public_id, $current_version, 'restricted'
			);
		}
		$type = (string) ( $profile['profile_type'] ?? '' );
		$entity_type = 'founder' === $type ? 'founder' : ( 'doctor' === $type ? 'doctor' : 'member_profile' );
		$document = SPD_Contracts::file26_fetch_object( $entity_type, $public_id );
		if ( ! is_array( $document ) || empty( $document['canonical_url'] )
			|| (int) ( $document['object_version'] ?? 0 ) < $event_version ) {
			return new WP_Error( 'spd_file26_current_projection_unavailable', 'Current approved owner projection is unavailable.' );
		}
		if ( ! function_exists( 'sabri_file26_upsert_document' ) ) {
			return new WP_Error( 'spd_file26_transport_unavailable', 'File26 owner-ACK consumer unavailable.' );
		}
		$ack = sabri_file26_upsert_document( $document );
		if ( is_wp_error( $ack ) ) { return $ack; }
		if ( true !== $ack && ( ! is_string( $ack ) || ! preg_match( '/^[a-f0-9]{64}$/D', $ack ) ) ) {
			return new WP_Error( 'spd_file26_identity_ack_invalid', 'File26 did not return a verifiable indexing receipt.' );
		}
		return true;
	}

	private static function record_failure( $code, $user_id ) {
		$record = array(
			'code' => sanitize_key( $code ),
			'owner' => 'file03',
			'source' => 'file00',
			'user_id_hash' => hash( 'sha256', (string) absint( $user_id ) ),
			'at' => SPD_Helpers::now(),
		);
		update_option( 'spd_file26_identity_last_error', $record, false );
		do_action( 'sabri_file24_file03_identity_projection_failure', $record );
	}
}
