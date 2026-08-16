<?php
/**
 * ComfyUI MCP client utilities.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized ComfyUI MCP operations for generation submit/status/cancel/artifacts.
 */
class ComfyuiMcpClient {

	/**
	 * Resolve MCP server base URL with backward-compatible fallbacks.
	 *
	 * @param string $preferred Preferred URL.
	 * @return string
	 */
	public static function resolve_server_url( string $preferred = '' ): string {
		$url = trim( $preferred );
		if ( '' === $url ) {
			$url = (string) get_option( 'storyos_comfyui_mcp_url', '' );
		}
		if ( '' === $url && defined( 'STORYOS_COMFYUI_MCP_URL' ) ) {
			$url = (string) STORYOS_COMFYUI_MCP_URL;
		}
		// Backward compatibility with previous orchestrator option.
		if ( '' === $url ) {
			$url = (string) get_option( 'storyos_orchestrator_url', '' );
		}
		if ( '' === $url && defined( 'STORYOS_ORCHESTRATOR_URL' ) ) {
			$url = (string) STORYOS_ORCHESTRATOR_URL;
		}

		return rtrim( $url, '/' );
	}

	/**
	 * Submit a generation request.
	 *
	 * @param array  $payload Generation payload.
	 * @param string $server_url Optional server URL.
	 * @param int    $timeout Request timeout.
	 * @return array
	 */
	public static function submit_generation( array $payload, string $server_url = '', int $timeout = 60 ): array {
		$url = self::build_operation_url( 'submit', $server_url, '' );
		if ( '' === $url ) {
			return [
				'success' => false,
				'error'   => 'ComfyUI MCP server URL is not configured.',
			];
		}

		return self::request( 'POST', $url, $timeout, $payload );
	}

	/**
	 * Fetch job status.
	 *
	 * @param string $job_id Job identifier.
	 * @param string $server_url Optional server URL.
	 * @param int    $timeout Request timeout.
	 * @return array
	 */
	public static function get_status( string $job_id, string $server_url = '', int $timeout = 30 ): array {
		$url = self::build_operation_url( 'status', $server_url, $job_id );
		if ( '' === $url ) {
			return [
				'success' => false,
				'error'   => 'ComfyUI MCP server URL is not configured.',
			];
		}

		return self::request( 'GET', $url, $timeout );
	}

	/**
	 * Cancel a job.
	 *
	 * @param string $job_id Job identifier.
	 * @param string $server_url Optional server URL.
	 * @param int    $timeout Request timeout.
	 * @return array
	 */
	public static function cancel_job( string $job_id, string $server_url = '', int $timeout = 20 ): array {
		$url = self::build_operation_url( 'cancel', $server_url, $job_id );
		if ( '' === $url ) {
			return [
				'success' => false,
				'error'   => 'ComfyUI MCP server URL is not configured.',
			];
		}

		return self::request( 'POST', $url, $timeout );
	}

	/**
	 * Fetch artifacts for a job.
	 *
	 * @param string $job_id Job identifier.
	 * @param string $server_url Optional server URL.
	 * @param int    $timeout Request timeout.
	 * @return array
	 */
	public static function get_artifacts( string $job_id, string $server_url = '', int $timeout = 30 ): array {
		$url = self::build_operation_url( 'artifacts', $server_url, $job_id );
		if ( '' === $url ) {
			return [
				'success' => false,
				'error'   => 'ComfyUI MCP server URL is not configured.',
			];
		}

		return self::request( 'GET', $url, $timeout );
	}

	/**
	 * Build operation URL for MCP adapter contract.
	 *
	 * @param string $operation Operation name.
	 * @param string $server_url Optional base URL.
	 * @param string $job_id Optional job ID.
	 * @return string
	 */
	private static function build_operation_url( string $operation, string $server_url = '', string $job_id = '' ): string {
		$base = self::resolve_server_url( $server_url );
		if ( '' === $base ) {
			return '';
		}

		$default_paths = [
			'submit'    => '/mcp/comfyui/jobs',
			'status'    => '/mcp/comfyui/jobs/%s',
			'cancel'    => '/mcp/comfyui/jobs/%s/cancel',
			'artifacts' => '/mcp/comfyui/jobs/%s/artifacts',
		];
		$paths = apply_filters( 'storyos_comfyui_mcp_paths', $default_paths );
		$template = $paths[ $operation ] ?? '';
		if ( '' === $template ) {
			return '';
		}

		if ( false !== strpos( $template, '%s' ) ) {
			$template = sprintf( $template, rawurlencode( (string) $job_id ) );
		}

		return $base . '/' . ltrim( $template, '/' );
	}

	/**
	 * Build headers for MCP requests.
	 *
	 * @return array
	 */
	private static function build_headers(): array {
		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		$token = (string) get_option( 'storyos_comfyui_mcp_token', '' );
		if ( '' === $token ) {
			$token = (string) get_option( 'storyos_orchestrator_token', '' );
		}
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . sanitize_text_field( $token );
		}

		return $headers;
	}

	/**
	 * Execute HTTP request and normalize response.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $url Endpoint URL.
	 * @param int        $timeout Timeout.
	 * @param array|null $body Body payload.
	 * @return array
	 */
	private static function request( string $method, string $url, int $timeout, ?array $body = null ): array {
		$args = [
			'timeout'   => max( 5, min( 300, $timeout ) ),
			'headers'   => self::build_headers(),
			'sslverify' => false,
		];
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = 'GET' === strtoupper( $method )
			? wp_remote_get( $url, $args )
			: wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );
		$data        = is_array( $decoded ) ? $decoded : [ 'raw' => $raw_body ];
		$job_id      = (string) ( $data['job_id'] ?? $data['id'] ?? $data['remote_job_ref'] ?? '' );
		$status      = self::normalize_status( (string) ( $data['status'] ?? $data['state'] ?? '' ) );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return [
				'success'     => false,
				'error'       => 'ComfyUI MCP request failed.',
				'status_code' => $status_code,
				'response'    => $data,
				'job_id'      => $job_id,
				'status'      => $status,
			];
		}

		return [
			'success'     => true,
			'status_code' => $status_code,
			'response'    => $data,
			'job_id'      => $job_id,
			'status'      => $status,
		];
	}

	/**
	 * Normalize provider status values to StoryOS status values.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	private static function normalize_status( string $status ): string {
		$value = strtolower( trim( $status ) );
		if ( in_array( $value, [ 'queued', 'pending', 'accepted', 'submitted' ], true ) ) {
			return 'queued';
		}
		if ( in_array( $value, [ 'running', 'processing', 'in_progress' ], true ) ) {
			return 'running';
		}
		if ( in_array( $value, [ 'completed', 'success', 'done' ], true ) ) {
			return 'completed';
		}
		if ( in_array( $value, [ 'failed', 'error' ], true ) ) {
			return 'failed';
		}
		if ( in_array( $value, [ 'cancelled', 'canceled' ], true ) ) {
			return 'cancelled';
		}

		return '' !== $value ? $value : 'unknown';
	}
}
