<?php
/**
 * Editorial REST API Controller for StoryOS.
 *
 * Handles editorial workflows, exports, and review processes.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Editorial Controller class.
 */
class Editorial_Controller extends Base_Controller {

	/**
	 * CPT slug (not used).
	 *
	 * @var string
	 */
	protected $cpt = '';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'editorial';

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
		// Get editorial overview.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/overview', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_overview' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Get editorial artifacts.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/artifacts', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_artifacts' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'type'       => [
					'description' => 'Filter by artifact type.',
					'type'        => 'string',
				],
				'page'       => [ 'default' => 1 ],
				'per_page'   => [ 'default' => 20, 'maximum' => 100 ],
			],
		] );

		// Create editorial artifact.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/artifacts', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_artifact' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'type'       => [
					'description' => 'Artifact type.',
					'type'        => 'string',
					'required'    => true,
				],
				'format'     => [
					'description' => 'Export format.',
					'type'        => 'string',
				],
			],
		] );

		// Export project.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/export', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'export_project' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'format'     => [
					'description' => 'Export format (pdf, json, xml).',
					'type'        => 'string',
					'default'     => 'json',
				],
				'scope'      => [
					'description' => 'Export scope (full, scenes, shots).',
					'type'        => 'string',
					'default'     => 'full',
				],
			],
		] );

		// Get review notes.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/reviews', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_reviews' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'page'       => [ 'default' => 1 ],
				'per_page'   => [ 'default' => 20, 'maximum' => 100 ],
			],
		] );

		// Add review note.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/reviews', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'add_review' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'content'    => [
					'description' => 'Review content.',
					'type'        => 'string',
					'required'    => true,
				],
				'entity_id'  => [
					'description' => 'Associated entity ID.',
					'type'        => 'integer',
				],
				'entity_type' => [
					'description' => 'Associated entity type.',
					'type'        => 'string',
				],
			],
		] );

		// Get storyboard sequence.
		register_rest_route( 'storyos/v1', '/editorial/(?P<project_id>\d+)/storyboard', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_storyboard' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'scene_id'   => [
					'description' => 'Filter by scene ID.',
					'type'        => 'integer',
				],
			],
		] );
	}

	/**
	 * Get editorial overview.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_overview( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		// Get project details.
		$project = get_post( $project_id );
		if ( ! $project || $project->post_type !== 'storyos_project' ) {
			return new WP_Error( 'project_not_found', 'Project not found.', [ 'status' => 404 ] );
		}

		// Get counts.
		$artifact_count = count_user_posts( $project_id, 'storyos_editorial' );
		$review_count = count_user_posts( $project_id, 'storyos_review' );

		// Get export history.
		$exports = get_post_meta( $project_id, '_storyos_export_history', true ) ?: [];

		return rest_ensure_response( [
			'project'       => [
				'id'    => $project_id,
				'title' => $project->post_title,
			],
			'counts'        => [
				'artifacts' => $artifact_count,
				'reviews'   => $review_count,
			],
			'export_count'  => count( $exports ),
			'last_export'   => ! empty( $exports ) ? end( $exports )['date'] : null,
		] );
	}

	/**
	 * Get editorial artifacts.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_artifacts( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$type = $request->get_param( 'type' );
		$page = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;

		$args = [
			'post_type'      => 'storyos_editorial',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => [
				[
					'key'   => 'project',
					'value' => $project_id,
				],
			],
		];

		if ( $type ) {
			$args['meta_query'][] = [
				'key'   => 'artifact_type',
				'value' => $type,
			];
		}

		$query = new \WP_Query( $args );
		$artifacts = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$artifacts[] = [
					'id'           => $post->ID,
					'type'         => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'artifact_type' ),
					'format'       => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'export_format' ),
					'created_date' => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'generated_date' ),
					'title'        => $post->post_title,
				];
			}
			wp_reset_postdata();
		}

		$response = rest_ensure_response( $artifacts );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Create an editorial artifact.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_artifact( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$type = $request->get_param( 'type' );
		$format = $request->get_param( 'format' ) ?: 'json';

		// Validate project exists.
		$project = get_post( $project_id );
		if ( ! $project || $project->post_type !== 'storyos_project' ) {
			return new WP_Error( 'project_not_found', 'Project not found.', [ 'status' => 404 ] );
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'storyos_editorial',
			'post_title'  => "Artifact: {$type} - " . current_time( 'mysql' ),
			'post_status' => 'draft',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save artifact metadata.
		\StoryOS\Utils\storyos_update_field_value( $post_id, 'artifact_type', $type );
		\StoryOS\Utils\storyos_update_field_value( $post_id, 'export_format', $format );
		\StoryOS\Utils\storyos_update_field_value( $post_id, 'generated_date', current_time( 'Y-m-d' ) );
		\StoryOS\Utils\storyos_update_field_value( $post_id, 'project', $project_id );

		return rest_ensure_response( [
			'id'         => $post_id,
			'type'       => $type,
			'format'     => $format,
			'created_at' => current_time( 'mysql' ),
		] );
	}

	/**
	 * Export a project.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function export_project( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$format = $request->get_param( 'format' ) ?: 'json';
		$scope = $request->get_param( 'scope' ) ?: 'full';

		// Validate project exists.
		$project = get_post( $project_id );
		if ( ! $project || $project->post_type !== 'storyos_project' ) {
			return new WP_Error( 'project_not_found', 'Project not found.', [ 'status' => 404 ] );
		}

		// Build export data.
		$data = self::build_export_data( $project_id, $scope );

		// Format export.
		$exported = self::format_export( $data, $format );

		// Log export.
		$exports = get_post_meta( $project_id, '_storyos_export_history', true ) ?: [];
		$exports[] = [
			'date'  => current_time( 'mysql' ),
			'format' => $format,
			'scope' => $scope,
		];
		update_post_meta( $project_id, '_storyos_export_history', $exports );

		return rest_ensure_response( [
			'message'  => 'Export completed.',
			'format'   => $format,
			'scope'    => $scope,
			'data'     => $exported,
		] );
	}

	/**
	 * Build export data.
	 *
	 * @param int    $project_id
	 * @param string $scope
	 * @return array
	 */
	private static function build_export_data( int $project_id, string $scope ): array {
		$data = [
			'project' => get_post( $project_id ),
		];

		if ( in_array( $scope, [ 'full', 'scenes' ], true ) ) {
			$data['scenes'] = new \WP_Query( [
				'post_type'      => 'storyos_scene',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'   => 'project',
						'value' => $project_id,
					],
				],
			] );
		}

		if ( in_array( $scope, [ 'full', 'shots' ], true ) ) {
			$data['shots'] = new \WP_Query( [
				'post_type'      => 'storyos_shot',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			] );
		}

		return $data;
	}

	/**
	 * Format export data.
	 *
	 * @param array  $data
	 * @param string $format
	 * @return string|array
	 */
	private static function format_export( array $data, string $format ) {
		switch ( $format ) {
			case 'json':
				return wp_json_encode( $data, JSON_PRETTY_PRINT );
			case 'xml':
				// Simplified XML export.
				return '<?xml version="1.0"?>' . "\n" . '<export>' . count( $data ) . ' items</export>';
			default:
				return $data;
		}
	}

	/**
	 * Get review notes.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_reviews( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$page = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;

		// Get reviews from post meta.
		$reviews = get_post_meta( $project_id, '_storyos_reviews', true ) ?: [];

		$total = count( $reviews );
		$reviews = array_slice( $reviews, ( $page - 1 ) * $per_page, $per_page );

		$response = rest_ensure_response( $reviews );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Add a review note.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_review( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$content = $request->get_param( 'content' );
		$entity_id = $request->get_param( 'entity_id' ) ? absint( $request->get_param( 'entity_id' ) ) : null;
		$entity_type = $request->get_param( 'entity_type' );

		$review = [
			'id'          => wp_generate_uuid4(),
			'content'     => $content,
			'entity_id'   => $entity_id,
			'entity_type' => $entity_type,
			'author'      => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		];

		$reviews = get_post_meta( $project_id, '_storyos_reviews', true ) ?: [];
		$reviews[] = $review;
		update_post_meta( $project_id, '_storyos_reviews', $reviews );

		return rest_ensure_response( $review );
	}

	/**
	 * Get storyboard sequence.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_storyboard( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$scene_id = $request->get_param( 'scene_id' ) ? absint( $request->get_param( 'scene_id' ) ) : null;

		$args = [
			'post_type'      => 'storyos_storyboard',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		];

		if ( $scene_id ) {
			$args['meta_query'] = [
				[
					'key'   => 'scene',
					'value' => $scene_id,
				],
			];
		}

		$query = new \WP_Query( $args );
		$frames = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$frames[] = [
					'id'             => $post->ID,
					'frame_number'   => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'frame_number' ),
					'description'    => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'frame_description' ),
					'image_asset'    => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'image_asset' ),
					'prompt_text'    => \StoryOS\Utils\storyos_get_field_value( $post->ID, 'prompt_text' ),
				];
			}
			wp_reset_postdata();
		}

		return rest_ensure_response( $frames );
	}
}
