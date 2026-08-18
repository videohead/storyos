<?php
/**
 * Generate asset tools.
 *
 * Produces a quick initial image for any StoryOS story element using
 * text-to-image, stores it in the WordPress media library, and links it back to
 * the originating post (featured image, asset gallery, and an Asset record).
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use StoryOS\AI\AI_Image_Client;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset generator.
 */
class Asset_Generator {

	/**
	 * Meta key holding supporting media attached to a story element.
	 */
	const GALLERY_META = '_storyos_asset_gallery_ids';

	/**
	 * Maximum accepted size for a generated video download, in bytes.
	 */
	const MAX_VIDEO_BYTES = 209715200; // 200MB.

	/**
	 * Accepted mime types for a generated video, keyed by file extension.
	 *
	 * @var array<string, string>
	 */
	const VIDEO_MIME_TYPES = [
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'mov'  => 'video/quicktime',
		'avi'  => 'video/x-msvideo',
	];

	/**
	 * Meta key on an attachment/asset pointing at the source story element.
	 */
	const SOURCE_META = '_storyos_generated_from';

	/**
	 * Map of source CPT to the Asset relationship field it populates.
	 *
	 * @var array<string, string>
	 */
	const ASSET_RELATIONSHIP_FIELDS = [
		'storyos_character'        => 'character',
		'storyos_location'         => 'location',
		'storyos_scene'            => 'scene',
		'storyos_storyboard_frame' => 'storyboard',
	];

	/**
	 * CPTs that can have an image generated for them.
	 *
	 * Templates and provider connections are configuration, not story elements.
	 *
	 * @return array<int, string>
	 */
	public static function supported_post_types(): array {
		$cpts = array_keys( storyos_get_all_cpts() );

		return array_values( array_diff( $cpts, [ 'storyos_template', 'storyos_connection' ] ) );
	}

