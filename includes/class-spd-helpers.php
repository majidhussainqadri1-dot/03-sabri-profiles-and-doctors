<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Helpers {
	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function public_id() {
		return wp_generate_uuid4();
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

	public static function same_origin_url( $url ) {
		if ( ! $url ) { return false; }
		$home = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( (string) $url );
		return is_array( $target ) && ! empty( $target['host'] ) && isset( $home['host'] )
			&& strtolower( $target['host'] ) === strtolower( $home['host'] )
			&& in_array( strtolower( $target['scheme'] ?? '' ), array( 'http', 'https' ), true );
	}

	public static function sanitize_multiline( $value, $max = 4000 ) {
		$value = sanitize_textarea_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	public static function normalize_locale( $locale ) {
		$locale = str_replace( '_', '-', sanitize_text_field( (string) $locale ) );
		return preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}$/', $locale ) ? $locale : 'en-US';
	}

	public static function normalize_focal( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 50.0;
		return max( 0.0, min( 100.0, $value ) );
	}

	public static function current_contract_claim( $claim, $minimum_contract = '1.0.0', $maximum_age = 600 ) {
		if ( ! is_array( $claim ) ) { return false; }
		$contract = sanitize_text_field( (string) ( $claim['contract_version'] ?? '' ) );
		$generated = strtotime( (string) ( $claim['generated_at'] ?? '' ) );
		$valid_until = strtotime( (string) ( $claim['valid_until'] ?? '' ) );
		$now = time();
		return $contract && version_compare( $contract, (string) $minimum_contract, '>=' )
			&& false !== $generated && false !== $valid_until
			&& $generated <= $now + 300 && ( $now - $generated ) <= max( 60, absint( $maximum_age ) )
			&& $valid_until > $now && $valid_until > $generated;
	}

	public static function slug_base( $display_name, $user_id ) {
		$base = sanitize_title( remove_accents( (string) $display_name ) );
		if ( '' === $base ) {
			$base = 'profile-' . absint( $user_id );
		}
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
		$from = sanitize_key( $from );
		$to = sanitize_key( $to );
		return isset( $maps[ $object ][ $from ] ) && in_array( $to, $maps[ $object ][ $from ], true );
	}

	public static function safe_error( $code, $message, $status = 400, $trace_id = '' ) {
		$trace_id = $trace_id ?: self::trace_id();
		return new WP_Error(
			sanitize_key( $code ),
			$message,
			array(
				'status'   => absint( $status ),
				'trace_id' => $trace_id,
			)
		);
	}

	public static function json_encode( $value ) {
		return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
