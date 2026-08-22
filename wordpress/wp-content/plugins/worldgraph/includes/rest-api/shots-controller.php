<?php
/**
 * Shots REST API Controller for World Graph Studio.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

/**
 * Shots Controller class.
 */
class Shots_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'worldgraph_shot';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'shots';

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
		register_rest_route( 'worldgraph/v1', '/shots', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
					'scene'    => [ 'type' => 'integer' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/shots/(?P<id>\d+)', [
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

		register_rest_route( 'worldgraph/v1', '/shots/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );

		// Reorder one complete Scene's Shot set within the global editorial cut.
		register_rest_route( 'worldgraph/v1', '/shots/reorder', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reorder_items' ],
			'permission_callback' => [ $this, 'check_reorder_permission' ],
			'args'                => [
				'scene_id'    => [
					'description' => 'Scene whose complete Shot set is being reordered.',
					'type'        => 'integer',
					'required'    => true,
				],
				'ordered_ids' => [
					'description' => 'Every Shot belonging to scene_id, exactly once, in the new order.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
				'sequence_id' => [
					'description' => 'Optional sequence term ID to assign the shots to.',
					'type'        => 'integer',
				],
			],
		] );
	}

	/**
	 * Authorize a Scene-scoped reorder before the callback runs.
	 *
	 * The shared service repeats this check and verifies every affected Shot.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public static function check_reorder_permission( \WP_REST_Request $request ) {
		$scene_id = absint( $request->get_param( 'scene_id' ) );
		if ( ! $scene_id || 'worldgraph_scene' !== get_post_type( $scene_id ) ) {
			return new \WP_Error( 'rest_invalid_scene', 'Scene not found.', [ 'status' => 404 ] );
		}
		if ( ! current_user_can( 'edit_post', $scene_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'You cannot edit this Scene.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Reorder shots and optionally assign them to a sequence.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function reorder_items( \WP_REST_Request $request ) {
		$scene_id    = absint( $request->get_param( 'scene_id' ) );
		$ordered_ids = (array) $request->get_param( 'ordered_ids' );
		$sequence_id = $request->get_param( 'sequence_id' ) ? absint( $request->get_param( 'sequence_id' ) ) : 0;
		$result      = \WorldGraph\Utils\worldgraph_reorder_scene_shots( $scene_id, $ordered_ids, $sequence_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['scene_id'] = $scene_id;
		return rest_ensure_response( $result );
	}

	/**
	 * Create a Shot without accepting an unscoped editorial position.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$order_error = $this->validate_direct_menu_order( $request );
		if ( is_wp_error( $order_error ) ) {
			return $order_error;
		}

		return parent::create_item( $request );
	}

	/**
	 * Update a Shot without bypassing the complete Scene reorder invariant.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$order_error = $this->validate_direct_menu_order( $request );
		if ( is_wp_error( $order_error ) ) {
			return $order_error;
		}

		return parent::update_item( $request );
	}

	/**
	 * Require all Shot ordering writes to use the Scene-scoped reorder route.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return true|\WP_Error
	 */
	private function validate_direct_menu_order( \WP_REST_Request $request ) {
		if ( null !== $request->get_param( 'menu_order' ) ) {
			return new \WP_Error(
				'worldgraph_shot_order_requires_scene',
				'Use /shots/reorder with a complete Scene membership to change Shot order.',
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Get graph connections.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_graph( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \WorldGraph\Utils\get_graph_entities( $post_id, 'worldgraph_shot' );
		return rest_ensure_response( $entities );
	}

	/**
	 * Override to add a display name for editorial workflows.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data = parent::get_item_data( $post, $params );

		$data['display_name'] = \WorldGraph\Utils\worldgraph_get_shot_display_name( $post->ID );
		$data['shot_type_label'] = \WorldGraph\Utils\worldgraph_shot_type_label(
			(string) get_post_meta( $post->ID, 'shot_type', true )
		);

		// Expose the scene this shot belongs to for editorial grouping.
		$scene_id = 0;
		foreach ( $data['relationships'] as $rel ) {
			if ( 'worldgraph_scene' === ( $rel['to_type'] ?? '' ) && ( $rel['type'] ?? '' ) === 'belongs_to' ) {
				$scene_id = (int) ( $rel['to_id'] ?? 0 );
				break;
			}
		}

		$scene = $scene_id ? get_post( $scene_id ) : null;
		$data['scene'] = $scene ? [
			'id'    => $scene->ID,
			'title' => $scene->post_title,
		] : null;

		return $data;
	}
}
