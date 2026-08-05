<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Media {
	const OWNER_META   = '_spd_media_owner_user_id';
	const PURPOSE_META = '_spd_media_purpose';
	const STATE_META   = '_spd_media_state';

	public static function prepare_upload( $user_id, $field, $purpose, array $context = array() ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( $purpose );
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return array();
		}
		if ( ! in_array( $purpose, array( 'avatar', 'cover' ), true ) ) {
			return new WP_Error( 'spd_media_purpose', __( 'The media purpose is invalid.', 'sabri-profiles-doctors' ) );
		}
		$rate_key = 'spd_media_rate_' . $user_id;
		$count = absint( get_transient( $rate_key ) );
		if ( $count >= 10 ) {
			return new WP_Error( 'spd_media_rate_limit', __( 'Too many profile uploads were attempted. Try again later.', 'sabri-profiles-doctors' ) );
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		$file = $_FILES[ $field ];
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || (int) $file['size'] < 1 || (int) $file['size'] > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'spd_upload', __( 'The image is invalid or exceeds 5 MB.', 'sabri-profiles-doctors' ) );
		}
		$mimes = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], array_values( $mimes ), true ) ) {
			return new WP_Error( 'spd_upload_type', __( 'Only genuine JPG, PNG, or WebP images are accepted.', 'sabri-profiles-doctors' ) );
		}
		$dimensions = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $dimensions ) || empty( $dimensions[0] ) || empty( $dimensions[1] ) || (int) $dimensions[0] * (int) $dimensions[1] > 40000000 ) {
			return new WP_Error( 'spd_upload_dimensions', __( 'The image dimensions are invalid or too large.', 'sabri-profiles-doctors' ) );
		}
		$minimum = 'avatar' === $purpose ? 200 : 640;
		if ( (int) $dimensions[0] < $minimum || (int) $dimensions[1] < ( 'avatar' === $purpose ? 200 : 200 ) ) {
			return new WP_Error( 'spd_upload_small', __( 'The image dimensions are too small for this profile placement.', 'sabri-profiles-doctors' ) );
		}

		$scan = apply_filters(
			'spd_profile_media_scan_v1',
			array( 'status' => 'clean', 'provider' => 'native-image-validation', 'reference' => hash_file( 'sha256', $file['tmp_name'] ) ),
			$file['tmp_name'],
			$checked['type'],
			$user_id,
			$purpose
		);
		if ( ! is_array( $scan ) || ! in_array( $scan['status'] ?? '', array( 'clean', 'pending', 'rejected' ), true ) ) {
			return new WP_Error( 'spd_scan_unavailable', __( 'The image safety scan did not return a valid result.', 'sabri-profiles-doctors' ) );
		}
		if ( 'rejected' === $scan['status'] ) {
			return new WP_Error( 'spd_upload_rejected', __( 'The image did not pass the safety scan.', 'sabri-profiles-doctors' ) );
		}
		if ( 'pending' === $scan['status'] ) {
			return new WP_Error( 'spd_upload_scan_pending', __( 'The image cannot enter the public WordPress media store until the safety scan is complete.', 'sabri-profiles-doctors' ) );
		}

		self::strip_metadata( $file['tmp_name'] );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload(
			$field,
			0,
			array( 'post_author' => $user_id ),
			array( 'test_form' => false, 'mimes' => $mimes )
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		$attachment_id = absint( $attachment_id );
		$state = 'active';
		update_post_meta( $attachment_id, self::OWNER_META, $user_id );
		update_post_meta( $attachment_id, self::PURPOSE_META, $purpose );
		update_post_meta( $attachment_id, self::STATE_META, $state );
		$alt = sanitize_text_field( (string) ( $context['alt_text'] ?? '' ) );
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		return array(
			'attachment_id' => $attachment_id,
			'state'         => $state,
			'alt_text'      => $alt,
			'focal_x'       => SPD_Helpers::normalize_focal( $context['focal_x'] ?? 50 ),
			'focal_y'       => SPD_Helpers::normalize_focal( $context['focal_y'] ?? 50 ),
			'scan_provider' => sanitize_text_field( (string) ( $scan['provider'] ?? '' ) ),
			'scan_reference'=> sanitize_text_field( (string) ( $scan['reference'] ?? '' ) ),
		);
	}

	private static function strip_metadata( $path ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->set_quality( 90 );
		$result = $editor->save( $path );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		if ( ! empty( $result['path'] ) && $result['path'] !== $path && file_exists( $result['path'] ) ) {
			copy( $result['path'], $path );
			wp_delete_file( $result['path'] );
		}
		return true;
	}

	public static function complete_scan( $attachment_id, $status, $reference = '' ) {
		global $wpdb;
		$attachment_id = absint( $attachment_id );
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'active', 'rejected' ), true ) ) {
			return false;
		}
		$table = SPD_DB::table( 'media' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE attachment_id=%d LIMIT 1", $attachment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ! SPD_Helpers::state_transition_allowed( $row['state'], $status, 'media' ) ) {
			return false;
		}
		$wpdb->update( $table, array( 'state' => $status, 'scan_reference' => sanitize_text_field( $reference ), 'version' => absint( $row['version'] ) + 1, 'updated_at' => SPD_Helpers::now() ), array( 'id' => absint( $row['id'] ) ) );
		update_post_meta( $attachment_id, self::STATE_META, $status );
		$profile = SPD_Profile_Repository::instance()->find_by_id( absint( $row['profile_id'] ) );
		if ( $profile ) {
			SPD_Profile_Repository::instance()->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'attachment_id' => $attachment_id, 'purpose' => $row['purpose'], 'state' => $status, 'scan_completed' => true ) );
			SPD_Profile_Repository::instance()->purge_profile_cache( $profile );
		}
		return true;
	}

	public static function state( $profile_id, $purpose ) {
		global $wpdb;
		$table = SPD_DB::table( 'media' );
		$state = $wpdb->get_var( $wpdb->prepare( "SELECT state FROM {$table} WHERE profile_id=%d AND purpose=%s LIMIT 1", absint( $profile_id ), sanitize_key( $purpose ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $state ? sanitize_key( $state ) : 'removed';
	}

	public static function delete_owned( $attachment_id, $user_id, $purpose = '' ) {
		$attachment_id = absint( $attachment_id );
		$owner = absint( get_post_meta( $attachment_id, self::OWNER_META, true ) );
		$stored_purpose = sanitize_key( (string) get_post_meta( $attachment_id, self::PURPOSE_META, true ) );
		if ( $attachment_id && $owner === absint( $user_id ) && ( ! $purpose || $stored_purpose === sanitize_key( $purpose ) ) ) {
			update_post_meta( $attachment_id, self::STATE_META, 'removed' );
			return (bool) wp_delete_attachment( $attachment_id, true );
		}
		return false;
	}
}
