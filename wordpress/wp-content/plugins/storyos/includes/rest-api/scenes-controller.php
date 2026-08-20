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
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
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
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/scenes/(?P<id>\d+)', [
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

		register_rest_route( 'storyos/v1', '/scenes/(?P<id>\d+)/graph', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
		] );

		// Reorder scenes within a sequence (or across the project by menu_order).
		register_rest_route( 'storyos/v1', '/scenes/reorder', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reorder_items' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'ordered_ids' => [
					'description' => 'Scene post IDs in the new order.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
				'sequence_id' => [
					'description' => 'Optional sequence term ID the scenes belong to.',
					'type'        => 'integer',
				],
			],
		] );
	}

	/**
	 * Reorder scenes and optionally assign them to a sequence.
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
		foreach ( $ordered_ids as $index => $scene_id ) {
			$post = get_post( $scene_id );
			if ( ! $post || 'storyos_scene' !== $post->post_type ) {
				continue;
			}

			wp_update_post( [
				'ID'         => $post->ID,
				'menu_order' => $index + 1,
			] );

			if ( $sequence ) {
				wp_set_object_terms( $post->ID, [ (int) $sequence->term_id ], 'storyos_sequence', false );
				update_post_meta( $post->ID, 'sequence_order', $index + 1 );
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

		// Position of the scene within its sequence / the project cut.
		$data['meta']['sequence_order'] = get_post_meta( $post->ID, 'sequence_order', true );
		$data['meta']['menu_order']     = (int) $post->menu_order;

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
		$related_ids = [];

		// Support legacy parent-owned edges as well as the canonical child-owned
		// Shot.scene relationship without double-counting reciprocal records.
		foreach ( \StoryOS\Utils\get_relationships( $post_id, $from_cpt, 'outgoing' ) as $relationship ) {
			if ( $related_cpt === (string) ( $relationship['to_type'] ?? '' ) ) {
				$related_ids[ (int) ( $relationship['to_id'] ?? 0 ) ] = true;
			}
		}

		foreach ( \StoryOS\Utils\get_relationships( $post_id, $from_cpt, 'incoming' ) as $relationship ) {
			if ( $related_cpt === (string) ( $relationship['from_type'] ?? '' ) ) {
				$related_ids[ (int) ( $relationship['from_id'] ?? 0 ) ] = true;
			}
		}

		unset( $related_ids[0] );
		return count( $related_ids );
	}
}
