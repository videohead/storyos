<?php
/**
 * AI Editor — Text-to-image client.
 *
 * Talks to an OpenAI-compatible `/images/generations` endpoint so StoryOS can
 * produce a quick initial image for any story element.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text-to-image client.
 */
class AI_Image_Client {

	/**
	 * Default image model.
	 */
	const DEFAULT_MODEL = 'gpt-image-1';

	/**
	 * Default image size.
	 */
	const DEFAULT_SIZE = '1024x1024';

	/**
	 * Sizes accepted by the endpoint.
	 *
	 * @var array<int, string>
	 */
	const ALLOWED_SIZES = [
		'256x256',
		'512x512',
		'768x768',
		'1024x1024',
		'1024x1536',
		'1536x1024',
		'1024x1792',
		'1792x1024',
	];

	/**
	 * Image mime types StoryOS will accept back from a provider.
	 *
	 * @var array<int, string>
	 */
	const ALLOWED_MIME_TYPES = [
		'image/png',
		'image/jpeg',
		'image/webp',
		'image/gif',
	];

	/**
	 * Largest generated image StoryOS will store, in bytes.
	 */
	const MAX_IMAGE_BYTES = 20971520;

	/**
	 * Whether a usable text-to-image endpoint is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->base_url();
	}

	/**
	 * Resolved, non-secret configuration for display in the UI.
	 *
	 * @return array{configured: bool, base_url: string, model: string, size: string}
	 */
	public function get_config(): array {
		return [
			'configured' => $this->is_configured(),
			'base_url'   => $this->base_url(),
			'model'      => $this->model(),
			'size'       => $this->size( '' ),
		];
	}

	/**
	 * Generate a single image from a text prompt.
	 *
	 * @param string $prompt  Text-to-image prompt.
	 * @param array  $options Optional overrides: model, size.
	 * @return array|WP_Error Array with data (raw bytes), mime, extension, model, size, revised_prompt.
	 */
	public function generate( string $prompt, array $options = [] ) {
		$prompt = trim( wp_strip_all_tags( $prompt ) );
		if ( '' === $prompt ) {
			return new WP_Error( 'storyos_image_prompt_missing', __( 'A text-to-image prompt is required.', 'storyos' ), [ 'status' => 400 ] );
		}

		$base_url = $this->base_url();
		if ( '' === $base_url ) {
			return new WP_Error(
				'storyos_image_endpoint_missing',
				__( 'Set an image generation base URL in StoryOS AI Settings before generating assets.', 'storyos' ),
				[ 'status' => 400 ]
			);
		}

		$model = isset( $options['model'] ) && '' !== trim( (string) $options['model'] )
			? sanitize_text_field( (string) $options['model'] )
			: $this->model();
		$size  = $this->size( (string) ( $options['size'] ?? '' ) );

		$headers = [ 'Content-Type' => 'application/json' ];
		$api_key = $this->api_key();
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_post( $base_url . '/images/generations', [
			'timeout' => 120,
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'model'           => $model,
				'prompt'          => $prompt,
				'n'               => 1,
				'size'            => $size,
				'response_format' => 'b64_json',
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'storyos_image_unreachable', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
				$message = (string) $body['error']['message'];
			} elseif ( 404 === $status ) {
				$message = __( 'The configured image endpoint does not support the OpenAI-compatible /images/generations API. Configure an image provider that supports this API in StoryOS AI Settings.', 'storyos' );
			} else {
				$message = __( 'The image provider rejected the request.', 'storyos' );
			}

			return new WP_Error( 'storyos_image_request_failed', $message, [ 'status' => 502 ] );
		}

		if ( ! is_array( $body ) || empty( $body['data'][0] ) || ! is_array( $body['data'][0] ) ) {
			return new WP_Error( 'storyos_image_invalid_response', __( 'The image provider returned an unexpected response.', 'storyos' ), [ 'status' => 502 ] );
		}

		$item  = $body['data'][0];
		$bytes = $this->extract_bytes( $item );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}

		$image = $this->validate_bytes( $bytes );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$image['model']          = $model;
		$image['size']           = $size;
		$image['revised_prompt'] = isset( $item['revised_prompt'] ) ? sanitize_text_field( (string) $item['revised_prompt'] ) : '';

