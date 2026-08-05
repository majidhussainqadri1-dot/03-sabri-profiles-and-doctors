<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Profile_Repository {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function founder_fields() {
		return array( 'professional_title', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications', 'institutional_links' );
	}

	public static function editable_fields() {
		return array_merge( array( 'bio', 'country', 'city', 'languages', 'studied_books', 'locale' ), self::founder_fields() );
	}

	public static function visibility_fields() {
		return array_merge( array( 'profile_visibility', 'bio', 'country', 'city', 'languages', 'studied_books', 'phone', 'email', 'whatsapp', 'internal_message' ), self::founder_fields() );
	}
	use SPD_Profile_Identity_Create;
	use SPD_Profile_Identity_Read;
	use SPD_Profile_Public_DTO;
	use SPD_Profile_Edit_Model;
	use SPD_Profile_Update;
	use SPD_Profile_Media;
	use SPD_Profile_Moderation;
	use SPD_Profile_Lifecycle;
	use SPD_Profile_Events;
	use SPD_Profile_Cache;
}
