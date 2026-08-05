<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Lifecycle {
	public function completeness( array $profile, array $claims, array $professional ) {
		$required = array( 'display_name' => ! empty( $claims['display_name'] ), 'bio' => ! empty( $profile['bio'] ), 'avatar' => ! empty( $profile['avatar_id'] ), 'country' => ! empty( $profile['country'] ), 'languages' => ! empty( $profile['languages'] ) );
		if ( 'doctor' === $profile['profile_type'] ) {
			$required['verification'] = SPD_Verification_Adapter::is_verified( $profile['user_id'] );
			$required['qualification'] = ! empty( $professional['qualification'] );
			$required['specialty'] = ! empty( $professional['specialty'] );
		}
		$complete = count( array_filter( $required ) );
		$total = count( $required );
		return array(
			'complete_items' => $complete,
			'total_items'    => $total,
			'missing'        => array_keys( array_filter( $required, static function ( $done ) { return ! $done; } ) ),
			'label'          => $complete === $total ? __( 'Core profile complete', 'sabri-profiles-doctors' ) : __( 'Complete the missing profile information', 'sabri-profiles-doctors' ),
		);
	}

	public function erase_profile( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$profile = $this->find_by_user_id( $user_id, false );
		if ( ! $profile ) {
			return array( 'removed' => false, 'retained' => false, 'messages' => array() );
		}
		if ( apply_filters( 'spd_profile_legal_hold', false, $user_id, $profile ) ) {
			return array( 'removed' => false, 'retained' => true, 'messages' => array( __( 'Profile data is retained under an active legal or governance hold.', 'sabri-profiles-doctors' ) ) );
		}
		if ( SPD_Membership_Adapter::is_founder( $user_id ) ) {
			return array( 'removed' => false, 'retained' => true, 'messages' => array( __( 'The official Founder profile requires an authorized governance decision before removal.', 'sabri-profiles-doctors' ) ) );
		}
		foreach ( array( 'avatar' => $profile['avatar_id'], 'cover' => $profile['cover_id'] ) as $purpose => $attachment_id ) {
			if ( $attachment_id ) {
				SPD_Media::delete_owned( $attachment_id, $user_id, $purpose );
			}
		}
		$profiles = SPD_DB::table( 'profiles' );
		$fields = SPD_DB::table( 'fields' );
		$media = SPD_DB::table( 'media' );
		$result = SPD_DB::transaction(
			function () use ( $wpdb, $profile, $profiles, $fields, $media ) {
				$wpdb->update(
					$profiles,
					array( 'state' => 'tombstoned', 'bio' => '', 'country' => '', 'city' => '', 'languages' => '', 'studied_books' => '', 'avatar_id' => 0, 'cover_id' => 0, 'version' => $profile['version'] + 1, 'updated_at' => SPD_Helpers::now() ),
					array( 'id' => $profile['id'] )
				);
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$fields} WHERE profile_id=%d", $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$media} WHERE profile_id=%d", $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->event( 'ProfileTombstoned.v1', 'profile', $profile['public_id'], array( 'user_id' => $profile['user_id'], 'version' => $profile['version'] + 1 ) );
				return true;
			}
		);
		$this->purge_profile_cache( $profile );
		return array( 'removed' => ! is_wp_error( $result ), 'retained' => true, 'messages' => array( __( 'A minimal public-ID tombstone and audit events are retained for link integrity and security.', 'sabri-profiles-doctors' ) ) );
	}

}
