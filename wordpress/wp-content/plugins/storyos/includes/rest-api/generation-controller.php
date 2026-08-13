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

		// Queue the generation task.
		$target_post_id = $asset_id ?: $post_id;
		$queued = self::queue_generation( $target_post_id, $workflow, $provider_type, $connection_id, $params, $post_id, $type, $prompt );

		if ( empty( $queued['success'] ) ) {
			update_post_meta( $post_id, '_storyos_generation_status', 'failed' );
			update_post_meta( $post_id, '_storyos_generation_error', $queued['error'] ?? 'Unknown queue error' );

			return new WP_Error(
				'generation_queue_failed',
				$queued['error'] ?? 'Failed to queue generation request.',
				[ 'status' => 500, 'details' => $queued ]
			);
		}

		update_post_meta( $post_id, '_storyos_generation_job_id', sanitize_text_field( (string) $queued['job_id'] ) );
		update_post_meta( $post_id, '_storyos_generation_status', 'queued' );

		return rest_ensure_response( [
			'id'         => $post_id,
			'job_id'     => $queued['job_id'],
			'status'     => 'queued',
			'type'       => $type,
			'provider_type' => $provider_type,
			'connection_id' => $connection_id,
			'created_at' => current_time( 'mysql' ),
		] );
	}

	/**
	 * Queue a generation task.
	 *
	 * @param int    $target_post_id Target post ID for generation context.
	 * @param string $workflow Workflow template.
	 * @param string $provider_type Provider type slug.
	 * @param int    $connection_id Provider connection ID.
	 * @param array  $params Generation parameters.
	 * @param int    $generation_post_id Generation tracking post ID.
	 * @param string $type Generation type.
	 * @param string $prompt Prompt text.
	 * @return array
	 */
	private static function queue_generation( int $target_post_id, string $workflow, string $provider_type, int $connection_id, array $params, int $generation_post_id, string $type, string $prompt ): array {
		$endpoint = trailingslashit( self::get_orchestrator_url() ) . 'generate';

		$payload = [
			'post_id'        => $target_post_id,
			'provider_type'  => $provider_type,
			'connection_id'  => $connection_id,
			'workflow'       => $workflow,
			'custom_params'  => array_merge(
				[
					'generation_post_id' => $generation_post_id,
					'generation_type'    => $type,
					'prompt'             => $prompt,
					'source'             => 'storyos-rest',
				],
				$params
			),
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => 30,
				'headers'   => self::build_orchestrator_headers(),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body_raw, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return [
				'success'     => false,
				'error'       => 'Orchestrator rejected generation request.',
				'status_code' => $status_code,
				'response'    => $decoded ?: $body_raw,
			];
		}

		$job_id = '';
		if ( is_array( $decoded ) ) {
			$job_id = (string) ( $decoded['job_id'] ?? '' );
		}

		if ( '' === $job_id ) {
			return [
				'success'  => false,
				'error'    => 'Orchestrator response did not include a job_id.',
				'response' => $decoded ?: $body_raw,
			];
		}

		return [
			'success'  => true,
			'job_id'   => $job_id,
			'response' => $decoded,
		];
	}

	/**
	 * Get generation status.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_generation_status( WP_REST_Request $request ) {
		$generation_id = absint( $request->get_param( 'id' ) );
		$job_id = get_post_meta( $generation_id, '_storyos_generation_job_id', true );

		if ( ! empty( $job_id ) ) {
			$endpoint = trailingslashit( self::get_orchestrator_url() ) . 'queue/task/' . rawurlencode( (string) $job_id );
			$response = wp_remote_get(
				$endpoint,
				[
					'timeout'   => 30,
					'headers'   => self::build_orchestrator_headers(),
					'sslverify' => false,
				]
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body_raw = wp_remote_retrieve_body( $response );
				$decoded  = json_decode( $body_raw, true );

				if ( is_array( $decoded ) ) {
					$status = sanitize_text_field( (string) ( $decoded['registry_status'] ?? $decoded['celery_state'] ?? 'unknown' ) );
					update_post_meta( $generation_id, '_storyos_generation_status', $status );

					return rest_ensure_response( [
						'id'            => $generation_id,
						'job_id'        => $job_id,
						'status'        => $status,
						'type'          => get_post_meta( $generation_id, '_storyos_generation_type', true ),
						'prompt'        => get_post_meta( $generation_id, '_storyos_generation_prompt', true ),
						'provider_type' => get_post_meta( $generation_id, '_storyos_generation_provider_type', true ),
						'connection_id' => absint( get_post_meta( $generation_id, '_storyos_generation_connection_id', true ) ),
						'created'       => get_post_meta( $generation_id, '_storyos_generation_created', true ),
						'orchestrator'  => $decoded,
					] );
				}
			}
		}

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

		if ( ! empty( $job_id ) ) {
			$endpoint = trailingslashit( self::get_orchestrator_url() ) . 'queue/cancel/' . rawurlencode( (string) $job_id );
			wp_remote_post(
				$endpoint,
				[
					'timeout'   => 20,
					'headers'   => self::build_orchestrator_headers(),
					'sslverify' => false,
				]
			);
		}

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

	/**
	 * Resolve orchestrator URL.
	 *
	 * @return string
	 */
	private static function get_orchestrator_url(): string {
		$url = get_option( 'storyos_orchestrator_url', '' );
		if ( empty( $url ) && defined( 'STORYOS_ORCHESTRATOR_URL' ) ) {
			$url = STORYOS_ORCHESTRATOR_URL;
		}
		if ( empty( $url ) ) {
			$url = 'http://orchestrator:8000';
		}

		return rtrim( (string) $url, '/' );
	}

	/**
	 * Build headers for orchestrator calls.
	 *
	 * @return array
	 */
	private static function build_orchestrator_headers(): array {
		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		$token = get_option( 'storyos_orchestrator_token', '' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . sanitize_text_field( (string) $token );
		}

		return $headers;
	}
}
