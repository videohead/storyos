<?php
/**
 * Graph REST API Controller for StoryOS.
 *
 * Provides global graph traversal and relationship queries.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Graph Controller class.
 */
class Graph_Controller extends Base_Controller {

	/**
	 * CPT slug (not used for graph controller).
	 *
	 * @var string
	 */
	protected $cpt = '';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'graph';

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
		// Get graph entities for a node.
		register_rest_route( 'storyos/v1', '/graph/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_graph' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			'args'                => [
				'id'     => [
					'description' => 'Node ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'type'   => [
					'description' => 'Node type (CPT slug).',
					'type'        => 'string',
				],
				'depth'  => [
					'description' => 'Traversal depth.',
					'type'        => 'integer',
					'default'     => 2,
				],
			],
		] );

		// Get all entities of a type.
		register_rest_route( 'storyos/v1', '/graph/entities', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_entities' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			'args'                => [
				'type'   => [
					'description' => 'Filter by CPT type.',
					'type'        => 'string',
				],
				'page'   => [ 'default' => 1 ],
				'per_page' => [ 'default' => 10, 'maximum' => 100 ],
			],
		] );

		// Get all relationships.
		register_rest_route( 'storyos/v1', '/graph/relationships', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_relationships' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			'args'                => [
				'from_id'  => [ 'type' => 'integer' ],
				'to_id'    => [ 'type' => 'integer' ],
				'from_type' => [ 'type' => 'string' ],
				'to_type'  => [ 'type' => 'string' ],
				'rel_type' => [ 'type' => 'string' ],
			],
		] );

		// Create relationship.
		register_rest_route( 'storyos/v1', '/graph/relationships', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'create_relationship' ],
			'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			'args'                => [
				'from_id'  => [
					'description' => 'Source node ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'from_type' => [
					'description' => 'Source node type.',
					'type'        => 'string',
					'required'    => true,
				],
				'to_id'    => [
					'description' => 'Target node ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'to_type'  => [
					'description' => 'Target node type.',
					'type'        => 'string',
					'required'    => true,
				],
				'type'     => [
					'description' => 'Relationship type.',
					'type'        => 'string',
					'default'     => 'related_to',
				],
			],
		] );

		// Delete relationship.
		register_rest_route( 'storyos/v1', '/graph/relationships/(?P<from_id>\d+)/(?P<to_id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => [ __CLASS__, 'delete_relationship' ],
			'permission_callback' => [ __CLASS__, 'check_delete_permission' ],
			'args'                => [
				'from_id'  => [ 'required' => true ],
				'to_id'    => [ 'required' => true ],
			],
		] );
	}

	/**
	 * Get graph entities for a node.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$node_type = $request->get_param( 'type' ) ?: get_post_type( $post_id );
		$depth = absint( $request->get_param( 'depth' ) ) ?: 2;

		$entities = \StoryOS\Utils\get_graph_entities( $post_id, $node_type, $depth );
		return rest_ensure_response( $entities );
	}

	/**
	 * Get all entities.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_entities( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$page = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;

		$args = [
			'post_type'      => $type ?: 'storyos_project',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		];

		$query = new \WP_Query( $args );
		$entities = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$entities[] = [
					'id'    => $post->ID,
					'type'  => $post->post_type,
					'title' => $post->post_title,
					'slug'  => $post->post_name,
				];
			}
			wp_reset_postdata();
		}

		$response = rest_ensure_response( $entities );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Get relationships.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_relationships( WP_REST_Request $request ) {
		$params = [
			'from_id'  => $request->get_param( 'from_id' ) ? absint( $request->get_param( 'from_id' ) ) : null,
			'to_id'    => $request->get_param( 'to_id' ) ? absint( $request->get_param( 'to_id' ) ) : null,
			'from_type' => $request->get_param( 'from_type' ),
			'to_type'  => $request->get_param( 'to_type' ),
			'rel_type' => $request->get_param( 'rel_type' ),
		];

		$relationships = [];

		// Get relationship types.
		$types = \StoryOS\Utils\relationship_types();

		if ( $params['from_id'] ) {
			$rels = \StoryOS\Utils\get_relationships( $params['from_id'], $params['from_type'] ?: get_post_type( $params['from_id'] ), 'outgoing' );
			$relationships = array_merge( $relationships, $rels );
		}

		if ( $params['to_id'] ) {
			$rels = \StoryOS\Utils\get_relationships( $params['to_id'], $params['to_type'] ?: get_post_type( $params['to_id'] ), 'incoming' );
			$relationships = array_merge( $relationships, $rels );
		}

		// Filter by type if specified.
		if ( $params['rel_type'] ) {
			$relationships = array_filter( $relationships, fn( $r ) => $r['type'] === $params['rel_type'] );
		}

		return rest_ensure_response( array_values( $relationships ) );
	}

	/**
	 * Create a relationship.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_relationship( WP_REST_Request $request ) {
		$from_id = absint( $request->get_param( 'from_id' ) );
		$to_id = absint( $request->get_param( 'to_id' ) );
		$from_type = $request->get_param( 'from_type' );
		$to_type = $request->get_param( 'to_type' );
		$rel_type = $request->get_param( 'type' ) ?: 'related_to';

		$result = \StoryOS\Utils\add_relationship(
			$from_id,
			$from_type,
			$to_id,
			$to_type,
			$rel_type
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [
			'message' => 'Relationship created successfully.',
			'relationship' => [
				'from_id'  => $from_id,
				'from_type' => $from_type,
				'to_id'    => $to_id,
				'to_type'  => $to_type,
				'type'     => $rel_type,
			],
		] );
	}

	/**
	 * Delete a relationship.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_relationship( WP_REST_Request $request ) {
		$from_id = absint( $request->get_param( 'from_id' ) );
		$to_id = absint( $request->get_param( 'to_id' ) );

		$result = \StoryOS\Utils\remove_relationship( $from_id, $to_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'message' => 'Relationship deleted successfully.' ] );
	}
}
