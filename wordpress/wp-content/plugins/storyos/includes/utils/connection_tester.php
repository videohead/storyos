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

		$has_key = defined( 'STORYOS_COMFY_API_KEY' ) || '' !== (string) get_option( 'storyos_comfy_api_key', '' );
		return self::record_result( $connection_id, $has_key, $has_key ? 'Comfy Cloud MCP credentials configured.' : 'Comfy Cloud MCP API key is not configured.', [] );
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
