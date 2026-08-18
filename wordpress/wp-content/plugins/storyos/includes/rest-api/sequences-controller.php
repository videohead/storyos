<?php
/**
 * Sequences REST API Controller for StoryOS.
 *
 * Manages the editorial sequence terms used by the editorial cut workflow:
 * ordering sequences, listing their shots/scenes, and assigning shots to a
 * sequence.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Sequences Controller class.
 */
class Sequences_Controller extends Base_Controller {

	/**
	 * CPT slug (not used; sequences are taxonomy terms).
	 *
	 * @var string
	 */
	protected $cpt = '';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'sequences';

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
		register_rest_route( 'storyos/v1', '/sequences', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
				'args'                => [
					'name' => [
						'description' => 'Sequence name.',
						'type'        => 'string',
						'required'    => true,
					],
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/sequences/(?P<id>\d+)', [
			'args' => [ 'id' => [ 'type' => 'integer' ] ],
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'check_delete_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/sequences/reorder', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reorder_items' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'ordered_ids' => [
					'description' => 'Sequence term IDs in the new editorial order.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/sequences/(?P<id>\d+)/shots', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'assign_shots' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'id'         => [ 'type' => 'integer' ],
				'shot_ids'   => [
					'description' => 'Shot post IDs to assign to this sequence.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
				'ordered_ids' => [
					'description' => 'Optional. When provided, sets the shot cut order within the sequence.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/sequences/(?P<id>\d+)/scenes', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'assign_scenes' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'id'         => [ 'type' => 'integer' ],
				'scene_ids'  => [
					'description' => 'Scene post IDs to assign to this sequence.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
			],
		] );

	}

	/**
	 * Get ordered sequence terms with shot/scene counts.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$sequences = \StoryOS\Utils\storyos_get_ordered_sequences();

		foreach ( $sequences as $index => $sequence ) {
			$shots = \StoryOS\Utils\get_objects_in_term(
				$sequence['id'],
				\StoryOS\Taxonomies\Sequence::TAXONOMY,
				[ 'storyos_shot' ]
			);

			$scenes = \StoryOS\Utils\get_objects_in_term(
				$sequence['id'],
				\StoryOS\Taxonomies\Sequence::TAXONOMY,
				[ 'storyos_scene' ]
			);

			$sequences[ $index ]['shot_count'] = is_array( $shots ) ? count( $shots ) : 0;
			$sequences[ $index ]['scene_count'] = is_array( $scenes ) ? count( $scenes ) : 0;
			$sequences[ $index ]['edit_link'] = admin_url( 'term.php?taxonomy=' . \StoryOS\Taxonomies\Sequence::TAXONOMY . '&tag_ID=' . $sequence['id'] );
		}

		return rest_ensure_response( $sequences );
	}

	/**
	 * Get a single sequence term with its ordered shots and scenes.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$shot_ids = \StoryOS\Utils\get_objects_in_term( $term->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY, [ 'storyos_shot' ] );
		$scene_ids = \StoryOS\Utils\get_objects_in_term( $term->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY, [ 'storyos_scene' ] );

		// Order shots by menu_order (the editorial cut order).
		$shots = [];
		if ( is_array( $shot_ids ) && ! empty( $shot_ids ) ) {
			$shot_posts = get_posts( [
				'post_type'      => 'storyos_shot',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'include'        => $shot_ids,
			] );

			foreach ( $shot_posts as $shot ) {
				$shots[] = [
					'id'           => $shot->ID,
					'title'        => $shot->post_title,
					'display_name' => \StoryOS\Utils\storyos_get_shot_display_name( $shot->ID ),
					'menu_order'   => (int) $shot->menu_order,
				];
			}
		}

		// Order scenes by menu_order within the sequence.
		$scenes = [];
		if ( is_array( $scene_ids ) && ! empty( $scene_ids ) ) {
			$scene_posts = get_posts( [
				'post_type'      => 'storyos_scene',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'include'        => $scene_ids,
			] );

			foreach ( $scene_posts as $scene ) {
				$scenes[] = [
					'id'             => $scene->ID,
					'title'          => $scene->post_title,
					'menu_order'     => (int) $scene->menu_order,
					'sequence_order' => get_post_meta( $scene->ID, 'sequence_order', true ),
				];
			}
		}

		return rest_ensure_response( [
			'id'         => $term->term_id,
			'name'       => $term->name,
			'slug'       => $term->slug,
			'order'      => \StoryOS\Utils\storyos_get_sequence_order( $term->term_id ),
			'shots'      => $shots,
			'scenes'     => $scenes,
		] );
	}

	/**
	 * Create a sequence term.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'rest_invalid_sequence_name', 'Sequence name is required.', [ 'status' => 400 ] );
		}

		$term = wp_insert_term( $name, \StoryOS\Taxonomies\Sequence::TAXONOMY );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = (int) $term['term_id'];

		// Append to the end of the current sequence order.
		$sequences = \StoryOS\Utils\storyos_get_ordered_sequences();
		$next_order = count( $sequences ) + 1;
		\StoryOS\Utils\storyos_set_sequence_order( $term_id, $next_order );

		return rest_ensure_response( [
			'id'    => $term_id,
			'name'  => $name,
			'order' => $next_order,
		] );
	}

	/**
	 * Reorder sequences for the editorial cut.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_items( WP_REST_Request $request ) {
		$ordered_ids = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'ordered_ids' ) ) ) );

		if ( empty( $ordered_ids ) ) {
			return new WP_Error( 'rest_invalid_ordered_ids', 'ordered_ids cannot be empty.', [ 'status' => 400 ] );
		}

		$updated = [];
		foreach ( $ordered_ids as $index => $term_id ) {
			$term = get_term( $term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			\StoryOS\Utils\storyos_set_sequence_order( $term->term_id, $index + 1 );
			$updated[] = (int) $term->term_id;
		}

		return rest_ensure_response( [ 'updated' => $updated ] );
	}

	/**
	 * Assign shots to a sequence.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign_shots( WP_REST_Request $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$shot_ids = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'shot_ids' ) ) ) );
		$ordered_ids = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'ordered_ids' ) ) ) );

		if ( empty( $shot_ids ) ) {
			return new WP_Error( 'rest_invalid_shot_ids', 'shot_ids cannot be empty.', [ 'status' => 400 ] );
		}

		$updated = [];
		foreach ( $shot_ids as $shot_id ) {
			$post = get_post( $shot_id );
			if ( ! $post || 'storyos_shot' !== $post->post_type ) {
				continue;
			}

			wp_set_object_terms( $post->ID, [ (int) $term->term_id ], \StoryOS\Taxonomies\Sequence::TAXONOMY, false );

			if ( ! empty( $ordered_ids ) ) {
				$position = array_search( $post->ID, $ordered_ids, true );
				if ( false !== $position ) {
					wp_update_post( [
						'ID'         => $post->ID,
						'menu_order' => $position + 1,
					] );
				}
			}

			$updated[] = $post->ID;
		}

		return rest_ensure_response( [ 'updated' => $updated, 'sequence' => [ 'id' => (int) $term->term_id, 'name' => $term->name ] ] );
	}

	/**
	 * Assign scenes to a sequence.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign_scenes( WP_REST_Request $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$scene_ids = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'scene_ids' ) ) ) );
		if ( empty( $scene_ids ) ) {
			return new WP_Error( 'rest_invalid_scene_ids', 'scene_ids cannot be empty.', [ 'status' => 400 ] );
		}

		$existing = \StoryOS\Utils\get_objects_in_term( $term->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY, [ 'storyos_scene' ] );
		$current_order = is_array( $existing ) ? count( $existing ) : 0;

		$updated = [];
		foreach ( $scene_ids as $scene_id ) {
			$post = get_post( $scene_id );
			if ( ! $post || 'storyos_scene' !== $post->post_type ) {
				continue;
			}

			wp_set_object_terms( $post->ID, [ (int) $term->term_id ], \StoryOS\Taxonomies\Sequence::TAXONOMY, false );
			$current_order++;
			update_post_meta( $post->ID, 'sequence_order', $current_order );
			$updated[] = $post->ID;
		}

		return rest_ensure_response( [ 'updated' => $updated, 'sequence' => [ 'id' => (int) $term->term_id, 'name' => $term->name ] ] );
	}

	/**
	 * Delete a sequence term.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		wp_delete_term( $term->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY );

		return rest_ensure_response( [ 'message' => 'Sequence deleted.' ] );
	}
}