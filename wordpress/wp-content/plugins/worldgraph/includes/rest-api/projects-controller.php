<?php
/**
 * Projects REST API Controller for World Graph Studio.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Projects Controller class.
 */
class Projects_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'worldgraph_project';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'projects';

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
		register_rest_route( 'worldgraph/v1', '/projects', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [
						'description' => 'Current page of the pagination.',
						'type'        => 'integer',
						'default'     => 1,
					],
					'per_page' => [
						'description' => 'Number of items per page.',
						'type'        => 'integer',
						'default'     => 10,
						'maximum'     => 100,
					],
					'status'   => [
						'description' => 'Filter by status slug.',
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

		register_rest_route( 'worldgraph/v1', '/projects/(?P<id>\d+)', [
			'args'   => [
				'id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
				],
			],
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

		// Graph endpoint for project.
		register_rest_route( 'worldgraph/v1', '/projects/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );
	}

	/**
	 * Get graph connections for a project.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \WorldGraph\Utils\get_graph_entities( $post_id, 'worldgraph_project' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add project-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );
		$related = self::get_project_related_ids( $post->ID );

		// Add project-specific computed fields.
		$data['meta']['scene_count']     = count( $related['worldgraph_scene'] );
		$data['meta']['character_count'] = count( $related['worldgraph_character'] );
		$data['meta']['asset_count']     = count( $related['worldgraph_asset'] );

		return $data;
	}

	/**
	 * Resolve Project-owned content through the canonical World and Episode graph.
	 *
	 * Legacy imports connect Scenes through their World's Characters and
	 * Locations, while version 1.2 also has explicit Episode and Project edges.
	 * Supporting both paths keeps aggregate counts correct without a data
	 * migration and de-duplicates entities connected through several paths.
	 *
	 * @param int $project_id Project post ID.
	 * @return array<string, array<int, int>>
	 */
	private static function get_project_related_ids( int $project_id ): array {
		$world_ids     = self::related_ids( $project_id, 'worldgraph_project', 'worldgraph_world' );
		$episode_ids   = self::related_ids( $project_id, 'worldgraph_project', 'worldgraph_episode' );
		$character_ids = self::related_ids( $project_id, 'worldgraph_project', 'worldgraph_character' );
		$location_ids  = [];
		$scene_ids     = self::related_ids( $project_id, 'worldgraph_project', 'worldgraph_scene' );
		$asset_ids     = self::related_ids( $project_id, 'worldgraph_project', 'worldgraph_asset' );

		foreach ( $world_ids as $world_id ) {
			$character_ids = array_merge( $character_ids, self::related_ids( $world_id, 'worldgraph_world', 'worldgraph_character' ) );
			$location_ids  = array_merge( $location_ids, self::related_ids( $world_id, 'worldgraph_world', 'worldgraph_location' ) );
		}
		$character_ids = array_values( array_unique( $character_ids ) );
		$location_ids  = array_values( array_unique( $location_ids ) );

		foreach ( $episode_ids as $episode_id ) {
			$scene_ids = array_merge( $scene_ids, self::related_ids( $episode_id, 'worldgraph_episode', 'worldgraph_scene' ) );
		}
		foreach ( $character_ids as $character_id ) {
			$scene_ids = array_merge( $scene_ids, self::related_ids( $character_id, 'worldgraph_character', 'worldgraph_scene' ) );
			$asset_ids = array_merge( $asset_ids, self::related_ids( $character_id, 'worldgraph_character', 'worldgraph_asset' ) );
		}
		foreach ( $location_ids as $location_id ) {
			$scene_ids = array_merge( $scene_ids, self::related_ids( $location_id, 'worldgraph_location', 'worldgraph_scene' ) );
			$asset_ids = array_merge( $asset_ids, self::related_ids( $location_id, 'worldgraph_location', 'worldgraph_asset' ) );
		}
		$scene_ids = array_values( array_unique( $scene_ids ) );
		foreach ( $scene_ids as $scene_id ) {
			$asset_ids = array_merge( $asset_ids, self::related_ids( $scene_id, 'worldgraph_scene', 'worldgraph_asset' ) );
		}

		return [
			'worldgraph_scene'     => $scene_ids,
			'worldgraph_character' => $character_ids,
			'worldgraph_asset'     => array_values( array_unique( $asset_ids ) ),
		];
	}

	/**
	 * Get directly adjacent entities of one CPT in either graph direction.
	 *
	 * @param int    $post_id     Source post ID.
	 * @param string $from_cpt    Source CPT.
	 * @param string $related_cpt Requested adjacent CPT.
	 * @return array<int, int>
	 */
	private static function related_ids( int $post_id, string $from_cpt, string $related_cpt ): array {
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
