<?php
/**
 * AJAX handler for StoryOS Generation Engine.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for submitting generation jobs.
 */
class Ajax_Handler {

	/**
	 * Initialize the AJAX handler.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_storyos_generation_engine_submit', [ __CLASS__, 'submit_generation' ] );
	}

	/**
	 * Submit a generation request for the current post.
	 */
	public static function submit_generation(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You are not allowed to run this action.', 'storyos-generation-engine' ) ],
				403
			);
		}

		check_ajax_referer( 'storyos_generation_engine_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid post ID.', 'storyos-generation-engine' ) ],
				400
			);
		}

		$settings_class = __NAMESPACE__ . '\\Settings';
		$client_class = __NAMESPACE__ . '\\Generation_Client';
		$settings = class_exists( $settings_class ) ? $settings_class::get_settings() : [];
		$workflow = isset( $_POST['workflow'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow'] ) ) : '';
		$custom_params = [];
		if ( isset( $_POST['custom_params'] ) ) {
			$custom_params = json_decode( wp_unslash( $_POST['custom_params'] ), true );
			if ( ! is_array( $custom_params ) ) {
				$custom_params = [];
			}
		}

		if ( ! class_exists( $client_class ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Generation client is unavailable.', 'storyos-generation-engine' ) ],
				500
			);
		}

		$result = $client_class::send_generation_request( $post_id, $settings, $workflow, $custom_params );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( [
				'status_code' => $result['status_code'] ?? 200,
				'response'    => $result['response'] ?? [],
				'job_id'      => $result['job_id'] ?? '',
			] );
		}

		wp_send_json_error( [
			'message'     => __( 'StoryOS generation request failed.', 'storyos-generation-engine' ),
			'details'     => $result['error'] ?? __( 'Unknown error.', 'storyos-generation-engine' ),
			'status_code' => $result['status_code'] ?? 500,
			'response'    => $result['response'] ?? [],
		], 500 );
	}
}
