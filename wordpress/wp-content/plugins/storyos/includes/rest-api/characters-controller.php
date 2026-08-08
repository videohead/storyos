<?php
/**
 * Characters REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

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
	protected $cpt = 'storyos_character';

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
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'storyos/v1', '/characters', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_items' ],
				'permission_callback' => [ __CLASS__, 'check_read_permission' ],
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
				'callback'            => [ __CLASS__, 'create_item' ],
				'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/characters/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/characters/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_graph' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
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
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_character' );
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
		$relations = get_the_terms( $post->ID, 'storyos_character_relation' );
		if ( $relations && ! is_wp_error( $relations ) ) {
			$data['meta']['character_relations'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $relations );
		}

		// Get character role terms.
		$roles = get_the_terms( $post->ID, 'storyos_character_role' );
		if ( $roles && ! is_wp_error( $roles ) ) {
			$data['meta']['character_roles'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $roles );
		}

		// Count related scenes and shots.
		$data['meta']['scene_count'] = self::count_related( $post->ID, 'storyos_scene', 'storyos_character' );
		$data['meta']['shot_count'] = self::count_related( $post->ID, 'storyos_shot', 'storyos_character' );
		$data['meta']['asset_count'] = self::count_related( $post->ID, 'storyos_asset', 'storyos_character' );

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
