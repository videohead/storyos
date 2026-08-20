<?php
/**
	 * Streamable HTTP client for Comfy Cloud MCP and local ComfyUI MCP servers.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comfy_Cloud_MCP {
	const ENDPOINT = 'https://cloud.comfy.org/mcp';

	/**
	 * Transient holding the MCP server's advertised tool names.
	 */
	const TOOLS_TRANSIENT = 'worldgraph_comfy_mcp_tools';

	/**
	 * Whether Comfy Cloud MCP credentials are available to WordPress.
	 *
	 * @return bool
	 */
	public static function is_configured( int $connection_id = 0 ): bool {
		if ( self::is_local_connection( $connection_id ) ) {
			return '' !== self::endpoint( $connection_id );
		}

		$connection = $connection_id ? Connection_Repository::get( $connection_id ) : null;
		$api_key = is_array( $connection ) ? (string) ( $connection['credential_reference'] ?? '' ) : '';

		return '' !== trim( (string) $api_key );
	}

	public static function run_template( string $template, string $prompt, array $parameters, int $connection_id = 0 ) {
		$arguments = array_filter( $parameters, static function ( $value ) {
			return null !== $value;
		} );
		$arguments['template_name'] = $template;
		$arguments['prompt'] = $prompt;

		Generation_Log::add( 'info', 'comfy_cloud_mcp', 'Calling run_template tool.', [ 'template' => $template, 'prompt' => $prompt ], '', $connection_id );
		$result = self::call_tool( 'run_template', $arguments, $connection_id );
		if ( is_wp_error( $result ) ) {
			Generation_Log::add( 'error', 'comfy_cloud_mcp', $result->get_error_message(), [], '', $connection_id );
		} else {
			Generation_Log::add( 'info', 'comfy_cloud_mcp', 'run_template tool call succeeded.', $result, (string) ( $result['job_id'] ?? $result['id'] ?? '' ), $connection_id );
		}

		return $result;
	}

	public static function get_job_status( string $job_id, int $connection_id = 0 ) {
		$result = self::call_tool( 'get_job_status', [ 'job_id' => $job_id ], $connection_id );
		if ( is_wp_error( $result ) ) {
			Generation_Log::add( 'error', 'comfy_cloud_mcp', $result->get_error_message(), [], $job_id, $connection_id );
		} else {
			Generation_Log::add( 'debug', 'comfy_cloud_mcp', 'get_job_status tool call returned status: ' . (string) ( $result['status'] ?? 'unknown' ), $result, $job_id, $connection_id );
		}

		return $result;
	}

	/**
	 * The tool names this MCP server advertises, cached for an hour so a
	 * capability probe does not cost a round trip per call.
	 *
	 * @param int $connection_id Connection post ID, for log correlation.
	 * @return array<int, string>|WP_Error
	 */
	public static function available_tools( int $connection_id = 0 ) {
		$cached = get_transient( self::TOOLS_TRANSIENT . md5( self::endpoint( $connection_id ) ) );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$session = self::initialize( $connection_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$result = self::request( 'tools/list', [], $session, $connection_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tools = [];
		foreach ( (array) ( $result['tools'] ?? [] ) as $tool ) {
			if ( is_array( $tool ) && ! empty( $tool['name'] ) ) {
				$tools[] = (string) $tool['name'];
			}
		}

		set_transient( self::TOOLS_TRANSIENT . md5( self::endpoint( $connection_id ) ), $tools, HOUR_IN_SECONDS );

		return $tools;
	}

	/**
	 * Whether the MCP server behind a Connection exposes a tool.
	 *
	 * @param string $name Tool name.
	 * @param int    $connection_id Connection post ID.
	 * @return bool
	 */
	public static function supports_tool( string $name, int $connection_id = 0 ): bool {
		$tools = self::available_tools( $connection_id );

		return is_array( $tools ) && in_array( $name, $tools, true );
	}

	/**
	 * Tools required for World Graph Studio to discover and provision templates without
	 * operator intervention.
	 *
	 * @var array<int, string>
	 */
	const TEMPLATE_TOOLS = [ 'list_templates', 'get_template', 'download_models' ];

	/**
	 * Classify what a Connection's MCP server can actually do, so callers can
	 * offer the right affordances instead of failing deep inside a job.
	 *
	 * `a` exposes the whole template system, `b` exposes part of it, `c` has no
	 * MCP endpoint at all, and `unreachable` could not be probed.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array{tier: string, tools: array<int, string>, endpoint: string, message: string}
	 */
	public static function capability_tier( int $connection_id = 0 ): array {
		$endpoint = self::endpoint( $connection_id );
		if ( '' === $endpoint ) {
			return [
				'tier'     => 'c',
				'tools'    => [],
				'endpoint' => '',
				'message'  => __( 'No MCP endpoint is configured, so template discovery falls back to the built-in modalities.', 'worldgraph' ),
			];
		}

		$tools = self::available_tools( $connection_id );
		if ( is_wp_error( $tools ) ) {
			return [
				'tier'     => 'unreachable',
				'tools'    => [],
				'endpoint' => $endpoint,
				'message'  => $tools->get_error_message(),
			];
		}

		$missing = array_values( array_diff( self::TEMPLATE_TOOLS, $tools ) );
		if ( empty( $missing ) ) {
			return [
				'tier'     => 'a',
				'tools'    => $tools,
				'endpoint' => $endpoint,
				'message'  => __( 'This connection exposes the full Comfy MCP template system.', 'worldgraph' ),
			];
		}

		return [
			'tier'     => 'b',
			'tools'    => $tools,
			'endpoint' => $endpoint,
			'message'  => sprintf(
				/* translators: %s: comma-separated list of MCP tool names. */
				__( 'This MCP server does not expose: %s. Some discovery or download steps will need manual work.', 'worldgraph' ),
				implode( ', ', $missing )
			),
		];
	}

	/**
	 * Discover ComfyUI workflow templates the MCP template system knows about.
	 *
	 * @param array $filters Optional `model_type` / `task_type` filters.
	 * @param int   $connection_id Connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	public static function list_templates( array $filters = [], int $connection_id = 0 ) {
		return self::call_discovery_tool( 'list_templates', array_filter( $filters, static function ( $value ) {
			return null !== $value && '' !== $value;
		} ), $connection_id );
	}

	/**
	 * Load one discovered template, including its workflow graph, required
	 * nodes, and default settings.
	 *
	 * @param string $template_id Template identifier from list_templates().
	 * @param array  $parameters  Optional parameter overrides.
	 * @param int    $connection_id Connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	public static function get_template( string $template_id, array $parameters = [], int $connection_id = 0 ) {
		return self::call_discovery_tool( 'get_template', [
			'templateId' => $template_id,
			'parameters' => (object) $parameters,
		], $connection_id );
	}

	/**
	 * Ask the MCP server to fetch model files into the ComfyUI workspace.
	 *
	 * @param array $urls Model download URLs.
	 * @param int   $connection_id Connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	public static function download_models( array $urls, int $connection_id = 0 ) {
		$urls = array_values( array_filter( array_map( 'esc_url_raw', $urls ) ) );
		if ( empty( $urls ) ) {
			return new WP_Error( 'comfy_mcp_no_models', 'No model download URLs were supplied.' );
		}

		return self::call_discovery_tool( 'download_models', [ 'urls' => $urls ], $connection_id );
	}

	/**
	 * Call a template-system tool, reporting clearly when the connected MCP
	 * server does not implement it rather than failing deep inside a job.
	 *
	 * @param string $name Tool name.
	 * @param array  $arguments Tool arguments.
	 * @param int    $connection_id Connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	private static function call_discovery_tool( string $name, array $arguments, int $connection_id ) {
		$tools = self::available_tools( $connection_id );
		if ( is_wp_error( $tools ) ) {
			return $tools;
		}
		if ( ! in_array( $name, $tools, true ) ) {
			return new WP_Error(
				'comfy_mcp_tool_unavailable',
				sprintf( 'The connected Comfy MCP server does not expose the "%s" tool.', $name ),
				[ 'available_tools' => $tools ]
			);
		}

		return self::call_tool( $name, $arguments, $connection_id );
	}

	private static function call_tool( string $name, array $arguments, int $connection_id = 0 ) {
		$session = self::initialize( $connection_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$result = self::request( 'tools/call', [
			'name'      => $name,
			'arguments' => $arguments,
		], $session, $connection_id );
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

	private static function initialize( int $connection_id = 0 ) {
		$result = self::request( 'initialize', [
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => [
				'name'    => 'World Graph Studio WordPress',
				'version' => defined( 'WORLDGRAPH_VERSION' ) ? WORLDGRAPH_VERSION : '1.0.0',
			],
		], '', $connection_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['_session_id'] ) ) {
			return new WP_Error( 'comfy_mcp_session_missing', 'Comfy Cloud MCP did not establish a session.' );
		}

		return $result['_session_id'];
	}

	private static function request( string $method, array $params, string $session_id = '', int $connection_id = 0 ) {
		$connection = $connection_id ? Connection_Repository::get( $connection_id ) : null;
		$api_key = is_array( $connection ) ? (string) ( $connection['credential_reference'] ?? '' ) : '';
		if ( ! self::is_configured( $connection_id ) ) {
			return new WP_Error( 'comfy_mcp_connection_credential_missing', 'Set the credential on the selected ComfyUI Connection before submitting generations.' );
		}

		$headers = [
			'Accept'        => 'application/json, text/event-stream',
			'Content-Type'  => 'application/json',
		];
		if ( ! self::is_local_connection( $connection_id ) && '' !== trim( (string) $api_key ) ) {
			$headers['X-API-Key'] = sanitize_text_field( (string) $api_key );
		}
		if ( '' !== $session_id ) {
			$headers['Mcp-Session-Id'] = $session_id;
		}

		$response = wp_remote_post( self::endpoint( $connection_id ), [
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'jsonrpc' => '2.0',
				'id'      => wp_generate_uuid4(),
				'method'  => $method,
				// Cast to object so an empty $params encodes as JSON `{}` rather
				// than `[]` — MCP servers reject an array where a params object
				// (however empty) is required.
				'params'  => (object) $params,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			Generation_Log::add( 'error', 'comfy_cloud_mcp', 'Unreachable: ' . $response->get_error_message(), [ 'method' => $method ], '', $connection_id );
			return new WP_Error( 'comfy_mcp_unreachable', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$payload = self::decode_response( wp_remote_retrieve_body( $response ) );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $payload ) ? ( $payload['error']['message'] ?? 'Comfy Cloud MCP request failed.' ) : 'Comfy Cloud MCP request failed.';
			Generation_Log::add( 'error', 'comfy_cloud_mcp', sprintf( 'HTTP %d on %s: %s', $status, $method, $message ), [ 'method' => $method, 'params' => $params ], '', $connection_id );
			return new WP_Error( 'comfy_mcp_request_failed', $message, [ 'status' => $status ] );
		}
		if ( ! is_array( $payload ) ) {
			Generation_Log::add( 'error', 'comfy_cloud_mcp', 'Non-JSON response on ' . $method, [ 'method' => $method ], '', $connection_id );
			return new WP_Error( 'comfy_mcp_invalid_response', 'Comfy Cloud MCP returned non-JSON content.' );
		}
		if ( isset( $payload['error'] ) ) {
			Generation_Log::add( 'error', 'comfy_cloud_mcp', sprintf( 'MCP error on %s: %s', $method, (string) ( $payload['error']['message'] ?? '' ) ), [ 'method' => $method, 'error' => $payload['error'] ], '', $connection_id );
			return new WP_Error( 'comfy_mcp_tool_error', (string) ( $payload['error']['message'] ?? 'Comfy Cloud MCP returned an error.' ), $payload['error'] );
		}

		$result = $payload['result'] ?? $payload;
		$headers = wp_remote_retrieve_headers( $response );
		if ( 'initialize' === $method && isset( $headers['mcp-session-id'] ) ) {
			$result['_session_id'] = (string) $headers['mcp-session-id'];
		}

		return $result;
	}

	/**
	 * Resolve the MCP endpoint from the selected Connection environment.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return string
	 */
	private static function endpoint( int $connection_id = 0 ): string {
		if ( self::is_local_connection( $connection_id ) ) {
			$connection = Connection_Repository::get( $connection_id );
			$endpoint = is_array( $connection ) ? (string) ( $connection['mcp_endpoint_url'] ?? '' ) : '';

			return untrailingslashit( esc_url_raw( $endpoint ?: (string) get_option( 'worldgraph_comfy_local_mcp_url', '' ) ) );
		}

		return self::ENDPOINT;
	}

	/**
	 * Whether a Connection represents the local MCP environment.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return bool
	 */
	private static function is_local_connection( int $connection_id ): bool {
		$connection = $connection_id ? Connection_Repository::get( $connection_id ) : null;

		return is_array( $connection ) && 'local' === ( $connection['environment'] ?? '' );
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