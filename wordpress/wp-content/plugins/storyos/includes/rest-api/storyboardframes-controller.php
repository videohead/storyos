<?php
/**
 * Storyboard Frames REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Storyboard Frames Controller class.
 */
class StoryboardFrames_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_storyboard';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'storyboard-frames';

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
		register_rest_route( 'storyos/v1', '/storyboard-frames', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'scene'    => [ 'type' => 'integer' ],
					'shot'     => [ 'type' => 'integer' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/storyboard-frames/(?P<id>\d+)', [
			'args'   => [ 'id' => [ 'type' => 'integer' ] ],
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'check_update_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'check_delete_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/storyboard-frames/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );
	}

	/**
	 * Get graph connections.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_graph( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_storyboard' );
		return rest_ensure_response( $entities );
	}
}
