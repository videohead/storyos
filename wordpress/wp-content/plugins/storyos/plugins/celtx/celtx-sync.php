<?php
/**
 * Plugin Name: StoryOS - Celtx Sync
 * Plugin URI: https://storyos.dev
 * Description: Synchronize StoryOS elements (Projects, Characters, Locations, Scenes, Shots) with Celtx using the Celtx GEM Bi-Directional API.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos-celtx
 * Requires Plugins: storyos/storyos.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOSCeltx
 */

namespace StoryOSCeltx;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_CELTX_VERSION', '1.0.0' );
define( 'STORYOS_CELTX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_CELTX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_CELTX_PLUGIN_BASE', plugin_basename( __FILE__ ) );

/**
 * Autoloader for StoryOS Celtx classes.
 *
 * @param string $class The class name.
 */
function autoloader( string $class ): void {
	$prefix = 'StoryOSCeltx\\';
	$base_dir = STORYOS_CELTX_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	
	// Convert namespace to file path.
	// StoryOSCeltx\API\Client -> api/client.php
	// StoryOSCeltx\Sync -> sync.php
	// StoryOSCeltx\Settings -> settings.php
	
	// Remove trailing class name for namespace paths
	$last_backslash = strrpos( $relative_class, '\\' );
	if ( false !== $last_backslash ) {
		$namespace = substr( $relative_class, 0, $last_backslash );
		$class_name = substr( $relative_class, $last_backslash + 1 );
		
		// Convert namespace to directory
		$namespace_dir = strtolower( str_replace( '\\', '/', $namespace ) );
		
		// Convert class name to filename (camelCase to kebab-case)
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );
		
		$file = $base_dir . $namespace_dir . '/' . $filename . '.php';
	} else {
		// Top-level class
		$class_name = $relative_class;
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );
		$file = $base_dir . $filename . '.php';
	}

	// Compatibility fallbacks for existing StoryOS Celtx file naming.
	if ( ! file_exists( $file ) ) {
		$fallback_map = [
			'sync'                => $base_dir . 'class-celtx-sync.php',
			'settings'            => $base_dir . 'class-celtx-settings.php',
			'api/client'          => $base_dir . 'class-celtx-api.php',
			'rest/sync-controller' => $base_dir . 'rest-api/sync-controller.php',
		];

		$normalized_key = strtolower( str_replace( '\\', '/', $relative_class ) );
		if ( isset( $fallback_map[ $normalized_key ] ) ) {
			$file = $fallback_map[ $normalized_key ];
		}
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
	// Initialize components.
	\StoryOSCeltx\API\Client::class; // Ensure class is loaded.
	\StoryOSCeltx\Sync::init();
	\StoryOSCeltx\Settings::init();
	
	// Register REST API routes.
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );
}

if ( did_action( 'init' ) ) {
	init();
} else {
	add_action( 'init', __NAMESPACE__ . '\\init' );
}

/**
 * Register REST API routes.
 */
function register_rest_routes(): void {
	\StoryOSCeltx\REST\Sync_Controller::init();
}
