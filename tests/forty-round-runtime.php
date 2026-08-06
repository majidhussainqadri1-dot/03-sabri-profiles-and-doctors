<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
function current_time( $type, $gmt = false ) { return '2026-08-06 00:00:00'; }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function home_url( $path = '' ) { return 'https://example.test:8443' . $path; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function remove_accents( $v ) { return $v; }
function sanitize_title( $v ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $v ) ) ); }
function user_trailingslashit( $v ) { return rtrim( $v, '/' ) . '/'; }
function rawurlencode_stub( $v ) { return rawurlencode( $v ); }
function __( $v, $domain = '' ) { return $v; }
class WP_Error { public function __construct(...$args) {} }
require dirname( __DIR__ ) . '/includes/class-spd-helpers.php';

function assert_true( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}
assert_true( SPD_Helpers::valid_uuid( '123e4567-e89b-42d3-a456-426614174000' ), 'valid UUID rejected' );
assert_true( ! SPD_Helpers::valid_uuid( '123e4567-e89b-12d3-a456-426614174000' ), 'non-v4 UUID accepted' );
assert_true( SPD_Helpers::same_origin_url( 'https://example.test:8443/profile/a' ), 'same origin rejected' );
assert_true( ! SPD_Helpers::same_origin_url( 'http://example.test:8443/profile/a' ), 'scheme downgrade accepted' );
assert_true( ! SPD_Helpers::same_origin_url( 'https://example.test/profile/a' ), 'port mismatch accepted' );
assert_true( ! SPD_Helpers::same_origin_url( 'https://user:pass@example.test:8443/profile/a' ), 'URL credentials accepted' );
assert_true( ! SPD_Helpers::same_origin_url( 'https://cdn.example.test:8443/profile/a' ), 'subdomain accepted' );
assert_true( SPD_Helpers::text_length( 'اردو عبارت' ) >= 10, 'multibyte text length invalid' );
assert_true( SPD_Helpers::json_encode( array( 'x' => 'اردو' ) ) !== 'null', 'valid JSON failed' );
echo "Forty-round runtime helper tests passed.\n";
