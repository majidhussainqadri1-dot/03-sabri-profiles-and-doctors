<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Timeline {
	public static function providers() {
		$providers = array(
			'file21' => array( __CLASS__, 'file21_provider' ),
			'file10' => array( __CLASS__, 'file10_provider' ),
			'file11' => array( __CLASS__, 'file11_provider' ),
			'file05' => array( __CLASS__, 'file05_provider' ),
		);
		return apply_filters( 'spd_profile_timeline_providers_v1', $providers );
	}

	public static function query( $identity, array $args = array(), $viewer_id = 0 ) {
		$repo = SPD_Profile_Repository::instance();
		$profile = is_numeric( $identity ) ? $repo->find_by_user_id( absint( $identity ) ) : $repo->find_by_public_id( (string) $identity );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $viewer_id ) ) {
			return new WP_Error( 'spd_timeline_unavailable', __( 'This timeline is private or unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$limit = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
		$cursor = self::decode_cursor( $args['cursor'] ?? '' );
		$provider_filter = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : '';
		$items = array();
		$health = array();
		foreach ( self::providers() as $provider_key => $callback ) {
			$provider_key = sanitize_key( $provider_key );
			if ( $provider_filter && $provider_filter !== $provider_key ) {
				continue;
			}
			if ( ! is_callable( $callback ) ) {
				$health[ $provider_key ] = 'unavailable';
				continue;
			}
			$result = call_user_func( $callback, $profile['user_id'], array( 'limit' => $limit + 1, 'cursor' => $cursor, 'viewer_id' => absint( $viewer_id ) ) );
			if ( is_wp_error( $result ) ) {
				$health[ $provider_key ] = 'degraded';
				continue;
			}
			$health[ $provider_key ] = empty( $result ) ? 'empty' : 'available';
			foreach ( (array) $result as $item ) {
				$normalized = self::normalize_item( $provider_key, $item );
				if ( ! $normalized || 'public' !== $normalized['visibility'] || ! in_array( $normalized['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
					continue;
				}
				if ( $cursor && ! self::before_cursor( $normalized, $cursor ) ) {
					continue;
				}
				$items[] = $normalized;
			}
		}
		usort(
			$items,
			static function ( $a, $b ) {
				$time = strcmp( $b['published_at'], $a['published_at'] );
				return 0 !== $time ? $time : strcmp( $b['sort_id'], $a['sort_id'] );
			}
		);
		$has_more = count( $items ) > $limit;
		$items = array_slice( $items, 0, $limit );
		$next_cursor = '';
		if ( $has_more && $items ) {
			$last = end( $items );
			$next_cursor = self::encode_cursor( $last['published_at'], $last['sort_id'] );
		}
		return array(
			'contract_version' => SPD_CONTRACT_VERSION,
			'profile_public_id'=> $profile['public_id'],
			'items'            => $items,
			'next_cursor'      => $next_cursor,
			'has_more'         => $has_more,
			'provider_health'  => $health,
			'partial'          => in_array( 'degraded', $health, true ) || in_array( 'unavailable', $health, true ),
		);
	}

	private static function normalize_item( $provider, $item ) {
		if ( ! is_array( $item ) ) {
			return array();
		}
		$id = sanitize_text_field( (string) ( $item['canonical_id'] ?? $item['id'] ?? '' ) );
		$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		$published = sanitize_text_field( (string) ( $item['published_at'] ?? '' ) );
		if ( ! $id || ! $url || ! $published || false === strtotime( $published ) ) {
			return array();
		}
		return array(
			'provider'      => sanitize_key( $provider ),
			'canonical_id'  => $id,
			'sort_id'       => sanitize_key( $provider ) . ':' . $id,
			'type'          => sanitize_key( (string) ( $item['type'] ?? 'publication' ) ),
			'title'         => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'excerpt'       => wp_kses_post( (string) ( $item['excerpt'] ?? '' ) ),
			'url'           => $url,
			'published_at'  => gmdate( 'Y-m-d H:i:s', strtotime( $published ) ),
			'visibility'    => sanitize_key( (string) ( $item['visibility'] ?? 'private' ) ),
			'status'        => sanitize_key( (string) ( $item['status'] ?? 'published' ) ),
			'thumbnail_url' => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
			'correction'    => sanitize_text_field( (string) ( $item['correction'] ?? '' ) ),
		);
	}

	private static function encode_cursor( $published_at, $sort_id ) {
		return rtrim( strtr( base64_encode( SPD_Helpers::json_encode( array( 't' => $published_at, 'i' => $sort_id ) ) ), '+/', '-_' ), '=' );
	}

	private static function decode_cursor( $cursor ) {
		$cursor = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $cursor );
		if ( ! $cursor ) {
			return array();
		}
		$raw = strtr( $cursor, '-_', '+/' );
		$raw .= str_repeat( '=', ( 4 - strlen( $raw ) % 4 ) % 4 );
		$decoded = base64_decode( $raw, true );
		$data = $decoded ? json_decode( $decoded, true ) : null;
		if ( ! is_array( $data ) || empty( $data['t'] ) || empty( $data['i'] ) ) {
			return array();
		}
		return array( 't' => sanitize_text_field( $data['t'] ), 'i' => sanitize_text_field( $data['i'] ) );
	}

	private static function before_cursor( array $item, array $cursor ) {
		if ( $item['published_at'] < $cursor['t'] ) {
			return true;
		}
		return $item['published_at'] === $cursor['t'] && $item['sort_id'] < $cursor['i'];
	}

	public static function file21_provider( $user_id, array $args ) {
		return apply_filters( 'sabri_file21_profile_timeline_items_v1', array(), absint( $user_id ), $args );
	}

	public static function file10_provider( $user_id, array $args ) {
		return apply_filters( 'sabri_file10_profile_timeline_items_v1', array(), absint( $user_id ), $args );
	}

	public static function file11_provider( $user_id, array $args ) {
		return apply_filters( 'sabri_file11_profile_timeline_items_v1', array(), absint( $user_id ), $args );
	}

	public static function file05_provider( $user_id, array $args ) {
		return apply_filters( 'sabri_file05_profile_timeline_items_v1', array(), absint( $user_id ), $args );
	}
}
