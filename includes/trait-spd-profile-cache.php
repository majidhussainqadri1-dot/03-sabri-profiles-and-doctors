<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Cache {
	public static function cache_generation() {
		$generation = absint( get_option( 'spd_profile_cache_generation', 1 ) );
		return max( 1, $generation );
	}

	private static function anonymous_cache_key( array $profile ) {
		return 'public:' . md5(
			(string) $profile['public_id'] . ':' .
			absint( $profile['version'] ) . ':' .
			self::cache_generation()
		);
	}

	private function get_anonymous_public_dto_cache( array $profile ) {
		unset( $profile );
		// File 03 DTOs contain current File 00 eligibility, File 09 verification,
		// File 08 clinic and File 17 contact projections. Until every provider
		// participates in a versioned invalidation contract, object caching could
		// preserve revoked access. Fail privacy-safe and rebuild each DTO.
		return false;
	}

	private function set_anonymous_public_dto_cache( array $profile, array $dto ) {
		unset( $profile, $dto );
		// Intentionally disabled until cross-owner revocation invalidation is
		// accepted in staging. The generation ledger remains for future activation.
	}

	public function purge_profile_cache( array $profile ) {
		$generation = self::cache_generation() + 1;
		update_option( 'spd_profile_cache_generation', $generation, false );
		update_option(
			'spd_reconciliation_required',
			array(
				'public_id'  => sanitize_text_field( (string) ( $profile['public_id'] ?? '' ) ),
				'user_id'    => absint( $profile['user_id'] ?? 0 ),
				'version'    => absint( $profile['version'] ?? 0 ),
				'generation' => $generation,
				'changed_at' => SPD_Helpers::now(),
			),
			false
		);
		wp_cache_delete( 'profile:' . ( $profile['public_id'] ?? '' ), 'spd' );
		delete_transient( 'spd_profile_' . md5( (string) ( $profile['public_id'] ?? '' ) ) );
		do_action( 'spd_profile_cache_purged', $profile['public_id'] ?? '', $profile['user_id'] ?? 0, $profile['version'] ?? 0 );
		do_action( 'sabri_file26_profile_changed', $profile['public_id'] ?? '', $profile['version'] ?? 0 );
	}
}
