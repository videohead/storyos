<?php
/**
 * Web Stories Sync REST Controller.
 *
 * Handles REST API endpoints for sync operations.
 *
 * @package StoryOSWebStories\REST
 */

namespace StoryOSWebStories\REST;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Controller class.
 *
 * REST API controller for Web Stories sync operations.
 */
class Sync_Controller {

	/**
	 * Controller instance.
	 *
	 * @var Sync_Controller|null
	 */
	private static $instance = null;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	private $namespace = 'storyos-web-stories/v1';

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
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// Sync a specific Web Story.
		register_rest_route(
			$this->namespace,
			'/sync/story/(?P<story_id>\d+)',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'sync_story' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'story_id' => [
							'required' => true,
							'type'     => 'integer',
						],
					],
				],
			]
		);

		// Sync a specific StoryOS Scene.
		register_rest_route(
			$this->namespace,
			'/sync/scene/(?P<scene_id>\d+)',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'sync_scene' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'scene_id' => [
							'required' => true,
							'type'     => 'integer',
						],
					],
				],
			]
		);

		// Sync all mapped items.
		register_rest_route(
			$this->namespace,
			'/sync/all',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'sync_all' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Get sync mapping for a StoryOS post.
		register_rest_route(
			$this->namespace,
			'/mapping/(?P<post_id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_mapping' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'post_id' => [
							'required' => true,
							'type'     => 'integer',
						],
					],
				],
			]
		);

		// Get sync status.
		register_rest_route(
			$this->namespace,
			'/status',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_status' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Get sync settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'sync_enabled'       => [
							'required' => false,
							'type'     => 'boolean',
						],
						'sync_direction'     => [
							'required' => false,
							'type'     => 'string',
							'enum'     => [ 'bidirectional', 'storyos_to_web', 'web_to_storyos' ],
						],
						'auto_sync_on_save'  => [
							'required' => false,
							'type'     => 'boolean',
						],
						'sync_storyboard'    => [
							'required' => false,
							'type'     => 'boolean',
						],
						'default_status'     => [
							'required' => false,
							'type'     => 'string',
							'enum'     => [ 'draft', 'publish', 'archived' ],
						],
						'create_pages_from'  => [
							'required' => false,
							'type'     => 'string',
							'enum'     => [ 'summary', 'script', 'content', 'combined' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Check if user has permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Sync a Web Story to StoryOS Scene.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sync_story( \WP_REST_Request $request ) {
		$story_id = (int) $request->get_param( 'story_id' );

		$result = \StoryOSWebStories\Sync::init()->sync_story( $story_id );

		if ( $result['success'] ) {
			return rest_ensure_response( [
				'success' => true,
				'message' => 'Story synced successfully.',
				'data'    => $result,
			] );
		}

		return rest_ensure_response( [
			'success' => false,
			'message' => $result['message'] ?? 'Failed to sync story.',
			'data'    => $result,
		] );
	}

	/**
	 * Sync a StoryOS Scene to Web Story.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sync_scene( \WP_REST_Request $request ) {
		$scene_id = (int) $request->get_param( 'scene_id' );

		$result = \StoryOSWebStories\Sync::init()->sync_scene( $scene_id );

		if ( $result['success'] ) {
			return rest_ensure_response( [
				'success' => true,
				'message' => 'Scene synced successfully.',
				'data'    => $result,
			] );
		}

		return rest_ensure_response( [
			'success' => false,
			'message' => $result['message'] ?? 'Failed to sync scene.',
			'data'    => $result,
		] );
	}

	/**
	 * Sync all mapped items.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sync_all( \WP_REST_Request $request ) {
		$result = \StoryOSWebStories\Sync::init()->sync_all();

		return rest_ensure_response( [
			'success' => $result['success'],
			'message' => $result['success'] ? 'Sync completed.' : 'Sync failed.',
			'data'    => $result,
		] );
	}

	/**
	 * Get sync mapping for a StoryOS post.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_mapping( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$mapping = \StoryOSWebStories\Sync::init()->get_mapping( $post_id );

		return rest_ensure_response( [
			'success' => true,
			'data'    => [
				'post_id' => $post_id,
				'mapping' => $mapping,
			],
		] );
	}

	/**
	 * Get sync status.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status( \WP_REST_Request $request ) {
		$settings = \StoryOSWebStories\Settings::init()->get_settings();

		// Check if Web Stories plugin is active.
		$web_stories_active = class_exists( 'Google\\Web_Stories\\Story_Post_Type' );

		// Count mapped items.
		global $wpdb;
		$story_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_storyos_web_stories_mapping' AND meta_value LIKE '%story_id%'" );
		$scene_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_storyos_web_stories_mapping' AND meta_value LIKE '%scene_id%'" );

		return rest_ensure_response( [
			'success' => true,
			'data'    => [
				'sync_enabled'       => (bool) ( $settings['sync_enabled'] ?? false ),
				'sync_direction'     => $settings['sync_direction'] ?? 'bidirectional',
				'auto_sync_on_save'  => (bool) ( $settings['auto_sync_on_save'] ?? true ),
				'web_stories_active' => $web_stories_active,
				'mapped_stories'     => (int) $story_count,
				'mapped_scenes'      => (int) $scene_count,
			],
		] );
	}

	/**
	 * Get sync settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_settings( \WP_REST_Request $request ) {
		$settings = \StoryOSWebStories\Settings::init()->get_settings();

		return rest_ensure_response( [
			'success' => true,
			'data'    => $settings,
		] );
	}

	/**
	 * Update sync settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$settings = \StoryOSWebStories\Settings::init()->get_settings();

		// Update each provided setting.
		$updatable = [
			'sync_enabled',
			'sync_direction',
			'auto_sync_on_save',
			'sync_storyboard',
			'default_status',
			'create_pages_from',
		];

		foreach ( $updatable as $key ) {
			if ( $request->has_param( $key ) ) {
				$settings[ $key ] = $request->get_param( $key );
			}
		}

		update_option( \StoryOSWebStories\Settings::init()->option_name, $settings );

		return rest_ensure_response( [
			'success' => true,
			'message' => 'Settings updated successfully.',
			'data'    => $settings,
		] );
	}
}
