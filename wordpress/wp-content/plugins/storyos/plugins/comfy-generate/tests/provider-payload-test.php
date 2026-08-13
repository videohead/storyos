<?php
/**
 * Regression test for provider-neutral orchestration payloads.
 */

namespace StoryOSGenerationEngine;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = [] ) {
		return $default;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_]+/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return (int) $value;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-provider-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-generation-client.php';

$method = new \ReflectionMethod( Generation_Client::class, 'build_payload' );
$method->setAccessible( true );
$payload = $method->invoke(
	null,
	42,
	[
		'default_provider' => 'veo',
		'connection_id'   => 9,
	],
	'character-sheet',
	[
		'prompt'              => 'A mountain at sunrise',
		'provider_settings'   => [ 'api_key' => 'must-not-cross-boundary' ],
		'provider_endpoint_url' => 'https://provider.example.test',
		'provider_api_key'    => 'must-not-cross-boundary',
	]
);

foreach ( [ 'provider_settings', 'provider_endpoint_url', 'provider_api_key' ] as $key ) {
	if ( isset( $payload[ $key ] ) || isset( $payload['custom_params'][ $key ] ) ) {
		fwrite( STDERR, "provider-specific payload key leaked: {$key}\n" );
		exit( 1 );
	}
}

if ( 'veo' !== $payload['provider_type'] || 9 !== $payload['connection_id'] ) {
	fwrite( STDERR, "provider identity was not preserved\n" );
	exit( 1 );
}

fwrite( STDOUT, "provider-neutral payload regression test passed\n" );