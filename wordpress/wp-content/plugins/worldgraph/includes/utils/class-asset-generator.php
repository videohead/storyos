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
	/** Content types captured from generated-media download responses. */
	private static $download_mime_types = [];

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
		'm4v'  => 'video/x-m4v',
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

	/** Write-ahead journal for a generation job's media-import side effects. */
	const IMPORT_JOURNAL_META = '_worldgraph_gen_import_journal';

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
		if ( ! in_array( $provider, [ 'comfyui', 'fal', 'videodraft' ], true ) ) {
			return new WP_Error( 'worldgraph_asset_provider_unsupported', __( 'This provider has no World Graph Studio asset generation adapter yet.', 'worldgraph' ), [ 'status' => 501 ] );
		}
		if ( '' === $provider_template_id ) {
			if ( in_array( $provider, [ 'fal', 'videodraft' ], true ) || 'local' !== $connection['environment'] ) {
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
		if ( 'videodraft' === $provider && '' === trim( (string) $connection['credential_reference'] ) ) {
			return new WP_Error( 'worldgraph_videodraft_unconfigured', __( 'The Template Connection has no VideoDraft token or credential reference.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		if ( 'videodraft' === $provider && ! in_array( $provider_template_id, VideoDraft_API::GENERATION_TOOLS, true ) ) {
			return new WP_Error( 'worldgraph_videodraft_tool_invalid', __( 'That Template does not select a supported VideoDraft generation tool.', 'worldgraph' ), [ 'status' => 400 ] );
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
		$adapter  = 'fal' === $provider ? 'fal_mcp' : ( 'videodraft' === $provider ? 'videodraft' : ( $use_local_template ? 'local_comfyui' : 'comfy_mcp' ) );
		$params   = 'fal' === $provider ? self::fal_template_input( $template_id ) : ( 'videodraft' === $provider ? [ 'aspect_ratio' => $profile['aspect_ratio'] ] : $profile );
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
		update_post_meta( $job_id, '_worldgraph_gen_requested_by', get_current_user_id() );
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
		$project_id = self::resolve_project_id( $post_id );

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
	 * Walk a story element's "contains" ancestry (e.g. Character -> World ->
	 * Project) to find its owning Project, since most elements are nested
	 * under an intermediate entity rather than owned by the Project directly.
	 *
	 * @param int $post_id Source story element ID.
	 * @return int Project post ID, or 0 when none is found.
	 */
	private static function resolve_project_id( int $post_id ): int {
		$seen         = [];
		$current_id   = $post_id;
		$current_type = get_post_type( $post_id );

		for ( $depth = 0; $depth < 6 && $current_id && $current_type; $depth++ ) {
			if ( isset( $seen[ $current_id ] ) ) {
				break;
			}
			$seen[ $current_id ] = true;

			$parent_id   = 0;
			$parent_type = '';
			foreach ( get_relationships( $current_id, $current_type, 'incoming' ) as $relationship ) {
				if ( 'contains' === ( $relationship['type'] ?? '' ) ) {
					$parent_id   = absint( $relationship['from_id'] ?? 0 );
					$parent_type = (string) ( $relationship['from_type'] ?? '' );
					break;
				}
			}

			if ( ! $parent_id ) {
				break;
			}
			if ( 'worldgraph_project' === $parent_type ) {
				return $parent_id;
			}

			$current_id   = $parent_id;
			$current_type = $parent_type;
		}

		return 0;
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

		return (int) ( Connection_Repository::get_default( 'comfyui', $environment ) ?? 0 );
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
		if ( ! self::recover_import_journal( $job_id ) ) {
			return new WP_Error( 'worldgraph_gen_journal_failed', __( 'WordPress could not clean up an interrupted media import.', 'worldgraph' ) );
		}

		$provider   = (string) get_post_meta( $job_id, '_worldgraph_gen_provider_type', true );
		$adapter    = (string) get_post_meta( $job_id, '_worldgraph_gen_adapter', true );
		$is_videodraft = 'videodraft' === $provider || 'videodraft' === $adapter;
		$typed_video_urls = self::find_typed_output_urls( $result, 'video' );
		$typed_audio_urls = self::find_typed_output_urls( $result, 'audio' );
		$typed_image_urls = self::find_typed_output_urls( $result, 'image' );
		$video_url  = (string) ( $typed_video_urls[0] ?? ( $is_videodraft ? '' : self::find_result_video_url( $result ) ) );
		$audio_urls = $is_videodraft ? $typed_audio_urls : array_values( array_unique( array_merge( $typed_audio_urls, self::find_result_audio_urls( $result ) ) ) );
		$audio_url  = (string) ( $audio_urls[0] ?? '' );
		$image_url  = (string) ( $typed_image_urls[0] ?? ( $is_videodraft ? '' : self::find_result_url( $result ) ) );

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
		if ( ! self::begin_import_journal( $job_id, $post->ID, (int) get_post_thumbnail_id( $post ) ) ) {
			return new WP_Error( 'worldgraph_gen_journal_failed', __( 'WordPress could not prepare a recoverable media import.', 'worldgraph' ) );
		}
		if ( '' !== $image_url ) {
			$download = $is_videodraft
				? self::download_to_file( $image_url, AI_Image_Client::MAX_IMAGE_BYTES, $job_id )
				: self::download_bytes( $image_url, $adapter, $job_id );
			if ( is_wp_error( $download ) ) {
				return self::rollback_media_import( $job_id, $download );
			}

			$media = is_array( $download ) ? self::validate_image_file( $download ) : self::validate_image_bytes( $download );
			if ( is_wp_error( $media ) ) {
				return self::rollback_media_import( $job_id, $media );
			}

			$attachment_id = self::sideload( $media, $post, $job_id );
			if ( is_wp_error( $attachment_id ) ) {
				return self::rollback_media_import( $job_id, $attachment_id );
			}
			$generated_attachment_ids[] = $attachment_id;

			if ( rest_sanitize_boolean( get_post_meta( $job_id, '_worldgraph_gen_set_featured', true ) ) && post_type_supports( $post->post_type, 'thumbnail' ) ) {
				if ( ! self::journal_featured_attachment( $job_id, $attachment_id ) ) {
					return self::rollback_media_import( $job_id, new WP_Error( 'worldgraph_gen_journal_failed', __( 'WordPress could not journal the generated featured image.', 'worldgraph' ) ) );
				}
				set_post_thumbnail( $post->ID, $attachment_id );
				if ( $attachment_id !== (int) get_post_thumbnail_id( $post->ID ) ) {
					return self::rollback_media_import( $job_id, new WP_Error( 'worldgraph_asset_link_failed', __( 'WordPress could not set the generated featured image.', 'worldgraph' ) ) );
				}
			}
			self::add_to_gallery( $post->ID, $attachment_id );
		}

		// Import the source video alongside its still frame, or on its own for
		// a text-to-video Template that produces no separate frame.
		if ( '' !== $video_url ) {
			$video_download = $is_videodraft
				? self::download_to_file( $video_url, self::MAX_VIDEO_BYTES, $job_id )
				: self::download_bytes( $video_url, $adapter, $job_id );
			if ( is_wp_error( $video_download ) ) {
				return self::rollback_media_import( $job_id, $video_download );
			}
			$video = is_array( $video_download )
				? self::validate_video_file( $video_download, $video_url, true )
				: self::validate_video_bytes( $video_download, $video_url );
			if ( is_wp_error( $video ) ) {
				return self::rollback_media_import( $job_id, $video );
			}
			$video_attachment_id = self::sideload( $video, $post, $job_id );
			if ( is_wp_error( $video_attachment_id ) ) {
				return self::rollback_media_import( $job_id, $video_attachment_id );
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
			$audio_mime = (string) ( $result['audio_mime'] ?? ( 'suno' === $provider ? 'audio/mpeg' : '' ) );
			if ( ! empty( $result['audio_data'] ) ) {
				$audio = self::validate_audio_bytes( (string) $result['audio_data'], $audio_mime, $audio_url );
			} elseif ( $is_videodraft ) {
				$audio_download = self::download_to_file( $audio_url, self::MAX_AUDIO_BYTES, $job_id );
				$audio = is_wp_error( $audio_download ) ? $audio_download : self::validate_audio_file( $audio_download, $audio_url );
			} else {
				$audio_download = self::download_bytes( $audio_url, $adapter, $job_id );
				$audio = is_wp_error( $audio_download ) ? $audio_download : self::validate_audio_bytes( $audio_download, $audio_mime, $audio_url );
			}
			if ( is_wp_error( $audio ) ) {
				return self::rollback_media_import( $job_id, $audio );
			}
			$audio_attachment_id = self::sideload( $audio, $post, $job_id );
			if ( is_wp_error( $audio_attachment_id ) ) {
				return self::rollback_media_import( $job_id, $audio_attachment_id );
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
				return self::rollback_media_import( $job_id, new WP_Error( 'worldgraph_gen_invalid_payload', __( 'ElevenLabs returned an unreadable voice preview.', 'worldgraph' ) ) );
			}
			$audio = self::validate_audio_bytes( (string) $audio_item['data'], (string) ( $audio_item['mime'] ?? '' ), '' );
			if ( is_wp_error( $audio ) ) {
				return self::rollback_media_import( $job_id, $audio );
			}
			$audio_attachment_id = self::sideload( $audio, $post, $job_id );
			if ( is_wp_error( $audio_attachment_id ) ) {
				return self::rollback_media_import( $job_id, $audio_attachment_id );
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
		$result_urls = $is_videodraft
			? array_merge( $typed_image_urls, $typed_video_urls, $typed_audio_urls )
			: self::find_result_urls( $result );
		$additional_urls = array_values( array_diff( array_unique( $result_urls ), [ $image_url, $video_url, $audio_url, '' ] ) );
		foreach ( $additional_urls as $additional_url ) {
			$is_video = self::is_video_url( $additional_url ) || in_array( $additional_url, $typed_video_urls, true );
			$is_audio = in_array( $additional_url, $audio_urls, true ) || self::is_audio_url( $additional_url );
			$additional_download = $is_videodraft
				? self::download_to_file( $additional_url, $is_video ? self::MAX_VIDEO_BYTES : ( $is_audio ? self::MAX_AUDIO_BYTES : AI_Image_Client::MAX_IMAGE_BYTES ), $job_id )
				: self::download_bytes( $additional_url, $adapter, $job_id );
			if ( is_wp_error( $additional_download ) ) {
				return self::rollback_media_import( $job_id, $additional_download );
			}
			$additional_media = $is_video
				? ( is_array( $additional_download ) ? self::validate_video_file( $additional_download, $additional_url, true ) : self::validate_video_bytes( $additional_download, $additional_url ) )
				: ( $is_audio
					? ( is_array( $additional_download ) ? self::validate_audio_file( $additional_download, $additional_url ) : self::validate_audio_bytes( $additional_download, 'suno' === $provider ? 'audio/mpeg' : '', $additional_url ) )
					: ( is_array( $additional_download ) ? self::validate_image_file( $additional_download ) : self::validate_image_bytes( $additional_download ) ) );
			if ( is_wp_error( $additional_media ) ) {
				return self::rollback_media_import( $job_id, $additional_media );
			}
			$additional_attachment_id = self::sideload( $additional_media, $post, $job_id );
			if ( is_wp_error( $additional_attachment_id ) ) {
				return self::rollback_media_import( $job_id, $additional_attachment_id );
			}
			$generated_attachment_ids[] = $additional_attachment_id;
			self::add_to_gallery( $post->ID, $additional_attachment_id );
		}

		if ( ! $attachment_id ) {
			return self::rollback_media_import( $job_id, new WP_Error( 'worldgraph_gen_output_missing', __( 'The generated media could not be imported into the media library.', 'worldgraph' ) ) );
		}

		$prompt   = (string) get_post_meta( $job_id, '_worldgraph_gen_prompt', true );
		$asset_id = 0;
		if ( ( ! $has_story_source || rest_sanitize_boolean( get_post_meta( $job_id, '_worldgraph_gen_create_asset', true ) ) ) && 'worldgraph_asset' !== $post->post_type ) {
			$asset_id = self::create_asset_record( $post, $attachment_id, $prompt, array_merge( $media, [ 'model' => $provider ?: 'generation-mcp', 'size' => (string) ( get_post_meta( $job_id, '_worldgraph_gen_params', true )['size'] ?? '' ), 'revised_prompt' => '', 'workflow' => (string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true ) ] ), $job_id );
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
	 * @param \WP_Post $post   Source post.
	 * @param int      $job_id Generation job ID, when this is a queued import.
	 * @return int|WP_Error Attachment ID.
	 */
	private static function sideload( array $image, \WP_Post $post, int $job_id = 0 ) {
		$filename = sanitize_file_name(
			sprintf( 'worldgraph-%s-%d-%s.%s', $post->post_type, $post->ID, gmdate( 'YmdHis' ), $image['extension'] )
		);

		$checked = wp_check_filetype( $filename, null );
		if ( empty( $checked['type'] ) || $checked['type'] !== $image['mime'] ) {
			self::delete_temp_media( $image );
			return new WP_Error( 'worldgraph_asset_filetype_blocked', __( 'This site does not allow uploads of the generated image type.', 'worldgraph' ), [ 'status' => 400 ] );
		}

		if ( ! empty( $image['file'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload = wp_handle_sideload( [
				'name'     => $filename,
				'type'     => $image['mime'],
				'tmp_name' => $image['file'],
				'error'    => 0,
				'size'     => (int) ( $image['size'] ?? 0 ),
			], [ 'test_form' => false ] );
		} else {
			$upload = wp_upload_bits( $filename, null, $image['data'] );
		}
		if ( ! empty( $upload['error'] ) ) {
			self::delete_temp_media( $image );
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
		if ( $job_id && ! self::journal_attachment( $job_id, (int) $attachment_id ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'worldgraph_gen_journal_failed', __( 'WordPress could not journal the generated media attachment.', 'worldgraph' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
		update_post_meta( $attachment_id, self::SOURCE_META, $post->ID );

		return (int) $attachment_id;
	}

	/** Delete a streamed temporary media file if WordPress did not move it. */
	private static function delete_temp_media( array $media ): void {
		if ( ! empty( $media['file'] ) && is_string( $media['file'] ) && file_exists( $media['file'] ) ) {
			wp_delete_file( $media['file'] );
		}
	}

	/** Start a durable record of side effects before importing provider media. */
	private static function begin_import_journal( int $job_id, int $post_id, int $previous_thumbnail_id ): bool {
		return self::update_import_journal(
			$job_id,
			[
				'version'                => 1,
				'post_id'                => $post_id,
				'previous_thumbnail_id'  => $previous_thumbnail_id,
				'featured_attachment_id' => 0,
				'attachment_ids'          => [],
				'asset_ids'               => [],
				'temp_files'              => [],
			]
		);
	}

	/** Persist a newly created attachment before generating metadata or links. */
	private static function journal_attachment( int $job_id, int $attachment_id ): bool {
		return self::append_import_journal_value( $job_id, 'attachment_ids', $attachment_id );
	}

	/** Persist the generated attachment that is about to become featured. */
	private static function journal_featured_attachment( int $job_id, int $attachment_id ): bool {
		$journal = get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true );
		if ( ! is_array( $journal ) ) {
			return false;
		}
		$journal['featured_attachment_id'] = $attachment_id;
		return self::update_import_journal( $job_id, $journal );
	}

	/** Persist a generated Asset post so a crashed import cannot orphan it. */
	private static function journal_asset( int $job_id, int $asset_id ): bool {
		return self::append_import_journal_value( $job_id, 'asset_ids', $asset_id );
	}

	/** Persist a temporary download path before the remote request writes to it. */
	private static function journal_temp_file( int $job_id, string $file ): bool {
		return self::append_import_journal_value( $job_id, 'temp_files', $file );
	}

	/** Append one unique value to a list in the current import journal. */
	private static function append_import_journal_value( int $job_id, string $key, $value ): bool {
		$journal = get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true );
		if ( ! is_array( $journal ) ) {
			return false;
		}
		$values = isset( $journal[ $key ] ) && is_array( $journal[ $key ] ) ? $journal[ $key ] : [];
		if ( ! in_array( $value, $values, true ) ) {
			$values[] = $value;
		}
		$journal[ $key ] = $values;
		return self::update_import_journal( $job_id, $journal );
	}

	/** Store a journal and treat an already-identical value as success. */
	private static function update_import_journal( int $job_id, array $journal ): bool {
		$updated = update_post_meta( $job_id, self::IMPORT_JOURNAL_META, $journal );
		return false !== $updated || $journal === get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true );
	}

	/**
	 * Remove side effects from an interrupted import before retrying it.
	 *
	 * The thumbnail is restored only while it still points at this import's
	 * generated attachment, preserving a later editor's explicit change.
	 */
	public static function recover_import_journal( int $job_id ): bool {
		$journal = get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true );
		if ( ! is_array( $journal ) || empty( $journal ) ) {
			return true;
		}

		$post_id                = absint( $journal['post_id'] ?? 0 );
		$previous_thumbnail_id  = absint( $journal['previous_thumbnail_id'] ?? 0 );
		$featured_attachment_id = absint( $journal['featured_attachment_id'] ?? 0 );
		$attachment_ids         = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $journal['attachment_ids'] ?? [] ) ) ) ) );
		$asset_ids              = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $journal['asset_ids'] ?? [] ) ) ) ) );
		$clean                  = true;

		if ( $post_id && $featured_attachment_id && $featured_attachment_id === (int) get_post_thumbnail_id( $post_id ) ) {
			if ( $previous_thumbnail_id && 'attachment' === get_post_type( $previous_thumbnail_id ) ) {
				set_post_thumbnail( $post_id, $previous_thumbnail_id );
				$clean = $previous_thumbnail_id === (int) get_post_thumbnail_id( $post_id ) && $clean;
			} else {
				delete_post_thumbnail( $post_id );
				$clean = 0 === (int) get_post_thumbnail_id( $post_id ) && $clean;
			}
		}

		if ( $post_id && ! empty( $attachment_ids ) ) {
			$current_gallery = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, self::GALLERY_META, true ) ) ) );
			$gallery         = array_values( array_diff( $current_gallery, $attachment_ids ) );
			if ( $gallery !== $current_gallery ) {
				update_post_meta( $post_id, self::GALLERY_META, $gallery );
				$stored_gallery = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, self::GALLERY_META, true ) ) ) );
				$clean          = $gallery === $stored_gallery && $clean;
			}
		}

		foreach ( $asset_ids as $asset_id ) {
			if ( 'worldgraph_asset' === get_post_type( $asset_id ) ) {
				wp_delete_post( $asset_id, true );
				$clean = ! get_post( $asset_id ) && $clean;
			}
		}
		foreach ( $attachment_ids as $attachment_id ) {
			if ( 'attachment' === get_post_type( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
				$clean = ! get_post( $attachment_id ) && $clean;
			}
		}

		delete_post_meta( $job_id, '_worldgraph_gen_attachment_id' );
		delete_post_meta( $job_id, '_worldgraph_gen_attachment_ids' );
		delete_post_meta( $job_id, '_worldgraph_gen_asset_id' );
		$clean = self::delete_journal_temp_files( $journal ) && $clean;
		if ( $clean ) {
			delete_post_meta( $job_id, self::IMPORT_JOURNAL_META );
			$clean = ! is_array( get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true ) );
		}

		return $clean;
	}

	/** Clear recovery state after attachment metadata and final status are durable. */
	public static function commit_import_journal( int $job_id ): bool {
		$journal = get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true );
		if ( ! is_array( $journal ) || empty( $journal ) ) {
			return true;
		}
		$clean = self::delete_journal_temp_files( $journal );
		if ( ! $clean ) {
			return false;
		}
		delete_post_meta( $job_id, self::IMPORT_JOURNAL_META );
		return ! is_array( get_post_meta( $job_id, self::IMPORT_JOURNAL_META, true ) );
	}

	/** Delete only temporary files created by this importer. */
	private static function delete_journal_temp_files( array $journal ): bool {
		$clean    = true;
		$temp_dir = realpath( get_temp_dir() );
		foreach ( array_unique( array_filter( (array) ( $journal['temp_files'] ?? [] ), 'is_string' ) ) as $file ) {
			$file_dir = realpath( dirname( $file ) );
			if ( 0 !== strpos( basename( $file ), 'worldgraph-videodraft-media' ) || ! file_exists( $file ) || ! $temp_dir || $temp_dir !== $file_dir ) {
				continue;
			}
			wp_delete_file( $file );
			$clean = ! file_exists( $file ) && $clean;
		}
		return $clean;
	}

	/** Roll back a partial multi-output import before retrying. */
	private static function rollback_media_import( int $job_id, WP_Error $error ): WP_Error {
		if ( ! self::recover_import_journal( $job_id ) ) {
			return new WP_Error(
				'worldgraph_gen_cleanup_failed',
				__( 'WordPress could not finish rolling back the interrupted media import.', 'worldgraph' ),
				[ 'cause' => $error->get_error_code() ]
			);
		}
		return $error;
	}

	/**
	 * Find the first image URL in a Comfy MCP job result.
	 *
	 * @param array $result MCP result payload.
	 * @return string
	 */
	private static function find_result_url( array $result ): string {
		foreach ( [ 'image_url', 'imageUrl', 'output_url', 'outputUrl', 'url', 'audio_url', 'audioUrl' ] as $key ) {
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
		foreach ( [ 'image_url', 'imageUrl', 'video_url', 'videoUrl', 'audio_url', 'audioUrl', 'speech_url', 'output_url', 'outputUrl', 'url', 'public_url' ] as $key ) {
			if ( isset( $result[ $key ] ) && is_string( $result[ $key ] ) && filter_var( $result[ $key ], FILTER_VALIDATE_URL ) ) {
				$urls[] = $result[ $key ];
			}
		}
		foreach ( [ 'outputUrls', 'output_urls' ] as $key ) {
			foreach ( (array) ( $result[ $key ] ?? [] ) as $url ) {
				if ( is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
					$urls[] = $url;
				}
			}
		}
		foreach ( $result as $value ) {
			if ( is_array( $value ) ) {
				$urls = array_merge( $urls, self::find_result_urls( $value ) );
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/** Find provider-normalized output_media URLs for one media kind. */
	private static function find_typed_output_urls( array $result, string $kind ): array {
		$urls = [];
		foreach ( (array) ( $result['output_media'] ?? [] ) as $media ) {
			if ( ! is_array( $media ) || $kind !== sanitize_key( (string) ( $media['kind'] ?? $media['type'] ?? '' ) ) ) {
				continue;
			}
			$url = (string) ( $media['url'] ?? '' );
			if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$urls[] = $url;
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

	/** Find all explicit or extension-recognizable audio URLs in a provider response. */
	private static function find_result_audio_urls( array $result ): array {
		$urls = [];
		foreach ( $result as $key => $value ) {
			if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$is_explicit_audio = in_array( (string) $key, [ 'audio_url', 'audioUrl', 'stream_audio_url', 'download_audio_url' ], true );
				if ( $is_explicit_audio || self::is_audio_url( $value ) ) {
					$urls[] = $value;
				}
			} elseif ( is_array( $value ) ) {
				$urls = array_merge( $urls, self::find_result_audio_urls( $value ) );
			}
		}

		return array_values( array_unique( $urls ) );
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
	 * @param string $url     Output URL.
	 * @param string $adapter Generation job adapter, e.g. 'local_comfyui'.
	 * @return string|WP_Error Raw bytes, or an error.
	 */
	private static function download_bytes( string $url, string $adapter, int $job_id = 0 ) {
		// Local ComfyUI runs on a trusted, non-public host (e.g. host.lando.internal),
		// which wp_safe_remote_get's SSRF check would otherwise reject.
		$timeout = 'videodraft' === $adapter ? 600 : 60;
		$args = [ 'timeout' => $timeout ];
		// OpenRouter's content endpoint requires the same bearer credential used to submit the job.
		if ( $job_id && 'openrouter' === (string) get_post_meta( $job_id, '_worldgraph_gen_provider_type', true ) ) {
			$args['headers'] = OpenRouter_API::download_headers( $job_id );
		}
		$download = 'local_comfyui' === $adapter ? wp_remote_get( $url, $args ) : wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $download ) ) {
			Generation_Log::add( 'error', 'generation_batch', 'Download request failed: ' . $download->get_error_message(), [ 'url' => $url ], '', 0 );
			return new WP_Error( 'worldgraph_gen_download_failed', __( 'The completed output could not be downloaded from the generation provider.', 'worldgraph' ) );
		}
		$code = wp_remote_retrieve_response_code( $download );
		if ( $code < 200 || $code >= 300 ) {
			Generation_Log::add( 'error', 'generation_batch', sprintf( 'Download request returned HTTP %d.', $code ), [ 'url' => $url ], '', 0 );
			return new WP_Error( 'worldgraph_gen_download_failed', __( 'The completed output could not be downloaded from the generation provider.', 'worldgraph' ) );
		}
		$mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $download, 'content-type' ) )[0] ) );
		if ( '' !== $mime ) {
			self::$download_mime_types[ $url ] = $mime;
		}

		return (string) wp_remote_retrieve_body( $download );
	}

	/** Stream a large VideoDraft output into a bounded temporary file. */
	private static function download_to_file( string $url, int $maximum_bytes, int $job_id ) {
		$temporary = wp_tempnam( 'worldgraph-videodraft-media' );
		if ( ! $temporary ) {
			return new WP_Error( 'worldgraph_gen_download_failed', __( 'WordPress could not create temporary storage for the generated media.', 'worldgraph' ) );
		}
		if ( ! self::journal_temp_file( $job_id, $temporary ) ) {
			wp_delete_file( $temporary );
			return new WP_Error( 'worldgraph_gen_journal_failed', __( 'WordPress could not journal temporary storage for the generated media.', 'worldgraph' ) );
		}

		$download = wp_safe_remote_get( $url, [
			'timeout'             => 600,
			'stream'              => true,
			'filename'            => $temporary,
			'limit_response_size' => $maximum_bytes + 1,
		] );
		if ( is_wp_error( $download ) ) {
			wp_delete_file( $temporary );
			Generation_Log::add( 'error', 'generation_batch', 'Download request failed: ' . $download->get_error_message(), [ 'url' => $url ], '', 0 );
			return new WP_Error( 'worldgraph_gen_download_failed', __( 'The completed output could not be downloaded from VideoDraft.', 'worldgraph' ) );
		}

		$code = wp_remote_retrieve_response_code( $download );
		$size = file_exists( $temporary ) ? filesize( $temporary ) : false;
		if ( $code < 200 || $code >= 300 ) {
			wp_delete_file( $temporary );
			return new WP_Error( 'worldgraph_gen_download_failed', sprintf( __( 'VideoDraft returned HTTP %d while downloading completed media.', 'worldgraph' ), $code ) );
		}
		if ( false === $size || $size <= 0 || $size > $maximum_bytes ) {
			wp_delete_file( $temporary );
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed VideoDraft media is empty or too large to store.', 'worldgraph' ) );
		}

		$mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $download, 'content-type' ) )[0] ) );
		if ( '' !== $mime ) {
			self::$download_mime_types[ $url ] = $mime;
		}

		return [ 'file' => $temporary, 'mime' => $mime, 'size' => (int) $size ];
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

	/** Validate a streamed image without materializing it in PHP memory. */
	private static function validate_image_file( array $download ) {
		if ( empty( $download['file'] ) || empty( $download['size'] ) || $download['size'] > AI_Image_Client::MAX_IMAGE_BYTES ) {
			self::delete_temp_media( $download );
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed image is empty or too large to store.', 'worldgraph' ) );
		}
		$info = @getimagesize( $download['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || empty( $info['mime'] ) || ! in_array( $info['mime'], AI_Image_Client::ALLOWED_MIME_TYPES, true ) ) {
			self::delete_temp_media( $download );
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'The generation provider returned a file that is not a supported image.', 'worldgraph' ) );
		}
		$extensions = [ 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif' ];
		return array_merge( $download, [
			'mime'      => $info['mime'],
			'extension' => $extensions[ $info['mime'] ],
			'width'     => (int) $info[0],
			'height'    => (int) $info[1],
		] );
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
	private static function validate_video_bytes( string $bytes, string $url, bool $assume_mp4 = false ) {
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_VIDEO_BYTES ) {
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed video is empty or too large to store.', 'worldgraph' ) );
		}
		$filetype = self::video_filetype( $url, $assume_mp4 );
		return is_wp_error( $filetype ) ? $filetype : array_merge( [ 'data' => $bytes ], $filetype );
	}

	/** Validate a streamed video while retaining its temporary file. */
	private static function validate_video_file( array $download, string $url, bool $assume_mp4 = false ) {
		if ( empty( $download['file'] ) || empty( $download['size'] ) || $download['size'] > self::MAX_VIDEO_BYTES ) {
			self::delete_temp_media( $download );
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed video is empty or too large to store.', 'worldgraph' ) );
		}

		$filetype = self::video_filetype( $url, $assume_mp4 );
		if ( is_wp_error( $filetype ) ) {
			self::delete_temp_media( $download );
			return $filetype;
		}

		return array_merge( $download, $filetype );
	}

	/** Resolve a supported generated-video extension and mime type. */
	private static function video_filetype( string $url, bool $assume_mp4 = false ) {

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$ext = strtolower( pathinfo( (string) ( $query['filename'] ?? '' ), PATHINFO_EXTENSION ) );
		}
		if ( '' === $ext && isset( self::$download_mime_types[ $url ] ) ) {
			$ext = (string) array_search( self::$download_mime_types[ $url ], self::VIDEO_MIME_TYPES, true );
		}
		if ( '' === $ext && $assume_mp4 ) {
			$ext = 'mp4';
		}

		if ( ! isset( self::VIDEO_MIME_TYPES[ $ext ] ) ) {
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'The generation provider returned an unsupported video type.', 'worldgraph' ) );
		}

		return [ 'mime' => self::VIDEO_MIME_TYPES[ $ext ], 'extension' => $ext ];
	}

	/** Validate generated audio bytes and normalize their WordPress file type. */
	private static function validate_audio_bytes( string $bytes, string $mime, string $url ) {
		if ( '' === $bytes || strlen( $bytes ) > self::MAX_AUDIO_BYTES ) {
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed audio is empty or too large to store.', 'worldgraph' ) );
		}
		$filetype = self::audio_filetype( $mime, $url );
		return is_wp_error( $filetype ) ? $filetype : array_merge( [ 'data' => $bytes ], $filetype );
	}

	/** Validate a streamed audio file while retaining its temporary path. */
	private static function validate_audio_file( array $download, string $url ) {
		if ( empty( $download['file'] ) || empty( $download['size'] ) || $download['size'] > self::MAX_AUDIO_BYTES ) {
			self::delete_temp_media( $download );
			return new WP_Error( 'worldgraph_gen_invalid_payload', __( 'The completed audio is empty or too large to store.', 'worldgraph' ) );
		}
		$filetype = self::audio_filetype( (string) ( $download['mime'] ?? '' ), $url );
		if ( is_wp_error( $filetype ) ) {
			self::delete_temp_media( $download );
			return $filetype;
		}
		return array_merge( $download, $filetype );
	}

	/** Resolve a supported generated-audio extension and mime type. */
	private static function audio_filetype( string $mime, string $url ) {
		$mime = strtolower( trim( explode( ';', $mime ?: (string) ( self::$download_mime_types[ $url ] ?? '' ) )[0] ) );
		$mime = 'audio/mp3' === $mime ? 'audio/mpeg' : $mime;
		$mime = 'audio/x-wav' === $mime ? 'audio/wav' : $mime;
		$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( '' === $ext && '' !== $mime ) {
			$ext = (string) array_search( $mime, self::AUDIO_MIME_TYPES, true );
		}
		if ( ! isset( self::AUDIO_MIME_TYPES[ $ext ] ) ) {
			return new WP_Error( 'worldgraph_gen_unsupported_type', __( 'The generation provider returned an unsupported audio type.', 'worldgraph' ) );
		}
		return [ 'mime' => self::AUDIO_MIME_TYPES[ $ext ], 'extension' => $ext ];
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
	 * @param int      $job_id        Generation job ID, when this is a queued import.
	 * @return int Asset post ID, or 0 on failure.
	 */
	private static function create_asset_record( \WP_Post $post, int $attachment_id, string $prompt, array $image, int $job_id = 0 ): int {
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
		if ( $job_id && ! self::journal_asset( $job_id, $asset_id ) ) {
			wp_delete_post( $asset_id, true );
			return 0;
		}
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
