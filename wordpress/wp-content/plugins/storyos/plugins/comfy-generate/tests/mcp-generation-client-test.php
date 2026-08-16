<?php
/**
 * Regression test for ComfyUI MCP generation routing.
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
	}
}

namespace StoryOS\Utils;

class ComfyuiMcpClient {
	public static $calls = [];

	public static function resolve_server_url( string $preferred = '' ): string {
		return $preferred ?: 'https://resolved.example.test';
	}

	public static function submit_generation( array $payload, string $server_url = '', int $timeout = 60 ): array {
		self::$calls[] = [
			'payload'    => $payload,
			'server_url' => $server_url,
			'timeout'    => $timeout,
		];

		return [
			'success'     => true,
			'status_code' => 202,
			'response'    => [ 'job_id' => 'job-123' ],
			'job_id'      => 'job-123',
		];
	}
}

namespace StoryOSGenerationEngine;

require_once dirname( __DIR__ ) . '/includes/class-provider-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-generation-client.php';

$result = Generation_Client::send_generation_request(
	42,
	[
		'mcp_server_url'  => 'https://mcp.example.test',
		'request_timeout' => 75,
		'provider_type'   => 'comfyui',
		'connection_id'   => 2,
	],
	'character-sheet',
	[
		'prompt' => 'Test prompt',
	]
);

if ( empty( $result['success'] ) || 'job-123' !== ( $result['job_id'] ?? '' ) ) {
	fwrite( STDERR, "generation request did not return expected MCP job id\n" );
	exit( 1 );
}

if ( empty( \StoryOS\Utils\ComfyuiMcpClient::$calls ) ) {
	fwrite( STDERR, "ComfyUI MCP client was not called\n" );
	exit( 1 );
}

$call = \StoryOS\Utils\ComfyuiMcpClient::$calls[0];
if ( 'https://mcp.example.test' !== $call['server_url'] ) {
	fwrite( STDERR, "ComfyUI MCP call used unexpected server URL\n" );
	exit( 1 );
}

if ( 75 !== $call['timeout'] ) {
	fwrite( STDERR, "ComfyUI MCP call used unexpected timeout\n" );
	exit( 1 );
}

fwrite( STDOUT, "mcp generation client regression test passed\n" );
