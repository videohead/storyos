<?php
/**
 * Provider Connections REST API Controller for StoryOS.
 *
 * Exposes the storyos_connection control-plane resource:
 *
 *   GET    /storyos/v1/connections            List connections
 *   POST   /storyos/v1/connections            Create a connection
 *   GET    /storyos/v1/connections/{id}       Get a connection
 *   PUT    /storyos/v1/connections/{id}       Update a connection
 *   DELETE /storyos/v1/connections/{id}       Delete a connection
 *   GET    /storyos/v1/connections/{id}/resolve  Resolve non-secret config
 *   POST   /storyos/v1/connections/{id}/test   Run a health check
 *   POST   /storyos/v1/connections/sync       Sync provider capabilities
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use StoryOS\Utils\Capability_Sync;
use StoryOS\Utils\Connection_Repository;
use StoryOS\Utils\Connection_Tester;

/**
 * Connections Controller class.
 */
class Connections_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_connection';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'connections';

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
		register_rest_route( 'storyos/v1', '/connections', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args'                => [
					'page'          => [ 'default' => 1 ],
					'per_page'      => [ 'default' => 10, 'maximum' => 100 ],
					'provider_type' => [ 'type' => 'string', 'default' => '' ],
					'environment'   => [ 'type' => 'string', 'default' => '' ],
					'status'        => [ 'type' => 'string', 'default' => '' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/connections/sync', [
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'sync_capabilities' ],
				'permission_callback' => [ $this, 'check_create_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/connections/(?P<id>\d+)', [
			'args' => [ 'id' => [ 'type' => 'integer' ] ],
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

		register_rest_route( 'storyos/v1', '/connections/(?P<id>\d+)/resolve', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'resolve_connection' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( 'storyos/v1', '/connections/(?P<id>\d+)/test', [
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'test_connection' ],
				'permission_callback' => [ $this, 'check_update_permission' ],
			],
		] );
	}

	/**
	 * List connections, filtered by provider type, environment, or status.
	 *
	 * Overrides the base implementation because connection status is a meta
	 * field (not the storyos_status taxonomy) and the repository provides
	 * meta-based filtering.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		if ( ! $this->check_read_permission( $request ) ) {
			return new \WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 401 ] );
		}

		$filters = [];
		foreach ( [ 'provider_type', 'environment', 'status' ] as $key ) {
			$value = $request->get_param( $key );
			if ( ! empty( $value ) ) {
				$filters[ $key ] = sanitize_key( $value );
			}
		}

		$connections = Connection_Repository::get_all( $filters );

		$items = [];
		foreach ( $connections as $connection ) {
			$post = get_post( $connection['id'] );
			if ( $post ) {
				$items[] = $this->prepare_item( $post, $request->get_params() );
			}
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', count( $items ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return $response;
	}

	/**
	 * Resolve the non-secret configuration for a connection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function resolve_connection( \WP_REST_Request $request ) {
		$connection_id = absint( $request->get_param( 'id' ) );
		$config        = Connection_Repository::resolve( $connection_id );

		if ( null === $config ) {
			return new \WP_Error( 'rest_connection_not_found', 'Connection not found.', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $config );
	}

	/**
	 * Run a health check against the orchestrator for a connection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function test_connection( \WP_REST_Request $request ) {
		$connection_id = absint( $request->get_param( 'id' ) );
		$result        = Connection_Tester::test( $connection_id );

		$response = rest_ensure_response( $result );
		$response->set_status( $result['success'] ? 200 : 422 );

		return $response;
	}

	/**
	 * Synchronize provider capabilities from the orchestrator.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function sync_capabilities( \WP_REST_Request $request ) {
		$result = Capability_Sync::sync();

		if ( ! $result['success'] ) {
			return new \WP_Error( 'rest_capability_sync_failed', $result['message'], [ 'status' => 502 ] );
		}

		return rest_ensure_response( $result );
	}
}
