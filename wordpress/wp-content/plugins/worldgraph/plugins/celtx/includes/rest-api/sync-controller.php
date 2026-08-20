<?php
/**
 * Celtx REST Sync Controller.
 *
 * Handles REST API endpoints for Celtx sync operations.
 *
 * @package WorldGraphCeltx
 */

namespace WorldGraphCeltx\REST;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST Sync Controller class.
 *
 * Registers and handles REST API routes for Celtx synchronization.
 */
class Sync_Controller {

	/**
	 * Controller instance.
	 *
	 * @var Sync_Controller|null
	 */
	private static $instance = null;

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'worldgraph/v1';

	/**
	 * Get the controller instance.
	 *
	 * @return Sync_Controller
	 */
	public static function init(): Sync_Controller {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register REST routes.
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		// Test Celtx API connection.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/test',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Sync all World Graph Studio elements.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/sync',
			[
				['methods'             => 'POST',
				'callback'            => [ $this, 'sync_all' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				['methods'             => 'GET',
				'callback'            => [ $this, 'get_sync_status' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// Sync specific element type.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/sync/(?P<type>[a-z_]+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'sync_element_type' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Sync individual element.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/sync/(?P<type>[a-z_]+)/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'sync_individual_element' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Get sync mapping for an element.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/mapping/(?P<type>[a-z_]+)/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_mapping' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Unsync an element.
		register_rest_route(
			self::NAMESPACE,
			'/celtx/unsync/(?P<type>[a-z_]+)/(?P<id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'unsync_element' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	/**
	 * Check if user has admin permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'You do not have permission to access Celtx sync settings.',
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Test Celtx API connection.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$client = \WorldGraphCeltx\API\Client::from_credentials();

		if ( ! $client ) {
			return new \WP_REST_Response(
				[ 'message' => 'Celtx API credentials not configured.' ],
				400
			);
		}

		$response = $client->get_status();
		$parsed = $client->parse_response( $response );

		if ( $client->is_success( $response ) ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => 'Connection successful!',
					'data'    => $parsed['body'] ?? [],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $parsed['error'] ?? 'Connection failed.',
			],
			500
		);
	}

	/**
	 * Sync all World Graph Studio elements to Celtx.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function sync_all( \WP_REST_Request $request ): \WP_REST_Response {
		$sync = \WorldGraphCeltx\Sync::init();
		$results = $sync->sync_all();

		$status_code = $results['failures'] > 0 ? 207 : 200;

		return new \WP_REST_Response(
			[
				'success'   => $results['failures'] === 0,
				'message'   => sprintf(
					'Sync completed: %d successes, %d failures out of %d total',
					$results['successes'],
					$results['failures'],
					$results['total']
				),
				'data'      => $results,
			],
			$status_code
		);
	}

	/**
	 * Get sync status.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_sync_status( \WP_REST_Request $request ): \WP_REST_Response {
		$status = [
			'enabled'      => \WorldGraphCeltx\celtx_sync_enabled(),
			'last_sync'    => \get_option( 'worldgraph_celtx_last_sync', null ),
			'sync_counts'  => [
				'projects'  => \wp_count_posts( 'worldgraph_project' )->publish,
				'characters'=> \wp_count_posts( 'worldgraph_character' )->publish,
				'locations' => \wp_count_posts( 'worldgraph_location' )->publish,
				'scenes'    => \wp_count_posts( 'worldgraph_scene' )->publish,
				'shots'     => \wp_count_posts( 'worldgraph_shot' )->publish,
			],
		];

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $status,
			],
			200
		);
	}

	/**
	 * Sync all elements of a specific type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function sync_element_type( \WP_REST_Request $request ): \WP_REST_Response {
		$type = $request->get_param( 'type' );
		$sync = \WorldGraphCeltx\Sync::init();

		$valid_types = [ 'project', 'character', 'location', 'scene', 'shot' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => 'Invalid element type. Must be: ' . implode( ', ', $valid_types ),
				],
				400
			);
		}

		// Get all posts of this type.
		$posts = \get_posts( [
			'post_type'   => 'worldgraph_' . $type,
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		$results = [];
		$successes = 0;
		$failures = 0;

		foreach ( $posts as $post ) {
			$action = 'sync_' . $type;
			$result = call_user_func( [ $sync, $action ], $post->ID );
			
			$results[ $post->ID ] = $result;
			if ( $result['success'] ) {
				$successes++;
			} else {
				$failures++;
			}
		}

		$status_code = $failures > 0 ? 207 : 200;

		return new \WP_REST_Response(
			[
				'success'   => $failures === 0,
				'message'   => sprintf(
					'Synced %d %s(s): %d successes, %d failures',
					count( $posts ),
					$type,
					$successes,
					$failures
				),
				'data'      => [
					'type'      => $type,
					'results'   => $results,
					'successes' => $successes,
					'failures'  => $failures,
				],
			],
			$status_code
		);
	}

	/**
	 * Sync an individual element.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function sync_individual_element( \WP_REST_Request $request ): \WP_REST_Response {
		$type = $request->get_param( 'type' );
		$id = $request->get_param( 'id' );
		$sync = \WorldGraphCeltx\Sync::init();

		$valid_types = [ 'project', 'character', 'location', 'scene', 'shot' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => 'Invalid element type.',
				],
				400
			);
		}

		$action = 'sync_' . $type;
		if ( ! method_exists( $sync, $action ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => 'No sync method found for type: ' . $type,
				],
				400
			);
		}

		$result = call_user_func( [ $sync, $action ], $id );

		return new \WP_REST_Response(
			[
				'success' => $result['success'],
				'message' => $result['message'] ?? ( $result['success'] ? 'Sync successful.' : 'Sync failed.' ),
				'data'    => $result,
			],
			$result['success'] ? 200 : 500
		);
	}

	/**
	 * Get Celtx mapping for an element.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_mapping( \WP_REST_Request $request ): \WP_REST_Response {
		$id = $request->get_param( 'id' );
		$sync = \WorldGraphCeltx\Sync::init();
		$mapping = $sync->get_celtx_mapping( $id );

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'post_id' => $id,
					'mapping' => $mapping,
				],
			],
			200
		);
	}

	/**
	 * Unsync an element (remove local mapping).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function unsync_element( \WP_REST_Request $request ): \WP_REST_Response {
		$type = $request->get_param( 'type' );
		$id = $request->get_param( 'id' );
		$sync = \WorldGraphCeltx\Sync::init();

		$success = $sync->unsync( $id, $type );

		return new \WP_REST_Response(
			[
				'success' => $success,
				'message' => $success ? 'Element unsynced successfully.' : 'Failed to unsync element.',
			],
			$success ? 200 : 400
		);
	}
}
