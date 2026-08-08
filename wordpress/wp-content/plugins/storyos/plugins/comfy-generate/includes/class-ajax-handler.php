<?php
/**
 * AJAX handler for ComfyUI Generate plugin.
 *
 * @package StoryOSComfyGenerate
 */

namespace StoryOSComfyGenerate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for sending posts to ComfyUI.
 */
class Ajax_Handler {

	/**
	 * Initialize the AJAX handler.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_comfy_generate_send_to_comfyui', [ __CLASS__, 'send_to_comfyui' ] );
	}

	/**
	 * Send a post to the ComfyUI endpoint.
	 */
	public static function send_to_comfyui(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to run this action.', 'storyos-comfy-generate' ) ],
				403
			);
		}

		check_ajax_referer( 'comfy_generate_send_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid post ID.', 'storyos-comfy-generate' ) ],
				400
			);
		}

		$settings = Settings::get_settings();
		$endpoint_url = trim( $settings['endpoint_url'] );

		if ( empty( $endpoint_url ) ) {
			wp_send_json_error(
				[ 'message' => __( 'ComfyUI endpoint URL is not configured.', 'storyos-comfy-generate' ) ],
				400
			);
		}

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		if ( ! empty( $settings['auth_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['auth_token'];
		} elseif ( ! empty( $settings['username'] ) || ! empty( $settings['password'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $settings['username'] . ':' . $settings['password'] );
		}

		$request_body = wp_json_encode( [
			'post_id' => $post_id,
		] );

		$response = wp_remote_post( $endpoint_url, [
			'timeout' => 30,
			'headers' => $headers,
			'body'    => $request_body,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => __( 'Request failed.', 'storyos-comfy-generate' ),
				'details' => $response->get_error_message(),
			], 500 );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			wp_send_json_error( [
				'message'  => __( 'ComfyUI endpoint returned an error.', 'storyos-comfy-generate' ),
				'status_code' => $status_code,
				'response' => $decoded ?: $raw_body,
			], 500 );
		}

		wp_send_json_success( [
			'status_code' => $status_code,
			'response'    => $decoded ?: $raw_body,
		] );
	}
}
