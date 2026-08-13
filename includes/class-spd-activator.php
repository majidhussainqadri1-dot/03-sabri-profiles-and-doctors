<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Activator {
	public static function activate() {
		if ( ! SPD_Membership_Adapter::available() ) {
			deactivate_plugins( plugin_basename( SPD_FILE ) );
			wp_die( esc_html__( 'Activate a compatible File 00 — Sabri Membership Core before File 03.', 'sabri-profiles-doctors' ), '', array( 'back_link' => true ) );
		}
		$activation_lock = SPD_Helpers::acquire_lock( 'activation', 10 * MINUTE_IN_SECONDS );
		if ( ! $activation_lock ) {
			deactivate_plugins( plugin_basename( SPD_FILE ) );
			wp_die( esc_html__( 'File 03 activation is already running. Wait briefly and retry.', 'sabri-profiles-doctors' ), '', array( 'back_link' => true ) );
		}
		try {
			$repair = self::repair_owned_resources();
			if ( is_wp_error( $repair ) ) { throw new RuntimeException( $repair->get_error_message() ); }
			$legacy = self::migrate_legacy_options();
			if ( is_wp_error( $legacy ) ) { throw new RuntimeException( $legacy->get_error_message() ); }
			foreach ( array( 'spd_version' => SPD_VERSION, 'spd_contract_version' => SPD_CONTRACT_VERSION, 'spd_plan_version' => SPD_PLAN_VERSION ) as $option => $value ) {
				if ( ! self::persist_exact_option( $option, $value ) ) {
					throw new RuntimeException( __( 'File 03 activation metadata could not be persisted safely.', 'sabri-profiles-doctors' ) );
				}
			}
			if ( null === get_option( 'spd_safe_mode', null ) && ! self::persist_exact_option( 'spd_safe_mode', false ) ) {
				throw new RuntimeException( __( 'File 03 safe-mode state could not be initialized safely.', 'sabri-profiles-doctors' ) );
			}
			if ( null === get_option( 'spd_migration_cursor', null ) && ! self::persist_exact_option( 'spd_migration_cursor', 0 ) ) {
				throw new RuntimeException( __( 'File 03 migration cursor could not be initialized safely.', 'sabri-profiles-doctors' ) );
			}
			if ( ! wp_next_scheduled( 'spd_migrate_profiles_batch' ) && ! wp_schedule_event( time() + 60, 'hourly', 'spd_migrate_profiles_batch' ) ) { throw new RuntimeException( __( 'The File 03 migration schedule could not be created.', 'sabri-profiles-doctors' ) ); }
			flush_rewrite_rules();
		} catch ( Throwable $exception ) {
			deactivate_plugins( plugin_basename( SPD_FILE ) );
			wp_die( esc_html( $exception->getMessage() ), '', array( 'back_link' => true ) );
		} finally {
			SPD_Helpers::release_lock( 'activation', $activation_lock );
		}
	}

	private static function persist_exact_option( $option, $value ) {
		$option = sanitize_key( (string) $option );
		if ( '' === $option ) { return false; }
		$updated = update_option( $option, $value, false );
		if ( false === $updated && get_option( $option, null ) !== $value ) { return false; }
		return get_option( $option, null ) === $value;
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'spd_dispatch_outbox' );
		wp_clear_scheduled_hook( 'spd_migrate_profiles_batch' );
		wp_clear_scheduled_hook( 'spd_retention_cleanup' );
		wp_clear_scheduled_hook( 'spd_process_media_deletions' );
		flush_rewrite_rules();
	}

	public static function repair_owned_resources() {
		if ( ! class_exists( 'SPD_Schema_Guard' ) ) { require_once SPD_DIR . 'includes/class-spd-schema-guard.php'; }
		$schema = SPD_DB::install();
		if ( is_wp_error( $schema ) || ! SPD_Schema_Guard::base_ready() ) {
			delete_option( 'spd_db_version' );
			return is_wp_error( $schema ) ? $schema : new WP_Error( 'spd_schema_shape_invalid', __( 'The File 03 base database schema is incomplete after repair.', 'sabri-profiles-doctors' ) );
		}
		$central = SPD_Central_Profile::install_schema();
		if ( is_wp_error( $central ) || ! SPD_Schema_Guard::central_ready() ) {
			delete_option( 'spd_central_schema_version' );
			return is_wp_error( $central ) ? $central : new WP_Error( 'spd_central_schema_shape_invalid', __( 'The File 03 central-plan database schema is incomplete after repair.', 'sabri-profiles-doctors' ) );
		}
		$future = SPD_Future_Profile::install_schema();
		if ( is_wp_error( $future ) || ! SPD_Schema_Guard::future_ready() ) {
			delete_option( 'spd_future_schema_version' );
			return is_wp_error( $future ) ? $future : new WP_Error( 'spd_future_schema_shape_invalid', __( 'The File 03 future-profile database schema is incomplete after repair.', 'sabri-profiles-doctors' ) );
		}
		$pages = self::pages(); if ( is_wp_error( $pages ) ) { return $pages; }
		$schedules = array(
			'spd_dispatch_outbox'         => array( time() + 300, 'hourly' ),
			'spd_retention_cleanup'       => array( time() + HOUR_IN_SECONDS, 'daily' ),
			'spd_process_media_deletions' => array( time() + 300, 'hourly' ),
		);
		foreach ( $schedules as $hook => $definition ) {
			if ( ! wp_next_scheduled( $hook ) && ! wp_schedule_event( $definition[0], $definition[1], $hook ) ) { return new WP_Error( 'spd_schedule_failed', sprintf( __( 'The File 03 schedule %s could not be created.', 'sabri-profiles-doctors' ), $hook ) ); }
		}
		$repair_at = SPD_Helpers::now();
		if ( ! self::persist_exact_option( 'spd_last_repair_at', $repair_at ) ) {
			return new WP_Error( 'spd_repair_evidence_failed', __( 'File 03 resources were repaired but the repair evidence could not be persisted safely.', 'sabri-profiles-doctors' ) );
		}
		return true;
	}

	private static function pages() {
		$map = (array) get_option( 'spd_page_map', array() );
		$definitions = array(
			'founder'               => array( 'Founder', '[sabri_founder_profile]', 'founder' ),
			'profile'               => array( 'Profile', '[sabri_profile_router]', 'profile' ),
			'account_profile'       => array( 'Edit Profile and Privacy', '[sabri_edit_profile]', 'account-profile' ),
			'personal_site'         => array( 'Personal Website Profile', '[sabri_profile_personal_site]', 'account-profile-personal-site' ),
			'private_preview'       => array( 'Private Profile Preview', '[sabri_profile_private_preview]', 'account-profile-preview' ),
		);
		foreach ( $definitions as $key => $definition ) {
			$page_id = self::managed_page( $key, $definition[0], $definition[1], $definition[2], absint( $map[ $key ] ?? 0 ) );
			if ( is_wp_error( $page_id ) ) { return $page_id; }
			$map[ $key ] = absint( $page_id );
		}
		$legacy = get_page_by_path( 'member-profile', OBJECT, 'page' ); if ( $legacy instanceof WP_Post ) { $map['legacy_profile'] = absint( $legacy->ID ); }
		if ( false === update_option( 'spd_page_map', array_filter( $map ), false ) && (array) get_option( 'spd_page_map', array() ) !== array_filter( $map ) ) { return new WP_Error( 'spd_page_map_failed', __( 'The File 03 route page map could not be recorded.', 'sabri-profiles-doctors' ) ); }
		return $map;
	}

	private static function managed_page( $key, $title, $shortcode, $slug, $stored_id ) {
		if ( $stored_id ) {
			$page = get_post( $stored_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type && $key === get_post_meta( $stored_id, '_spd_managed_page_key', true ) ) {
				$changes = array( 'ID' => $stored_id );
				if ( trim( $page->post_content ) !== $shortcode ) { $changes['post_content'] = $shortcode; }
				if ( $page->post_name !== $slug ) { $changes['post_name'] = $slug; }
				if ( 'publish' !== $page->post_status ) { $changes['post_status'] = 'publish'; }
				if ( count( $changes ) > 1 ) { $updated = wp_update_post( $changes, true ); if ( is_wp_error( $updated ) ) { return $updated; } }
				return $stored_id;
			}
		}
		$slug_page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $slug_page instanceof WP_Post ) {
			$current_marker = (string) get_post_meta( $slug_page->ID, '_spd_managed_page_key', true );
			$is_owned = $key === $current_marker;
			$is_exact = trim( $slug_page->post_content ) === $shortcode;
			if ( $is_owned || $is_exact ) {
				if ( ! $is_owned && false === update_post_meta( $slug_page->ID, '_spd_managed_page_key', $key ) ) { return new WP_Error( 'spd_managed_page_marker_failed', __( 'A File 03 managed-page marker could not be recorded.', 'sabri-profiles-doctors' ) ); }
				if ( 'publish' !== $slug_page->post_status ) {
					$published = wp_update_post( array( 'ID' => absint( $slug_page->ID ), 'post_status' => 'publish' ), true );
					if ( is_wp_error( $published ) ) { return $published; }
				}
				return absint( $slug_page->ID );
			}
			$slug .= '-file03';
		}
		$id = wp_insert_post( array( 'post_title' => $title, 'post_name' => wp_unique_post_slug( $slug, 0, 'publish', 'page', 0 ), 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ), true );
		if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'spd_managed_page_failed', __( 'A required File 03 route page could not be created.', 'sabri-profiles-doctors' ) ); }
		if ( false === update_post_meta( $id, '_spd_managed_page_key', $key ) ) { wp_delete_post( $id, true ); return new WP_Error( 'spd_managed_page_marker_failed', __( 'A File 03 managed-page marker could not be recorded.', 'sabri-profiles-doctors' ) ); }
		return absint( $id );
	}

	private static function migrate_legacy_options() {
		$profile = (array) get_option( 'spd_founder_profile', array() );
		foreach ( array( 'name', 'location', 'phone', 'whatsapp', 'photo_id', 'cover_id' ) as $legacy_key ) { unset( $profile[ $legacy_key ] ); }
		$persisted = update_option( 'spd_founder_profile_legacy_read_only', $profile, false );
		if ( false === $persisted && (array) get_option( 'spd_founder_profile_legacy_read_only', array() ) !== $profile ) {
			return new WP_Error( 'spd_legacy_option_migration_failed', __( 'Legacy Founder profile data could not be preserved safely, so the original record was left unchanged.', 'sabri-profiles-doctors' ) );
		}
		delete_option( 'spd_founder_profile' );
		$admin = get_role( 'administrator' ); if ( $admin ) { $admin->remove_cap( 'manage_sabri_doctors' ); }
		return true;
	}
}
