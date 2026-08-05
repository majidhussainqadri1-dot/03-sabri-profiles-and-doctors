<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Contracts {
	const VERSION = '1.0.0';

	public static function manifest() {
		return array(
			'file' => '03',
			'owner' => 'profiles-and-doctors',
			'contract_version' => self::VERSION,
			'provides' => array( 'profile.read.v1', 'profile.visibility.v1', 'profile.timeline-slot.v1', 'profile.health.v1' ),
			'requires' => array(
				'file00' => array( 'min' => '1.1.2', 'functions' => array( 'smc_get_profile', 'smc_user_status' ) ),
				'file20' => array( 'optional' => true, 'constant' => 'SABRI_SHELL_VERSION' ),
				'file21' => array( 'optional' => true, 'filter' => 'spd_timeline_providers' ),
				'file25' => array( 'optional' => true, 'filter' => 'spd_profile_component_contract' ),
			),
		);
	}

	public static function health() {
		$issues = array();
		if ( ! SPD_Membership_Adapter::available() ) {
			$issues[] = array( 'code' => 'file00_missing', 'severity' => 'critical' );
		}
		if ( defined( 'SMC_CONTRACT_VERSION' ) && version_compare( SMC_CONTRACT_VERSION, '1.1.2', '<' ) ) {
			$issues[] = array( 'code' => 'file00_contract_too_old', 'severity' => 'critical' );
		}
		if ( ! defined( 'SABRI_SHELL_VERSION' ) ) {
			$issues[] = array( 'code' => 'file20_optional_missing', 'severity' => 'warning' );
		}
		return array(
			'ok' => ! array_filter( $issues, static function( $i ) { return 'critical' === $i['severity']; } ),
			'contract_version' => self::VERSION,
			'issues' => $issues,
			'checked_at' => current_time( 'mysql', true ),
		);
	}

	public static function register() {
		add_filter( 'sabri_platform_contracts', static function( $contracts ) {
			$contracts['file03'] = self::manifest();
			return $contracts;
		} );
		add_filter( 'sabri_platform_health_checks', static function( $checks ) {
			$checks['file03'] = self::health();
			return $checks;
		} );
	}
}
