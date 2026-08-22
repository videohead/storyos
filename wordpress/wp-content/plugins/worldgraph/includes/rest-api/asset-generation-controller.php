<?php
/**
 * Representative-media REST API controller for World Graph Studio.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WorldGraph\Utils\Asset_Generator;
use WorldGraph\Utils\Generation_Workflows;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Builds detailed prompts and manages durable representative-media batches.
 */
class Asset_Generation_Controller extends Base_Controller {

	/** CPT slug (not used). */
	protected $cpt = '';

	/** REST base. */
	protected $rest_base = 'assets/generate';

	/** Initialize the controller. */
	public static function init(): void {
		$instance = new self();
		add_action( 'rest_api_init', [ $instance, 'register_routes' ] );
	}

	/** Register individual, planning, and durable batch routes. */
	public function register_routes() {
		register_rest_route( 'worldgraph/v1', '/assets/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'generate' ],
			'permission_callback' => [ $this, 'check_generate_permission' ],
			'args'                => [
				'post_id'      => [ 'description' => 'Story element post ID the image belongs to.', 'type' => 'integer', 'required' => true ],
				'prompt'       => [ 'description' => 'Optional edited prompt. A fresh detailed prompt is built from saved Story Graph fields when omitted.', 'type' => 'string' ],
				'intent'       => [ 'description' => 'Optional built-in representative-media intent.', 'type' => 'string' ],
				'set_featured' => [ 'description' => 'Set the generated image as the featured asset.', 'type' => 'boolean', 'default' => true ],
				'create_asset' => [ 'description' => 'Create a linked World Graph Studio Asset record.', 'type' => 'boolean', 'default' => true ],
				'template_id'  => [ 'description' => 'Active image Template post ID.', 'type' => 'integer', 'required' => true ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/assets/generate/prompt', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_prompt' ],
			'permission_callback' => [ $this, 'check_generate_permission' ],
			'args'                => [
				'post_id' => [ 'description' => 'Story element post ID.', 'type' => 'integer', 'required' => true ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/assets/generate/plan', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_plan' ],
			'permission_callback' => [ $this, 'check_generate_permission' ],
			'args'                => [
				'post_id' => [ 'description' => 'Story element or Project post ID.', 'type' => 'integer', 'required' => true ],
				'scope'   => [ 'description' => 'Plan one item or every owned item in a Project.', 'type' => 'string', 'enum' => [ 'item', 'project' ], 'default' => 'item' ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/assets/generate/batches', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_batch' ],
			'permission_callback' => [ $this, 'check_generate_permission' ],
			'args'                => [
				'post_id'           => [ 'description' => 'Story element or Project post ID.', 'type' => 'integer', 'required' => true ],
				'scope'             => [ 'description' => 'Generate one item or every owned item in a Project.', 'type' => 'string', 'enum' => [ 'item', 'project' ], 'default' => 'item' ],
				'base_prompt'       => [ 'description' => 'Optional author-edited prompt for an item batch.', 'type' => 'string', 'default' => '' ],
				'image_template_id' => [ 'description' => 'Optional image Template override applied to every image task.', 'type' => 'integer', 'default' => 0 ],
				'video_template_id' => [ 'description' => 'Optional video Template override applied to every video task.', 'type' => 'integer', 'default' => 0 ],
				'idempotency_key'   => [ 'description' => 'Caller-generated key that makes a repeated start request return the existing batch.', 'type' => 'string', 'required' => true ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/assets/generate/batches/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_batch' ],
			'permission_callback' => [ $this, 'check_batch_permission' ],
			'args'                => [
				'id' => [ 'description' => 'Representative-media batch ID.', 'type' => 'integer', 'required' => true ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/assets/generate/batches/(?P<id>\d+)/cancel', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel_batch' ],
			'permission_callback' => [ $this, 'check_batch_permission' ],
			'args'                => [
				'id' => [ 'description' => 'Representative-media batch ID.', 'type' => 'integer', 'required' => true ],
			],
		] );
	}

	/** Only editors of the target post may inspect or spend generation budget. */
	public static function check_generate_permission( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'worldgraph_rest_forbidden',
				__( 'You are not allowed to generate assets for this item.', 'worldgraph' ),
				[ 'status' => is_user_logged_in() ? 403 : 401 ]
			);
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'worldgraph_rest_forbidden_upload', __( 'You are not allowed to upload files to this site.', 'worldgraph' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/** Only the requester or an editor of the source may inspect/manage a batch. */
	public static function check_batch_permission( WP_REST_Request $request ) {
		$batch_id = absint( $request->get_param( 'id' ) );
		$batch    = get_post( $batch_id );
		if ( ! $batch instanceof \WP_Post || 'worldgraph_gen' !== $batch->post_type || Generation_Workflows::REPRESENTATIVE_BATCH !== get_post_meta( $batch_id, Generation_Workflows::BATCH_KIND_META, true ) ) {
			return new WP_Error( 'worldgraph_generation_batch_not_found', __( 'That representative-media batch does not exist.', 'worldgraph' ), [ 'status' => 404 ] );
		}

		$requester_id = absint( get_post_meta( $batch_id, '_worldgraph_gen_requested_by', true ) );
		$user_id      = get_current_user_id();
		if ( ! $user_id || ! current_user_can( 'upload_files' ) || ( $requester_id !== $user_id && ! current_user_can( 'edit_post', (int) $batch->post_parent ) ) ) {
			return new WP_Error( 'worldgraph_generation_batch_forbidden', __( 'You are not allowed to manage this representative-media batch.', 'worldgraph' ), [ 'status' => $user_id ? 403 : 401 ] );
		}

		return true;
	}

	/** Queue a backwards-compatible single representative image. */
	public static function generate( WP_REST_Request $request ) {
		$result = Asset_Generator::queue_for_post( absint( $request->get_param( 'post_id' ) ), [
			'type'         => 'image',
			'prompt'       => (string) $request->get_param( 'prompt' ),
			'intent'       => sanitize_key( (string) $request->get_param( 'intent' ) ),
			'set_featured' => $request->get_param( 'set_featured' ),
			'create_asset' => $request->get_param( 'create_asset' ),
			'template_id'  => absint( $request->get_param( 'template_id' ) ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 202 );
	}

	/** Return the detailed default prompt and item workflow for the metabox. */
	public static function get_prompt( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$plan    = Generation_Workflows::plan( $post_id, 'item' );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$task       = (array) ( $plan['tasks'][0] ?? [] );
		$templates  = Generation_Workflows::runnable_templates( $post_id, 'image' );
		$default_id = empty( $task ) ? 0 : Generation_Workflows::resolve_template_id( $task );

		return rest_ensure_response( [
			'post_id'             => $post_id,
			'prompt'              => (string) ( $task['prompt'] ?? Asset_Generator::build_prompt( $post_id ) ),
			'intent'              => (string) ( $task['intent'] ?? '' ),
			'configured'          => 0 !== $default_id,
			'model'               => $default_id ? __( 'Template provider', 'worldgraph' ) : '',
			'profile'             => Asset_Generator::project_media_profile( $post_id ),
			'workflow'            => $plan['workflow'],
			'counts'              => $plan['counts'],
			'total_jobs'          => $plan['total_jobs'],
			'templates'           => $templates,
			'image_templates'     => $templates,
			'video_templates'     => Generation_Workflows::runnable_templates( $post_id, 'video' ),
			'default_template_id' => $default_id,
			'latest_batch'        => Generation_Workflows::latest_batch( $post_id, 'item' ),
			'latest_project_batch' => 'worldgraph_project' === get_post_type( $post_id ) ? Generation_Workflows::latest_batch( $post_id, 'project' ) : [],
		] );
	}

	/** Dry-run an item or project representative-media plan. */
	public static function get_plan( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$scope   = 'project' === sanitize_key( (string) $request->get_param( 'scope' ) ) ? 'project' : 'item';
		$plan    = Generation_Workflows::plan( $post_id, $scope );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$permission = self::check_plan_sources( $plan );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		return rest_ensure_response( self::prepare_plan_response( $plan ) );
	}

	/** Persist every task in a confirmed representative-media plan. */
	public static function create_batch( WP_REST_Request $request ) {
		$result = Generation_Workflows::queue_batch(
			absint( $request->get_param( 'post_id' ) ),
			(string) $request->get_param( 'scope' ),
			[
				'base_prompt'       => (string) $request->get_param( 'base_prompt' ),
				'image_template_id' => absint( $request->get_param( 'image_template_id' ) ),
				'video_template_id' => absint( $request->get_param( 'video_template_id' ) ),
				'idempotency_key'   => (string) $request->get_param( 'idempotency_key' ),
			]
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = new WP_REST_Response( $result, 202 );
		$response->header( 'Location', rest_url( 'worldgraph/v1/assets/generate/batches/' . (int) $result['batch_id'] ) );
		return $response;
	}

	/** Return durable aggregate and child-job progress. */
	public static function get_batch( WP_REST_Request $request ) {
		$status = Generation_Workflows::batch_status( absint( $request->get_param( 'id' ) ) );
		return empty( $status )
			? new WP_Error( 'worldgraph_generation_batch_not_found', __( 'That representative-media batch does not exist.', 'worldgraph' ), [ 'status' => 404 ] )
			: rest_ensure_response( $status );
	}

	/** Stop batch jobs that have not yet been submitted to a provider. */
	public static function cancel_batch( WP_REST_Request $request ) {
		$status = Generation_Workflows::cancel_batch( absint( $request->get_param( 'id' ) ) );
		return empty( $status )
			? new WP_Error( 'worldgraph_generation_batch_not_found', __( 'That representative-media batch does not exist.', 'worldgraph' ), [ 'status' => 404 ] )
			: rest_ensure_response( $status );
	}

	/** Verify access to every source in an expanded Project plan. */
	private static function check_plan_sources( array $plan ) {
		$checked = [];
		foreach ( (array) ( $plan['tasks'] ?? [] ) as $task ) {
			$source_id = absint( $task['source_id'] ?? 0 );
			if ( $source_id && ! isset( $checked[ $source_id ] ) ) {
				$checked[ $source_id ] = true;
				if ( ! current_user_can( 'edit_post', $source_id ) ) {
					return new WP_Error( 'worldgraph_generation_source_forbidden', __( 'The plan contains an item you are not allowed to generate media for.', 'worldgraph' ), [ 'status' => 403 ] );
				}
			}
		}

		return true;
	}

	/** Add Template readiness while keeping long prompt text out of plan lists. */
	private static function prepare_plan_response( array $plan ): array {
		$image_templates = Generation_Workflows::common_templates( (array) $plan['tasks'], 'image' );
		$video_templates = Generation_Workflows::common_templates( (array) $plan['tasks'], 'video' );
		$blockers        = [];
		$defaults        = [ 'image' => [], 'video' => [] ];
		$tasks           = [];

		foreach ( (array) $plan['tasks'] as $task ) {
			$template_id = Generation_Workflows::resolve_template_id( $task );
			$type        = (string) $task['type'];
			if ( $template_id ) {
				$defaults[ $type ][ $template_id ] = true;
			} else {
				$blockers[] = [
					'source_id'    => (int) $task['source_id'],
					'source_title' => (string) $task['source_title'],
					'intent'       => (string) $task['intent'],
					'type'         => $type,
				];
			}
			$tasks[] = [
				'source_id'    => (int) $task['source_id'],
				'source_type'  => (string) $task['source_type'],
				'source_title' => (string) $task['source_title'],
				'workflow_id'  => (string) $task['workflow_id'],
				'intent'       => (string) $task['intent'],
				'label'        => (string) $task['label'],
				'type'         => $type,
				'featured'     => ! empty( $task['featured'] ),
				'prompt_hash'  => hash( 'sha256', (string) $task['prompt'] ),
			];
		}

		$default_ids = [];
		foreach ( $defaults as $type => $ids ) {
			$ids                  = array_map( 'intval', array_keys( $ids ) );
			$default_ids[ $type ] = 1 === count( $ids ) ? $ids[0] : 0;
		}

		return [
			'post_id'              => (int) $plan['post_id'],
			'scope'                => (string) $plan['scope'],
			'workflow'             => $plan['workflow'],
			'sources'              => (int) $plan['sources'],
			'total_jobs'           => (int) $plan['total_jobs'],
			'counts'               => $plan['counts'],
			'tasks'                => $tasks,
			'ready'                => empty( $blockers ),
			'blockers'             => $blockers,
			'image_templates'      => $image_templates,
			'video_templates'      => $video_templates,
			'default_template_ids' => $default_ids,
			'latest_batch'         => Generation_Workflows::latest_batch( (int) $plan['post_id'], (string) $plan['scope'] ),
		];
	}
}
