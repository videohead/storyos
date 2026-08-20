<?php
/**
 * Generation REST API Controller for World Graph Studio.
 *
 * Handles asset generation requests and status tracking.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

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
		register_rest_route( 'worldgraph/v1', '/generation', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_generation' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
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
				'inputs'     => [
					'description' => 'Template input slots (prompt, negative_prompt).',
					'type'        => 'object',
				],
				'workflow'   => [
					'description' => 'Workflow template slug.',
					'type'        => 'string',
					'required'    => true,
				],
				'provider_type' => [
					'description' => 'Provider type slug.',
					'type'        => 'string',
				],
				'connection_id' => [
					'description' => 'Provider connection ID.',
					'type'        => 'integer',
				],
			],
		] );

		// Get generation status.
		register_rest_route( 'worldgraph/v1', '/generation/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_generation_status' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'id' => [
					'description' => 'Generation request ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Cancel generation.
		register_rest_route( 'worldgraph/v1', '/generation/(?P<id>\d+)/cancel', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel_generation' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'id' => [
					'description' => 'Generation request ID.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		// Get generation history for an asset.
		register_rest_route( 'worldgraph/v1', '/generation/asset/(?P<asset_id>\d+)/history', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_asset_history' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
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

		// Inspect what a Template needs from ComfyUI, and whether it is installed.
		register_rest_route( 'worldgraph/v1', '/generation/templates/(?P<id>\d+)/requirements', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_template_requirements' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'id'       => [
					'description' => 'Template post ID.',
					'type'        => 'integer',
					'required'    => true,
				],
				'validate' => [
					'description' => 'Also check the requirements against the configured ComfyUI instance.',
					'type'        => 'boolean',
					'default'     => true,
				],
			],
		] );
	}

	/**
	 * Return a Template's ComfyUI requirement manifest, optionally validated
	 * against the configured ComfyUI instance.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_template_requirements( WP_REST_Request $request ) {
		\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );
		$manifest = \WorldGraph\Utils\Comfy_Manifest::for_template( absint( $request->get_param( 'id' ) ) );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		if ( ! rest_sanitize_boolean( $request->get_param( 'validate' ) ) ) {
			return rest_ensure_response( $manifest );
		}

		$report = \WorldGraph\Utils\Comfy_Manifest::validate( absint( $request->get_param( 'id' ) ) );
		$manifest['validation'] = is_wp_error( $report )
			? [ 'ok' => false, 'error' => $report->get_error_message() ]
			: $report;

		return rest_ensure_response( $manifest );
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
		$workflow = sanitize_text_field( (string) $request->get_param( 'workflow' ) );

		// Validate generation type.
		$valid_types = [ 'image', 'video', 'audio', 'text' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_type', 'Invalid generation type.', [ 'status' => 400 ] );
		}

		$template = self::resolve_active_template( $workflow );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		$connection_id = absint( get_post_meta( $template->ID, 'connection_id', true ) );
		$connection = \WorldGraph\Utils\Connection_Repository::get( $connection_id );
		$template_provider = sanitize_key( (string) get_post_meta( $template->ID, 'provider_type', true ) );
		if ( ! $connection || '' === $template_provider || 'disabled' === $connection['status'] || $template_provider !== $connection['provider_type'] ) {
			return new WP_Error( 'invalid_template_connection', 'The selected Template and Connection must use the same provider.', [ 'status' => 400 ] );
		}
		$template_modality = sanitize_key( (string) get_post_meta( $template->ID, 'modality', true ) );
		if ( $template_modality && $type !== \WorldGraph\Utils\Generation_Modality::output_type( $template_modality ) ) {
			return new WP_Error( 'generation_type_mismatch', 'The requested type must match the selected Template output type.', [ 'status' => 400 ] );
		}
		\WorldGraph\Utils\Connection_Adapters::load( (string) $connection['provider_type'] );
		$provider_template_id = sanitize_text_field( (string) ( get_post_meta( $template->ID, 'provider_template_id', true ) ?: get_post_meta( $template->ID, 'comfy_template_id', true ) ) );
		if ( 'fal' === $connection['provider_type'] && '' === $provider_template_id ) {
			$provider_template_id = sanitize_text_field( (string) ( $connection['model'] ?? '' ) );
		}
		if ( '' === $provider_template_id ) {
			return new WP_Error( 'missing_provider_template', 'The selected Template must reference a provider MCP Template.', [ 'status' => 400 ] );
		}
		if ( 'fal' === $connection['provider_type'] && ! \WorldGraph\Utils\Fal_MCP::endpoint_is_allowed( $connection, $provider_template_id ) ) {
			return new WP_Error( 'fal_endpoint_not_allowed', 'That fal model endpoint is not allowed by the selected Connection.', [ 'status' => 400 ] );
		}
		$provider_type = $connection['provider_type'];
		$workflow = $provider_template_id;

		// Create generation request post.
		$post_id = wp_insert_post( [
			'post_type'   => 'worldgraph_gen',
			'post_title'  => "Generation: {$type} - " . wp_strip_all_tags( $prompt ),
			'post_status' => 'draft',
			'post_parent' => $asset_id,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save generation metadata.
		update_post_meta( $post_id, '_worldgraph_gen_type', $type );
		update_post_meta( $post_id, '_worldgraph_gen_prompt', $prompt );
		update_post_meta( $post_id, '_worldgraph_gen_params', $params );
		update_post_meta( $post_id, '_worldgraph_gen_inputs', self::sanitize_inputs( $request->get_param( 'inputs' ) ) );
		update_post_meta( $post_id, '_worldgraph_gen_workflow', $workflow );
		update_post_meta( $post_id, '_worldgraph_gen_template_id', $template->ID );
		update_post_meta( $post_id, '_worldgraph_gen_provider_type', $provider_type );
		update_post_meta( $post_id, '_worldgraph_gen_connection_id', $connection_id );
		update_post_meta( $post_id, '_worldgraph_gen_status', 'queued' );
		update_post_meta( $post_id, '_worldgraph_gen_created', current_time( 'mysql' ) );

		\WorldGraph\Utils\Generation_Batch::schedule();

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
	 * Resolve an active Template by post ID, slug, or title reference.
	 *
	 * @param string $reference Template reference.
	 * @return \WP_Post|WP_Error
	 */
	private static function resolve_active_template( string $reference ) {
		$template = ctype_digit( $reference ) ? get_post( absint( $reference ) ) : null;
		if ( ! $template ) {
			$templates = get_posts( [
				'post_type'      => 'worldgraph_template',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'name'           => sanitize_title( $reference ),
			] );
			$template = $templates ? $templates[0] : null;
		}

		if ( ! $template instanceof \WP_Post || 'worldgraph_template' !== $template->post_type || 'publish' !== $template->post_status || 'active' !== get_post_meta( $template->ID, 'status', true ) ) {
			return new WP_Error( 'invalid_template', 'An active Template is required for generation.', [ 'status' => 400 ] );
		}

		return $template;
	}

	/**
	 * Reduce submitted modality inputs to known slots and scalar values. Media
	 * slots stay as an attachment ID or URL; the provider client resolves and
	 * uploads them at submission time.
	 *
	 * @param mixed $inputs Raw `inputs` parameter.
	 * @return array<string, string>
	 */
	private static function sanitize_inputs( $inputs ): array {
		if ( ! is_array( $inputs ) ) {
			return [];
		}

		$slots     = array_merge( [ 'prompt', 'negative_prompt' ], \WorldGraph\Utils\Generation_Modality::MEDIA_SLOTS );
		$sanitized = [];
		foreach ( $slots as $slot ) {
			if ( ! isset( $inputs[ $slot ] ) || ! is_scalar( $inputs[ $slot ] ) ) {
				continue;
			}

			$value = trim( (string) $inputs[ $slot ] );
			if ( '' === $value ) {
				continue;
			}

			if ( ! in_array( $slot, \WorldGraph\Utils\Generation_Modality::MEDIA_SLOTS, true ) ) {
				$sanitized[ $slot ] = sanitize_textarea_field( $value );
				continue;
			}

			$sanitized[ $slot ] = preg_match( '#^https?://#', $value ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
		}

		return $sanitized;
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
		$job_id = get_post_meta( $generation_id, '_worldgraph_gen_job_id', true );

		$generation = [
			'id'            => $generation_id,
			'job_id'        => $job_id,
			'status'        => get_post_meta( $generation_id, '_worldgraph_gen_status', true ) ?: 'unknown',
			'type'          => get_post_meta( $generation_id, '_worldgraph_gen_type', true ),
			'prompt'        => get_post_meta( $generation_id, '_worldgraph_gen_prompt', true ),
			'provider_type' => get_post_meta( $generation_id, '_worldgraph_gen_provider_type', true ),
			'connection_id' => absint( get_post_meta( $generation_id, '_worldgraph_gen_connection_id', true ) ),
			'created'       => get_post_meta( $generation_id, '_worldgraph_gen_created', true ),
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
		$job_id = get_post_meta( $generation_id, '_worldgraph_gen_job_id', true );

		// Update status.
		update_post_meta( $generation_id, '_worldgraph_gen_status', 'cancelled' );

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
			'post_type'      => 'worldgraph_gen',
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
					'job_id'   => get_post_meta( $post->ID, '_worldgraph_gen_job_id', true ),
					'type'     => get_post_meta( $post->ID, '_worldgraph_gen_type', true ),
					'prompt'   => get_post_meta( $post->ID, '_worldgraph_gen_prompt', true ),
					'status'   => get_post_meta( $post->ID, '_worldgraph_gen_status', true ),
					'provider_type' => get_post_meta( $post->ID, '_worldgraph_gen_provider_type', true ),
					'connection_id' => absint( get_post_meta( $post->ID, '_worldgraph_gen_connection_id', true ) ),
					'created'  => get_post_meta( $post->ID, '_worldgraph_gen_created', true ),
				];
			}
			wp_reset_postdata();
		}

		return rest_ensure_response( $history );
	}

}
