<?php
/**
 * Characters REST API Controller for World Graph Studio.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WP_REST_Request;

/**
 * Characters Controller class.
 */
class Characters_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'worldgraph_character';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'characters';

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
		register_rest_route( 'worldgraph/v1', '/characters', [
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
					'character_role' => [
						'description' => 'Filter by character role slug (or comma-separated slugs).',
						'type'        => 'string',
					],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/characters/(?P<id>\d+)', [
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

		register_rest_route( 'worldgraph/v1', '/characters/(?P<id>\d+)/graph', [
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
		$entities = \WorldGraph\Utils\get_graph_entities( $post_id, 'worldgraph_character' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add character-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		// Get character relation terms.
		$relations = get_the_terms( $post->ID, 'worldgraph_character_relation' );
		if ( $relations && ! is_wp_error( $relations ) ) {
			$data['meta']['character_relations'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $relations );
		}

		// Get character role terms.
		$roles = get_the_terms( $post->ID, 'worldgraph_character_role' );
		if ( $roles && ! is_wp_error( $roles ) ) {
			$data['meta']['character_roles'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $roles );
		}

		// Count related scenes and shots.
		$scene_ids = self::related_ids( $post->ID, 'worldgraph_scene', 'worldgraph_character' );
		$shot_ids  = self::related_ids( $post->ID, 'worldgraph_shot', 'worldgraph_character' );
		foreach ( $scene_ids as $scene_id ) {
			$shot_ids = array_merge( $shot_ids, self::related_ids( $scene_id, 'worldgraph_shot', 'worldgraph_scene' ) );
		}

		$data['meta']['scene_count'] = count( $scene_ids );
		$data['meta']['shot_count']  = count( array_unique( $shot_ids ) );
		$data['meta']['asset_count'] = self::count_related( $post->ID, 'worldgraph_asset', 'worldgraph_character' );

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
		return count( self::related_ids( $post_id, $related_cpt, $from_cpt ) );
	}

	/**
	 * Get directly adjacent entities of one CPT in either graph direction.
	 *
	 * @param int    $post_id
	 * @param string $related_cpt
	 * @param string $from_cpt
	 * @return array<int, int>
	 */
	private static function related_ids( int $post_id, string $related_cpt, string $from_cpt ): array {
		$related_ids = [];
		foreach ( [ 'outgoing', 'incoming' ] as $direction ) {
			foreach ( \WorldGraph\Utils\get_relationships( $post_id, $from_cpt, $direction ) as $relationship ) {
				$type = 'outgoing' === $direction ? ( $relationship['to_type'] ?? '' ) : ( $relationship['from_type'] ?? '' );
				$id   = 'outgoing' === $direction ? ( $relationship['to_id'] ?? 0 ) : ( $relationship['from_id'] ?? 0 );
				if ( $related_cpt === $type ) {
					$related_ids[] = absint( $id );
				}
			}
		}
		return array_values( array_unique( array_filter( $related_ids ) ) );
	}
}
