<?php
/**
 * Locations REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;

/**
 * Locations Controller class.
 */
class Locations_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_location';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'locations';

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
		register_rest_route( 'storyos/v1', '/locations', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'world'    => [
						'description' => 'Filter by story world ID.',
						'type'        => 'integer',
					],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/locations/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/locations/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );
	}

	/**
	 * Get graph connections.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_location' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add location-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		// Count related scenes.
		$data['meta']['scene_count'] = self::count_related( $post->ID, 'storyos_scene', 'storyos_location' );

		return $data;
	}

	/**
	 * Count related items.
	 *
	 * @param int    $post_id
	 * @param string $related_cpt
	 * @param string $from_cpt
	 * @return int
	 */
	private static function count_related( int $post_id, string $related_cpt, string $from_cpt ): int {
		$rels = \StoryOS\Utils\get_relationships( $post_id, $from_cpt, 'outgoing' );
		return count( array_filter( $rels, fn( $r ) => $r['to_type'] === $related_cpt ) );
	}
}
