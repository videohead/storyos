<?php
/**
 * Production REST API Controller for StoryOS.
 *
 * Handles production workflow and pipeline management.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Production Controller class.
 */
class Production_Controller extends Base_Controller {

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
	protected $rest_base = 'production';

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
		// Get production overview for a project.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/overview', [
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

		// Get production pipeline status.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/pipeline', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_pipeline' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Update production stage.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/stage', [
			'methods'             => 'PUT',
			'callback'            => [ $this, 'update_stage' ],
			'permission_callback' => [ $this, 'check_update_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'stage'      => [
					'description' => 'New production stage.',
					'type'        => 'string',
					'required'    => true,
				],
			],
		] );

		// Get production tasks.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/tasks', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tasks' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'status'     => [
					'description' => 'Filter by task status.',
					'type'        => 'string',
				],
				'page'       => [ 'default' => 1 ],
				'per_page'   => [ 'default' => 20, 'maximum' => 100 ],
			],
		] );

		// Create production task.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/tasks', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_task' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'title'      => [
					'description' => 'Task title.',
					'type'        => 'string',
					'required'    => true,
				],
				'description' => [
					'description' => 'Task description.',
					'type'        => 'string',
				],
				'status'     => [
					'description' => 'Task status.',
					'type'        => 'string',
					'default'     => 'pending',
				],
			],
		] );

		// Update task status.
		register_rest_route( 'storyos/v1', '/production/tasks/(?P<task_id>\d+)/status', [
			'methods'             => 'PUT',
			'callback'            => [ $this, 'update_task_status' ],
			'permission_callback' => [ $this, 'check_update_permission' ],
			'args'                => [
				'task_id'  => [
					'description' => 'Task ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'status'   => [
					'description' => 'New task status.',
					'type'        => 'string',
					'required'    => true,
				],
			],
		] );

		// Get production timeline.
		register_rest_route( 'storyos/v1', '/production/(?P<project_id>\d+)/timeline', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_timeline' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'project_id' => [
					'description' => 'Project ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );
	}

	/**
	 * Get production overview.
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
		$scene_count = count_user_posts( $project_id, 'storyos_scene' );
		$shot_count = count_user_posts( $project_id, 'storyos_shot' );
		$asset_count = count_user_posts( $project_id, 'storyos_asset' );
		$episode_count = count_user_posts( $project_id, 'storyos_episode' );

		// Get production stage.
		$stage = get_post_meta( $project_id, 'production_stage', true ) ?: 'draft';

		return rest_ensure_response( [
			'project'       => [
				'id'   => $project_id,
				'title' => $project->post_title,
				'stage' => $stage,
			],
			'counts'        => [
				'scenes'      => $scene_count,
				'shots'       => $shot_count,
				'assets'      => $asset_count,
				'episodes'    => $episode_count,
			],
			'production_stage' => $stage,
		] );
	}

	/**
	 * Get production pipeline.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_pipeline( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		// Define pipeline stages.
		$pipeline = [
			'pre_production' => [
				'label' => 'Pre-Production',
				'status' => 'completed',
				'items' => [
					'type' => 'scenes',
					'count' => count_user_posts( $project_id, 'storyos_scene' ),
				],
			],
			'production' => [
				'label' => 'Production',
				'status' => 'in_progress',
				'items' => [
					'type' => 'shots',
					'count' => count_user_posts( $project_id, 'storyos_shot' ),
				],
			],
			'post_production' => [
				'label' => 'Post-Production',
				'status' => 'pending',
				'items' => [
					'type' => 'assets',
					'count' => count_user_posts( $project_id, 'storyos_asset' ),
				],
			],
			'review' => [
				'label' => 'Review',
				'status' => 'pending',
			],
			'final' => [
				'label' => 'Final',
				'status' => 'pending',
			],
		];

		return rest_ensure_response( $pipeline );
	}

	/**
	 * Update production stage.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_stage( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$stage = $request->get_param( 'stage' );

		$valid_stages = [ 'draft', 'pre_production', 'production', 'post_production', 'review', 'final' ];
		if ( ! in_array( $stage, $valid_stages, true ) ) {
			return new WP_Error( 'invalid_stage', 'Invalid production stage.', [ 'status' => 400 ] );
		}

		update_post_meta( $project_id, 'production_stage', $stage );

		return rest_ensure_response( [
			'message' => 'Production stage updated.',
			'stage'   => $stage,
		] );
	}

	/**
	 * Get production tasks.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_tasks( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$status = $request->get_param( 'status' );
		$page = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;

		// Get tasks from post meta.
		$tasks = get_post_meta( $project_id, '_storyos_production_tasks', true ) ?: [];

		// Filter by status if specified.
		if ( $status ) {
			$tasks = array_filter( $tasks, fn( $t ) => $t['status'] === $status );
		}

		$total = count( $tasks );
		$tasks = array_slice( $tasks, ( $page - 1 ) * $per_page, $per_page );

		$response = rest_ensure_response( $tasks );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Create a production task.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_task( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$title = $request->get_param( 'title' );
		$description = $request->get_param( 'description' ) ?: '';
		$status = $request->get_param( 'status' ) ?: 'pending';

		$task = [
			'id'          => wp_generate_uuid4(),
			'title'       => $title,
			'description' => $description,
			'status'      => $status,
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		$tasks = get_post_meta( $project_id, '_storyos_production_tasks', true ) ?: [];
		$tasks[] = $task;
		update_post_meta( $project_id, '_storyos_production_tasks', $tasks );

		return rest_ensure_response( $task );
	}

	/**
	 * Update task status.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_task_status( WP_REST_Request $request ) {
		$task_id = $request->get_param( 'task_id' );
		$status = $request->get_param( 'status' );

		// Find the task across all projects.
		$query = new \WP_Query( [
			'post_type'      => 'storyos_project',
			'meta_key'       => '_storyos_production_tasks',
			'posts_per_page' => -1,
		] );

		foreach ( $query->posts as $project ) {
			$tasks = get_post_meta( $project->ID, '_storyos_production_tasks', true ) ?: [];
			$found = false;

			foreach ( $tasks as &$task ) {
				if ( $task['id'] === $task_id ) {
					$task['status'] = $status;
					$task['updated_at'] = current_time( 'mysql' );
					$found = true;
					break;
				}
			}
			unset( $task );

			if ( $found ) {
				update_post_meta( $project->ID, '_storyos_production_tasks', $tasks );
				return rest_ensure_response( [
					'message' => 'Task status updated.',
					'task'    => [ 'id' => $task_id, 'status' => $status ],
				] );
			}
		}

		wp_reset_postdata();

		return new WP_Error( 'task_not_found', 'Task not found.', [ 'status' => 404 ] );
	}

	/**
	 * Get production timeline.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_timeline( WP_REST_Request $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		// Get timeline events from post meta.
		$timeline = get_post_meta( $project_id, '_storyos_production_timeline', true ) ?: [];

		return rest_ensure_response( $timeline );
	}
}
