<?php
/**
 * Shots REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * Shots Controller class.
 */
class Shots_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_shot';

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
		register_rest_route( 'storyos/v1', '/shots', [
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

		register_rest_route( 'storyos/v1', '/shots/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/shots/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );

		// Reorder shots within the editorial cut (optionally within a sequence).
		register_rest_route( 'storyos/v1', '/shots/reorder', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reorder_items' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'ordered_ids' => [
					'description' => 'Shot post IDs in the new editorial order.',
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
	 * Reorder shots and optionally assign them to a sequence.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function reorder_items( \WP_REST_Request $request ) {
		$ordered_ids = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'ordered_ids' ) ) ) );
		$sequence_id = $request->get_param( 'sequence_id' ) ? absint( $request->get_param( 'sequence_id' ) ) : 0;

		if ( empty( $ordered_ids ) ) {
			return new WP_Error( 'rest_invalid_ordered_ids', 'ordered_ids cannot be empty.', [ 'status' => 400 ] );
		}

		$sequence = null;
		if ( $sequence_id ) {
			$sequence = get_term( $sequence_id, 'storyos_sequence' );
			if ( ! $sequence || is_wp_error( $sequence ) ) {
				return new WP_Error( 'rest_invalid_sequence', 'Sequence term not found.', [ 'status' => 404 ] );
			}
		}

		$updated = [];
		foreach ( $ordered_ids as $index => $shot_id ) {
			$post = get_post( $shot_id );
			if ( ! $post || 'storyos_shot' !== $post->post_type ) {
				continue;
			}

			wp_update_post( [
				'ID'         => $post->ID,
				'menu_order' => $index + 1,
			] );

			if ( $sequence ) {
				wp_set_object_terms( $post->ID, [ (int) $sequence->term_id ], 'storyos_sequence', false );
			}

			$updated[] = $post->ID;
		}

		return rest_ensure_response( [ 'updated' => $updated ] );
	}

	/**
	 * Get graph connections.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_graph( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$entities = \StoryOS\Utils\get_graph_entities( $post_id, 'storyos_shot' );
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

		$data['display_name'] = \StoryOS\Utils\storyos_get_shot_display_name( $post->ID );
		$data['shot_type_label'] = \StoryOS\Utils\storyos_shot_type_label(
			(string) get_post_meta( $post->ID, 'shot_type', true )
		);

		// Expose the scene this shot belongs to for editorial grouping.
		$scene_id = 0;
		foreach ( $data['relationships'] as $rel ) {
			if ( 'storyos_scene' === ( $rel['to_type'] ?? '' ) && ( $rel['type'] ?? '' ) === 'belongs_to' ) {
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
