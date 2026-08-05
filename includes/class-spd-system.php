<?php
defined( 'ABSPATH' ) || exit;

final class SPD_System {
	const SAFE_MODE = 'spd_safe_mode';

	public static function is_safe_mode() { return '1' === (string) get_option( self::SAFE_MODE, '0' ); }

	public static function checks() {
		$map = (array) get_option( 'spd_page_map', array() );
		$checks = array(
			'contracts' => SPD_Contracts::health(),
			'founder' => array( 'ok' => (bool) SPD_Membership_Adapter::founder_id() ),
			'cron' => array( 'ok' => (bool) wp_next_scheduled( 'spd_legacy_audit_retention' ) ),
			'routes' => array( 'ok' => (bool) get_option( 'permalink_structure' ) ),
			'pages' => array( 'ok' => ! empty( $map['founder'] ) && ! empty( $map['edit'] ) ),
			'safe_mode' => array( 'enabled' => self::is_safe_mode() ),
		);
		return $checks;
	}

	public static function repair( $dry_run = true ) {
		$actions = array();
		if ( ! wp_next_scheduled( 'spd_legacy_audit_retention' ) ) { $actions[] = 'schedule_retention'; }
		if ( ! get_option( 'permalink_structure' ) ) { $actions[] = 'manual_permalink_configuration_required'; }
		if ( ! $dry_run && in_array( 'schedule_retention', $actions, true ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spd_legacy_audit_retention' );
		}
		return array( 'dry_run' => (bool) $dry_run, 'actions' => $actions, 'destructive' => false );
	}

	public static function register() {
		add_action( 'admin_post_spd_toggle_safe_mode', static function() {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
			check_admin_referer( 'spd_toggle_safe_mode' );
			update_option( self::SAFE_MODE, self::is_safe_mode() ? '0' : '1', false );
			wp_safe_redirect( wp_get_referer() ?: admin_url() ); exit;
		} );
	}
}
