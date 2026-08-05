<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Timeline {
	public static function providers() {
		$providers = apply_filters( 'spd_timeline_providers', array() );
		return is_array( $providers ) ? $providers : array();
	}

	public static function query( $user_id, $cursor = '', $limit = 20 ) {
		$limit = min( 50, max( 1, absint( $limit ) ) );
		$items = array();
		$errors = array();
		foreach ( self::providers() as $key => $provider ) {
			if ( ! is_callable( $provider ) ) { $errors[] = sanitize_key( $key ) . '_invalid'; continue; }
			try {
				$result = call_user_func( $provider, array( 'author_user_id' => absint( $user_id ), 'cursor' => sanitize_text_field( $cursor ), 'limit' => $limit ) );
				if ( is_wp_error( $result ) ) { $errors[] = sanitize_key( $key ) . '_' . $result->get_error_code(); continue; }
				foreach ( (array) $result as $item ) {
					$normalized = self::normalize( $item, $key, $user_id );
					if ( $normalized ) { $items[] = $normalized; }
				}
			} catch ( Throwable $e ) {
				$errors[] = sanitize_key( $key ) . '_failed';
			}
		}
		usort( $items, static function( $a, $b ) { return strcmp( $b['published_at'], $a['published_at'] ); } );
		return array( 'items' => array_slice( $items, 0, $limit ), 'errors' => $errors, 'partial' => ! empty( $errors ) );
	}

	private static function normalize( $item, $provider, $user_id ) {
		$item = is_array( $item ) ? $item : array();
		$required = array( 'canonical_id','canonical_url','title','published_at','visibility','version' );
		foreach ( $required as $key ) { if ( empty( $item[ $key ] ) ) { return array(); } }
		if ( 'public' !== $item['visibility'] || ! wp_http_validate_url( $item['canonical_url'] ) ) { return array(); }
		if ( isset( $item['author_user_id'] ) && absint( $item['author_user_id'] ) !== absint( $user_id ) ) { return array(); }
		return array(
			'provider' => sanitize_key( $provider ),
			'canonical_id' => sanitize_text_field( $item['canonical_id'] ),
			'version' => sanitize_text_field( $item['version'] ),
			'type' => sanitize_key( $item['type'] ?? 'publication' ),
			'title' => sanitize_text_field( $item['title'] ),
			'excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
			'canonical_url' => esc_url_raw( $item['canonical_url'] ),
			'published_at' => gmdate( 'c', strtotime( $item['published_at'] ) ?: time() ),
			'state' => sanitize_key( $item['state'] ?? 'published' ),
		);
	}

	public static function render( $user_id ) {
		$data = self::query( $user_id );
		$out = '<section class="spd-timeline" aria-labelledby="spd-timeline-title"><h2 id="spd-timeline-title">Profile Timeline</h2>';
		if ( $data['partial'] ) { $out .= '<p class="spd-notice">Some timeline providers are temporarily unavailable.</p>'; }
		if ( ! $data['items'] ) { return $out . '<p class="spd-empty">No public timeline items are available.</p></section>'; }
		foreach ( $data['items'] as $item ) {
			$out .= '<article class="spd-card"><p><small>' . esc_html( ucfirst( $item['type'] ) ) . ' · ' . esc_html( mysql2date( get_option( 'date_format' ), $item['published_at'] ) ) . '</small></p><h3><a href="' . esc_url( $item['canonical_url'] ) . '">' . esc_html( $item['title'] ) . '</a></h3><p>' . esc_html( $item['excerpt'] ) . '</p></article>';
		}
		return $out . '</section>';
	}
}
