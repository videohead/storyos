<?php
/**
 * Provider Connection Testing.
 *
 * Validates a storyos_connection record against the configured provider.
 * For Comfy Cloud MCP, this verifies that the API key is configured.
 *
 * On success the connection status is set to "verified" and
 * last_validated_at is stamped. On failure the status is set to "error".
 * Status is never set from user input — mirroring the Generation Engine
 * settings behavior.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

/**
 * Connection tester.
 */
class Connection_Tester {

	/**
	 * HTTP timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 30;

	/**
	 * Test a connection.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array Result: [ 'success' => bool, 'status' => string, 'message' => string, 'health' => array ].
	 */
	public static function test( int $connection_id ): array {
		$record = Connection_Repository::get( $connection_id );
		if ( null === $record ) {
			return [
				'success' => false,
				'status'  => 'error',
				'message' => 'Connection not found.',
				'health'  => [],
			];
		}

		if ( '' === $record['provider_type'] ) {
			return [
				'success' => false,
				'status'  => 'error',
				'message' => 'Connection has no provider type configured.',
				'health'  => [],
			];
		}

		$llm_backends = [ 'openai_compatible', 'openai', 'anthropic', 'dual' ];
		if ( in_array( $record['provider_type'], $llm_backends, true ) ) {
			return self::test_llm( $connection_id, $record );
		}

		if ( 'comfyui' === $record['provider_type'] && '' !== $record['endpoint_url'] && Comfy_Cloud_MCP::ENDPOINT !== untrailingslashit( $record['endpoint_url'] ) ) {
			return self::test_local_comfyui( $connection_id, $record );
		}

		$has_key = '' !== trim( (string) $record['credential_reference'] );
		return self::record_result( $connection_id, $has_key, $has_key ? 'Comfy Cloud MCP credentials configured.' : 'Comfy Cloud MCP API key is not configured.', [] );
	}

	/**
	 * Test an LLM-backed connection.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $record        Connection record.
	 * @return array
	 */
	private static function test_llm( int $connection_id, array $record ): array {
		$configuration = [
			'backend' => $record['provider_type'],
			'url'     => $record['endpoint_url'],
			'model'   => $record['model'],
			'api_key' => $record['credential_reference'],
		];

		$result = ( new \StoryOS\AI\AI_LLM_Client() )->test_connection( $configuration );
		$message = $result['healthy']
			? ( ! empty( $result['url'] ) ? sprintf( 'Connected to %s.', $result['url'] ) : 'Provider credentials are configured.' )
			: ( $result['error'] ?? 'Unable to reach the LLM endpoint.' );

		return self::record_result( $connection_id, ! empty( $result['healthy'] ), $message, $result );
	}

	/**
	 * Test a local ComfyUI HTTP API connection.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $record        Connection record.
	 * @return array
	 */
	private static function test_local_comfyui( int $connection_id, array $record ): array {
		$url = untrailingslashit( $record['endpoint_url'] );
		$response = wp_remote_get( $url . '/system_stats', [ 'timeout' => self::TIMEOUT ] );

		if ( is_wp_error( $response ) ) {
			return self::record_result( $connection_id, false, sprintf( 'Unable to reach ComfyUI: %s', $response->get_error_message() ), [] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return self::record_result( $connection_id, false, sprintf( 'ComfyUI returned HTTP %d from /system_stats.', $code ), [] );
		}

		return self::record_result( $connection_id, true, sprintf( 'Connected to ComfyUI at %s.', $url ), [] );
	}

	/**
	 * Persist the outcome of a connection test.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param bool   $success       Whether the health check passed.
	 * @param string $message       Human-readable result message.
	 * @param array  $health        Raw health payload.
	 * @return array
	 */
	private static function record_result( int $connection_id, bool $success, string $message, array $health ): array {
		$status = $success ? 'verified' : 'error';

		update_post_meta( $connection_id, 'status', $status );
		update_post_meta( $connection_id, 'last_validated_at', gmdate( 'Y-m-d H:i:s' ) );
		if ( ! empty( $health ) ) {
			update_post_meta( $connection_id, 'last_health_report', wp_json_encode( $health ) );
		}

		/**
		 * Fires after a connection test completes.
		 *
		 * @param int    $connection_id Connection post ID.
		 * @param bool   $success       Whether the test passed.
		 * @param string $message       Result message.
		 * @param array  $health        Raw health payload.
		 */
		do_action( 'storyos_connection_tested', $connection_id, $success, $message, $health );

		return [
			'success' => $success,
			'status'  => $status,
			'message' => $message,
			'health'  => $health,
		];
	}
}
