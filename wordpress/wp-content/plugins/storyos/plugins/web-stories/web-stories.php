<?php
/**
 * Plugin Name: StoryOS - Web Stories Sync
 * Plugin URI: https://storyos.dev
 * Description: Synchronize StoryOS elements (Scenes, Storyboard Frames) with Google Web Stories. Import Web Stories into StoryOS for production management, or export StoryOS scenes to Web Stories format.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos-web-stories
 * Requires Plugins: storyos/storyos.php, web-stories/web-stories.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOSWebStories
 */

namespace StoryOSWebStories;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_WEB_STORIES_VERSION', '1.0.0' );
define( 'STORYOS_WEB_STORIES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_WEB_STORIES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_WEB_STORIES_PLUGIN_BASE', plugin_basename( __FILE__ ) );

/**
 * Autoloader for StoryOS Web Stories classes.
 *
 * @param string $class The class name.
 */
function autoloader( string $class ): void {
	$prefix = 'StoryOSWebStories\\';
	$base_dir = STORYOS_WEB_STORIES_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Convert namespace to file path.
	$last_backslash = strrpos( $relative_class, '\\' );
	if ( false !== $last_backslash ) {
		$namespace = substr( $relative_class, 0, $last_backslash );
		$class_name = substr( $relative_class, $last_backslash + 1 );

		$namespace_dir = strtolower( str_replace( '\\', '/', $namespace ) );
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );

		$file = $base_dir . $namespace_dir . '/' . $filename . '.php';
	} else {
		$class_name = $relative_class;
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );
		$file = $base_dir . $filename . '.php';
	}

	if ( file_exists( $file ) ) {
		require $file;
	}
}

spl_autoload_register( __NAMESPACE__ . '\\autoloader' );

/**
 * Initialize the plugin.
 */
function init(): void {
	// Check dependencies.
	if ( ! class_exists( 'Google\\Web_Stories\\Story_Post_Type' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notice_web_stories_missing' );
		return;
	}

	if ( ! in_array( 'storyos/storyos.php', get_option( 'active_plugins', [] ), true ) && ! class_exists( 'StoryOS\\Utils\\register_cpt' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notice_storyos_missing' );
		return;
	}

	// Initialize components.
	\StoryOSWebStories\API\Client::class;
	\StoryOSWebStories\Sync::init();
	\StoryOSWebStories\Settings::init();

	// Register REST API routes.
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );

	// Register hooks for sync triggers.
	add_action( 'save_post_web-story', __NAMESPACE__ . '\\on_web_story_save', 10, 3 );
	add_action( 'save_post_storyos_scene', __NAMESPACE__ . '\\on_storyos_scene_save', 10, 3 );
}
add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Register REST API routes.
 */
function register_rest_routes(): void {
	\StoryOSWebStories\REST\Sync_Controller::init();
}

/**
 * Admin notice when Web Stories plugin is missing.
 */
function admin_notice_web_stories_missing(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				__( 'StoryOS Web Stories Sync requires the <strong>Web Stories</strong> plugin by Google to be installed and activated.', 'storyos-web-stories' ),
				[ 'strong' => [] ]
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Admin notice when StoryOS plugin is missing.
 */
function admin_notice_storyos_missing(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				__( 'StoryOS Web Stories Sync requires the <strong>StoryOS</strong> plugin to be installed and activated.', 'storyos-web-stories' ),
				[ 'strong' => [] ]
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Trigger sync when a Web Story is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function on_web_story_save( int $post_id, \WP_Post $post, bool $update ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Only sync if this story is already mapped.
	$mapping = get_post_meta( $post_id, '_storyos_web_stories_mapping', true );
	if ( empty( $mapping['scene_id'] ) ) {
		return;
	}

	// Trigger sync.
	\StoryOSWebStories\Sync::init()->sync_story( $post_id );
}

/**
 * Trigger sync when a StoryOS Scene is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function on_storyos_scene_save( int $post_id, \WP_Post $post, bool $update ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Only sync if this scene is already mapped.
	$mapping = get_post_meta( $post_id, '_storyos_web_stories_mapping', true );
	if ( empty( $mapping['story_id'] ) ) {
		return;
	}

	// Trigger sync.
	\StoryOSWebStories\Sync::init()->sync_scene( $post_id );
}
