<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Media {
	const OWNER_META   = '_spd_media_owner_user_id';
	const PURPOSE_META = '_spd_media_purpose';

	public static function upload( $user_id, $field, $purpose ) {
		$user_id = absint( $user_id );
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		$file = $_FILES[ $field ];
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || (int) $file['size'] < 1 || (int) $file['size'] > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'spd_upload', __( 'The image is invalid or exceeds 5 MB.', 'sabri-profiles-doctors' ) );
		}
		$mimes   = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], array_values( $mimes ), true ) ) {
			return new WP_Error( 'spd_upload', __( 'Only genuine JPG, PNG, or WebP images are accepted.', 'sabri-profiles-doctors' ) );
		}
		$dimensions = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $dimensions ) || empty( $dimensions[0] ) || empty( $dimensions[1] ) || $dimensions[0] * $dimensions[1] > 40000000 ) {
			return new WP_Error( 'spd_upload', __( 'The image dimensions are invalid or too large.', 'sabri-profiles-doctors' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload(
			$field,
			0,
			array( 'post_author' => $user_id ),
			array( 'test_form' => false, 'mimes' => $mimes )
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::OWNER_META, $user_id );
		update_post_meta( $id, self::PURPOSE_META, sanitize_key( $purpose ) );
		return absint( $id );
	}

	public static function replace( $user_id, $meta_key, $new_id, $purpose ) {
		$user_id = absint( $user_id );
		$new_id  = absint( $new_id );
		$old_id  = absint( get_user_meta( $user_id, '_spd_' . $meta_key, true ) );
		if ( $new_id ) {
			update_user_meta( $user_id, '_spd_' . $meta_key, $new_id );
		}
		if ( $old_id && $old_id !== $new_id ) {
			self::delete_owned( $old_id, $user_id, $purpose );
		}
	}

	public static function delete_owned( $attachment_id, $user_id, $purpose = '' ) {
		$attachment_id = absint( $attachment_id );
		$owner         = absint( get_post_meta( $attachment_id, self::OWNER_META, true ) );
		$stored_purpose= sanitize_key( (string) get_post_meta( $attachment_id, self::PURPOSE_META, true ) );
		if ( $attachment_id && $owner === absint( $user_id ) && ( ! $purpose || $stored_purpose === sanitize_key( $purpose ) ) ) {
			return (bool) wp_delete_attachment( $attachment_id, true );
		}
		return false;
	}
}
