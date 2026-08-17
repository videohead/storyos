<?php
/**
 * Minimal Streamable HTTP client for the Comfy Cloud MCP server.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comfy_Cloud_MCP {
	const ENDPOINT = 'https://cloud.comfy.org/mcp';

	public static function run_template( string $template, string $prompt, array $parameters ) {
		$arguments = array_filter( $parameters, static function ( $value ) {
			return null !== $value;
		} );
		$arguments['template_name'] = $template;
		$arguments['prompt'] = $prompt;

		return self::call_tool( 'run_template', $arguments );
	}

	public static function get_job_status( string $job_id ) {
		return self::call_tool( 'get_job_status', [ 'job_id' => $job_id ] );
	}

	private static function call_tool( string $name, array $arguments ) {
		$session = self::initialize();
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$result = self::request( 'tools/call', [
			'name'      => $name,
			'arguments' => $arguments,
		], $session );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['content'] ) && is_array( $result['content'] ) ) {
			foreach ( $result['content'] as $content ) {
				if ( isset( $content['text'] ) ) {
					$decoded = json_decode( (string) $content['text'], true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
				}
			}
		}

		return is_array( $result ) ? $result : new WP_Error( 'comfy_mcp_invalid_response', 'Comfy Cloud MCP returned an invalid tool response.' );
	}

	private static function initialize() {
		$result = self::request( 'initialize', [
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => [
				'name'    => 'StoryOS WordPress',
				'version' => defined( 'STORYOS_VERSION' ) ? STORYOS_VERSION : '1.0.0',
			],
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['_session_id'] ) ) {
			return new WP_Error( 'comfy_mcp_session_missing', 'Comfy Cloud MCP did not establish a session.' );
		}

		return $result['_session_id'];
	}

	private static function request( string $method, array $params, string $session_id = '' ) {
		$api_key = defined( 'STORYOS_COMFY_API_KEY' ) ? STORYOS_COMFY_API_KEY : get_option( 'storyos_comfy_api_key', '' );
		if ( '' === trim( (string) $api_key ) ) {
			return new WP_Error( 'comfy_mcp_api_key_missing', 'Set STORYOS_COMFY_API_KEY or the StoryOS Comfy API key option before submitting generations.' );
		}

		$headers = [
			'Accept'        => 'application/json, text/event-stream',
			'Content-Type'  => 'application/json',
			'X-API-Key'     => sanitize_text_field( (string) $api_key ),
		];
		if ( '' !== $session_id ) {
			$headers['Mcp-Session-Id'] = $session_id;
		}

		$response = wp_remote_post( self::ENDPOINT, [
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'jsonrpc' => '2.0',
				'id'      => wp_generate_uuid4(),
				'method'  => $method,
				'params'  => $params,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'comfy_mcp_unreachable', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$payload = self::decode_response( wp_remote_retrieve_body( $response ) );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $payload ) ? ( $payload['error']['message'] ?? 'Comfy Cloud MCP request failed.' ) : 'Comfy Cloud MCP request failed.';
			return new WP_Error( 'comfy_mcp_request_failed', $message, [ 'status' => $status ] );
		}
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'comfy_mcp_invalid_response', 'Comfy Cloud MCP returned non-JSON content.' );
		}
		if ( isset( $payload['error'] ) ) {
			return new WP_Error( 'comfy_mcp_tool_error', (string) ( $payload['error']['message'] ?? 'Comfy Cloud MCP returned an error.' ), $payload['error'] );
		}

		$result = $payload['result'] ?? $payload;
		$headers = wp_remote_retrieve_headers( $response );
		if ( 'initialize' === $method && isset( $headers['mcp-session-id'] ) ) {
			$result['_session_id'] = (string) $headers['mcp-session-id'];
		}

		return $result;
	}

	private static function decode_response( string $body ) {
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		foreach ( preg_split( '/\r?\n/', $body ) as $line ) {
			if ( 0 === strpos( $line, 'data: ' ) ) {
				$decoded = json_decode( substr( $line, 6 ), true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		return null;
	}
}