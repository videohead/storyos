<?php
/**
 * Generation REST API Controller for StoryOS.
 *
 * Handles asset generation requests and status tracking.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Generation Controller class.
 */
class Generation_Controller extends Base_Controller {

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
	protected $rest_base = 'generation';

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
		// Submit generation request.
		register_rest_route( 'storyos/v1', '/generation', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'submit_generation' ],
			'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			'args'                => [
				'type'       => [
					'description' => 'Generation type (image, video, audio).',
					'type'        => 'string',
					'required'    => true,
				],
				'prompt'     => [
					'description' => 'Generation prompt.',
					'type'        => 'string',
					'required'    => true,
				],
				'asset_id'   => [
					'description' => 'Associated asset ID.',
					'type'        => 'integer',
				],
				'params'     => [
					'description' => 'Generation parameters.',
					'type'        => 'object',
				],
				'workflow'   => [
					'description' => 'Workflow template slug.',
					'type'        => 'string',
				],
				'provider_type' => [
					'description' => 'Provider type slug.',
					'type'        => 'string',
					'required'    => true,
				],
				'connection_id' => [
					'description' => 'Provider connection ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Get generation status.
		register_rest_route( 'storyos/v1', '/generation/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_generation_status' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			'args'                => [
				'id' => [
					'description' => 'Generation request ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Cancel generation.
		register_rest_route( 'storyos/v1', '/generation/(?P<id>\d+)/cancel', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'cancel_generation' ],
			'permission_callback' => [ __CLASS__, 'check_create_permission' ],
			'args'                => [
				'id' => [
					'description' => 'Generation request ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Get generation history for an asset.
		register_rest_route( 'storyos/v1', '/generation/asset/(?P<asset_id>\d+)/history', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_asset_history' ],
			'permission_callback' => [ __CLASS__, 'check_read_permission' ],
			'args'                => [
				'asset_id' => [
					'description' => 'Asset ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'page'     => [ 'default' => 1 ],
				'per_page' => [ 'default' => 20, 'maximum' => 100 ],
			],
		] );
	}

	/**
	 * Submit a generation request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_generation( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$prompt = $request->get_param( 'prompt' );
		$asset_id = $request->get_param( 'asset_id' ) ? absint( $request->get_param( 'asset_id' ) ) : null;
		$params = $request->get_param( 'params' ) ?? [];
		$workflow = sanitize_text_field( (string) ( $request->get_param( 'workflow' ) ?: 'character-sheet' ) );
		$provider_type = sanitize_text_field( (string) $request->get_param( 'provider_type' ) );
		$connection_id = absint( $request->get_param( 'connection_id' ) );

		// Validate generation type.
		$valid_types = [ 'image', 'video', 'audio', 'text' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_type', 'Invalid generation type.', [ 'status' => 400 ] );
		}

		if ( '' === $provider_type ) {
			return new WP_Error( 'invalid_provider_type', 'Provider type is required.', [ 'status' => 400 ] );
		}

		if ( $connection_id < 1 ) {
			return new WP_Error( 'invalid_connection_id', 'Connection ID must be a positive integer.', [ 'status' => 400 ] );
		}

		// Create generation request post.
		$post_id = wp_insert_post( [
			'post_type'   => 'storyos_generation',
			'post_title'  => "Generation: {$type} - " . wp_strip_all_tags( $prompt ),
			'post_status' => 'draft',
			'post_parent' => $asset_id,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save generation metadata.
		update_post_meta( $post_id, '_storyos_generation_type', $type );
		update_post_meta( $post_id, '_storyos_generation_prompt', $prompt );
		update_post_meta( $post_id, '_storyos_generation_params', $params );
		update_post_meta( $post_id, '_storyos_generation_workflow', $workflow );
		update_post_meta( $post_id, '_storyos_generation_provider_type', $provider_type );
		update_post_meta( $post_id, '_storyos_generation_connection_id', $connection_id );
		update_post_meta( $post_id, '_storyos_generation_status', 'queued' );
		update_post_meta( $post_id, '_storyos_generation_created', current_time( 'mysql' ) );

		\StoryOS\Utils\Generation_Batch::schedule();

		return rest_ensure_response( [
			'id'         => $post_id,
			'job_id'     => '',
			'status'     => 'queued',
			'type'       => $type,
			'provider_type' => $provider_type,
			'connection_id' => $connection_id,
			'created_at' => current_time( 'mysql' ),
		] );
	}

	/**
	/**
	 * Get generation status.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_generation_status( WP_REST_Request $request ) {
		$generation_id = absint( $request->get_param( 'id' ) );
		$job_id = get_post_meta( $generation_id, '_storyos_generation_job_id', true );

		$generation = [
			'id'            => $generation_id,
			'job_id'        => $job_id,
			'status'        => get_post_meta( $generation_id, '_storyos_generation_status', true ) ?: 'unknown',
			'type'          => get_post_meta( $generation_id, '_storyos_generation_type', true ),
			'prompt'        => get_post_meta( $generation_id, '_storyos_generation_prompt', true ),
			'provider_type' => get_post_meta( $generation_id, '_storyos_generation_provider_type', true ),
			'connection_id' => absint( get_post_meta( $generation_id, '_storyos_generation_connection_id', true ) ),
			'created'       => get_post_meta( $generation_id, '_storyos_generation_created', true ),
		];

		return rest_ensure_response( $generation );
	}

	/**
	 * Cancel a generation request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cancel_generation( WP_REST_Request $request ) {
		$generation_id = absint( $request->get_param( 'id' ) );
		$job_id = get_post_meta( $generation_id, '_storyos_generation_job_id', true );

		// Update status.
		update_post_meta( $generation_id, '_storyos_generation_status', 'cancelled' );

		return rest_ensure_response( [
			'id'       => $generation_id,
			'job_id'   => $job_id,
			'status'   => 'cancelled',
			'message'  => 'Generation request cancelled.',
		] );
	}

	/**
	 * Get generation history for an asset.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_asset_history( WP_REST_Request $request ) {
		$asset_id = absint( $request->get_param( 'asset_id' ) );

		$generations = new \WP_Query( [
			'post_type'      => 'storyos_generation',
			'post_parent'    => $asset_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$history = [];

		if ( $generations->have_posts() ) {
			foreach ( $generations->posts as $post ) {
				$history[] = [
					'id'       => $post->ID,
					'job_id'   => get_post_meta( $post->ID, '_storyos_generation_job_id', true ),
					'type'     => get_post_meta( $post->ID, '_storyos_generation_type', true ),
					'prompt'   => get_post_meta( $post->ID, '_storyos_generation_prompt', true ),
					'status'   => get_post_meta( $post->ID, '_storyos_generation_status', true ),
					'provider_type' => get_post_meta( $post->ID, '_storyos_generation_provider_type', true ),
					'connection_id' => absint( get_post_meta( $post->ID, '_storyos_generation_connection_id', true ) ),
					'created'  => get_post_meta( $post->ID, '_storyos_generation_created', true ),
				];
			}
			wp_reset_postdata();
		}

		return rest_ensure_response( $history );
	}

}
