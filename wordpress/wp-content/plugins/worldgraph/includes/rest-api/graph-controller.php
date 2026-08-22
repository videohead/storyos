<?php
/**
 * Graph REST API Controller for World Graph Studio.
 *
 * Provides global graph traversal and relationship queries.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

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
		$instance = new self();
		add_action( 'rest_api_init', [ $instance, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Get graph entities for a node.
		register_rest_route( 'worldgraph/v1', '/graph/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_graph' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
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
		register_rest_route( 'worldgraph/v1', '/graph/entities', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_entities' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
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
		register_rest_route( 'worldgraph/v1', '/graph/relationships', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_relationships' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'from_id'  => [ 'type' => 'integer' ],
				'to_id'    => [ 'type' => 'integer' ],
				'from_type' => [ 'type' => 'string' ],
				'to_type'  => [ 'type' => 'string' ],
				'rel_type' => [ 'type' => 'string' ],
			],
		] );

		// Create relationship.
		register_rest_route( 'worldgraph/v1', '/graph/relationships', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_relationship' ],
			'permission_callback' => [ $this, 'check_relationship_create_permission' ],
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
		register_rest_route( 'worldgraph/v1', '/graph/relationships/(?P<from_id>\d+)/(?P<to_id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'delete_relationship' ],
			'permission_callback' => [ $this, 'check_relationship_delete_permission' ],
			'args'                => [
				'from_id'  => [ 'required' => true ],
				'to_id'    => [ 'required' => true ],
			],
		] );
	}

	/**
	 * Preserve the generic create gate and require edit access to both nodes.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_relationship_create_permission( WP_REST_Request $request ) {
		$permission = parent::check_create_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		return $this->check_relationship_endpoint_edit_permissions( $request );
	}

	/**
	 * Preserve the generic delete gate and require edit access to both nodes.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_relationship_delete_permission( WP_REST_Request $request ) {
		$permission = parent::check_delete_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		return $this->check_relationship_endpoint_edit_permissions( $request );
	}

	/**
	 * Require edit access to both endpoints of a relationship mutation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	private function check_relationship_endpoint_edit_permissions( WP_REST_Request $request ) {
		$from_id = absint( $request->get_param( 'from_id' ) );
		$to_id   = absint( $request->get_param( 'to_id' ) );
		if ( ! $from_id || ! $to_id || ! get_post( $from_id ) || ! get_post( $to_id ) ) {
			return new WP_Error( 'rest_post_not_found', 'Relationship endpoint not found.', [ 'status' => 404 ] );
		}

		$can_edit_from = current_user_can( 'edit_post', $from_id );
		$can_edit_to   = current_user_can( 'edit_post', $to_id );
		if ( ! $can_edit_from || ! $can_edit_to ) {
			return new WP_Error( 'rest_forbidden', 'You cannot edit both relationship endpoints.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Whether the current user may read both endpoint posts of an edge.
	 *
	 * @param array<string, mixed> $relationship Relationship record.
	 * @return bool
	 */
	private static function can_read_relationship( array $relationship ): bool {
		$from_id = absint( $relationship['from_id'] ?? 0 );
		$to_id   = absint( $relationship['to_id'] ?? 0 );
		if ( ! $from_id || ! $to_id ) {
			return false;
		}

		$can_read_from = current_user_can( 'read_post', $from_id );
		$can_read_to   = current_user_can( 'read_post', $to_id );
		return $can_read_from && $can_read_to;
	}

	/**
	 * Get graph entities for a node.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot read this post.', [ 'status' => 403 ] );
		}

		$node_type = $request->get_param( 'type' ) ?: $post->post_type;
		$depth     = absint( $request->get_param( 'depth' ) ) ?: 2;

		$entities = \WorldGraph\Utils\get_graph_entities( $post_id, $node_type, $depth );
		$entities = array_filter(
			$entities,
			static function( array $entity ): bool {
				$entity_id = absint( $entity['id'] ?? 0 );
				return $entity_id > 0 && current_user_can( 'read_post', $entity_id );
			}
		);
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
			'post_type'      => $type ?: 'worldgraph_project',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'nopaging'       => true,
		];

		$query = new \WP_Query( $args );
		$readable_posts = array_values(
			array_filter(
				$query->posts,
				static function( \WP_Post $post ): bool {
					return current_user_can( 'read_post', $post->ID );
				}
			)
		);
		$total          = count( $readable_posts );
		$page_posts     = array_slice( $readable_posts, ( $page - 1 ) * $per_page, $per_page );
		$entities       = [];

		if ( ! empty( $page_posts ) ) {
			foreach ( $page_posts as $post ) {
					$entities[] = [
						'id'          => $post->ID,
						'external_id' => (string) get_post_meta( $post->ID, 'external_id', true ),
						'type'        => $post->post_type,
						'title'       => $post->post_title,
						'slug'        => $post->post_name,
					];
			}
		}
		wp_reset_postdata();

		$response = rest_ensure_response( $entities );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

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
		$types = \WorldGraph\Utils\relationship_types();

		if ( $params['from_id'] ) {
			$rels = \WorldGraph\Utils\get_relationships( $params['from_id'], $params['from_type'] ?: get_post_type( $params['from_id'] ), 'outgoing' );
			$relationships = array_merge( $relationships, $rels );
		}

		if ( $params['to_id'] ) {
			$rels = \WorldGraph\Utils\get_relationships( $params['to_id'], $params['to_type'] ?: get_post_type( $params['to_id'] ), 'incoming' );
			$relationships = array_merge( $relationships, $rels );
		}

		$relationships = array_filter(
			$relationships,
			static function( array $relationship ): bool {
				return self::can_read_relationship( $relationship );
			}
		);

		// Filter by type if specified.
		if ( $params['rel_type'] ) {
			$relationships = array_filter( $relationships, fn( $r ) => $r['type'] === $params['rel_type'] );
		}
		$relationships = array_map(
			static function( array $relationship ): array {
				$relationship['from_external_id'] = (string) get_post_meta( absint( $relationship['from_id'] ?? 0 ), 'external_id', true );
				$relationship['to_external_id']   = (string) get_post_meta( absint( $relationship['to_id'] ?? 0 ), 'external_id', true );
				return $relationship;
			},
			$relationships
		);

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

		$result = \WorldGraph\Utils\add_relationship(
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
					'from_id'          => $from_id,
					'from_external_id' => (string) get_post_meta( $from_id, 'external_id', true ),
					'from_type'        => $from_type,
					'to_id'            => $to_id,
					'to_external_id'   => (string) get_post_meta( $to_id, 'external_id', true ),
					'to_type'          => $to_type,
					'type'             => $rel_type,
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

		$result = \WorldGraph\Utils\remove_relationship( $from_id, $to_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'message' => 'Relationship deleted successfully.' ] );
	}
}
