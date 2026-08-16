<?php
/**
 * Generation client for the StoryOS ComfyUI MCP pipeline.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends generation requests to the StoryOS ComfyUI MCP adapter service.
 */
class Generation_Client {

	/**
	 * Send a generation request to the orchestrator.
	 *
	 * @param int   $post_id Post identifier to generate for.
	 * @param array $settings Optional settings array.
	 * @param string $workflow Workflow template slug.
	 * @param array $custom_params Optional override parameters.
	 * @return array
	 */
	public static function send_generation_request( int $post_id, array $settings = [], string $workflow = '', array $custom_params = [] ): array {
		$settings = wp_parse_args( $settings, Settings::get_settings() );
		$timeout = isset( $settings['request_timeout'] ) ? max( 5, min( 300, absint( $settings['request_timeout'] ) ) ) : 60;
		$mcp_server_url = self::normalize_endpoint_url( $settings['mcp_server_url'] ?? $settings['orchestrator_url'] ?? $settings['endpoint_url'] ?? '' );
		if ( empty( $mcp_server_url ) ) {
			return [
				'success' => false,
				'error'   => __( 'StoryOS ComfyUI MCP server URL is not configured.', 'storyos-generation-engine' ),
			];
		}

		$payload = self::build_payload( $post_id, $settings, $workflow, $custom_params );
		$result = self::submit_via_mcp( $payload, $mcp_server_url, $timeout );
		if ( empty( $result['success'] ) ) {
			$result['payload'] = $payload;
			return $result;
		}

		do_action( 'storyos_generation_engine_job_submitted', $post_id, $payload, $result['response'] ?? [] );

		return [
			'success'     => true,
			'status_code' => $result['status_code'] ?? 200,
			'response'    => $result['response'] ?? [],
			'job_id'      => (string) ( $result['job_id'] ?? '' ),
			'payload'     => $payload,
		];
	}

	/**
	 * Build a normalized payload for MCP submission.
	 *
	 * @param int    $post_id Post identifier.
	 * @param array  $settings Settings array.
	 * @param string $workflow Workflow template slug.
	 * @param array  $custom_params Optional overrides.
	 * @return array
	 */
	protected static function build_payload( int $post_id, array $settings, string $workflow = '', array $custom_params = [] ): array {
		$provider_type = ! empty( $custom_params['provider_type'] )
			? sanitize_text_field( (string) $custom_params['provider_type'] )
			: sanitize_text_field( (string) ( $settings['provider_type'] ?? '' ) );
		$provider_type = Provider_Registry::normalize( $provider_type );
		$connection_id = isset( $settings['connection_id'] ) ? absint( $settings['connection_id'] ) : 0;
		if ( $connection_id < 1 ) {
			$connection_id = 1;
		}

		$custom_params = is_array( $custom_params ) ? $custom_params : [];
		// Provider configuration is resolved by the orchestrator from the connection.
		unset(
			$custom_params['provider_settings'],
			$custom_params['provider_endpoint_url'],
			$custom_params['provider_api_key'],
			$custom_params['provider_username'],
			$custom_params['provider_password']
		);

		$payload = [
			'post_id'       => $post_id,
			'provider_type' => $provider_type,
			'connection_id' => $connection_id,
			'workflow'      => $workflow ?: ( $settings['workflow'] ?? 'character-sheet' ),
			'custom_params'        => array_filter(
				array_merge(
					[
						'provider_type'            => $provider_type,
						'connection_id'            => $connection_id,
						'source'                => 'wordpress-plugin',
						'storyos_generation_engine' => true,
					],
					$custom_params
				),
				static function ( $value ) {
					return null !== $value;
				}
			),
		];

		return apply_filters( 'storyos_generation_engine_request_payload', $payload, $post_id, $settings );
	}

	/**
	 * Normalize the endpoint URL so it targets the orchestrator generate route.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function normalize_endpoint_url( string $url ): string {
		$trimmed = trim( $url );
		if ( '' !== $trimmed ) {
			return rtrim( $trimmed, '/' );
		}

		return class_exists( '\\StoryOS\\Utils\\ComfyuiMcpClient' )
			? \StoryOS\Utils\ComfyuiMcpClient::resolve_server_url()
			: '';
	}

	/**
	 * Submit payload through ComfyUI MCP adapter.
	 *
	 * @param array  $payload Request payload.
	 * @param string $mcp_server_url MCP base URL.
	 * @param int    $timeout Timeout.
	 * @return array
	 */
	private static function submit_via_mcp( array $payload, string $mcp_server_url, int $timeout ): array {
		if ( class_exists( '\\StoryOS\\Utils\\ComfyuiMcpClient' ) ) {
			$result = \StoryOS\Utils\ComfyuiMcpClient::submit_generation( $payload, $mcp_server_url, $timeout );
			if ( empty( $result['success'] ) ) {
				$result['error'] = $result['error'] ?? __( 'StoryOS ComfyUI MCP server returned an error.', 'storyos-generation-engine' );
			}
			return $result;
		}

		$response = wp_remote_post(
			rtrim( $mcp_server_url, '/' ) . '/mcp/comfyui/jobs',
			[
				'timeout' => $timeout,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return [
				'success'     => false,
				'status_code' => $status_code,
				'error'       => __( 'StoryOS ComfyUI MCP server returned an error.', 'storyos-generation-engine' ),
				'response'    => $decoded ?: [ 'raw' => $raw_body ],
			];
		}

		return [
			'success'     => true,
			'status_code' => $status_code,
			'response'    => $decoded ?: [ 'raw' => $raw_body ],
			'job_id'      => is_array( $decoded ) ? (string) ( $decoded['job_id'] ?? $decoded['id'] ?? $decoded['remote_job_ref'] ?? '' ) : '',
		];
	}
}
