<?php
/**
 * Scenes REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Scenes Controller class.
 */
class Scenes_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_scene';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'scenes';

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
		register_rest_route( 'storyos/v1', '/scenes', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_items' ],
				'permission_callback' => [ __CLASS__, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'project'  => [ 'type' => 'integer' ],
					'episode'  => [ 'type' => 'integer' ],
					'location' => [ 'type' => 'integer' ],
					'sequence' => [
						'description' => 'Filter by sequence slug (or comma-separated slugs).',
						'type'        => 'string',
					],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_item' ],
				'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/scenes/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/scenes/(?P<id>\d+)/graph', [
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
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_scene' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add scene-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		// Get scene tags.
		$tags = get_the_terms( $post->ID, 'storyos_scene_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$data['meta']['scene_tags'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $tags );
		}

		// Get sequence terms.
		$sequences = get_the_terms( $post->ID, 'storyos_sequence' );
		if ( $sequences && ! is_wp_error( $sequences ) ) {
			$data['meta']['sequences'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $sequences );
		}

		// Count related shots.
		$data['meta']['shot_count'] = self::count_related( $post->ID, 'storyos_shot', 'storyos_scene' );

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
