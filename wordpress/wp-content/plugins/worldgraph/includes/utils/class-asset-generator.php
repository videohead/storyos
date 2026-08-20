<?php
/**
 * Generate asset tools.
 *
 * Produces a quick initial image for any World Graph Studio story element using
 * text-to-image, stores it in the WordPress media library, and links it back to
 * the originating post (featured image, asset gallery, and an Asset record).
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

use WorldGraph\AI\AI_Image_Client;
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
	const GALLERY_META = '_worldgraph_asset_gallery_ids';

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

	/** Accepted generated-audio mime types, keyed by file extension. */
	const AUDIO_MIME_TYPES = [
		'mp3'  => 'audio/mpeg',
		'wav'  => 'audio/wav',
		'm4a'  => 'audio/mp4',
		'aac'  => 'audio/aac',
		'ogg'  => 'audio/ogg',
		'flac' => 'audio/flac',
	];

	/** Maximum accepted generated-audio size (50MB). */
	const MAX_AUDIO_BYTES = 52428800;

	/**
	 * Meta key on an attachment/asset pointing at the source story element.
	 */
	const SOURCE_META = '_worldgraph_generated_from';

	/**
	 * Map of source CPT to the Asset relationship field it populates.
	 *
	 * @var array<string, string>
	 */
	const ASSET_RELATIONSHIP_FIELDS = [
		'worldgraph_character'        => 'character',
		'worldgraph_location'         => 'location',
		'worldgraph_scene'            => 'scene',
		'worldgraph_board'       => 'storyboard',
	];

	/**
	 * CPTs that can have an image generated for them.
	 *
	 * Templates and provider connections are configuration, not story elements.
	 *
	 * @return array<int, string>
	 */
	public static function supported_post_types(): array {
		$cpts = array_keys( worldgraph_get_all_cpts() );

		return array_values( array_diff( $cpts, [ 'worldgraph_template', 'worldgraph_conn' ] ) );
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

		$labels = worldgraph_get_all_cpts();
		$label  = $labels[ $post->post_type ] ?? __( 'Story element', 'worldgraph' );

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

		$parts[] = __( 'Cinematic concept art, single subject, coherent lighting, high detail, no text or watermarks.', 'worldgraph' );

		$prompt = implode( '. ', array_filter( $parts ) );

		/**
		 * Filter the generated text-to-image prompt for a story element.
		 *
		 * @param string   $prompt  Generated prompt.
		 * @param \WP_Post $post    Source post.
		 */
		return (string) apply_filters( 'worldgraph_generate_asset_prompt', $prompt, $post );
	}

	/**
	 * Queue an MCP image generation job for a story element.
	 *
	 * @param int   $post_id Source post ID.
	 * @param array $args Optional prompt, set_featured, and create_asset settings.
	 * @return array|WP_Error
	 */
	public static function queue_for_post( int $post_id, array $args = [] ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::supports( $post_id ) ) {
			return new WP_Error( 'worldgraph_asset_invalid_post', __( 'That post cannot have a World Graph Studio asset generated for it.', 'worldgraph' ), [ 'status' => 404 ] );
		}

		$args   = wp_parse_args( $args, [ 'prompt' => '', 'set_featured' => true, 'create_asset' => true, 'template_id' => 0 ] );
		$prompt = trim( wp_strip_all_tags( (string) $args['prompt'] ) );
		$prompt = '' !== $prompt ? $prompt : self::build_prompt( $post_id );
		$profile = self::project_media_profile( $post_id );

		$template_id = absint( $args['template_id'] );
		if ( ! $template_id || ! self::is_active_template( $template_id ) ) {
			return new WP_Error( 'worldgraph_asset_invalid_template', __( 'That Template is not available to generate from.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		$connection_id = absint( get_post_meta( $template_id, 'connection_id', true ) );
		$connection = Connection_Repository::get( $connection_id );
		$template_provider = sanitize_key( (string) get_post_meta( $template_id, 'provider_type', true ) );
		if ( ! $connection || '' === $template_provider || 'disabled' === $connection['status'] || $template_provider !== $connection['provider_type'] ) {
			return new WP_Error( 'worldgraph_asset_invalid_connection', __( 'That Template and Connection must use the same provider.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		$provider = $connection['provider_type'];
		Connection_Adapters::load( (string) $provider );
		$provider_template_id = sanitize_text_field( (string) ( get_post_meta( $template_id, 'provider_template_id', true ) ?: get_post_meta( $template_id, 'comfy_template_id', true ) ) );
		if ( 'fal' === $provider && '' === $provider_template_id ) {
			$provider_template_id = sanitize_text_field( (string) ( $connection['model'] ?? '' ) );
		}
		$use_local_template = false;
		if ( ! in_array( $provider, [ 'comfyui', 'fal' ], true ) ) {
			return new WP_Error( 'worldgraph_asset_provider_unsupported', __( 'This provider has no World Graph Studio asset generation adapter yet.', 'worldgraph' ), [ 'status' => 501 ] );
		}
		if ( '' === $provider_template_id ) {
			if ( 'fal' === $provider || 'local' !== $connection['environment'] ) {
				return new WP_Error( 'worldgraph_asset_missing_provider_template', __( 'That Template has no provider MCP Template selected.', 'worldgraph' ), [ 'status' => 400 ] );
			}

			$use_local_template = true;
		}

		if ( 'comfyui' === $provider && 'local' === $connection['environment'] && $use_local_template && ! Local_ComfyUI::is_configured() ) {
			return new WP_Error( 'worldgraph_local_comfyui_unconfigured', __( 'That Template has no provider MCP Template selected and local ComfyUI API is not configured.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		if ( 'comfyui' === $provider && 'local' === $connection['environment'] && ! $use_local_template && ! Comfy_Cloud_MCP::is_configured( $connection_id ) ) {
			return new WP_Error( 'worldgraph_local_comfyui_unconfigured', __( 'The Template Connection has no configured local ComfyUI MCP endpoint.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		if ( 'comfyui' === $provider && 'local' !== $connection['environment'] && ! Comfy_Cloud_MCP::is_configured( $connection_id ) ) {
			return new WP_Error( 'worldgraph_comfy_mcp_unconfigured', __( 'The Template Connection has no configured Comfy Cloud API key.', 'worldgraph' ), [ 'status' => 400 ] );
		}

		if ( 'fal' === $provider && '' === trim( (string) $connection['credential_reference'] ) ) {
			return new WP_Error( 'worldgraph_fal_unconfigured', __( 'The Template Connection has no fal API key or credential reference.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		if ( 'fal' === $provider && ! Fal_MCP::endpoint_is_allowed( $connection, $provider_template_id ) ) {
			return new WP_Error( 'worldgraph_fal_endpoint_not_allowed', __( 'That fal model endpoint is not allowed by the Template Connection.', 'worldgraph' ), [ 'status' => 400 ] );
		}

		if ( $use_local_template ) {
			$requirements = self::ensure_local_template_requirements( $template_id, $connection_id );
			if ( is_wp_error( $requirements ) ) {
				return $requirements;
			}
		}

		$bound_inputs = [];
		if ( $template_id ) {
			$missing = Template_Bindings::missing_required( $template_id, $post_id );
			if ( ! empty( $missing ) ) {
				return new WP_Error(
					'worldgraph_asset_missing_template_input',
					sprintf(
						/* translators: %s: comma-separated missing input slot names. */
						__( 'That Template needs %s, which could not be found on this story element.', 'worldgraph' ),
						implode( ', ', $missing )
					),
					[ 'status' => 400 ]
				);
			}

			$bound_inputs = Template_Bindings::resolve( $template_id, $post_id );
		}

		$job_id = wp_insert_post( [
			'post_type'   => 'worldgraph_gen',
			'post_title'  => sprintf( __( 'Image generation: %s', 'worldgraph' ), $post->post_title ),
			'post_status' => 'draft',
			'post_parent' => $post_id,
		], true );

		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		// A user-selected Template wins; otherwise keep the legacy per-CPT
		// workflow name so existing jobs without a Template keep working.
		$template = $use_local_template ? (string) $template_id : $provider_template_id;
		$adapter  = 'fal' === $provider ? 'fal_mcp' : ( $use_local_template ? 'local_comfyui' : 'comfy_mcp' );
		$params   = 'fal' === $provider ? self::fal_template_input( $template_id ) : $profile;
		update_post_meta( $job_id, '_worldgraph_gen_type', 'image' );
		update_post_meta( $job_id, '_worldgraph_gen_prompt', $prompt );
		update_post_meta( $job_id, '_worldgraph_gen_params', $params );
		if ( ! empty( $bound_inputs ) ) {
			update_post_meta( $job_id, '_worldgraph_gen_inputs', $bound_inputs );
		}
		update_post_meta( $job_id, '_worldgraph_gen_workflow', $template );
		update_post_meta( $job_id, '_worldgraph_gen_adapter', $adapter );
		update_post_meta( $job_id, '_worldgraph_gen_template_id', $template_id );
		update_post_meta( $job_id, '_worldgraph_gen_provider_type', $provider );
		update_post_meta( $job_id, '_worldgraph_gen_connection_id', $connection_id );
		update_post_meta( $job_id, '_worldgraph_gen_source_post_id', $post_id );
		update_post_meta( $job_id, '_worldgraph_gen_set_featured', rest_sanitize_boolean( $args['set_featured'] ) );
		update_post_meta( $job_id, '_worldgraph_gen_create_asset', rest_sanitize_boolean( $args['create_asset'] ) );
		update_post_meta( $job_id, '_worldgraph_gen_status', 'queued' );
		update_post_meta( $job_id, '_worldgraph_gen_created', current_time( 'mysql' ) );
		Generation_Batch::schedule();

		return [
			'generation_id' => (int) $job_id,
			'post_id'       => $post_id,
			'prompt'        => $prompt,
			'status'        => 'queued',
		];
	}

	/**
	 * Read fal model inputs from a Template's provider-neutral configuration.
	 *
	 * Supported shapes are {"input": {...}} (preferred),
	 * {"parameters": {...}}, or a flat object for simple configurations.
	 */
	private static function fal_template_input( int $template_id ): array {
		$decoded = json_decode( (string) get_post_meta( $template_id, 'configuration_json', true ), true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		if ( isset( $decoded['input'] ) && is_array( $decoded['input'] ) ) {
			return $decoded['input'];
		}
		if ( isset( $decoded['parameters'] ) && is_array( $decoded['parameters'] ) ) {
			return $decoded['parameters'];
		}

		return $decoded;
	}

	/**
	 * Validate local ComfyUI Template requirements before queueing. When MCP
	 * download support is available, attempt to fetch missing checkpoint files
	 * and re-validate once.
	 *
	 * @param int $template_id   Template post ID.
	 * @param int $connection_id Connection post ID.
	 * @return true|WP_Error
	 */
	private static function ensure_local_template_requirements( int $template_id, int $connection_id ) {
		$report = Comfy_Manifest::validate( $template_id );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		if ( ! empty( $report['ok'] ) ) {
			return true;
		}

		if ( ! empty( $report['missing_models'] ) && Comfy_Cloud_MCP::supports_tool( 'download_models', $connection_id ) ) {
			$download = Comfy_Manifest::request_downloads( $template_id );
			if ( ! is_wp_error( $download ) ) {
				Comfy_Manifest::flush_catalog();
				$report = Comfy_Manifest::validate( $template_id );
				if ( is_wp_error( $report ) ) {
					return $report;
				}
				if ( ! empty( $report['ok'] ) ) {
					return true;
				}
			}
		}

		$missing = [];
		foreach ( (array) ( $report['missing_models'] ?? [] ) as $model ) {
			if ( ! empty( $model['filename'] ) ) {
				$missing[] = (string) $model['filename'];
			}
		}

		return new WP_Error(
			'worldgraph_local_comfyui_requirements_missing',
			empty( $missing )
				? __( 'ComfyUI is missing one or more Template requirements. Open the Template requirements panel and install missing models before generating.', 'worldgraph' )
				: sprintf(
					/* translators: %s: comma-separated missing model filenames. */
					__( 'ComfyUI is missing required model files: %s. Use the Template requirements panel to install them, then try again.', 'worldgraph' ),
					implode( ', ', $missing )
				),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Resolve the media profile from the containing project.
	 *
	 * @param int $post_id Source story element ID.
	 * @return array<string, int|float|string>
	 */
	public static function project_media_profile( int $post_id ): array {
		$project_id = 0;
		foreach ( get_relationships( $post_id, get_post_type( $post_id ), 'incoming' ) as $relationship ) {
			if ( 'worldgraph_project' === ( $relationship['from_type'] ?? '' ) && 'contains' === ( $relationship['type'] ?? '' ) ) {
				$project_id = absint( $relationship['from_id'] ?? 0 );
				break;
			}
		}

		$profile = [
			'width'        => 1024,
			'height'       => 1024,
			'aspect_ratio' => '1:1',
			'frame_rate'   => 24,
		];

		if ( $project_id ) {
			$profile['width']        = max( 1, absint( get_post_meta( $project_id, 'frame_width', true ) ?: $profile['width'] ) );
			$profile['height']       = max( 1, absint( get_post_meta( $project_id, 'frame_height', true ) ?: $profile['height'] ) );
			$profile['aspect_ratio'] = sanitize_text_field( (string) ( get_post_meta( $project_id, 'aspect_ratio', true ) ?: $profile['aspect_ratio'] ) );
			$profile['frame_rate']   = max( 0.001, (float) ( get_post_meta( $project_id, 'frame_rate', true ) ?: $profile['frame_rate'] ) );
		}

		$profile['size'] = $profile['width'] . 'x' . $profile['height'];

		return $profile;
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
	 * Whether a post ID is a published, active worldgraph_template.
	 *
	 * @param int $template_id Template post ID.
	 * @return bool
	 */
	private static function is_active_template( int $template_id ): bool {
		$template = get_post( $template_id );

		return $template instanceof \WP_Post
			&& 'worldgraph_template' === $template->post_type
			&& 'publish' === $template->post_status
			&& 'active' === get_post_meta( $template_id, 'status', true );
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
			return new WP_Error( 'worldgraph_asset_invalid_post', __( 'That post cannot have a World Graph Studio asset generated for it.', 'worldgraph' ), [ 'status' => 404 ] );
		}

		$args = wp_parse_args( $args, [
			'prompt'       => '',
			'model'        => '',
			'set_featured' => true,
			'create_asset' => true,
			'template_id'  => 0,
		] );

		$prompt = trim( wp_strip_all_tags( (string) $args['prompt'] ) );
		if ( '' === $prompt ) {
			$prompt = self::build_prompt( $post_id );
		}

		$client = new AI_Image_Client();
		$image  = $client->generate( $prompt, [
			'size'  => self::project_media_profile( $post_id )['size'],
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
		if ( rest_sanitize_boolean( $args['create_asset'] ) && 'worldgraph_asset' !== $post->post_type ) {
			$asset_id = self::create_asset_record( $post, $attachment_id, $prompt, $image );
		} elseif ( 'worldgraph_asset' === $post->post_type ) {
			self::store_asset_fields( $post->ID, $attachment_id, $prompt, $image );
		}

		/**
		 * Fires after a World Graph Studio asset image has been generated and attached.
		 *
		 * @param int      $attachment_id Generated attachment ID.
		 * @param \WP_Post $post          Source post.
		 * @param int      $asset_id      Created Asset post ID, or 0.
		 */
		do_action( 'worldgraph_asset_generated', $attachment_id, $post, $asset_id );

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
		$has_story_source = $post instanceof \WP_Post && self::supports( $post_id );
		if ( ! $has_story_source ) {
			$post = get_post( $job_id );
			if ( ! $post instanceof \WP_Post ) {
				return new WP_Error( 'worldgraph_gen_source_missing', __( 'The generation record no longer exists.', 'worldgraph' ) );
			}
		}

		$provider  = (string) get_post_meta( $job_id, '_worldgraph_gen_provider_type', true );
		$video_url = self::find_result_video_url( $result );
		$audio_url = self::find_result_audio_url( $result );
		$image_url = self::find_result_url( $result );

		// A video-only workflow reports its file through the same result keys
		// as an image, so do not try to decode the video as a still frame.
		if ( $image_url === $video_url ) {
			$image_url = '';
		}
		if ( $image_url === $audio_url ) {
			$image_url = '';
		}

		if ( '' === $image_url && '' === $video_url && '' === $audio_url && empty( $result['audio_data'] ) && empty( $result['audio_items'] ) ) {
			return new WP_Error( 'worldgraph_gen_output_missing', __( 'The generation provider completed the job but did not return downloadable media.', 'worldgraph' ) );
		}

		$attachment_id = 0;
		$media         = [];
		$generated_attachment_ids = [];
		if ( '' !== $image_url ) {
			$download = self::download_bytes( $image_url, $provider );
			if ( is_wp_error( $download ) ) {
				return $download;
			}

			$media = self::validate_image_bytes( $download );
			if ( is_wp_error( $media ) ) {
				return $media;
			}

			$attachment_id = self::sideload( $media, $post );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			$generated_attachment_ids[] = $attachment_id;

			if ( rest_sanitize_boolean( get_post_meta( $job_id, '_worldgraph_gen_set_featured', true ) ) && post_type_supports( $post->post_type, 'thumbnail' ) ) {
				set_post_thumbnail( $post->ID, $attachment_id );
			}
			self::add_to_gallery( $post->ID, $attachment_id );
		}

		// Import the source video alongside its still frame, or on its own for
		// a text-to-video Template that produces no separate frame.
		if ( '' !== $video_url ) {
			$video_download = self::download_bytes( $video_url, $provider );
			if ( is_wp_error( $video_download ) ) {
				return $video_download;
			}
			$video = self::validate_video_bytes( $video_download, $video_url );
			if ( is_wp_error( $video ) ) {
				return $video;
			}
			$video_attachment_id = self::sideload( $video, $post );
			if ( is_wp_error( $video_attachment_id ) ) {
				return $video_attachment_id;
			}
			$generated_attachment_ids[] = $video_attachment_id;
			self::add_to_gallery( $post->ID, $video_attachment_id );
			if ( ! $attachment_id ) {
				$attachment_id = $video_attachment_id;
				$media         = $video;
			}
		}

		// Synchronous providers may return audio bytes directly; URL-based audio
		// is downloaded through the same WordPress-owned media boundary.
		if ( ! empty( $result['audio_data'] ) || '' !== $audio_url ) {
			$audio_bytes = ! empty( $result['audio_data'] ) ? (string) $result['audio_data'] : self::download_bytes( $audio_url, $provider );
			if ( is_wp_error( $audio_bytes ) ) {
				return $audio_bytes;
			}
			$audio = self::validate_audio_bytes( $audio_bytes, (string) ( $result['audio_mime'] ?? '' ), $audio_url );
			if ( is_wp_error( $audio ) ) {
				return $audio;
			}
			$audio_attachment_id = self::sideload( $audio, $post );
			if ( is_wp_error( $audio_attachment_id ) ) {
				return $audio_attachment_id;
			}
			$generated_attachment_ids[] = $audio_attachment_id;
			self::add_to_gallery( $post->ID, $audio_attachment_id );
			if ( ! $attachment_id ) {
				$attachment_id = $audio_attachment_id;
				$media = $audio;
			}
		}
		foreach ( (array) ( $result['audio_items'] ?? [] ) as $audio_item ) {
			if ( ! is_array( $audio_item ) || empty( $audio_item['data'] ) ) {
				return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'ElevenLabs returned an unreadable voice preview.', 'worldgraph' ) );
			}
			$audio = self::validate_audio_bytes( (string) $audio_item['data'], (string) ( $audio_item['mime'] ?? '' ), '' );
			if ( is_wp_error( $audio ) ) {
				return $audio;
			}
			$audio_attachment_id = self::sideload( $audio, $post );
			if ( is_wp_error( $audio_attachment_id ) ) {
				return $audio_attachment_id;
			}
			if ( ! empty( $audio_item['generated_voice_id'] ) ) {
				update_post_meta( $audio_attachment_id, '_worldgraph_elevenlabs_generated_voice_id', sanitize_text_field( (string) $audio_item['generated_voice_id'] ) );
			}
			$generated_attachment_ids[] = $audio_attachment_id;
			self::add_to_gallery( $post->ID, $audio_attachment_id );
			if ( ! $attachment_id ) {
				$attachment_id = $audio_attachment_id;
				$media = $audio;
			}
		}

		// Providers such as fal can return multiple images. Every advertised
		// media URL must become a WordPress attachment before the job completes.
		$additional_urls = array_values( array_diff( self::find_result_urls( $result ), [ $image_url, $video_url, $audio_url, '' ] ) );
		foreach ( $additional_urls as $additional_url ) {
			$additional_download = self::download_bytes( $additional_url, $provider );
			if ( is_wp_error( $additional_download ) ) {
				return $additional_download;
			}
			$additional_media = self::is_video_url( $additional_url )
				? self::validate_video_bytes( $additional_download, $additional_url )
				: ( self::is_audio_url( $additional_url ) ? self::validate_audio_bytes( $additional_download, '', $additional_url ) : self::validate_image_bytes( $additional_download ) );
			if ( is_wp_error( $additional_media ) ) {
				return $additional_media;
			}
			$additional_attachment_id = self::sideload( $additional_media, $post );
			if ( is_wp_error( $additional_attachment_id ) ) {
				return $additional_attachment_id;
			}
			$generated_attachment_ids[] = $additional_attachment_id;
			self::add_to_gallery( $post->ID, $additional_attachment_id );
		}

		if ( ! $attachment_id ) {
			return new WP_Error( 'worldgraph_gen_output_missing', __( 'The generated media could not be imported into the media library.', 'worldgraph' ) );
		}

		$prompt   = (string) get_post_meta( $job_id, '_worldgraph_gen_prompt', true );
		$asset_id = 0;
		if ( ( ! $has_story_source || rest_sanitize_boolean( get_post_meta( $job_id, '_worldgraph_gen_create_asset', true ) ) ) && 'worldgraph_asset' !== $post->post_type ) {
			$asset_id = self::create_asset_record( $post, $attachment_id, $prompt, array_merge( $media, [ 'model' => $provider ?: 'generation-mcp', 'size' => (string) ( get_post_meta( $job_id, '_worldgraph_gen_params', true )['size'] ?? '' ), 'revised_prompt' => '', 'workflow' => (string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true ) ] ) );
		}

		if ( ! in_array( $attachment_id, $generated_attachment_ids, true ) ) {
			$generated_attachment_ids[] = $attachment_id;
		}

		return [ 'attachment_id' => $attachment_id, 'attachment_ids' => $generated_attachment_ids, 'asset_id' => $asset_id, 'url' => (string) wp_get_attachment_url( $attachment_id ) ];
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
			sprintf( 'worldgraph-%s-%d-%s.%s', $post->post_type, $post->ID, gmdate( 'YmdHis' ), $image['extension'] )
		);

		$checked = wp_check_filetype( $filename, null );
		if ( empty( $checked['type'] ) || $checked['type'] !== $image['mime'] ) {
			return new WP_Error( 'worldgraph_asset_filetype_blocked', __( 'This site does not allow uploads of the generated image type.', 'worldgraph' ), [ 'status' => 400 ] );
		}

		$upload = wp_upload_bits( $filename, null, $image['data'] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'worldgraph_asset_upload_failed', (string) $upload['error'], [ 'status' => 500 ] );
		}

		$title_format = __( 'Generated image for %s', 'worldgraph' );
		if ( 0 === strpos( $image['mime'], 'video/' ) ) {
			$title_format = __( 'Generated video for %s', 'worldgraph' );
		} elseif ( 0 === strpos( $image['mime'], 'audio/' ) ) {
			$title_format = __( 'Generated audio for %s', 'worldgraph' );
		}
		$title = sprintf(
			/* translators: %s: story element title. */
			$title_format,
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

	/** Find all media URLs advertised in a nested provider result. */
	private static function find_result_urls( array $result ): array {
		$urls = [];
		foreach ( [ 'image_url', 'output_url', 'url' ] as $key ) {
			if ( isset( $result[ $key ] ) && is_string( $result[ $key ] ) && filter_var( $result[ $key ], FILTER_VALIDATE_URL ) ) {
				$urls[] = $result[ $key ];
			}
		}
		foreach ( $result as $value ) {
			if ( is_array( $value ) ) {
				$urls = array_merge( $urls, self::find_result_urls( $value ) );
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/** Whether a result URL has a supported video extension. */
	private static function is_video_url( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$ext = strtolower( pathinfo( (string) ( $query['filename'] ?? '' ), PATHINFO_EXTENSION ) );
		}

		return in_array( $ext, array_keys( self::VIDEO_MIME_TYPES ), true );
	}

	/** Whether a result URL has a supported audio extension. */
	private static function is_audio_url( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $ext, array_keys( self::AUDIO_MIME_TYPES ), true );
	}

	/** Find the first supported audio URL in a nested provider response. */
	private static function find_result_audio_url( array $result ): string {
		foreach ( $result as $value ) {
			if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) && self::is_audio_url( $value ) ) {
				return $value;
			}
			if ( is_array( $value ) ) {
				$url = self::find_result_audio_url( $value );
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
			return new WP_Error( 'worldgraph_gen_download_failed', __( 'The completed output could not be downloaded from the generation provider.', 'worldgraph' ) );
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
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed image is empty or too large to store.', 'worldgraph' ) );
		}

		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], AI_Image_Client::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'The generation provider returned a file that is not a supported image.', 'worldgraph' ) );
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
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed video is empty or too large to store.', 'worldgraph' ) );
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$ext = strtolower( pathinfo( (string) ( $query['filename'] ?? '' ), PATHINFO_EXTENSION ) );
		}

		if ( ! isset( self::VIDEO_MIME_TYPES[ $ext ] ) ) {
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'Comfy MCP returned a video file type that is not supported.', 'worldgraph' ) );
		}

		return [
			'data'      => $bytes,
			'mime'      => self::VIDEO_MIME_TYPES[ $ext ],
			'extension' => $ext,
		];
	}

	/** Validate generated audio bytes and normalize their WordPress file type. */
	private static function validate_audio_bytes( string $bytes, string $mime, string $url ) {
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_AUDIO_BYTES ) {
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed audio is empty or too large to store.', 'worldgraph' ) );
		}
		$mime = strtolower( trim( explode( ';', $mime )[0] ) );
		$mime = 'audio/mp3' === $mime ? 'audio/mpeg' : $mime;
		$mime = 'audio/x-wav' === $mime ? 'audio/wav' : $mime;
		$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( '' === $ext && '' !== $mime ) {
			$ext = (string) array_search( $mime, self::AUDIO_MIME_TYPES, true );
		}
		if ( ! isset( self::AUDIO_MIME_TYPES[ $ext ] ) ) {
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'The generation provider returned an unsupported audio type.', 'worldgraph' ) );
		}
		return [ 'data' => $bytes, 'mime' => self::AUDIO_MIME_TYPES[ $ext ], 'extension' => $ext ];
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
		$mime = (string) ( $image['mime'] ?? '' );
		$kind = 0 === strpos( $mime, 'video/' ) ? __( 'Video', 'worldgraph' ) : ( 0 === strpos( $mime, 'audio/' ) ? __( 'Audio', 'worldgraph' ) : __( 'Image', 'worldgraph' ) );
		$title = sprintf(
			/* translators: 1: story element title, 2: generated media kind. */
			__( '%1$s — Generated %2$s', 'worldgraph' ),
			$post->post_title,
			$kind
		);

		$asset_id = wp_insert_post(
			[
				'post_type'   => 'worldgraph_asset',
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $asset_id ) ) {
			return 0;
		}

		$asset_id = (int) $asset_id;
		if ( 0 !== strpos( $mime, 'audio/' ) ) {
			set_post_thumbnail( $asset_id, $attachment_id );
		}
		worldgraph_update_field_value( $asset_id, 'asset_title', $title );
		update_post_meta( $asset_id, self::SOURCE_META, $post->ID );

		$asset_type = 0 === strpos( $mime, 'video/' ) ? 'video' : ( 0 === strpos( $mime, 'audio/' ) ? 'audio' : 'image' );
		$term       = term_exists( $asset_type, 'worldgraph_asset_type' );
		if ( ! $term ) {
			$term = wp_insert_term( ucfirst( $asset_type ), 'worldgraph_asset_type' );
		}
		if ( ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			wp_set_object_terms( $asset_id, [ $term_id ], 'worldgraph_asset_type', false );
		}

		if ( isset( self::ASSET_RELATIONSHIP_FIELDS[ $post->post_type ] ) ) {
			worldgraph_update_field_value( $asset_id, self::ASSET_RELATIONSHIP_FIELDS[ $post->post_type ], $post->ID );
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
		worldgraph_update_field_value( $asset_id, 'workflow_name', (string) ( $image['workflow'] ?? '' ) ?: 'text-to-image' );
		worldgraph_update_field_value( $asset_id, 'prompt', $prompt );
		worldgraph_update_field_value( $asset_id, 'model_name', (string) ( $image['model'] ?? '' ) );
		worldgraph_update_field_value( $asset_id, 'status', 'done' );
		worldgraph_update_field_value( $asset_id, 'storage_uri', (string) wp_get_attachment_url( $attachment_id ) );
		worldgraph_update_field_value( $asset_id, 'generation_parameters', (string) wp_json_encode( [
			'size'           => (string) ( $image['size'] ?? '' ),
			'mime'           => (string) ( $image['mime'] ?? '' ),
			'width'          => (int) ( $image['width'] ?? 0 ),
			'height'         => (int) ( $image['height'] ?? 0 ),
			'revised_prompt' => (string) ( $image['revised_prompt'] ?? '' ),
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
