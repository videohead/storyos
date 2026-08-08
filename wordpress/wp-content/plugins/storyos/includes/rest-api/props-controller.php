<?php
/**
 * Props REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Props Controller class.
 */
class Props_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_prop';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'props';

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
		register_rest_route( 'storyos/v1', '/props', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_items' ],
				'permission_callback' => [ __CLASS__, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_item' ],
				'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/props/(?P<id>\d+)', [
			'args'   => [ 'id' => [ 'type' => 'integer' ] ],
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_item' ],
				'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ __CLASS__, 'update_item' ],
				'permission_callback' => [ __CLASS__, 'check_update_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ __CLASS__, 'delete_item' ],
				'permission_callback' => [ __CLASS__, 'check_delete_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/props/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_graph' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
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
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_prop' );
		return rest_ensure_response( $entities );
	}
}
