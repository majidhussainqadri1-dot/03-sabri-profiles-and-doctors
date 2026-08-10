<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Helpers {
	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function public_id() {
		return wp_generate_uuid4();
	}

	public static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	public static function trace_id() {
		return 'spd-' . str_replace( '-', '', wp_generate_uuid4() );
	}

	public static function clean_phone( $value ) {
		$value = preg_replace( '/[^0-9+]/', '', (string) $value );
		$value = preg_replace( '/(?!^)\+/', '', $value );
		return substr( $value, 0, 18 );
	}

	public static function whatsapp_url( $number ) {
		$digits = preg_replace( '/\D+/', '', (string) $number );
		return $digits ? 'https://wa.me/' . rawurlencode( $digits ) : '';
	}

	private static function effective_port( array $parts ) {
		if ( isset( $parts['port'] ) ) { return absint( $parts['port'] ); }
		return 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) ? 443 : 80;
	}

	public static function same_origin_url( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) { return false; }
		$home   = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $target ) || empty( $home['host'] ) || empty( $target['host'] ) ) { return false; }
		if ( isset( $target['user'] ) || isset( $target['pass'] ) ) { return false; }
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		if ( ! in_array( $target_scheme, array( 'http', 'https' ), true ) || $target_scheme !== $home_scheme ) { return false; }
		return strtolower( (string) $target['host'] ) === strtolower( (string) $home['host'] )
			&& self::effective_port( $target ) === self::effective_port( $home );
	}

	public static function sanitize_multiline( $value, $max = 4000 ) {
		$value = sanitize_textarea_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	public static function text_length( $value ) {
		$value = (string) $value;
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	public static function valid_locale( $locale ) {
		$locale = str_replace( '_', '-', sanitize_text_field( (string) $locale ) );
		return '' !== $locale && 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}$/', $locale );
	}

	public static function normalize_locale( $locale ) {
		$locale = str_replace( '_', '-', sanitize_text_field( (string) $locale ) );
		return self::valid_locale( $locale ) ? $locale : 'en-US';
	}

	public static function normalize_focal( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 50.0;
		return max( 0.0, min( 100.0, $value ) );
	}

	public static function current_contract_claim( $claim, $minimum_contract = '1.0.0', $maximum_age = 600 ) {
		if ( ! is_array( $claim ) ) { return false; }
		$contract    = sanitize_text_field( (string) ( $claim['contract_version'] ?? '' ) );
		$generated   = strtotime( (string) ( $claim['generated_at'] ?? '' ) );
		$valid_until = strtotime( (string) ( $claim['valid_until'] ?? '' ) );
		$now         = time();
		return $contract && version_compare( $contract, (string) $minimum_contract, '>=' )
			&& false !== $generated && false !== $valid_until
			&& $generated <= $now + 300 && ( $now - $generated ) <= max( 60, absint( $maximum_age ) )
			&& $valid_until > $now && $valid_until > $generated;
	}

	/**
	 * Acquire a process-safe, expiring lock backed by the unique WordPress option
	 * name. The random owner token prevents an expired worker from releasing a
	 * newer worker's lease.
	 *
	 * @return string|false Lock owner token on success, false when already held.
	 */
	public static function acquire_lock( $name, $ttl = 600 ) {
		$name = sanitize_key( (string) $name );
		if ( ! $name ) { return false; }
		$ttl = min( HOUR_IN_SECONDS, max( 10, absint( $ttl ) ) );
		$key = 'spd_lock_' . substr( hash( 'sha256', $name ), 0, 32 );
		$token = self::trace_id();
		$value = wp_json_encode( array( 'token' => $token, 'expires' => time() + $ttl ) );
		if ( add_option( $key, $value, '', false ) ) { return $token; }
		$current = json_decode( (string) get_option( $key, '' ), true );
		if ( ! is_array( $current ) || absint( $current['expires'] ?? 0 ) >= time() ) { return false; }
		delete_option( $key );
		return add_option( $key, $value, '', false ) ? $token : false;
	}

	public static function release_lock( $name, $token ) {
		$name = sanitize_key( (string) $name );
		$token = (string) $token;
		if ( ! $name || ! $token ) { return false; }
		$key = 'spd_lock_' . substr( hash( 'sha256', $name ), 0, 32 );
		$current = json_decode( (string) get_option( $key, '' ), true );
		if ( ! is_array( $current ) || ! hash_equals( (string) ( $current['token'] ?? '' ), $token ) ) { return false; }
		return delete_option( $key );
	}

	/**
	 * Small fixed-window limiter serialized by the same atomic option lock. It is
	 * deliberately fail-closed when the counter lock cannot be acquired.
	 */
	public static function consume_rate_limit( $bucket, $limit, $window ) {
		$bucket = sanitize_key( (string) $bucket );
		$limit = max( 1, absint( $limit ) );
		$window = max( 60, absint( $window ) );
		if ( ! $bucket ) { return false; }
		$lock = self::acquire_lock( 'rate_' . $bucket, 10 );
		if ( ! $lock ) { return false; }
		try {
			$key = 'spd_rate_' . substr( hash( 'sha256', $bucket ), 0, 32 );
			$record = json_decode( (string) get_transient( $key ), true );
			$now = time();
			if ( ! is_array( $record ) || absint( $record['expires'] ?? 0 ) <= $now ) {
				$record = array( 'count' => 0, 'expires' => $now + $window );
			}
			if ( absint( $record['count'] ?? 0 ) >= $limit ) { return false; }
			$record['count'] = absint( $record['count'] ?? 0 ) + 1;
			$ttl = max( 1, absint( $record['expires'] ) - $now );
			set_transient( $key, wp_json_encode( $record ), $ttl );
			return true;
		} finally {
			self::release_lock( 'rate_' . $bucket, $lock );
		}
	}

	public static function slug_base( $display_name, $user_id ) {
		$base = sanitize_title( remove_accents( (string) $display_name ) );
		if ( '' === $base ) { $base = 'profile-' . absint( $user_id ); }
		return substr( $base, 0, 160 );
	}

	public static function canonical_profile_url( $public_id ) {
		return home_url( user_trailingslashit( 'profile/' . rawurlencode( (string) $public_id ) ) );
	}

	public static function timeline_url( $public_id ) {
		return home_url( user_trailingslashit( 'profile/' . rawurlencode( (string) $public_id ) . '/timeline' ) );
	}

	public static function report_url( $public_id ) {
		return home_url( user_trailingslashit( 'profile/' . rawurlencode( (string) $public_id ) . '/report' ) );
	}

	public static function initials( $name ) {
		$parts = preg_split( '/\s+/u', trim( (string) $name ) );
		$out = '';
		foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $part ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
	}

	public static function state_transition_allowed( $from, $to, $object = 'profile' ) {
		$maps = array(
			'profile' => array(
				'incomplete' => array( 'active', 'limited', 'suspended', 'archived', 'tombstoned' ),
				'active'     => array( 'limited', 'suspended', 'archived', 'tombstoned' ),
				'limited'    => array( 'active', 'suspended', 'archived', 'tombstoned' ),
				'suspended'  => array( 'limited', 'active', 'archived', 'tombstoned' ),
				'archived'   => array( 'active', 'tombstoned' ),
				'tombstoned' => array(),
			),
			'professional_field' => array(
				'draft'          => array( 'pending_review', 'superseded' ),
				'pending_review' => array( 'approved', 'rejected', 'superseded' ),
				'approved'       => array( 'superseded' ),
				'rejected'       => array( 'draft', 'superseded' ),
				'superseded'     => array(),
			),
			'media' => array(
				'pending_scan' => array( 'active', 'rejected', 'removed' ),
				'active'       => array( 'rejected', 'removed' ),
				'rejected'     => array( 'removed' ),
				'removed'      => array(),
			),
		);
		$object = sanitize_key( $object );
		$from   = sanitize_key( $from );
		$to     = sanitize_key( $to );
		return isset( $maps[ $object ][ $from ] ) && in_array( $to, $maps[ $object ][ $from ], true );
	}

	public static function safe_error( $code, $message, $status = 400, $trace_id = '' ) {
		$trace_id = $trace_id ?: self::trace_id();
		return new WP_Error( sanitize_key( $code ), $message, array( 'status' => absint( $status ), 'trace_id' => $trace_id ) );
	}

	public static function json_encode( $value ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $json ? 'null' : $json;
	}
}
