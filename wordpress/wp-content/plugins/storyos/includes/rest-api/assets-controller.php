<?php
/**
 * Assets REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Assets Controller class.
 */
class Assets_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_asset';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'assets';

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
		register_rest_route( 'storyos/v1', '/assets', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'project'  => [ 'type' => 'integer' ],
					'character'=> [ 'type' => 'integer' ],
					'scene'    => [ 'type' => 'integer' ],
					'type'     => [ 'type' => 'string' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/assets/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/assets/(?P<id>\d+)/graph', [
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
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_asset' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add asset-specific fields.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		// Get asset type taxonomy.
		$types = get_the_terms( $post->ID, 'storyos_asset_type' );
		if ( $types && ! is_wp_error( $types ) ) {
			$data['meta']['asset_types'] = array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $types );
		}

		return $data;
	}
}
