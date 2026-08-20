<?php
/**
 * Story Worlds REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Story Worlds Controller class.
 */
class StoryWorlds_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_storyworld';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'storyworlds';

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
		register_rest_route( 'storyos/v1', '/storyworlds', [
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
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/storyworlds/(?P<id>\d+)', [
			'args'   => [
				'id' => [
					'description' => 'Story World ID.',
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

		// Graph endpoint.
		register_rest_route( 'storyos/v1', '/storyworlds/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );
	}

	/**
	 * Get graph connections for a story world.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_storyworld' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add story world-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		// Add story world-specific computed fields.
		$data['meta']['location_count'] = self::count_related( $post->ID, 'storyos_location', 'storyos_storyworld' );
		$data['meta']['character_count'] = self::count_related( $post->ID, 'storyos_character', 'storyos_storyworld' );
		$data['meta']['organization_count'] = self::count_related( $post->ID, 'storyos_organization', 'storyos_storyworld' );

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
		$count = 0;
		foreach ( $rels as $rel ) {
			if ( $rel['to_type'] === $related_cpt ) {
				$count++;
			}
		}
		return $count;
	}
}
