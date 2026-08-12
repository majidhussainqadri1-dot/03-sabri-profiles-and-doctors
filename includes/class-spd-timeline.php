<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Timeline {
	const PROVIDER_CONTRACT_MIN = '1.0.0';
	const MAX_PROVIDER_ITEMS = 250;

	public static function providers() {
		$providers = array(
			'file21' => array( 'callback' => array( __CLASS__, 'file21_provider' ), 'availability_filter' => 'sabri_file21_profile_timeline_provider_health_v1' ),
			'file10' => array( 'callback' => array( __CLASS__, 'file10_provider' ), 'availability_filter' => 'sabri_file10_profile_timeline_provider_health_v1' ),
			'file11' => array( 'callback' => array( __CLASS__, 'file11_provider' ), 'availability_filter' => 'sabri_file11_profile_timeline_provider_health_v1' ),
			'file05' => array( 'callback' => array( __CLASS__, 'file05_provider' ), 'availability_filter' => 'sabri_file05_profile_timeline_provider_health_v1' ),
		);
		try {
			$filtered = apply_filters( 'spd_profile_timeline_providers_v1', $providers );
		} catch ( Throwable $e ) {
			return $providers;
		}
		return is_array( $filtered ) ? $filtered : $providers;
	}

	public static function query( $identity, array $args = array(), $viewer_id = 0 ) {
		global $wpdb;
		$repo = SPD_Profile_Repository::instance();
		if ( is_numeric( $identity ) ) {
			$db_available = is_object( $wpdb );
			if ( $db_available ) { $wpdb->last_error = ''; }
			$profile = $repo->find_by_user_id( absint( $identity ), false );
			if ( ( $db_available && $wpdb->last_error ) || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) {
				return new WP_Error( 'spd_timeline_profile_store_unavailable', __( 'The profile timeline store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
			}
		} else {
			$profile = $repo->find_by_public_id_strict( (string) $identity );
			if ( is_wp_error( $profile ) ) { return $profile; }
		}
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $viewer_id ) ) { return new WP_Error( 'spd_timeline_unavailable', __( 'This timeline is private or unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) ); }
		$limit  = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
		$filter = sanitize_key( (string) ( $args['provider'] ?? '' ) );
		$cursor = self::decode_cursor( $args['cursor'] ?? '', $profile['public_id'], $filter );
		if ( is_wp_error( $cursor ) ) { return $cursor; }
		$items = array();
		$health = array();
		foreach ( self::providers() as $key => $definition ) {
			$key = sanitize_key( $key );
			if ( ! $key || ( $filter && $filter !== $key ) ) { continue; }
			$callback = is_array( $definition ) && isset( $definition['callback'] ) ? $definition['callback'] : $definition;
			$health_filter = is_array( $definition ) ? (string) ( $definition['availability_filter'] ?? '' ) : '';
			try {
				$provider_health = $health_filter ? apply_filters( $health_filter, null, $profile['user_id'], SPD_CONTRACT_VERSION ) : null;
			} catch ( Throwable $e ) {
				$health[ $key ] = 'unavailable';
				continue;
			}
			if ( ! SPD_Helpers::current_contract_claim( $provider_health, self::PROVIDER_CONTRACT_MIN, 300 ) || 'available' !== sanitize_key( (string) ( $provider_health['status'] ?? '' ) ) || ! is_callable( $callback ) ) { $health[ $key ] = 'unavailable'; continue; }
			if ( get_transient( 'spd_timeline_circuit_' . $key ) ) { $health[ $key ] = 'circuit_open'; continue; }
			$started = microtime( true );
			try {
				$result = call_user_func( $callback, $profile['user_id'], array( 'limit' => min( self::MAX_PROVIDER_ITEMS, $limit + 1 ), 'cursor' => $cursor, 'viewer_id' => absint( $viewer_id ), 'profile_public_id' => $profile['public_id'], 'contract_version' => SPD_CONTRACT_VERSION ) );
			} catch ( Throwable $e ) {
				$result = new WP_Error( 'spd_timeline_provider_exception', __( 'A timeline provider failed safely.', 'sabri-profiles-doctors' ) );
			}
			$elapsed = microtime( true ) - $started;
			if ( $elapsed > 2.0 || is_wp_error( $result ) || ! is_array( $result ) || count( $result ) > self::MAX_PROVIDER_ITEMS ) {
				set_transient( 'spd_timeline_circuit_' . $key, 1, MINUTE_IN_SECONDS );
				$health[ $key ] = 'degraded';
				continue;
			}
			$health[ $key ] = empty( $result ) ? 'empty' : 'available';
			foreach ( $result as $item ) {
				$normalized = self::normalize_item( $key, $item, $profile['user_id'] );
				if ( ! $normalized || 'public' !== $normalized['visibility'] || ! in_array( $normalized['status'], array( 'published', 'corrected', 'retracted' ), true ) ) { continue; }
				if ( $cursor && ! self::before_cursor( $normalized, $cursor ) ) { continue; }
				$items[] = $normalized;
			}
		}
		usort( $items, static function ( $a, $b ) { $time = strcmp( $b['published_at'], $a['published_at'] ); return 0 !== $time ? $time : strcmp( $b['sort_id'], $a['sort_id'] ); } );
		$has_more = count( $items ) > $limit;
		$items = array_slice( $items, 0, $limit );
		$next = '';
		if ( $has_more && $items ) { $last = end( $items ); $next = self::encode_cursor( $last['published_at'], $last['sort_id'], $profile['public_id'], $filter ); }
		return array( 'contract_version' => SPD_CONTRACT_VERSION, 'profile_public_id' => $profile['public_id'], 'items' => $items, 'next_cursor' => $next, 'has_more' => $has_more, 'provider_health' => $health, 'partial' => (bool) array_intersect( array( 'degraded', 'unavailable', 'circuit_open' ), array_values( $health ) ) );
	}

	private static function normalize_item( $provider, $item, $expected_user_id ) {
		if ( ! is_array( $item ) || absint( $item['author_user_id'] ?? 0 ) !== absint( $expected_user_id ) ) { return array(); }
		if ( empty( $item['contract_version'] ) || version_compare( (string) $item['contract_version'], self::PROVIDER_CONTRACT_MIN, '<' ) ) { return array(); }
		$id = sanitize_text_field( (string) ( $item['canonical_id'] ?? '' ) );
		$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		$published = sanitize_text_field( (string) ( $item['published_at'] ?? '' ) );
		$timestamp = strtotime( $published );
		if ( ! $id || strlen( $id ) > 191 || ! $url || ! SPD_Helpers::same_origin_url( $url ) || ! $published || false === $timestamp || $timestamp > time() + 300 || empty( $item['owner_version'] ) ) { return array(); }
		$thumbnail = esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) );
		if ( $thumbnail && ! SPD_Helpers::same_origin_url( $thumbnail ) ) { $thumbnail = ''; }
		return array(
			'provider' => sanitize_key( $provider ),
			'canonical_id' => $id,
			'owner_version' => sanitize_text_field( (string) $item['owner_version'] ),
			'sort_id' => sanitize_key( $provider ) . ':' . $id,
			'type' => sanitize_key( (string) ( $item['type'] ?? 'publication' ) ),
			'title' => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'excerpt' => wp_kses_post( (string) ( $item['excerpt'] ?? '' ) ),
			'url' => $url,
			'published_at' => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'visibility' => sanitize_key( (string) ( $item['visibility'] ?? 'private' ) ),
			'status' => sanitize_key( (string) ( $item['status'] ?? 'published' ) ),
			'thumbnail_url' => $thumbnail,
			'correction' => sanitize_text_field( (string) ( $item['correction'] ?? '' ) ),
		);
	}

	private static function encode_cursor( $time, $id, $public_id, $filter ) {
		$body = rtrim( strtr( base64_encode( SPD_Helpers::json_encode( array( 't' => $time, 'i' => $id, 'p' => $public_id, 'f' => $filter ) ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$sig = substr( hash_hmac( 'sha256', $body, wp_salt( 'auth' ) ), 0, 32 );
		return $body . '.' . $sig;
	}

	private static function decode_cursor( $cursor, $public_id, $filter ) {
		$cursor = trim( (string) $cursor );
		if ( '' === $cursor ) { return array(); }
		if ( strlen( $cursor ) > 768 || ! preg_match( '/^([A-Za-z0-9_-]{8,700})\.([0-9a-f]{32})$/', $cursor, $m ) ) { return new WP_Error( 'spd_timeline_cursor_invalid', __( 'The timeline cursor is invalid. Reload the timeline and try again.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		if ( ! hash_equals( substr( hash_hmac( 'sha256', $m[1], wp_salt( 'auth' ) ), 0, 32 ), $m[2] ) ) { return new WP_Error( 'spd_timeline_cursor_invalid', __( 'The timeline cursor is invalid. Reload the timeline and try again.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) ); }
		$raw = strtr( $m[1], '-_', '+/' );
		$raw .= str_repeat( '=', ( 4 - strlen( $raw ) % 4 ) % 4 );
		$decoded = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = $decoded ? json_decode( $decoded, true ) : null;
		if ( ! is_array( $data ) || empty( $data['t'] ) || empty( $data['i'] ) || (string) ( $data['p'] ?? '' ) !== (string) $public_id || sanitize_key( (string) ( $data['f'] ?? '' ) ) !== sanitize_key( $filter ) || false === strtotime( (string) $data['t'] ) || strlen( (string) $data['i'] ) > 240 ) {
			return new WP_Error( 'spd_timeline_cursor_invalid', __( 'The timeline cursor does not belong to this profile or filter.', 'sabri-profiles-doctors' ), array( 'status' => 400 ) );
		}
		return array( 't' => gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['t'] ) ), 'i' => sanitize_text_field( (string) $data['i'] ) );
	}
	private static function before_cursor( array $item, array $cursor ) { return $item['published_at'] < $cursor['t'] || ( $item['published_at'] === $cursor['t'] && $item['sort_id'] < $cursor['i'] ); }
	public static function file21_provider( $u, array $a ) { return apply_filters( 'sabri_file21_profile_timeline_items_v1', array(), absint( $u ), $a ); }
	public static function file10_provider( $u, array $a ) { return apply_filters( 'sabri_file10_profile_timeline_items_v1', array(), absint( $u ), $a ); }
	public static function file11_provider( $u, array $a ) { return apply_filters( 'sabri_file11_profile_timeline_items_v1', array(), absint( $u ), $a ); }
	public static function file05_provider( $u, array $a ) { return apply_filters( 'sabri_file05_profile_timeline_items_v1', array(), absint( $u ), $a ); }
}
