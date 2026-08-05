<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Activator {
	public static function activate() {
		if ( ! SPD_Membership_Adapter::available() ) {
			deactivate_plugins( plugin_basename( SPD_FILE ) );
			wp_die( esc_html__( 'Activate a compatible File 00 — Sabri Membership Core before File 03.', 'sabri-profiles-doctors' ), '', array( 'back_link' => true ) );
		}
		if ( get_transient( 'spd_activation_lock' ) ) {
			return;
		}
		set_transient( 'spd_activation_lock', 1, 10 * MINUTE_IN_SECONDS );
		self::repair_owned_resources();
		self::migrate_legacy_options();
		update_option( 'spd_version', SPD_VERSION, false );
		update_option( 'spd_contract_version', SPD_CONTRACT_VERSION, false );
		if ( false === get_option( 'spd_safe_mode', false ) ) {
			add_option( 'spd_safe_mode', false, '', false );
		}
		if ( false === get_option( 'spd_migration_cursor', false ) ) {
			add_option( 'spd_migration_cursor', 0, '', false );
		}
		if ( ! wp_next_scheduled( 'spd_migrate_profiles_batch' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'spd_migrate_profiles_batch' );
		}
		delete_transient( 'spd_activation_lock' );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'spd_dispatch_outbox' );
		wp_clear_scheduled_hook( 'spd_migrate_profiles_batch' );
		wp_clear_scheduled_hook( 'spd_retention_cleanup' );
		flush_rewrite_rules();
	}

	public static function repair_owned_resources() {
		SPD_DB::install();
		self::pages();
		if ( ! wp_next_scheduled( 'spd_dispatch_outbox' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'spd_dispatch_outbox' );
		}
		if ( ! wp_next_scheduled( 'spd_retention_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spd_retention_cleanup' );
		}
		update_option( 'spd_last_repair_at', SPD_Helpers::now(), false );
	}

	private static function pages() {
		$map = (array) get_option( 'spd_page_map', array() );
		$definitions = array(
			'founder'         => array( 'Founder', '[sabri_founder_profile]', 'founder' ),
			'profile'         => array( 'Profile', '[sabri_profile_router]', 'profile' ),
			'account_profile' => array( 'Edit Profile and Privacy', '[sabri_edit_profile]', 'account-profile' ),
		);
		foreach ( $definitions as $key => $definition ) {
			$map[ $key ] = self::managed_page( $key, $definition[0], $definition[1], $definition[2], absint( $map[ $key ] ?? 0 ) );
		}
		$legacy = get_page_by_path( 'member-profile', OBJECT, 'page' );
		if ( $legacy instanceof WP_Post ) {
			$map['legacy_profile'] = absint( $legacy->ID );
		}
		update_option( 'spd_page_map', array_filter( $map ), false );
	}

	private static function managed_page( $key, $title, $shortcode, $slug, $stored_id ) {
		if ( $stored_id ) {
			$page = get_post( $stored_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type && $key === get_post_meta( $stored_id, '_spd_managed_page_key', true ) ) {
				$changes = array( 'ID' => $stored_id );
				if ( trim( $page->post_content ) !== $shortcode ) {
					$changes['post_content'] = $shortcode;
				}
				if ( $page->post_name !== $slug ) {
					$changes['post_name'] = $slug;
				}
				if ( count( $changes ) > 1 ) {
					wp_update_post( $changes );
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
			$slug .= '-file03';
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

	private static function migrate_legacy_options() {
		$profile = (array) get_option( 'spd_founder_profile', array() );
		foreach ( array( 'name', 'location', 'phone', 'whatsapp', 'photo_id', 'cover_id' ) as $legacy_key ) {
			unset( $profile[ $legacy_key ] );
		}
		update_option( 'spd_founder_profile_legacy_read_only', $profile, false );
		delete_option( 'spd_founder_profile' );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( 'manage_sabri_doctors' );
		}
	}
}
