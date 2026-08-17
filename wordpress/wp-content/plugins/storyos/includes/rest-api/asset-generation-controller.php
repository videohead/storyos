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

use StoryOS\AI\AI_Image_Client;
use StoryOS\Utils\Asset_Generator;
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
				'size'         => [
					'description' => 'Requested image size, e.g. 1024x1024.',
					'type'        => 'string',
					'enum'        => AI_Image_Client::ALLOWED_SIZES,
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
			'size'         => (string) $request->get_param( 'size' ),
			'set_featured' => $request->get_param( 'set_featured' ),
			'create_asset' => $request->get_param( 'create_asset' ),
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

		$configured = \StoryOS\Utils\Comfy_Cloud_MCP::is_configured();

		return rest_ensure_response( [
			'post_id'    => $post_id,
			'prompt'     => Asset_Generator::build_prompt( $post_id ),
			'configured' => $configured,
			'model'      => 'Comfy Cloud MCP',
			'size'       => AI_Image_Client::DEFAULT_SIZE,
			'sizes'      => AI_Image_Client::ALLOWED_SIZES,
		] );
	}
}
