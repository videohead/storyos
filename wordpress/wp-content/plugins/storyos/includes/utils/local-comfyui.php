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
	 * Wizard slot marker for the single default local ComfyUI Template. One
	 * Connection can back many checkpoints/Templates; only one is auto-managed
	 * as the default for now.
	 */
	const TEMPLATE_SLOT = 'local_comfyui_default';

	/**
	 * Whether WordPress has a local ComfyUI URL. A workflow is always
	 * available: either a pasted custom one or the built-in default.
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
	 * @param int    $connection_id Optional parent storyos_connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	public static function run_template( string $template, string $prompt, array $parameters, int $connection_id = 0 ) {
		$workflow = self::workflow();
		if ( '' === self::endpoint() || ! is_array( $workflow ) ) {
			Generation_Log::add( 'error', 'local_comfyui', 'Local ComfyUI is not configured.', [], '', $connection_id );
			return new WP_Error( 'local_comfyui_unconfigured', __( 'Set a local ComfyUI URL in StoryOS AI Settings before generating an asset.', 'storyos' ) );
		}

		Generation_Log::add( 'info', 'local_comfyui', 'Submitting workflow to ' . self::url( 'prompt' ), [ 'prompt' => $prompt ], '', $connection_id );

		$response = wp_remote_post( self::url( 'prompt' ), [
			'timeout' => 60,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'prompt'    => self::replace_prompt( $workflow, $prompt ),
				'client_id' => wp_generate_uuid4(),
			] ),
		] );

		$result = self::decode_response( $response, 'submit the workflow', $connection_id );
		if ( is_wp_error( $result ) ) {
			Generation_Log::add( 'error', 'local_comfyui', $result->get_error_message(), [], '', $connection_id );
		} else {
			Generation_Log::add( 'info', 'local_comfyui', 'Workflow submitted.', $result, (string) ( $result['prompt_id'] ?? '' ), $connection_id );
		}

		return $result;
	}

	/**
	 * Retrieve a local ComfyUI job status and output URLs.
	 *
	 * @param string $job_id ComfyUI prompt ID.
	 * @param int    $connection_id Optional parent storyos_connection post ID, for log correlation.
	 * @return array|WP_Error
	 */
	public static function get_job_status( string $job_id, int $connection_id = 0 ) {
		$response = wp_remote_get( self::url( 'history/' . rawurlencode( $job_id ) ), [ 'timeout' => 60 ] );
		$result   = self::decode_response( $response, 'retrieve the job history', $connection_id );
		if ( is_wp_error( $result ) ) {
			Generation_Log::add( 'error', 'local_comfyui', $result->get_error_message(), [], $job_id, $connection_id );
			return $result;
		}

		$history = $result[ $job_id ] ?? [];
		if ( empty( $history ) || ! is_array( $history ) ) {
			Generation_Log::add( 'debug', 'local_comfyui', 'No history yet; job still running.', [], $job_id, $connection_id );
			return [ 'status' => 'submitted' ];
		}
		if ( ! empty( $history['status']['status_str'] ) && 'error' === $history['status']['status_str'] ) {
			Generation_Log::add( 'error', 'local_comfyui', 'ComfyUI reported that the workflow failed.', $history, $job_id, $connection_id );
			return [ 'status' => 'failed', 'error' => __( 'ComfyUI reported that the workflow failed.', 'storyos' ) ];
		}

		// ComfyUI's SaveVideo node writes its output under the same "images"
		// output key as SaveImage, so a workflow with both a still frame and
		// its source video (e.g. an LTX-Video Template) can list either one
		// first depending on node execution order. Keep them separate so
		// `image_url` reliably points at a real image, not a video file.
		$image_urls = [];
		$video_urls = [];
		foreach ( (array) ( $history['outputs'] ?? [] ) as $output ) {
			foreach ( (array) ( $output['images'] ?? [] ) as $image ) {
				if ( empty( $image['filename'] ) ) {
					continue;
				}

				$ext = strtolower( pathinfo( (string) $image['filename'], PATHINFO_EXTENSION ) );
				if ( in_array( $ext, [ 'mp4', 'webm', 'mov', 'avi' ], true ) ) {
					$video_urls[] = self::view_url( $image );
				} else {
					$image_urls[] = self::view_url( $image );
				}
			}
		}

		$images = array_merge( $image_urls, $video_urls );
		if ( empty( $images ) ) {
			Generation_Log::add( 'debug', 'local_comfyui', 'History present but no output images yet.', [], $job_id, $connection_id );
			return [ 'status' => 'submitted' ];
		}

		Generation_Log::add( 'info', 'local_comfyui', 'Job completed with ' . count( $images ) . ' image(s).', [], $job_id, $connection_id );
		return [ 'status' => 'completed', 'image_url' => $image_urls[0] ?? $images[0], 'images' => $images ];
	}

	/**
	 * Get the configured ComfyUI base URL: the `storyos_comfy_local_url`
	 * option, falling back to a local `storyos_connection` record's
	 * endpoint URL so the two configuration surfaces cannot drift apart.
	 *
	 * @return string
	 */
	private static function endpoint(): string {
		$url = untrailingslashit( esc_url_raw( (string) get_option( 'storyos_comfy_local_url', '' ) ) );
		if ( '' !== $url ) {
			return $url;
		}

		foreach ( Connection_Repository::get_all( [ 'provider_type' => 'comfyui', 'environment' => 'local' ] ) as $connection ) {
			if ( '' !== $connection['endpoint_url'] ) {
				return untrailingslashit( esc_url_raw( (string) $connection['endpoint_url'] ) );
			}
		}

		return '';
	}

	/**
	 * The single default local ComfyUI Template record, or null when none has
	 * been configured yet.
	 *
	 * @return \WP_Post|null
	 */
	private static function default_template(): ?\WP_Post {
		$posts = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'storyos_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => self::TEMPLATE_SLOT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		] );

		return $posts ? $posts[0] : null;
	}

	/**
	 * Decode the configured API workflow, or build a default text-to-image
	 * workflow from the configured checkpoint when none is pasted. Reads from
	 * the default Template record, falling back to the legacy global option
	 * only until it has been migrated.
	 *
	 * @return array|null
	 */
	private static function workflow() {
		$template = self::default_template();
		$raw      = $template ? (string) get_post_meta( $template->ID, 'workflow_json', true ) : (string) get_option( 'storyos_comfy_local_workflow', '' );
		$workflow = json_decode( $raw, true );

		return is_array( $workflow ) && ! empty( $workflow ) ? $workflow : self::default_workflow();
	}

	/**
	 * The configured checkpoint/model filename for the default workflow. Reads
	 * from the default Template record, falling back to the legacy global
	 * option only until it has been migrated.
	 *
	 * @return string
	 */
	private static function checkpoint(): string {
		$template = self::default_template();
		$raw      = $template ? (string) get_post_meta( $template->ID, 'checkpoint', true ) : (string) get_option( 'storyos_comfy_local_checkpoint', '' );
		$checkpoint = trim( $raw );

		return '' !== $checkpoint ? $checkpoint : 'ltx-2.3.safetensors';
	}

	/**
	 * A standard ComfyUI API-format text-to-image graph (checkpoint loader,
	 * positive/negative CLIP text encode, KSampler, VAE decode, save image)
	 * built from the configured checkpoint. Used when no custom workflow has
	 * been pasted in StoryOS AI Settings.
	 *
	 * @return array
	 */
	private static function default_workflow(): array {
		return [
			'3' => [
				'class_type' => 'KSampler',
				'inputs'     => [
					'seed'          => wp_rand( 0, PHP_INT_MAX >> 1 ),
					'steps'         => 20,
					'cfg'           => 7,
					'sampler_name'  => 'euler',
					'scheduler'     => 'normal',
					'denoise'       => 1,
					'model'         => [ '4', 0 ],
					'positive'      => [ '6', 0 ],
					'negative'      => [ '7', 0 ],
					'latent_image'  => [ '5', 0 ],
				],
			],
			'4' => [
				'class_type' => 'CheckpointLoaderSimple',
				'inputs'     => [ 'ckpt_name' => self::checkpoint() ],
			],
			'5' => [
				'class_type' => 'EmptyLatentImage',
				'inputs'     => [ 'width' => 1024, 'height' => 1024, 'batch_size' => 1 ],
			],
			'6' => [
				'class_type' => 'CLIPTextEncode',
				'inputs'     => [ 'text' => '{{prompt}}', 'clip' => [ '4', 1 ] ],
			],
			'7' => [
				'class_type' => 'CLIPTextEncode',
				'inputs'     => [ 'text' => '', 'clip' => [ '4', 1 ] ],
			],
			'8' => [
				'class_type' => 'VAEDecode',
				'inputs'     => [ 'samples' => [ '3', 0 ], 'vae' => [ '4', 2 ] ],
			],
			'9' => [
				'class_type' => 'SaveImage',
				'inputs'     => [ 'filename_prefix' => 'StoryOS', 'images' => [ '8', 0 ] ],
			],
		];
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
	private static function decode_response( $response, string $action, int $connection_id = 0 ) {
		if ( is_wp_error( $response ) ) {
			Generation_Log::add( 'error', 'local_comfyui', sprintf( 'Unreachable while trying to %s: %s', $action, $response->get_error_message() ), [], '', $connection_id );
			return new WP_Error( 'local_comfyui_unreachable', sprintf( __( 'Unable to %s through local ComfyUI: %s', 'storyos' ), $action, $response->get_error_message() ) );
		}
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
			Generation_Log::add( 'error', 'local_comfyui', sprintf( 'HTTP %d while trying to %s.', wp_remote_retrieve_response_code( $response ), $action ), [ 'body' => wp_remote_retrieve_body( $response ) ], '', $connection_id );
			return new WP_Error( 'local_comfyui_request_failed', sprintf( __( 'Local ComfyUI could not %s.', 'storyos' ), $action ) );
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $result ) ) {
			Generation_Log::add( 'error', 'local_comfyui', sprintf( 'Invalid response body while trying to %s.', $action ), [ 'body' => wp_remote_retrieve_body( $response ) ], '', $connection_id );
			return new WP_Error( 'local_comfyui_invalid_response', __( 'Local ComfyUI returned an invalid response.', 'storyos' ) );
		}

		return $result;
	}
}