<?php
/**
 * Editorial Artifacts REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Editorial Artifacts Controller class.
 */
class EditorialArtifacts_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_editorial_artifact';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'editorial-artifacts';

	/**
	 * Initialize the controller.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'storyos/v1', '/editorial-artifacts', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_items' ],
				'permission_callback' => [ __CLASS__, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'project'  => [ 'type' => 'integer' ],
					'type'     => [ 'type' => 'string' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_item' ],
				'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/editorial-artifacts/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/editorial-artifacts/(?P<id>\d+)/graph', [
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
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_editorial_artifact' );
		return rest_ensure_response( $entities );
	}
}
