<?php
/**
 * HTTP client for a local ComfyUI API server.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Local_ComfyUI {
	/**
	 * Whether WordPress has a local ComfyUI URL and API workflow.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== self::endpoint() && is_array( self::workflow() );
	}

	/**
	 * Submit a workflow to ComfyUI.
	 *
	 * @param string $template Unused compatibility parameter for cloud templates.
	 * @param string $prompt Text prompt.
	 * @param array  $parameters Unused generation parameters.
	 * @return array|WP_Error
	 */
	public static function run_template( string $template, string $prompt, array $parameters ) {
		$workflow = self::workflow();
		if ( '' === self::endpoint() || ! is_array( $workflow ) ) {
			return new WP_Error( 'local_comfyui_unconfigured', __( 'Set a local ComfyUI URL and paste an API-format workflow before generating an asset.', 'storyos' ) );
		}

		$response = wp_remote_post( self::url( 'prompt' ), [
			'timeout' => 60,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'prompt'    => self::replace_prompt( $workflow, $prompt ),
				'client_id' => wp_generate_uuid4(),
			] ),
		] );

		return self::decode_response( $response, 'submit the workflow' );
	}

	/**
	 * Retrieve a local ComfyUI job status and output URLs.
	 *
	 * @param string $job_id ComfyUI prompt ID.
	 * @return array|WP_Error
	 */
	public static function get_job_status( string $job_id ) {
		$response = wp_remote_get( self::url( 'history/' . rawurlencode( $job_id ) ), [ 'timeout' => 60 ] );
		$result   = self::decode_response( $response, 'retrieve the job history' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$history = $result[ $job_id ] ?? [];
		if ( empty( $history ) || ! is_array( $history ) ) {
			return [ 'status' => 'submitted' ];
		}
		if ( ! empty( $history['status']['status_str'] ) && 'error' === $history['status']['status_str'] ) {
			return [ 'status' => 'failed', 'error' => __( 'ComfyUI reported that the workflow failed.', 'storyos' ) ];
		}

		$images = [];
		foreach ( (array) ( $history['outputs'] ?? [] ) as $output ) {
			foreach ( (array) ( $output['images'] ?? [] ) as $image ) {
				if ( ! empty( $image['filename'] ) ) {
					$images[] = self::view_url( $image );
				}
			}
		}

		return empty( $images ) ? [ 'status' => 'submitted' ] : [ 'status' => 'completed', 'image_url' => $images[0], 'images' => $images ];
	}

	/**
	 * Get the configured ComfyUI base URL.
	 *
	 * @return string
	 */
	private static function endpoint(): string {
		return untrailingslashit( esc_url_raw( (string) get_option( 'storyos_comfy_local_url', '' ) ) );
	}

	/**
	 * Decode the configured API workflow.
	 *
	 * @return array|null
	 */
	private static function workflow() {
		$workflow = json_decode( (string) get_option( 'storyos_comfy_local_workflow', '' ), true );

		return is_array( $workflow ) && ! empty( $workflow ) ? $workflow : null;
	}

	/**
	 * Replace prompt placeholders in a ComfyUI API workflow.
	 *
	 * @param mixed  $value Workflow value.
	 * @param string $prompt Text prompt.
	 * @return mixed
	 */
	private static function replace_prompt( $value, string $prompt ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::replace_prompt( $item, $prompt );
			}
			return $value;
		}

		return is_string( $value ) ? str_replace( '{{prompt}}', $prompt, $value ) : $value;
	}

	/**
	 * Build a URL relative to the configured API endpoint.
	 *
	 * @param string $path Endpoint path.
	 * @return string
	 */
	private static function url( string $path ): string {
		return self::endpoint() . '/' . ltrim( $path, '/' );
	}

	/**
	 * Build a downloadable ComfyUI output image URL.
	 *
	 * @param array $image ComfyUI output descriptor.
	 * @return string
	 */
	private static function view_url( array $image ): string {
		return add_query_arg( [
			'filename'  => (string) $image['filename'],
			'subfolder' => (string) ( $image['subfolder'] ?? '' ),
			'type'      => (string) ( $image['type'] ?? 'output' ),
		], self::url( 'view' ) );
	}

	/**
	 * Validate and decode a ComfyUI HTTP response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $action Action for an error message.
	 * @return array|WP_Error
	 */
	private static function decode_response( $response, string $action ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'local_comfyui_unreachable', sprintf( __( 'Unable to %s through local ComfyUI: %s', 'storyos' ), $action, $response->get_error_message() ) );
		}
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
			return new WP_Error( 'local_comfyui_request_failed', sprintf( __( 'Local ComfyUI could not %s.', 'storyos' ), $action ) );
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $result ) ? $result : new WP_Error( 'local_comfyui_invalid_response', __( 'Local ComfyUI returned an invalid response.', 'storyos' ) );
	}
}