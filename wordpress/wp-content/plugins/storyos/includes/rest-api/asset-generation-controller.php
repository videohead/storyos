<?php
/**
 * Asset Generation REST API Controller for StoryOS.
 *
 * Exposes the "generate asset" tools: build a text-to-image prompt for a story
 * element, run it, and attach the result to the post.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use StoryOS\Utils\Asset_Generator;
use StoryOS\Utils\Connection_Repository;
use StoryOS\Utils\Generation_Modality;
use StoryOS\Utils\Template_Bindings;
use WP_Error;
use WP_REST_Request;

/**
 * Asset Generation Controller class.
 */
class Asset_Generation_Controller extends Base_Controller {

	/**
	 * CPT slug (not used).
	 *
	 * @var string
	 */
	protected $cpt = '';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'assets/generate';

	/**
	 * Initialize the controller.
	 */
	public static function init(): void {
		$instance = new self();
		add_action( 'rest_api_init', [ $instance, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'storyos/v1', '/assets/generate', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'generate' ],
			'permission_callback' => [ __CLASS__, 'check_generate_permission' ],
			'args'                => [
				'post_id'      => [
					'description' => 'Story element post ID the image belongs to.',
					'type'        => 'integer',
					'required'    => true,
				],
				'prompt'       => [
					'description' => 'Text-to-image prompt. Built from the story element when omitted.',
					'type'        => 'string',
				],
				'set_featured' => [
					'description' => 'Set the generated image as the featured asset.',
					'type'        => 'boolean',
					'default'     => true,
				],
				'create_asset' => [
					'description' => 'Create a linked StoryOS Asset record.',
					'type'        => 'boolean',
					'default'     => true,
				],
				'template_id'  => [
					'description' => 'Active Template post ID to run instead of the built-in default.',
					'type'        => 'integer',
					'default'     => 0,
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/assets/generate/prompt', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_prompt' ],
			'permission_callback' => [ __CLASS__, 'check_generate_permission' ],
			'args'                => [
				'post_id' => [
					'description' => 'Story element post ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );
	}

	/**
	 * Only editors of the target post may spend generation budget on it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function check_generate_permission( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'storyos_rest_forbidden',
				__( 'You are not allowed to generate assets for this item.', 'storyos' ),
				[ 'status' => is_user_logged_in() ? 403 : 401 ]
			);
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'storyos_rest_forbidden_upload',
				__( 'You are not allowed to upload files to this site.', 'storyos' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Generate and attach an image for a story element.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function generate( WP_REST_Request $request ) {
		$result = Asset_Generator::queue_for_post( absint( $request->get_param( 'post_id' ) ), [
			'prompt'       => (string) $request->get_param( 'prompt' ),
			'set_featured' => $request->get_param( 'set_featured' ),
			'create_asset' => $request->get_param( 'create_asset' ),
			'template_id'  => absint( $request->get_param( 'template_id' ) ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Return the suggested prompt and provider configuration for a story element.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function get_prompt( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! Asset_Generator::supports( $post_id ) ) {
			return new WP_Error( 'storyos_asset_invalid_post', __( 'That post cannot have a StoryOS asset generated for it.', 'storyos' ), [ 'status' => 404 ] );
		}

		$templates  = self::runnable_templates( $post_id );
		$configured = ! empty( $templates );

		return rest_ensure_response( [
			'post_id'             => $post_id,
			'prompt'              => Asset_Generator::build_prompt( $post_id ),
			'configured'          => $configured,
			'model'               => 'Comfy Cloud MCP',
			'profile'             => Asset_Generator::project_media_profile( $post_id ),
			'templates'           => $templates,
			'default_template_id' => self::default_template_id( $templates ),
		] );
	}

	/**
	 * Prefer the managed local text-to-image Template as the panel default.
	 *
	 * @param array<int, array<string, mixed>> $templates Runnable templates.
	 * @return int
	 */
	private static function default_template_id( array $templates ): int {
		$managed_posts = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'storyos_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'local_comfyui_default', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		] );
		$managed = $managed_posts ? (int) $managed_posts[0] : 0;
		if ( $managed ) {
			foreach ( $templates as $template ) {
				if ( $managed === (int) ( $template['id'] ?? 0 ) ) {
					return $managed;
				}
			}
		}

		return isset( $templates[0]['id'] ) ? (int) $templates[0]['id'] : 0;
	}

	/**
	 * Active Templates this panel can run for a given post without error. A
	 * Template needing more than a prompt is still offered when its
	 * `input_bindings` resolve from this post's featured image, media gallery,
	 * or a StoryOS Details / SCF field; otherwise it's left out so a selection
	 * here can never fail with a missing-input error. If a Template names an
	 * explicit Connection, that Connection must also be available. This keeps
	 * the dropdown inextricably tied to the Templates area while guaranteeing a
	 * selection here can actually be generated.
	 *
	 * @param int $post_id Source story element post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function runnable_templates( int $post_id ): array {
		$templates = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => 'status',
			'meta_value'     => 'active',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$options = [];
		foreach ( $templates as $template ) {
			$modality = Generation_Modality::sanitize( (string) get_post_meta( $template->ID, 'modality', true ) );
			if ( 'image' !== Generation_Modality::output_type( $modality ) ) {
				continue;
			}
			if ( ! empty( Template_Bindings::missing_required( $template->ID, $post_id ) ) ) {
				continue;
			}

			$connection_id = absint( get_post_meta( $template->ID, 'connection_id', true ) );
			if ( ! $connection_id || ! Connection_Repository::is_available( $connection_id ) ) {
				continue;
			}

			$options[] = [
				'id'       => (int) $template->ID,
				'name'     => (string) ( get_post_meta( $template->ID, 'template_name', true ) ?: $template->post_title ),
				'modality' => $modality,
			];
		}

		return $options;
	}
}
