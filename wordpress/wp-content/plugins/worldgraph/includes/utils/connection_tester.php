<?php
/**
 * Provider Connection Testing.
 *
 * Validates a worldgraph_conn record against the configured provider.
 * For Comfy Cloud MCP, this verifies that the API key is configured.
 *
 * On success the connection status is set to "verified" and
 * last_validated_at is stamped. On failure the status is set to "error".
 * Status is never set from user input — mirroring the Generation Engine
 * settings behavior.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

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

		Connection_Adapters::load( (string) $record['provider_type'] );

		$llm_backends = [ 'openai_compatible', 'openai', 'anthropic', 'dual' ];
		if ( in_array( $record['provider_type'], $llm_backends, true ) ) {
			return self::test_llm( $connection_id, $record );
		}

		if ( 'comfyui' === $record['provider_type'] && '' !== $record['endpoint_url'] && Comfy_Cloud_MCP::ENDPOINT !== untrailingslashit( $record['endpoint_url'] ) ) {
			return self::test_local_comfyui( $connection_id, $record );
		}

		if ( 'fal' === $record['provider_type'] ) {
			return self::test_fal( $connection_id );
		}
		if ( 'elevenlabs' === $record['provider_type'] ) {
			return self::test_elevenlabs( $connection_id );
		}
		if ( 'suno' === $record['provider_type'] ) {
			return self::test_suno( $connection_id );
		}
		if ( 'videodraft' === $record['provider_type'] ) {
			return self::test_videodraft( $connection_id );
		}
		if ( 'descript' === $record['provider_type'] ) {
			return self::test_descript( $connection_id, $record );
		}
		if ( 'openrouter' === $record['provider_type'] ) {
			return self::test_openrouter( $connection_id );
		}

		$has_key = '' !== trim( (string) $record['credential_reference'] );
		return self::record_result( $connection_id, $has_key, $has_key ? 'Comfy Cloud MCP credentials configured.' : 'Comfy Cloud MCP API key is not configured.', [] );
	}

	/** Test a fal Streamable HTTP MCP connection and required generation tools. */
	private static function test_fal( int $connection_id ): array {
		$tools = Fal_MCP::available_tools( $connection_id );
		if ( is_wp_error( $tools ) ) {
			return self::record_result( $connection_id, false, $tools->get_error_message(), [] );
		}

		$missing = array_values( array_diff( Fal_MCP::GENERATION_TOOLS, $tools ) );
		$success = empty( $missing );
		$message = $success
			? sprintf( 'Connected to fal MCP; %d tools available.', count( $tools ) )
			: sprintf( 'fal MCP is reachable but does not expose required tools: %s.', implode( ', ', $missing ) );
		$health = [ 'tools' => $tools ];
		if ( $success ) {
			$provisioned = Fal_Catalog::provision( $connection_id );
			if ( is_wp_error( $provisioned ) ) {
				$message .= ' Template provisioning needs attention: ' . $provisioned->get_error_message();
				$health['template_provisioning_error'] = $provisioned->get_error_message();
			} else {
				$count = count( (array) ( $provisioned['template_ids'] ?? [] ) );
				$message .= sprintf( ' %d World Graph Studio Template(s) synchronized from fal MCP.', $count );
				$health['template_ids'] = $provisioned['template_ids'] ?? [];
			}
		}

		return self::record_result( $connection_id, $success, $message, $health );
	}

	/** Test ElevenLabs authentication, catalog access, and Template provisioning. */
	private static function test_elevenlabs( int $connection_id ): array {
		$catalog = ElevenLabs_API::catalog( $connection_id );
		if ( is_wp_error( $catalog ) ) {
			return self::record_result( $connection_id, false, $catalog->get_error_message(), [] );
		}
		$model_count = count( (array) ( $catalog['text_to_speech_models'] ?? [] ) );
		$voice_count = count( (array) ( $catalog['voices'] ?? [] ) );
		if ( 0 === $model_count || 0 === $voice_count ) {
			return self::record_result( $connection_id, false, 'ElevenLabs returned no usable text-to-speech models or voices.', [ 'model_count' => $model_count, 'voice_count' => $voice_count ] );
		}

		$provisioned = ElevenLabs_Catalog::provision( $connection_id );
		if ( is_wp_error( $provisioned ) ) {
			return self::record_result( $connection_id, false, $provisioned->get_error_message(), [ 'model_count' => $model_count, 'voice_count' => $voice_count ] );
		}
		$template_count = count( (array) ( $provisioned['template_ids'] ?? [] ) );
		return self::record_result(
			$connection_id,
			true,
			sprintf( 'Connected to ElevenLabs; %d voice(s), %d text-to-speech model(s), and %d endpoint Template(s) available.', $voice_count, $model_count, $template_count ),
			[ 'model_count' => $model_count, 'voice_count' => $voice_count, 'template_ids' => $provisioned['template_ids'] ?? [] ]
		);
	}

	/** Test both services represented by a combined Suno Connection. */
	private static function test_suno( int $connection_id ): array {
		$credits = Suno_API::credits( $connection_id );
		if ( is_wp_error( $credits ) ) {
			return self::record_result( $connection_id, false, $credits->get_error_message(), [] );
		}

		$tools = Suno_MCP::available_tools( $connection_id );
		if ( is_wp_error( $tools ) ) {
			return self::record_result( $connection_id, false, $tools->get_error_message(), [ 'credits' => $credits ] );
		}

		$missing = array_values( array_diff( Suno_MCP::REQUIRED_TOOLS, $tools ) );
		if ( ! empty( $missing ) ) {
			return self::record_result(
				$connection_id,
				false,
				sprintf( 'Suno MCP is reachable but does not expose required tools: %s.', implode( ', ', $missing ) ),
				[ 'credits' => $credits, 'tools' => $tools ]
			);
		}

		$provisioned = Suno_Catalog::provision( $connection_id );
		if ( is_wp_error( $provisioned ) ) {
			return self::record_result( $connection_id, false, $provisioned->get_error_message(), [ 'credits' => $credits, 'tools' => $tools ] );
		}

		$template_ids = (array) ( $provisioned['template_ids'] ?? [] );
		return self::record_result(
			$connection_id,
			true,
			sprintf( 'Connected to SunoAPI.org and AceData Cloud Suno MCP; %d MCP tools and %d transport-specific Templates are available.', count( $tools ), count( $template_ids ) ),
			[ 'credits' => $credits, 'tools' => $tools, 'template_ids' => $template_ids ]
		);
	}

	/** Test a Descript REST connection by listing one project. */
	private static function test_descript( int $connection_id, array $record ): array {
		$result = Descript_API::list_projects( $connection_id, [ 'limit' => 1 ] );
		if ( is_wp_error( $result ) ) {
			return self::record_result( $connection_id, false, $result->get_error_message(), [] );
		}

		$count = is_array( $result['projects'] ?? null ) ? count( $result['projects'] ) : 0;
		return self::record_result( $connection_id, true, sprintf( 'Connected to Descript (%d project(s) visible in this drive).', $count ), [ 'projects' => $result['projects'] ?? [] ] );
	}

	/** Test OpenRouter authentication and video model discovery. */
	private static function test_openrouter( int $connection_id ): array {
		$models = OpenRouter_API::video_models( $connection_id );
		if ( is_wp_error( $models ) ) {
			return self::record_result( $connection_id, false, $models->get_error_message(), [] );
		}

		return self::record_result(
			$connection_id,
			true,
			sprintf( 'Connected to OpenRouter; %d video generation model(s) available.', count( $models ) ),
			[ 'model_count' => count( $models ) ]
		);
	}

	/** Test VideoDraft generation, project-sync tools, and Template provisioning. */
	private static function test_videodraft( int $connection_id ): array {		$tools = VideoDraft_API::available_tools( $connection_id );
		if ( is_wp_error( $tools ) ) {
			return self::record_result( $connection_id, false, $tools->get_error_message(), [] );
		}

		$missing = array_values( array_diff( VideoDraft_API::REQUIRED_TOOLS, $tools ) );
		if ( ! empty( $missing ) ) {
			return self::record_result(
				$connection_id,
				false,
				sprintf( 'VideoDraft is reachable but does not expose required tools: %s.', implode( ', ', $missing ) ),
				[ 'tools' => $tools, 'missing_tools' => $missing ]
			);
		}

		$provisioned = VideoDraft_Catalog::provision( $connection_id );
		if ( is_wp_error( $provisioned ) ) {
			return self::record_result( $connection_id, false, $provisioned->get_error_message(), [ 'tools' => $tools ] );
		}

		$template_ids = (array) ( $provisioned['template_ids'] ?? [] );
		return self::record_result(
			$connection_id,
			true,
			sprintf( 'Connected to VideoDraft; %d tools and %d generation Templates are available.', count( $tools ), count( $template_ids ) ),
			[ 'tools' => $tools, 'template_ids' => $template_ids ]
		);
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

		$result = ( new \WorldGraph\AI\AI_LLM_Client() )->test_connection( $configuration );
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
		do_action( 'worldgraph_conn_tested', $connection_id, $success, $message, $health );

		return [
			'success' => $success,
			'status'  => $status,
			'message' => $message,
			'health'  => $health,
		];
	}
}
