<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Activator {
	public static function activate() {
		if ( ! SPD_Membership_Adapter::available() ) {
			deactivate_plugins( plugin_basename( SPD_FILE ) );
			wp_die( esc_html__( 'Activate File 00 — Sabri Membership Core before File 03.', 'sabri-profiles-doctors' ), '', array( 'back_link' => true ) );
		}
		self::remove_legacy_capability();
		self::pages();
		self::sanitize_legacy_founder_option();
		self::migrate_verified_snapshots();
		self::schedule_retention();
		update_option( 'spd_version', SPD_VERSION, false );
		update_option( 'spd_db_version', SPD_DB_VERSION, false );
		set_transient( 'spd_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'spd_legacy_audit_retention' );
		flush_rewrite_rules();
	}


	private static function sanitize_legacy_founder_option() {
		$profile = (array) get_option( 'spd_founder_profile', array() );
		foreach ( array( 'name', 'location', 'phone', 'whatsapp', 'photo_id', 'cover_id' ) as $legacy_key ) {
			unset( $profile[ $legacy_key ] );
		}
		update_option( 'spd_founder_profile', $profile, false );
	}

	private static function schedule_retention() {
		if ( ! wp_next_scheduled( 'spd_legacy_audit_retention' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spd_legacy_audit_retention' );
		}
	}

	public static function retention_cleanup() {
		global $wpdb;
		$table = $wpdb->prefix . 'spd_audit_log';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}
		$wpdb->query( "UPDATE {$table} SET actor_id = 0, target_user_id = 0 WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 180 DAY)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table} WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 365 DAY)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		update_option( 'spd_legacy_audit_retention_last_run', current_time( 'mysql', true ), false );
	}

	private static function remove_legacy_capability() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( 'manage_sabri_doctors' );
		}
	}

	private static function pages() {
		$map = (array) get_option( 'spd_page_map', array() );
		$definitions = array(
			'founder' => array( 'Founder', '[sabri_founder_profile]', 'founder' ),
			'doctors' => array( 'Doctors', '[sabri_doctor_directory]', 'doctors' ),
			'profile' => array( 'Member Profile', '[sabri_member_profile]', 'member-profile' ),
			'edit'    => array( 'Edit Profile Presentation', '[sabri_edit_profile]', 'edit-profile-presentation' ),
		);
		foreach ( $definitions as $key => $definition ) {
			$map[ $key ] = self::managed_page( $key, $definition[0], $definition[1], $definition[2], isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0 );
		}
		update_option( 'spd_page_map', array_filter( $map ), false );
	}

	private static function managed_page( $key, $title, $shortcode, $slug, $stored_id ) {
		if ( $stored_id ) {
			$page = get_post( $stored_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type && $key === get_post_meta( $stored_id, '_spd_managed_page_key', true ) ) {
				if ( trim( $page->post_content ) !== $shortcode ) {
					wp_update_post( array( 'ID' => $stored_id, 'post_content' => $shortcode ) );
				}
				return $stored_id;
			}
		}

		$slug_page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $slug_page instanceof WP_Post ) {
			$is_owned = $key === get_post_meta( $slug_page->ID, '_spd_managed_page_key', true );
			$is_exact = trim( $slug_page->post_content ) === $shortcode;
			if ( $is_owned || $is_exact ) {
				update_post_meta( $slug_page->ID, '_spd_managed_page_key', $key );
				return absint( $slug_page->ID );
			}
			$slug .= '-spd';
		}

		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => wp_unique_post_slug( $slug, 0, 'publish', 'page', 0 ),
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_spd_managed_page_key', $key );
		return absint( $id );
	}

	private static function migrate_verified_snapshots() {
		$users = get_users( array( 'fields' => 'ids', 'number' => 500, 'meta_key' => '_gdo_reviewed_at', 'meta_compare' => 'EXISTS' ) );
		foreach ( $users as $user_id ) {
			if ( ! SPD_Verification_Adapter::snapshot( $user_id ) ) {
				SPD_Verification_Adapter::capture_snapshot( $user_id );
			}
		}
	}
}
