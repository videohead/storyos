<?php
/**
 * Generation client for the StoryOS orchestration pipeline.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends generation requests to the StoryOS orchestrator, which then queues them
 * with the Celery worker pipeline.
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

		$endpoint_url = self::normalize_endpoint_url( $settings['orchestrator_url'] ?? $settings['endpoint_url'] ?? '' );
		if ( empty( $endpoint_url ) ) {
			return [
				'success' => false,
				'error'   => __( 'StoryOS orchestrator URL is not configured.', 'storyos-generation-engine' ),
			];
		}

		$payload = self::build_payload( $post_id, $settings, $workflow, $custom_params );

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		$response = wp_remote_post(
			$endpoint_url,
			[
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
				'payload' => $payload,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return [
				'success' => false,
				'error'   => __( 'StoryOS orchestrator returned an error.', 'storyos-generation-engine' ),
				'status_code' => $status_code,
				'response' => $decoded ?: $raw_body,
				'payload' => $payload,
			];
		}

		$job_id = '';
		if ( is_array( $decoded ) ) {
			$job_id = $decoded['job_id'] ?? $decoded['id'] ?? '';
		}

		do_action( 'storyos_generation_engine_job_submitted', $post_id, $payload, $decoded );

		return [
			'success'     => true,
			'status_code' => $status_code,
			'response'    => $decoded ?: $raw_body,
			'job_id'      => $job_id,
			'payload'     => $payload,
		];
	}

	/**
	 * Build a normalized payload for the orchestrator queue.
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
		if ( '' === $trimmed ) {
			return '';
		}

		if ( preg_match( '#/generate/?$#', $trimmed ) ) {
			return $trimmed;
		}

		return rtrim( $trimmed, '/' ) . '/generate';
	}
}
