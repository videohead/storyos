<?php
/**
 * Sequences REST API Controller for World Graph Studio.
 *
 * Manages the editorial sequence terms used by the editorial cut workflow:
 * ordering sequences, listing their shots/scenes, and assigning shots to a
 * sequence.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

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
		register_rest_route( 'worldgraph/v1', '/sequences', [
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

		register_rest_route( 'worldgraph/v1', '/sequences/(?P<id>\d+)', [
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

		register_rest_route( 'worldgraph/v1', '/sequences/reorder', [
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

		register_rest_route( 'worldgraph/v1', '/sequences/(?P<id>\d+)/shots', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'assign_shots' ],
			'permission_callback' => [ $this, 'check_assign_shots_permission' ],
			'args'                => [
				'id'       => [ 'type' => 'integer' ],
				'shot_ids' => [
					'description' => 'Shot post IDs to assign to this sequence.',
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
				],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/sequences/(?P<id>\d+)/scenes', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'assign_scenes' ],
			'permission_callback' => [ $this, 'check_assign_scenes_permission' ],
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
	 * Require taxonomy-management access for the Sequence editorial workspace.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_read_permission( WP_REST_Request $request ) {
		unset( $request );
		return $this->check_sequence_management_capability();
	}

	/**
	 * Require taxonomy-management access when creating or reordering Sequences.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_create_permission( WP_REST_Request $request ) {
		unset( $request );
		return $this->check_sequence_management_capability();
	}

	/**
	 * Resolve the Sequence taxonomy and enforce its native management cap.
	 *
	 * @return true|WP_Error
	 */
	private function check_sequence_management_capability() {
		$taxonomy = get_taxonomy( \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( ! $taxonomy ) {
			return new WP_Error( 'rest_sequence_taxonomy_unavailable', 'Sequence taxonomy is unavailable.', [ 'status' => 500 ] );
		}
		if ( ! current_user_can( $taxonomy->cap->manage_terms ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot manage Sequence terms.', [ 'status' => is_user_logged_in() ? 403 : 401 ] );
		}

		return true;
	}

	/**
	 * Check whether the current request may assign Shots to a Sequence.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_assign_shots_permission( WP_REST_Request $request ) {
		return $this->check_assignment_permission( $request, 'shot_ids', 'worldgraph_shot', 'Shot' );
	}

	/**
	 * Check whether the current request may assign Scenes to a Sequence.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_assign_scenes_permission( WP_REST_Request $request ) {
		return $this->check_assignment_permission( $request, 'scene_ids', 'worldgraph_scene', 'Scene' );
	}

	/**
	 * Validate a Sequence assignment request before any write is attempted.
	 *
	 * @param WP_REST_Request $request   REST request.
	 * @param string          $param     Request parameter containing post IDs.
	 * @param string          $post_type Required post type.
	 * @param string          $label     Human-readable object label.
	 * @return true|WP_Error
	 */
	private function check_assignment_permission( WP_REST_Request $request, string $param, string $post_type, string $label ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term    = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$taxonomy = get_taxonomy( \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( ! $taxonomy ) {
			return new WP_Error( 'rest_sequence_taxonomy_unavailable', 'Sequence taxonomy is unavailable.', [ 'status' => 500 ] );
		}
		if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot assign this Sequence taxonomy.', [ 'status' => 403 ] );
		}

		$ids = $this->validate_assignment_ids( $request->get_param( $param ), $param, $post_type, $label );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		foreach ( $ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error(
					'rest_forbidden',
					sprintf( 'You cannot edit %s ID %d.', $label, $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Require a non-empty, unique list of positive IDs of one exact post type.
	 *
	 * @param mixed  $submitted Submitted request value.
	 * @param string $param     Request parameter name.
	 * @param string $post_type Required post type.
	 * @param string $label     Human-readable object label.
	 * @return array<int, int>|WP_Error
	 */
	private function validate_assignment_ids( $submitted, string $param, string $post_type, string $label ) {
		if ( ! is_array( $submitted ) || empty( $submitted ) ) {
			return new WP_Error( 'rest_invalid_' . $param, $param . ' cannot be empty.', [ 'status' => 400 ] );
		}

		$ids = [];
		foreach ( $submitted as $submitted_id ) {
			$is_integer = is_int( $submitted_id ) || ( is_string( $submitted_id ) && ctype_digit( $submitted_id ) );
			$post_id    = $is_integer ? (int) $submitted_id : 0;
			if ( $post_id < 1 ) {
				return new WP_Error( 'rest_invalid_' . $param, $param . ' must contain only positive post IDs.', [ 'status' => 400 ] );
			}
			$ids[] = $post_id;
		}

		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			return new WP_Error( 'rest_invalid_' . $param, $param . ' cannot contain duplicate post IDs.', [ 'status' => 400 ] );
		}

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post_type !== $post->post_type ) {
				return new WP_Error(
					'rest_invalid_' . $param,
					sprintf( '%s ID %d is invalid.', $label, $post_id ),
					[ 'status' => 400 ]
				);
			}
		}

		return $ids;
	}

	/**
	 * Read the Sequence term IDs currently assigned to an object.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, int>|WP_Error
	 */
	private function get_object_sequence_term_ids( int $post_id ) {
		if ( function_exists( 'wp_get_object_terms' ) ) {
			$term_ids = wp_get_object_terms(
				$post_id,
				\WorldGraph\Taxonomies\Sequence::TAXONOMY,
				[ 'fields' => 'ids' ]
			);
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}

			return array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );
		}

		// Lightweight test environments may not provide wp_get_object_terms().
		$terms = get_the_terms( $post_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( ! $terms ) {
			return [];
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $term ): int {
							return absint( is_object( $term ) ? $term->term_id : $term );
						},
						(array) $terms
					)
				)
			)
		);
	}

	/**
	 * Replace each object's Sequence terms, rolling back prior writes on error.
	 *
	 * Original assignments are captured for every object before the first write,
	 * so a lookup failure cannot leave a partially-updated request.
	 *
	 * @param array<int, int> $post_ids Sequence object post IDs.
	 * @param int             $term_id  Destination Sequence term ID.
	 * @return array<int, int>|WP_Error
	 */
	private function assign_sequence_term( array $post_ids, int $term_id ) {
		$original_terms = [];
		foreach ( $post_ids as $post_id ) {
			$term_ids = $this->get_object_sequence_term_ids( $post_id );
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}
			$original_terms[ $post_id ] = $term_ids;
		}

		$updated = [];
		foreach ( $post_ids as $post_id ) {
			$attempted = array_merge( $updated, [ $post_id ] );
			$result    = wp_set_object_terms(
				$post_id,
				[ $term_id ],
				\WorldGraph\Taxonomies\Sequence::TAXONOMY,
				false
			);
			if ( is_wp_error( $result ) ) {
				foreach ( $attempted as $rollback_id ) {
					// Best effort: preserve the original error even if rollback fails.
					wp_set_object_terms(
						$rollback_id,
						$original_terms[ $rollback_id ],
						\WorldGraph\Taxonomies\Sequence::TAXONOMY,
						false
					);
				}

				return $result;
			}

			$updated[] = $post_id;
		}

		return $updated;
	}

	/**
	 * Get ordered sequence terms with shot/scene counts.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$sequences = \WorldGraph\Utils\worldgraph_get_ordered_sequences();

		foreach ( $sequences as $index => $sequence ) {
			$sequences[ $index ]['external_id'] = (string) get_term_meta( (int) $sequence['id'], 'external_id', true );
			$shots  = \WorldGraph\Utils\worldgraph_get_sequence_object_ids( (int) $sequence['id'], 'worldgraph_shot' );
			$scenes = \WorldGraph\Utils\worldgraph_get_sequence_object_ids( (int) $sequence['id'], 'worldgraph_scene' );

			$sequences[ $index ]['shot_count']  = count( array_filter( $shots, static fn( $post_id ): bool => current_user_can( 'read_post', $post_id ) ) );
			$sequences[ $index ]['scene_count'] = count( array_filter( $scenes, static fn( $post_id ): bool => current_user_can( 'read_post', $post_id ) ) );
			$sequences[ $index ]['edit_link'] = admin_url( 'term.php?taxonomy=' . \WorldGraph\Taxonomies\Sequence::TAXONOMY . '&tag_ID=' . $sequence['id'] );
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
		$term = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$shot_ids  = \WorldGraph\Utils\worldgraph_get_sequence_object_ids( (int) $term->term_id, 'worldgraph_shot' );
		$scene_ids = \WorldGraph\Utils\worldgraph_get_sequence_object_ids( (int) $term->term_id, 'worldgraph_scene' );

		// Order shots by menu_order (the editorial cut order).
		$shots = [];
		if ( is_array( $shot_ids ) && ! empty( $shot_ids ) ) {
			$shot_posts = get_posts( [
				'post_type'      => 'worldgraph_shot',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'include'        => $shot_ids,
			] );

			foreach ( $shot_posts as $shot ) {
				if ( ! current_user_can( 'read_post', $shot->ID ) ) {
					continue;
				}
				$shots[] = [
					'id'           => $shot->ID,
					'external_id'  => (string) get_post_meta( $shot->ID, 'external_id', true ),
					'title'        => $shot->post_title,
					'display_name' => \WorldGraph\Utils\worldgraph_get_shot_display_name( $shot->ID ),
					'menu_order'   => (int) $shot->menu_order,
				];
			}
		}

		// Order scenes by menu_order within the sequence.
		$scenes = [];
		if ( is_array( $scene_ids ) && ! empty( $scene_ids ) ) {
			$scene_posts = get_posts( [
				'post_type'      => 'worldgraph_scene',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'include'        => $scene_ids,
			] );
			usort(
				$scene_posts,
				static function( \WP_Post $left, \WP_Post $right ): int {
					$left_sequence_order  = absint( get_post_meta( $left->ID, 'sequence_order', true ) );
					$right_sequence_order = absint( get_post_meta( $right->ID, 'sequence_order', true ) );
					$left_order            = $left_sequence_order ?: PHP_INT_MAX;
					$right_order           = $right_sequence_order ?: PHP_INT_MAX;
					$comparison            = $left_order <=> $right_order;

					return 0 !== $comparison ? $comparison : (int) $left->menu_order <=> (int) $right->menu_order;
				}
			);

			foreach ( $scene_posts as $scene ) {
				if ( ! current_user_can( 'read_post', $scene->ID ) ) {
					continue;
				}
				$scenes[] = [
					'id'             => $scene->ID,
					'external_id'    => (string) get_post_meta( $scene->ID, 'external_id', true ),
					'title'          => $scene->post_title,
					'menu_order'     => (int) $scene->menu_order,
					'sequence_order' => get_post_meta( $scene->ID, 'sequence_order', true ),
				];
			}
		}

		return rest_ensure_response( [
			'id'          => $term->term_id,
			'external_id' => (string) get_term_meta( $term->term_id, 'external_id', true ),
			'name'        => $term->name,
			'slug'       => $term->slug,
			'order'      => \WorldGraph\Utils\worldgraph_get_sequence_order( $term->term_id ),
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

		$term = wp_insert_term( $name, \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = (int) $term['term_id'];

		// Append to the end of the current sequence order.
		$sequences = \WorldGraph\Utils\worldgraph_get_ordered_sequences();
		$next_order = count( $sequences ) + 1;
		\WorldGraph\Utils\worldgraph_set_sequence_order( $term_id, $next_order );

		return rest_ensure_response( [
			'id'          => $term_id,
			'external_id' => '',
			'name'        => $name,
			'order'       => $next_order,
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
			$term = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			\WorldGraph\Utils\worldgraph_set_sequence_order( $term->term_id, $index + 1 );
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
		$term = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$shot_ids = $this->validate_assignment_ids( $request->get_param( 'shot_ids' ), 'shot_ids', 'worldgraph_shot', 'Shot' );
		if ( is_wp_error( $shot_ids ) ) {
			return $shot_ids;
		}

		$updated = $this->assign_sequence_term( $shot_ids, (int) $term->term_id );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return rest_ensure_response( [
			'updated'  => $updated,
			'sequence' => [
				'id'          => (int) $term->term_id,
				'external_id' => (string) get_term_meta( $term->term_id, 'external_id', true ),
				'name'        => $term->name,
			],
		] );
	}

	/**
	 * Assign scenes to a sequence.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function assign_scenes( WP_REST_Request $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$scene_ids = $this->validate_assignment_ids( $request->get_param( 'scene_ids' ), 'scene_ids', 'worldgraph_scene', 'Scene' );
		if ( is_wp_error( $scene_ids ) ) {
			return $scene_ids;
		}

		$existing      = \WorldGraph\Utils\worldgraph_get_sequence_object_ids( (int) $term->term_id, 'worldgraph_scene' );
		$current_order = count( $existing );

		$updated = $this->assign_sequence_term( $scene_ids, (int) $term->term_id );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		foreach ( $scene_ids as $scene_id ) {
			$current_order++;
			update_post_meta( $scene_id, 'sequence_order', $current_order );
		}

		return rest_ensure_response(
			[
				'updated'  => $updated,
				'sequence' => [
					'id'          => (int) $term->term_id,
					'external_id' => (string) get_term_meta( $term->term_id, 'external_id', true ),
					'name'        => $term->name,
				],
			]
		);
	}

	/**
	 * Check taxonomy-native permission for deleting a Sequence term.
	 *
	 * Sequence route IDs are term IDs, so Base_Controller's post-object
	 * capability check is not applicable here.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_delete_permission( WP_REST_Request $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term    = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		$taxonomy = get_taxonomy( \WorldGraph\Taxonomies\Sequence::TAXONOMY );
		if ( ! $taxonomy ) {
			return new WP_Error( 'rest_sequence_taxonomy_unavailable', 'Sequence taxonomy is unavailable.', [ 'status' => 500 ] );
		}
		if ( ! current_user_can( $taxonomy->cap->delete_terms ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot delete Sequence terms.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Delete a sequence term.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$term_id = absint( $request->get_param( 'id' ) );
		$term = get_term( $term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'rest_sequence_not_found', 'Sequence term not found.', [ 'status' => 404 ] );
		}

		wp_delete_term( $term->term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY );

		return rest_ensure_response( [ 'message' => 'Sequence deleted.' ] );
	}
}
