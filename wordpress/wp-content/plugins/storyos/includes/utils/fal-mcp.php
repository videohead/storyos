<?php
/**
 * Streamable HTTP client for the hosted fal MCP server.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * fal MCP adapter.
 */
class Fal_MCP {

	/** Hosted fal MCP endpoint. */
	const ENDPOINT = 'https://mcp.fal.ai/mcp';

	/** Tools StoryOS requires for asynchronous generation. */
	const GENERATION_TOOLS = [ 'submit_job', 'check_job' ];

	/** HTTP timeout in seconds. */
	const TIMEOUT = 60;

	/**
	 * Test an unsaved fal configuration, as used by the setup wizard.
	 *
	 * @param string $endpoint             MCP endpoint.
	 * @param string $credential_reference API key or env:// reference.
	 * @return array<int, string>|WP_Error
	 */
	public static function test_configuration( string $endpoint, string $credential_reference ) {
		return self::available_tools_for( $endpoint, $credential_reference );
	}

	/**
	 * Return the MCP tools exposed for a saved Connection.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array<int, string>|WP_Error
	 */
	public static function available_tools( int $connection_id ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'fal' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'fal_connection_invalid', __( 'Select a fal Connection first.', 'storyos' ) );
		}

		return self::available_tools_for( self::endpoint( $connection ), (string) ( $connection['credential_reference'] ?? '' ) );
	}

	/** Search fal's MCP model catalog. */
	public static function search_models( array $filters, int $connection_id ) {
		return self::call_tool( 'search_models', array_filter( $filters, static function ( $value ): bool {
			return null !== $value && '' !== $value;
		} ), $connection_id );
	}

	/** Load the provider schema for one fal model endpoint. */
	public static function get_model_schema( string $endpoint_id, int $connection_id ) {
		return self::call_tool( 'get_model_schema', [ 'endpoint_id' => $endpoint_id ], $connection_id );
	}

	/**
	 * Submit a fal job without waiting for long-running media generation.
	 *
	 * The method intentionally matches the generation adapter signature used by
	 * Generation_Batch. For fal, `$template` is an endpoint ID such as
	 * `fal-ai/flux/dev` and `$parameters` are model input fields.
	 *
	 * @param string $template      fal endpoint ID.
	 * @param string $prompt        Generation prompt.
	 * @param array  $parameters    Model input values.
	 * @param int    $connection_id Connection post ID.
	 * @return array|WP_Error
	 */
	public static function run_template( string $template, string $prompt, array $parameters, int $connection_id = 0 ) {
		$endpoint_id = trim( $template );
		if ( '' === $endpoint_id ) {
			return new WP_Error( 'fal_endpoint_id_missing', __( 'The fal Template must specify a fal model endpoint ID.', 'storyos' ) );
		}
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || ! self::endpoint_is_allowed( $connection, $endpoint_id ) ) {
			return new WP_Error( 'fal_endpoint_not_allowed', __( 'That fal model endpoint is not allowed by the selected Connection.', 'storyos' ) );
		}

		$input = array_filter( $parameters, static function ( $value ): bool {
			return null !== $value;
		} );
		$input['prompt'] = $prompt;

		Generation_Log::add( 'info', 'fal_mcp', 'Calling submit_job.', [ 'endpoint_id' => $endpoint_id ], '', $connection_id );
		$result = self::call_tool( 'submit_job', [
			'endpoint_id' => $endpoint_id,
			'input'       => (object) $input,
		], $connection_id );

		if ( is_wp_error( $result ) ) {
			Generation_Log::add( 'error', 'fal_mcp', $result->get_error_message(), [], '', $connection_id );
			return $result;
		}

		$request_id = (string) ( $result['request_id'] ?? $result['job_id'] ?? $result['id'] ?? '' );
		if ( '' !== $request_id ) {
			$result['job_id'] = $request_id;
		}

		return $result;
	}

	/**
	 * Whether a fal endpoint is permitted by a Connection's model_access list.
	 * An empty list means any fal endpoint may be selected by a Template.
	 */
	public static function endpoint_is_allowed( array $connection, string $endpoint_id ): bool {
		$raw = trim( (string) ( $connection['model_access'] ?? '' ) );
		if ( '' === $raw ) {
			return true;
		}

		$allowed = json_decode( $raw, true );
		return is_array( $allowed ) && in_array( $endpoint_id, array_map( 'strval', $allowed ), true );
	}

	/**
	 * Poll a submitted fal job and fetch its result once complete.
	 *
	 * @param string $job_id       fal request ID.
	 * @param int    $connection_id Connection post ID.
	 * @param string $endpoint_id   fal model endpoint ID.
	 * @return array|WP_Error
	 */
	public static function get_job_status( string $job_id, int $connection_id = 0, string $endpoint_id = '' ) {
		if ( '' === trim( $endpoint_id ) ) {
			return new WP_Error( 'fal_endpoint_id_missing', __( 'The fal job is missing its model endpoint ID.', 'storyos' ) );
		}

		$args = [
			'endpoint_id' => $endpoint_id,
			'request_id'  => $job_id,
			'action'      => 'status',
		];
		$result = self::call_tool( 'check_job', $args, $connection_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = self::normalize_status( (string) ( $result['status'] ?? $result['state'] ?? '' ) );
		if ( 'completed' !== $status ) {
			$result['status'] = $status ?: 'submitted';
			return $result;
		}

		$args['action'] = 'result';
		$completed = self::call_tool( 'check_job', $args, $connection_id );
		if ( is_wp_error( $completed ) ) {
			return $completed;
		}

		$completed['status'] = 'completed';
		return $completed;
	}

	/**
	 * Probe an endpoint and list its tools.
	 *
	 * fal's hosted server is stateless, so a session header is optional.
	 *
	 * @param string $endpoint             MCP endpoint.
	 * @param string $credential_reference API key or reference.
	 * @return array<int, string>|WP_Error
	 */
	private static function available_tools_for( string $endpoint, string $credential_reference ) {
		$initialized = self::request_to( $endpoint, $credential_reference, 'initialize', [
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => [
				'name'    => 'StoryOS WordPress',
				'version' => defined( 'STORYOS_VERSION' ) ? STORYOS_VERSION : '1.0.0',
			],
		] );
		if ( is_wp_error( $initialized ) ) {
			return $initialized;
		}

		$result = self::request_to( $endpoint, $credential_reference, 'tools/list', [], (string) ( $initialized['_session_id'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tools = [];
		foreach ( (array) ( $result['tools'] ?? [] ) as $tool ) {
			if ( is_array( $tool ) && ! empty( $tool['name'] ) ) {
				$tools[] = (string) $tool['name'];
			}
		}

		return $tools;
	}

	/** Call one fal MCP tool. */
	private static function call_tool( string $name, array $arguments, int $connection_id ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'fal' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'fal_connection_invalid', __( 'The selected Connection is not a fal Connection.', 'storyos' ) );
		}

		$endpoint   = self::endpoint( $connection );
		$credential = (string) ( $connection['credential_reference'] ?? '' );
		$initialized = self::request_to( $endpoint, $credential, 'initialize', [
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => [ 'name' => 'StoryOS WordPress', 'version' => defined( 'STORYOS_VERSION' ) ? STORYOS_VERSION : '1.0.0' ],
		] );
		if ( is_wp_error( $initialized ) ) {
			return $initialized;
		}

		$result = self::request_to(
			$endpoint,
			$credential,
			'tools/call',
			[ 'name' => $name, 'arguments' => (object) $arguments ],
			(string) ( $initialized['_session_id'] ?? '' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::decode_tool_result( $result );
	}

	/** Send one JSON-RPC request to fal MCP. */
	private static function request_to( string $endpoint, string $credential_reference, string $method, array $params, string $session_id = '' ) {
		$endpoint = untrailingslashit( esc_url_raw( $endpoint ?: self::ENDPOINT ) );
		$api_key  = self::resolve_credential( $credential_reference );
		if ( '' === $api_key ) {
			return new WP_Error( 'fal_credential_missing', __( 'Set a fal API key or env://FAL_KEY reference on this Connection.', 'storyos' ) );
		}

		$headers = [
			'Accept'        => 'application/json, text/event-stream',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		];
		if ( '' !== $session_id ) {
			$headers['Mcp-Session-Id'] = $session_id;
		}

		$response = wp_remote_post( $endpoint, [
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'jsonrpc' => '2.0',
				'id'      => wp_generate_uuid4(),
				'method'  => $method,
				'params'  => (object) $params,
			] ),
		] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fal_mcp_unreachable', $response->get_error_message() );
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$payload = self::decode_response( (string) wp_remote_retrieve_body( $response ) );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $payload ) ? (string) ( $payload['error']['message'] ?? __( 'fal MCP request failed.', 'storyos' ) ) : __( 'fal MCP request failed.', 'storyos' );
			return new WP_Error( 'fal_mcp_request_failed', $message, [ 'status' => $status ] );
		}
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'fal_mcp_invalid_response', __( 'fal MCP returned non-JSON content.', 'storyos' ) );
		}
		if ( isset( $payload['error'] ) ) {
			return new WP_Error( 'fal_mcp_error', (string) ( $payload['error']['message'] ?? __( 'fal MCP returned an error.', 'storyos' ) ), $payload['error'] );
		}

		$result  = $payload['result'] ?? $payload;
		$headers = wp_remote_retrieve_headers( $response );
		if ( 'initialize' === $method && isset( $headers['mcp-session-id'] ) ) {
			$result['_session_id'] = (string) $headers['mcp-session-id'];
		}

		return $result;
	}

	/** Resolve a literal key or an env:// environment-variable reference. */
	private static function resolve_credential( string $reference ): string {
		$reference = trim( $reference );
		if ( 0 === strpos( $reference, 'env://' ) ) {
			$name = substr( $reference, 6 );
			if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
				return '';
			}
			$value = getenv( $name );
			return false === $value ? '' : trim( (string) $value );
		}

		return $reference;
	}

	/** Resolve a saved Connection's fal MCP endpoint. */
	private static function endpoint( array $connection ): string {
		return (string) ( $connection['mcp_endpoint_url'] ?: $connection['endpoint_url'] ?: self::ENDPOINT );
	}

	/** Decode JSON or Streamable HTTP SSE data frames. */
	private static function decode_response( string $body ) {
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		foreach ( preg_split( '/\r?\n/', $body ) as $line ) {
			if ( 0 === strpos( $line, 'data:' ) ) {
				$decoded = json_decode( trim( substr( $line, 5 ) ), true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		return null;
	}

	/** Decode the text content returned by an MCP tools/call response. */
	private static function decode_tool_result( array $result ) {
		if ( ! empty( $result['isError'] ) ) {
			$message = __( 'fal MCP tool call failed.', 'storyos' );
			foreach ( (array) ( $result['content'] ?? [] ) as $content ) {
				if ( is_array( $content ) && ! empty( $content['text'] ) ) {
					$message = (string) $content['text'];
					break;
				}
			}
			return new WP_Error( 'fal_mcp_tool_error', $message );
		}

		foreach ( (array) ( $result['content'] ?? [] ) as $content ) {
			if ( ! is_array( $content ) || ! isset( $content['text'] ) ) {
				continue;
			}
			$decoded = json_decode( (string) $content['text'], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return $result;
	}

	/** Map fal queue states onto StoryOS generation states. */
	private static function normalize_status( string $status ): string {
		$status = strtolower( str_replace( [ ' ', '-' ], '_', trim( $status ) ) );
		if ( in_array( $status, [ 'completed', 'complete', 'succeeded', 'success', 'ok' ], true ) ) {
			return 'completed';
		}
		if ( in_array( $status, [ 'failed', 'error' ], true ) ) {
			return 'failed';
		}
		if ( in_array( $status, [ 'cancelled', 'canceled' ], true ) ) {
			return 'cancelled';
		}

		return 'submitted';
	}
}
