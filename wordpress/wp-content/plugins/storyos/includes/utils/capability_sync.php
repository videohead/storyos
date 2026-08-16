<?php
/**
 * Provider Capability Synchronization.
 *
 * Pulls provider descriptors from the StoryOS orchestrator
 * (GET /providers and GET /providers/discovery) and caches them in the
 * storyos_provider_capabilities option. The cache powers the connection
 * management UI (provider type list, model access validation) and the
 * "Sync Capabilities" action.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

/**
 * Capability synchronization.
 */
class Capability_Sync {

	/**
	 * Option name for the cached capability snapshot.
	 *
	 * @var string
	 */
	const OPTION = 'storyos_provider_capabilities';

	/**
	 * HTTP timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * Resolve the orchestrator base URL.
	 *
	 * @return string
	 */
	public static function orchestrator_url(): string {
		$url = defined( 'STORYOS_ORCHESTRATOR_URL' ) ? STORYOS_ORCHESTRATOR_URL : '';
		if ( '' === $url ) {
			$url = (string) get_option( 'storyos_orchestrator_url', 'http://localhost:8000' );
		}

		return untrailingslashit( $url );
	}

	/**
	 * Synchronize provider capabilities from the orchestrator.
	 *
	 * @return array Result: [ 'success' => bool, 'message' => string, 'providers' => array ].
	 */
	public static function sync(): array {
		$base = self::orchestrator_url();

		$providers_response = self::fetch( $base . '/providers' );
		if ( is_wp_error( $providers_response ) ) {
			return [
				'success'   => false,
				'message'   => 'Orchestrator unreachable: ' . $providers_response->get_error_message(),
				'providers' => [],
			];
		}

		$providers = isset( $providers_response['providers'] ) ? (array) $providers_response['providers'] : [];

		$discovery_response = self::fetch( $base . '/providers/discovery' );
		$discovery = [];
		if ( ! is_wp_error( $discovery_response ) && isset( $discovery_response['providers'] ) ) {
			$discovery = (array) $discovery_response['providers'];
		}

		$snapshot = [
			'synced_at' => gmdate( 'Y-m-d H:i:s' ),
			'orchestrator_url' => $base,
			'providers' => $providers,
			'discovery' => $discovery,
		];

		update_option( self::OPTION, $snapshot, false );

		return [
			'success'   => true,
			'message'   => sprintf( 'Synchronized %d provider(s) from %s.', count( $providers ), $base ),
			'providers' => $providers,
		];
	}

	/**
	 * Get the cached capability snapshot.
	 *
	 * @return array Snapshot with synced_at, orchestrator_url, providers, discovery.
	 */
	public static function get_cached(): array {
		$snapshot = get_option( self::OPTION, [] );
		if ( ! is_array( $snapshot ) ) {
			$snapshot = [];
		}

		return wp_parse_args(
			$snapshot,
			[
				'synced_at' => '',
				'orchestrator_url' => '',
				'providers' => [],
				'discovery' => [],
			]
		);
	}

	/**
	 * List known provider types from the cache (falls back to built-ins).
	 *
	 * @return array<int, string>
	 */
	public static function provider_types(): array {
		$cached = self::get_cached();
		$types = [];
		foreach ( (array) $cached['providers'] as $provider ) {
			if ( is_array( $provider ) && ! empty( $provider['provider_type'] ) ) {
				$types[] = $provider['provider_type'];
			}
		}

		if ( empty( $types ) ) {
			$types = [ 'comfyui', 'veo', 'nova_reel' ];
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Validate a model ID against the cached capability descriptors.
	 *
	 * @param string $provider_type Provider type slug.
	 * @param string $model_id      Model ID to check.
	 * @return bool True when the model is known to the provider, or when no
	 *              descriptor is cached (fail-open so connections keep working
	 *              offline).
	 */
	public static function model_is_known( string $provider_type, string $model_id ): bool {
		$cached = self::get_cached();
		$found = false;
		foreach ( (array) $cached['providers'] as $provider ) {
			if ( ! is_array( $provider ) || $provider_type !== ( $provider['provider_type'] ?? '' ) ) {
				continue;
			}

			$models = $provider['models'] ?? $provider['model_ids'] ?? [];
			if ( is_array( $models ) && in_array( $model_id, $models, true ) ) {
				$found = true;
				break;
			}
		}

		// Fail-open: no cached descriptor for this provider means we cannot
		// disprove the model, so allow it.
		return $found || empty( $cached['providers'] );
	}

	/**
	 * Perform a GET request and decode the JSON body.
	 *
	 * @param string $url Absolute URL.
	 * @return array|\WP_Error Decoded JSON body.
	 */
	private static function fetch( string $url ) {
		$response = wp_remote_get(
			$url,
			[
				'timeout' => self::TIMEOUT,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'storyos_capability_sync_http',
				sprintf( 'Orchestrator returned HTTP %d for %s.', $code, $url )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'storyos_capability_sync_decode', 'Orchestrator returned a non-JSON response.' );
		}

		return $body;
	}
}