		return $image;
	}

	/**
	 * Pull raw image bytes out of a provider response item.
	 *
	 * @param array $item Single `data` entry.
	 * @return string|WP_Error
	 */
	private function extract_bytes( array $item ) {
		if ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
			$decoded = base64_decode( $item['b64_json'], true );
			if ( false === $decoded || '' === $decoded ) {
				return new WP_Error( 'storyos_image_decode_failed', __( 'The generated image could not be decoded.', 'storyos' ), [ 'status' => 502 ] );
			}

			return $decoded;
		}

		if ( empty( $item['url'] ) || ! is_string( $item['url'] ) ) {
			return new WP_Error( 'storyos_image_missing_payload', __( 'The image provider did not return image data.', 'storyos' ), [ 'status' => 502 ] );
		}

		// wp_safe_remote_get blocks requests to private/internal hosts.
		$download = wp_safe_remote_get( $item['url'], [ 'timeout' => 60 ] );
		if ( is_wp_error( $download ) ) {
			return new WP_Error( 'storyos_image_download_failed', $download->get_error_message(), [ 'status' => 502 ] );
		}

		$code = wp_remote_retrieve_response_code( $download );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'storyos_image_download_failed', __( 'The generated image could not be downloaded.', 'storyos' ), [ 'status' => 502 ] );
		}

		return (string) wp_remote_retrieve_body( $download );
	}

	/**
	 * Confirm the payload really is a supported image.
	 *
	 * @param string $bytes Raw image bytes.
	 * @return array{data: string, mime: string, extension: string, width: int, height: int}|WP_Error
	 */
	private function validate_bytes( string $bytes ) {
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'storyos_image_invalid_payload', __( 'The generated image is empty or too large to store.', 'storyos' ), [ 'status' => 502 ] );
		}

		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'storyos_image_unsupported_type', __( 'The provider returned a file that is not a supported image.', 'storyos' ), [ 'status' => 502 ] );
		}

		$extensions = [
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		];

		return [
			'data'      => $bytes,
			'mime'      => (string) $info['mime'],
			'extension' => $extensions[ $info['mime'] ],
			'width'     => (int) ( $info[0] ?? 0 ),
			'height'    => (int) ( $info[1] ?? 0 ),
		];
	}

	/**
	 * Image endpoint base URL, falling back to the configured LLM base URL.
	 *
	 * @return string Base URL without a trailing slash, or an empty string.
	 */
	private function base_url(): string {
		$url = trim( (string) get_option( 'storyos_ai_image_url', '' ) );
		if ( '' === $url ) {
			$url = trim( (string) get_option( 'storyos_ai_url', '' ) );
		}

		$url = untrailingslashit( $url );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ? $url : '';
	}

	/**
	 * Image API key, falling back to the shared AI key.
	 *
	 * @return string
	 */
	private function api_key(): string {
		if ( defined( 'STORYOS_AI_IMAGE_API_KEY' ) && '' !== trim( (string) STORYOS_AI_IMAGE_API_KEY ) ) {
			return trim( (string) STORYOS_AI_IMAGE_API_KEY );
		}

		$key = trim( (string) get_option( 'storyos_ai_image_api_key', '' ) );

		return '' !== $key ? $key : trim( (string) get_option( 'storyos_ai_api_key', '' ) );
	}

	/**
	 * Configured image model.
	 *
	 * @return string
	 */
	private function model(): string {
		$model = trim( (string) get_option( 'storyos_ai_image_model', '' ) );

		return '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Resolve a requested size against the allow-list.
	 *
	 * @param string $requested Requested size.
	 * @return string
	 */
	private function size( string $requested ): string {
		$requested = trim( $requested );
		if ( in_array( $requested, self::ALLOWED_SIZES, true ) ) {
			return $requested;
		}

		$configured = trim( (string) get_option( 'storyos_ai_image_size', '' ) );

		return in_array( $configured, self::ALLOWED_SIZES, true ) ? $configured : self::DEFAULT_SIZE;
	}
}
