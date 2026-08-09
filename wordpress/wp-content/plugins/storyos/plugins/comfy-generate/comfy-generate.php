<?php
/**
 * Plugin Name: StoryOS - ComfyUI Generate
 * Plugin URI: https://storyos.dev
 * Description: Adds a "Send to ComfyUI" button to WordPress posts and forwards jobs to a configurable ComfyUI endpoint.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos-comfy-generate
 * Requires Plugins: storyos/storyos.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOSComfyGenerate
 */

namespace StoryOSComfyGenerate;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_COMFY_GENERATE_VERSION', '1.0.0' );
define( 'STORYOS_COMFY_GENERATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_COMFY_GENERATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_COMFY_GENERATE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Load dependencies.
if ( file_exists( STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-settings.php' ) ) {
	require_once STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-settings.php';
}
if ( file_exists( STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-ajax-handler.php' ) ) {
	require_once STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-ajax-handler.php';
}
if ( file_exists( STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-editor-button.php' ) ) {
	require_once STORYOS_COMFY_GENERATE_PLUGIN_DIR . 'includes/class-editor-button.php';
}

/**
 * Initialize the plugin.
 */
function init(): void {
	if ( ! class_exists( __NAMESPACE__ . '\\Settings' ) ) {
		return;
	}

	Settings::init();

	if ( ! Settings::is_enabled() ) {
		return;
	}

	Ajax_Handler::init();
	Editor_Button::init();
}

if ( did_action( 'plugins_loaded' ) ) {
	init();
} else {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );
}
