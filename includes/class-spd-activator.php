<?php
defined( 'ABSPATH' ) || exit;

class SPD_Activator {
	public static function activate() {
		self::roles();
		self::audit_table();
		self::pages();
		update_option( 'spd_version', SPD_VERSION, false );
		set_transient( 'spd_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	private static function roles() {
		add_role( 'sabri_doctor_verified', 'Sabri Verified Doctor', array( 'read' => true ) );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_sabri_doctors' );
		}
	}

	private static function audit_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'spd_audit_log';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_status varchar(30) NOT NULL DEFAULT '',
			new_status varchar(30) NOT NULL DEFAULT '',
			reason text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY target_user_id (target_user_id),
			KEY created_at (created_at)
		) {$charset};" );
	}

	private static function pages() {
		$map = (array) get_option( 'spd_page_map', array() );
		$map['founder'] = self::page( 'founder', 'Founder', '[sabri_founder_profile]', 'sabri-founder' );
		$map['doctors'] = self::page( 'doctors', 'Doctors', '[sabri_doctor_directory]', 'homeopathy-doctors' );
		$map['profile'] = self::page( 'profile', 'Member Profile', '[sabri_member_profile]', 'member-profile' );
		$map['edit']    = self::page( 'edit', 'Edit Profile', '[sabri_edit_profile]', 'edit-profile' );
		update_option( 'spd_page_map', $map, false );
		$spf = (array) get_option( 'spf_page_map', array() );
		$spf['founder'] = $map['founder'];
		$spf['doctors'] = $map['doctors'];
		update_option( 'spf_page_map', $spf, false );
	}

	private static function page( $key, $title, $shortcode, $slug ) {
		$spf      = (array) get_option( 'spf_page_map', array() );
		$existing = ! empty( $spf[ $key ] ) ? get_post( absint( $spf[ $key ] ) ) : get_page_by_path( $slug );
		if ( $existing instanceof WP_Post ) {
			$managed = get_post_meta( $existing->ID, '_spf_managed_page', true ) || get_post_meta( $existing->ID, '_spd_managed_page', true );
			if ( $managed || false !== strpos( $existing->post_content, '[sabri_' ) ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $shortcode ) );
				update_post_meta( $existing->ID, '_spd_managed_page', '1' );
			}
			return $existing->ID;
		}
		$id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_spd_managed_page', '1' );
			return $id;
		}
		return 0;
	}
}