	/**
	 * Whether a post can have an image generated for it.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function supports( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post && in_array( $post->post_type, self::supported_post_types(), true );
	}

	/**
	 * Build a text-to-image prompt from a story element.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function build_prompt( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$labels = storyos_get_all_cpts();
		$label  = $labels[ $post->post_type ] ?? __( 'Story element', 'storyos' );

		$parts = [ sprintf( '%s: %s', $label, $post->post_title ) ];

		$summary = trim( wp_strip_all_tags( $post->post_excerpt ) );
		if ( '' === $summary ) {
			$summary = trim( wp_strip_all_tags( $post->post_content ) );
		}
		if ( '' !== $summary ) {
			$parts[] = wp_trim_words( $summary, 90, '' );
		}

		foreach ( self::descriptive_meta( $post ) as $value ) {
			$parts[] = $value;
		}

		$parts[] = __( 'Cinematic concept art, single subject, coherent lighting, high detail, no text or watermarks.', 'storyos' );

		$prompt = implode( '. ', array_filter( $parts ) );

		/**
		 * Filter the generated text-to-image prompt for a story element.
		 *
		 * @param string   $prompt  Generated prompt.
		 * @param \WP_Post $post    Source post.
		 */
		return (string) apply_filters( 'storyos_generate_asset_prompt', $prompt, $post );
	}

	/**
	 * Queue an MCP image generation job for a story element.
	 *
	 * @param int   $post_id Source post ID.
	 * @param array $args Optional prompt, size, set_featured, and create_asset settings.
	 * @return array|WP_Error
	 */
	public static function queue_for_post( int $post_id, array $args = [] ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::supports( $post_id ) ) {
			return new WP_Error( 'storyos_asset_invalid_post', __( 'That post cannot have a StoryOS asset generated for it.', 'storyos' ), [ 'status' => 404 ] );
		}

		$provider = 'local_mcp' === get_option( 'storyos_comfy_connection_mode', 'none' ) ? 'local_comfyui' : 'comfy_cloud_mcp';
		if ( 'local_comfyui' === $provider && ! Local_ComfyUI::is_configured() ) {
			return new WP_Error( 'storyos_local_comfyui_unconfigured', __( 'Set a local ComfyUI URL in StoryOS AI Settings before generating an asset.', 'storyos' ), [ 'status' => 400 ] );
		}
		if ( 'comfy_cloud_mcp' === $provider && ! Comfy_Cloud_MCP::is_configured() ) {
			return new WP_Error( 'storyos_comfy_mcp_unconfigured', __( 'Set a Comfy Cloud MCP API key in StoryOS AI Settings before generating an asset.', 'storyos' ), [ 'status' => 400 ] );
		}

		$args   = wp_parse_args( $args, [ 'prompt' => '', 'size' => '', 'set_featured' => true, 'create_asset' => true ] );
		$prompt = trim( wp_strip_all_tags( (string) $args['prompt'] ) );
		$prompt = '' !== $prompt ? $prompt : self::build_prompt( $post_id );
		$size   = trim( (string) $args['size'] );
		$job_id = wp_insert_post( [
			'post_type'   => 'storyos_generation',
			'post_title'  => sprintf( __( 'Image generation: %s', 'storyos' ), $post->post_title ),
			'post_status' => 'draft',
			'post_parent' => $post_id,
		], true );

		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		$template = 'storyos_character' === $post->post_type ? 'character-sheet' : 'scene-image';
		update_post_meta( $job_id, '_storyos_generation_type', 'image' );
		update_post_meta( $job_id, '_storyos_generation_prompt', $prompt );
		update_post_meta( $job_id, '_storyos_generation_params', [ 'size' => $size ?: null ] );
		update_post_meta( $job_id, '_storyos_generation_workflow', $template );
		update_post_meta( $job_id, '_storyos_generation_provider_type', $provider );
		update_post_meta( $job_id, '_storyos_generation_connection_id', self::resolve_connection_id( $provider ) );
		update_post_meta( $job_id, '_storyos_generation_source_post_id', $post_id );
		update_post_meta( $job_id, '_storyos_generation_set_featured', rest_sanitize_boolean( $args['set_featured'] ) );
		update_post_meta( $job_id, '_storyos_generation_create_asset', rest_sanitize_boolean( $args['create_asset'] ) );
		update_post_meta( $job_id, '_storyos_generation_status', 'queued' );
		update_post_meta( $job_id, '_storyos_generation_created', current_time( 'mysql' ) );
		Generation_Batch::schedule();

		return [
			'generation_id' => (int) $job_id,
			'post_id'       => $post_id,
			'prompt'        => $prompt,
			'status'        => 'queued',
		];
	}

	/**
	 * Resolve the Connection record that owns a generation provider, so
	 * generation jobs and their log entries can be traced back to their
	 * parent Connection. Mirrors the connection lookup fallback used by
	 * Local_ComfyUI and the Setup Wizard's managed "generation" connection.
	 *
	 * @param string $provider 'local_comfyui' or 'comfy_cloud_mcp'.
	 * @return int Connection post ID, or 0 when none is configured.
	 */
	private static function resolve_connection_id( string $provider ): int {
		$environment = 'local_comfyui' === $provider ? 'local' : 'production';
		$connections = Connection_Repository::get_all( [ 'provider_type' => 'comfyui', 'environment' => $environment ] );

		return ! empty( $connections ) ? (int) $connections[0]['id'] : 0;
	}

	/**
	 * Generate an image for a story element and attach it.
	 *
	 * @param int   $post_id Source post ID.
	 * @param array $args    Optional: prompt, size, model, set_featured, create_asset.
	 * @return array|WP_Error
	 */
	public static function generate_for_post( int $post_id, array $args = [] ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::supports( $post_id ) ) {
			return new WP_Error( 'storyos_asset_invalid_post', __( 'That post cannot have a StoryOS asset generated for it.', 'storyos' ), [ 'status' => 404 ] );
		}

		$args = wp_parse_args( $args, [
			'prompt'       => '',
			'size'         => '',
			'model'        => '',
			'set_featured' => true,
			'create_asset' => true,
		] );

		$prompt = trim( wp_strip_all_tags( (string) $args['prompt'] ) );
		if ( '' === $prompt ) {
			$prompt = self::build_prompt( $post_id );
		}

		$client = new AI_Image_Client();
		$image  = $client->generate( $prompt, [
			'size'  => (string) $args['size'],
			'model' => (string) $args['model'],
		] );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$attachment_id = self::sideload( $image, $post );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$set_featured = rest_sanitize_boolean( $args['set_featured'] );
		if ( $set_featured && post_type_supports( $post->post_type, 'thumbnail' ) ) {
			set_post_thumbnail( $post->ID, $attachment_id );
		}

		self::add_to_gallery( $post->ID, $attachment_id );

		$asset_id = 0;
		if ( rest_sanitize_boolean( $args['create_asset'] ) && 'storyos_asset' !== $post->post_type ) {
			$asset_id = self::create_asset_record( $post, $attachment_id, $prompt, $image );
		} elseif ( 'storyos_asset' === $post->post_type ) {
			self::store_asset_fields( $post->ID, $attachment_id, $prompt, $image );
		}

		/**
		 * Fires after a StoryOS asset image has been generated and attached.
		 *
		 * @param int      $attachment_id Generated attachment ID.
		 * @param \WP_Post $post          Source post.
		 * @param int      $asset_id      Created Asset post ID, or 0.
		 */
		do_action( 'storyos_asset_generated', $attachment_id, $post, $asset_id );

		return [
			'post_id'       => $post->ID,
			'attachment_id' => $attachment_id,
			'asset_id'      => $asset_id,
			'url'           => (string) wp_get_attachment_url( $attachment_id ),
			'thumbnail_url' => (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			'featured'      => $set_featured && get_post_thumbnail_id( $post->ID ) === $attachment_id,
			'prompt'        => $prompt,
			'model'         => $image['model'],
			'size'          => $image['size'],
		];
	}

	/**
	 * Import a completed MCP image result and link it to the originating post.
	 *
	 * @param int   $job_id Generation job ID.
	 * @param array $result MCP job status result.
	 * @return array|WP_Error Imported asset data.
	 */
	public static function import_completed_job( int $job_id, array $result ) {
		$post_id = (int) get_post_field( 'post_parent', $job_id );
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::supports( $post_id ) ) {
			return new WP_Error( 'storyos_generation_source_missing', __( 'The source story element for this generation no longer exists.', 'storyos' ) );
		}

		$url = self::find_result_url( $result );
		if ( '' === $url ) {
			return new WP_Error( 'storyos_generation_output_missing', __( 'Comfy MCP completed the job but did not return a downloadable image URL.', 'storyos' ) );
		}

		$provider = (string) get_post_meta( $job_id, '_storyos_generation_provider_type', true );
		$download = self::download_bytes( $url, $provider );
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		$image = self::validate_image_bytes( $download );
		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$attachment_id = self::sideload( $image, $post );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( rest_sanitize_boolean( get_post_meta( $job_id, '_storyos_generation_set_featured', true ) ) && post_type_supports( $post->post_type, 'thumbnail' ) ) {
			set_post_thumbnail( $post->ID, $attachment_id );
		}
		self::add_to_gallery( $post->ID, $attachment_id );

		// Import the source video alongside its still frame, when the workflow
		// produced one (e.g. an LTX-Video Template with a frame-extraction node).
		$video_url = self::find_result_video_url( $result );
		if ( '' !== $video_url ) {
			$video_download = self::download_bytes( $video_url, $provider );
			if ( ! is_wp_error( $video_download ) ) {
				$video = self::validate_video_bytes( $video_download, $video_url );
				if ( ! is_wp_error( $video ) ) {
					$video_attachment_id = self::sideload( $video, $post );
					if ( ! is_wp_error( $video_attachment_id ) ) {
						self::add_to_gallery( $post->ID, $video_attachment_id );
					}
				}
			}
		}

		$prompt   = (string) get_post_meta( $job_id, '_storyos_generation_prompt', true );
		$asset_id = 0;
		if ( rest_sanitize_boolean( get_post_meta( $job_id, '_storyos_generation_create_asset', true ) ) && 'storyos_asset' !== $post->post_type ) {
			$asset_id = self::create_asset_record( $post, $attachment_id, $prompt, array_merge( $image, [ 'model' => 'comfy-mcp', 'size' => (string) ( get_post_meta( $job_id, '_storyos_generation_params', true )['size'] ?? '' ), 'revised_prompt' => '' ] ) );
		}

		return [ 'attachment_id' => $attachment_id, 'asset_id' => $asset_id, 'url' => (string) wp_get_attachment_url( $attachment_id ) ];
	}

	/**
	 * Store raw image bytes in the media library.
	 *
	 * @param array    $image Image payload from AI_Image_Client.
	 * @param \WP_Post $post  Source post.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function sideload( array $image, \WP_Post $post ) {
		$filename = sanitize_file_name(
			sprintf( 'storyos-%s-%d-%s.%s', $post->post_type, $post->ID, gmdate( 'YmdHis' ), $image['extension'] )
		);

		$checked = wp_check_filetype( $filename, null );
		if ( empty( $checked['type'] ) || $checked['type'] !== $image['mime'] ) {
			return new WP_Error( 'storyos_asset_filetype_blocked', __( 'This site does not allow uploads of the generated image type.', 'storyos' ), [ 'status' => 400 ] );
		}

		$upload = wp_upload_bits( $filename, null, $image['data'] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'storyos_asset_upload_failed', (string) $upload['error'], [ 'status' => 500 ] );
		}

		$title = sprintf(
			/* translators: %s: story element title. */
			0 === strpos( $image['mime'], 'video/' ) ? __( 'Generated video for %s', 'storyos' ) : __( 'Generated image for %s', 'storyos' ),
			$post->post_title
		);

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => $image['mime'],
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			],
			$upload['file'],
			$post->ID,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
		update_post_meta( $attachment_id, self::SOURCE_META, $post->ID );

		return (int) $attachment_id;
	}

	/**
	 * Find the first image URL in a Comfy MCP job result.
	 *
	 * @param array $result MCP result payload.
	 * @return string
	 */
	private static function find_result_url( array $result ): string {
		foreach ( [ 'image_url', 'output_url', 'url' ] as $key ) {
			if ( isset( $result[ $key ] ) && is_string( $result[ $key ] ) && filter_var( $result[ $key ], FILTER_VALIDATE_URL ) ) {
				return $result[ $key ];
			}
		}

		foreach ( $result as $value ) {
			if ( is_array( $value ) ) {
				$url = self::find_result_url( $value );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Find the first video URL in a Comfy MCP job result, so a workflow that
	 * returns both a still frame and its source video can import both.
	 *
	 * @param array $result MCP result payload.
	 * @return string
	 */
	private static function find_result_video_url( array $result ): string {
		$extensions = array_keys( self::VIDEO_MIME_TYPES );

		foreach ( $result as $value ) {
			if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$path = (string) wp_parse_url( $value, PHP_URL_PATH );
				$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( '' === $ext ) {
					// ComfyUI's /view URL keeps the real name in a query arg.
					$filename = (string) wp_parse_url( $value, PHP_URL_QUERY );
					parse_str( $filename, $query );
					$ext = strtolower( pathinfo( (string) ( $query['filename'] ?? '' ), PATHINFO_EXTENSION ) );
				}
				if ( in_array( $ext, $extensions, true ) ) {
					return $value;
				}
			} elseif ( is_array( $value ) ) {
				$url = self::find_result_video_url( $value );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Download bytes from a generation provider's output URL.
	 *
	 * @param string $url      Output URL.
	 * @param string $provider Generation provider type.
	 * @return string|WP_Error Raw bytes, or an error.
	 */
	private static function download_bytes( string $url, string $provider ) {
		$download = 'local_comfyui' === $provider ? wp_remote_get( $url, [ 'timeout' => 60 ] ) : wp_safe_remote_get( $url, [ 'timeout' => 60 ] );
		if ( is_wp_error( $download ) || wp_remote_retrieve_response_code( $download ) < 200 || wp_remote_retrieve_response_code( $download ) >= 300 ) {
			return new WP_Error( 'storyos_generation_download_failed', __( 'The completed output could not be downloaded from Comfy MCP.', 'storyos' ) );
		}

		return (string) wp_remote_retrieve_body( $download );
	}

	/**
	 * Validate generated image bytes for media-library import.
	 *
	 * @param string $bytes Raw image data.
	 * @return array|WP_Error
	 */
	private static function validate_image_bytes( string $bytes ) {
		if ( '' === $bytes || strlen( $bytes ) > AI_Image_Client::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'storyos_generation_invalid_payload', __( 'The completed image is empty or too large to store.', 'storyos' ) );
		}

		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], AI_Image_Client::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'storyos_generation_unsupported_type', __( 'Comfy MCP returned a file that is not a supported image.', 'storyos' ) );
		}

		$extensions = [ 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif' ];
		return [
			'data'      => $bytes,
			'mime'      => $info['mime'],
			'extension' => $extensions[ $info['mime'] ],
			'width'     => (int) $info[0],
			'height'    => (int) $info[1],
		];
	}

	/**
	 * Validate generated video bytes for media-library import. Video content
	 * can't be sniffed the way getimagesizefromstring() sniffs images, so the
	 * source URL's extension (already restricted to VIDEO_MIME_TYPES by
	 * find_result_video_url()) determines the mime type.
	 *
	 * @param string $bytes Raw video data.
	 * @param string $url   Source URL the bytes were downloaded from.
	 * @return array|WP_Error
	 */
	private static function validate_video_bytes( string $bytes, string $url ) {
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_VIDEO_BYTES ) {
			return new WP_Error( 'storyos_generation_invalid_payload', __( 'The completed video is empty or too large to store.', 'storyos' ) );
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$ext = strtolower( pathinfo( (string) ( $query['filename'] ?? '' ), PATHINFO_EXTENSION ) );
		}

		if ( ! isset( self::VIDEO_MIME_TYPES[ $ext ] ) ) {
			return new WP_Error( 'storyos_generation_unsupported_type', __( 'Comfy MCP returned a video file type that is not supported.', 'storyos' ) );
		}

		return [
			'data'      => $bytes,
			'mime'      => self::VIDEO_MIME_TYPES[ $ext ],
			'extension' => $ext,
		];
	}

	/**
	 * Add an attachment to a story element's supporting media gallery.
	 *
	 * @param int $post_id       Source post ID.
	 * @param int $attachment_id Attachment ID.
	 */
	private static function add_to_gallery( int $post_id, int $attachment_id ): void {
		$gallery_ids = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, self::GALLERY_META, true ) ) ) );
		if ( in_array( $attachment_id, $gallery_ids, true ) ) {
			return;
		}

		$gallery_ids[] = $attachment_id;
		update_post_meta( $post_id, self::GALLERY_META, $gallery_ids );
	}

	/**
	 * Create an Asset record describing the generated image.
	 *
	 * @param \WP_Post $post          Source post.
	 * @param int      $attachment_id Attachment ID.
	 * @param string   $prompt        Prompt used.
	 * @param array    $image         Image payload.
	 * @return int Asset post ID, or 0 on failure.
	 */
	private static function create_asset_record( \WP_Post $post, int $attachment_id, string $prompt, array $image ): int {
		$title = sprintf(
			/* translators: %s: story element title. */
			__( '%s — Generated Image', 'storyos' ),
			$post->post_title
		);

		$asset_id = wp_insert_post(
			[
				'post_type'   => 'storyos_asset',
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $asset_id ) ) {
			return 0;
		}

		$asset_id = (int) $asset_id;
		set_post_thumbnail( $asset_id, $attachment_id );
		update_post_meta( $asset_id, 'asset_title', $title );
		update_post_meta( $asset_id, self::SOURCE_META, $post->ID );

		if ( isset( self::ASSET_RELATIONSHIP_FIELDS[ $post->post_type ] ) ) {
			update_post_meta( $asset_id, self::ASSET_RELATIONSHIP_FIELDS[ $post->post_type ], $post->ID );
		}

		self::store_asset_fields( $asset_id, $attachment_id, $prompt, $image );

		return $asset_id;
	}

	/**
	 * Write generation provenance onto an Asset post.
	 *
	 * @param int    $asset_id      Asset post ID.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $prompt        Prompt used.
	 * @param array  $image         Image payload.
	 */
	private static function store_asset_fields( int $asset_id, int $attachment_id, string $prompt, array $image ): void {
		update_post_meta( $asset_id, 'workflow_name', 'text-to-image' );
		update_post_meta( $asset_id, 'prompt', $prompt );
		update_post_meta( $asset_id, 'model_name', $image['model'] );
		update_post_meta( $asset_id, 'status', 'done' );
		update_post_meta( $asset_id, 'storage_uri', (string) wp_get_attachment_url( $attachment_id ) );
		update_post_meta( $asset_id, 'generation_parameters', (string) wp_json_encode( [
			'size'           => $image['size'],
			'mime'           => $image['mime'],
			'width'          => $image['width'],
			'height'         => $image['height'],
			'revised_prompt' => $image['revised_prompt'],
		] ) );
	}

	/**
	 * Collect short descriptive meta values that improve the prompt.
	 *
	 * @param \WP_Post $post Source post.
	 * @return array<int, string>
	 */
	private static function descriptive_meta( \WP_Post $post ): array {
		$keys = [
			'description',
			'appearance',
			'physical_description',
			'visual_style',
			'setting',
			'mood',
			'time_of_day',
			'shot_type',
			'camera_angle',
		];

		$values = [];
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( ! is_string( $value ) ) {
				continue;
			}

			$value = trim( wp_strip_all_tags( $value ) );
			if ( '' !== $value ) {
				$values[] = wp_trim_words( $value, 40, '' );
			}
		}

		return $values;
	}
}
