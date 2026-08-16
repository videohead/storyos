<?php
/**
 * Regression test for provider-neutral control-plane settings.
 */

namespace StoryOSGenerationEngine;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = [] ) {
		return $GLOBALS['storyos_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['storyos_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		if ( ! is_array( $args ) ) {
			$args = [];
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return (int) $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_]+/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

require_once dirname(__DIR__) . '/includes/class-provider-registry.php';
require_once dirname(__DIR__) . '/includes/class-settings.php';

use StoryOSGenerationEngine\Provider_Registry;
use StoryOSGenerationEngine\Settings;

$GLOBALS['storyos_test_options'] = [];


if ( [] !== Provider_Registry::get_providers() ) {
	fwrite( STDERR, "core plugin contains provider descriptors\n" );
	exit( 1 );
}


$settings = Settings::sanitize_settings( [
	'provider_type'       => 'new-provider',
	'provider_api_key'    => 'must-not-be-stored',
	'provider_endpoint_url' => 'https://provider.example.test',
	'orchestrator_url'    => 'https://legacy.example.test',
] );
if ( 'newprovider' !== $settings['provider_type'] ) {
	fwrite( STDERR, "provider type was not normalized\n" );
	exit( 1 );
}

foreach ( [ 'provider_api_key', 'provider_endpoint_url', 'provider_password', 'provider_username' ] as $key ) {
	if ( isset( $settings[ $key ] ) ) {
		fwrite( STDERR, "provider-specific setting was stored: {$key}\n" );
		exit( 1 );
	}

	if ( 'https://legacy.example.test' !== $settings['mcp_server_url'] ) {
		fwrite( STDERR, "legacy orchestrator URL did not backfill mcp_server_url\n" );
		exit( 1 );
	}

	$settings = Settings::sanitize_settings( [
		'mcp_server_url' => 'https://mcp.example.test',
	] );
	if ( 'https://mcp.example.test' !== $settings['mcp_server_url'] ) {
		fwrite( STDERR, "mcp_server_url was not preserved\n" );
		exit( 1 );
	}
}

if ( 'unknownprovider' !== Provider_Registry::normalize( 'unknown-provider' ) ) {
	fwrite( STDERR, "provider type was incorrectly constrained by core registry\n" );
	exit( 1 );
}

fwrite( STDOUT, "provider settings regression test passed\n" );
