<?php
/**
 * Agents REST API Controller for World Graph Studio.
 *
 * Manages AI agent configurations and interactions.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Agents Controller class.
 */
class Agents_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'worldgraph_agent';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'agents';

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
		register_rest_route( 'worldgraph/v1', '/agents', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'     => [ 'default' => 1 ],
					'per_page' => [ 'default' => 10, 'maximum' => 100 ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'worldgraph/v1', '/agents/(?P<id>\d+)', [
			'args'   => [ 'id' => [ 'type' => 'integer' ] ],
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'check_update_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'check_delete_permission' ],
			],
		] );

		// Agent actions.
		register_rest_route( 'worldgraph/v1', '/agents/(?P<id>\d+)/actions', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'agent_action' ],
			'permission_callback' => [ $this, 'check_create_permission' ],
			'args'                => [
				'action' => [
					'description' => 'Action to perform.',
					'type'        => 'string',
					'required'    => true,
				],
				'params' => [
					'description' => 'Action parameters.',
					'type'        => 'object',
				],
			],
		] );

		// Agent history.
		register_rest_route( 'worldgraph/v1', '/agents/(?P<id>\d+)/history', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_history' ],
			'permission_callback' => [ $this, 'check_read_permission' ],
			'args'                => [
				'page'     => [ 'default' => 1 ],
				'per_page' => [ 'default' => 20, 'maximum' => 100 ],
			],
		] );
	}

	/**
	 * Execute an agent action.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function agent_action( WP_REST_Request $request ) {
		$agent_id = absint( $request->get_param( 'id' ) );
		$action = $request->get_param( 'action' );
		$params = $request->get_param( 'params' ) ?? [];

		// Validate agent exists.
		$agent = get_post( $agent_id );
		if ( ! $agent || $agent->post_type !== 'worldgraph_agent' ) {
			return new WP_Error( 'agent_not_found', 'Agent not found.', [ 'status' => 404 ] );
		}

		// Get agent configuration.
		$config = get_post_meta( $agent_id, '_worldgraph_agent_config', true );
		if ( ! $config ) {
			return new WP_Error( 'agent_config_missing', 'Agent configuration not found.', [ 'status' => 400 ] );
		}

		// Execute action based on agent type.
		$agent_type = $config['type'] ?? 'default';
		$result = self::execute_action( $agent_type, $action, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log the action.
		self::log_action( $agent_id, $action, $params, $result );

		return rest_ensure_response( [
			'success' => true,
			'action'  => $action,
			'result'  => $result,
		] );
	}

	/**
	 * Execute an action based on agent type.
	 *
	 * @param string $agent_type
	 * @param string $action
	 * @param array  $params
	 * @return array|WP_Error
	 */
	private static function execute_action( string $agent_type, string $action, array $params ) {
		// Default action handler.
		switch ( $agent_type ) {
			case 'writing':
				return self::handle_writing_action( $action, $params );
			case 'image':
				return self::handle_image_action( $action, $params );
			case 'video':
				return self::handle_video_action( $action, $params );
			default:
				return [ 'status' => 'completed', 'message' => "Action '{$action}' executed." ];
		}
	}

	/**
	 * Handle writing agent actions.
	 *
	 * @param string $action
	 * @param array  $params
	 * @return array|WP_Error
	 */
	private static function handle_writing_action( string $action, array $params ) {
		switch ( $action ) {
			case 'generate_scene':
				// Generate a scene based on parameters.
				return [
					'status' => 'completed',
					'content' => 'Generated scene content...',
				];
			case 'generate_dialogue':
				return [
					'status' => 'completed',
					'content' => 'Generated dialogue...',
				];
			default:
				return new WP_Error( 'invalid_action', "Unknown writing action: {$action}" );
		}
	}

	/**
	 * Handle image agent actions.
	 *
	 * @param string $action
	 * @param array  $params
	 * @return array|WP_Error
	 */
	private static function handle_image_action( string $action, array $params ) {
		switch ( $action ) {
			case 'generate_image':
				// Trigger image generation via ComfyUI.
				return [
					'status' => 'queued',
					'prompt' => $params['prompt'] ?? '',
				];
			default:
				return new WP_Error( 'invalid_action', "Unknown image action: {$action}" );
		}
	}

	/**
	 * Handle video agent actions.
	 *
	 * @param string $action
	 * @param array  $params
	 * @return array|WP_Error
	 */
	private static function handle_video_action( string $action, array $params ) {
		switch ( $action ) {
			case 'generate_video':
				return [
					'status' => 'queued',
					'prompt' => $params['prompt'] ?? '',
				];
			default:
				return new WP_Error( 'invalid_action', "Unknown video action: {$action}" );
		}
	}

	/**
	 * Log an agent action.
	 *
	 * @param int    $agent_id
	 * @param string $action
	 * @param array  $params
	 * @param array  $result
	 */
	private static function log_action( int $agent_id, string $action, array $params, array $result ): void {
		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'action'    => $action,
			'params'    => $params,
			'result'    => $result,
		];

		$logs = get_post_meta( $agent_id, '_worldgraph_agent_logs', true ) ?: [];
		$logs[] = $log_entry;

		// Keep only last 100 logs.
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_post_meta( $agent_id, '_worldgraph_agent_logs', $logs );
	}

	/**
	 * Get agent action history.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_history( WP_REST_Request $request ) {
		$agent_id = absint( $request->get_param( 'id' ) );

		$logs = get_post_meta( $agent_id, '_worldgraph_agent_logs', true ) ?: [];

		$page = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;
		$total = count( $logs );

		// Paginate.
		$logs = array_slice( $logs, ( $page - 1 ) * $per_page, $per_page );

		$response = rest_ensure_response( [
			'logs' => array_reverse( $logs ),
		] );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}
}
