<?php
/**
 * Plugin Name: StoryOS - Generation Engine
 * Plugin URI: https://storyos.dev
 * Description: Adds a generation button to WordPress posts and forwards jobs to the StoryOS ComfyUI MCP bridge.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos-generation-engine
 * Requires Plugins: storyos/storyos.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_GENERATION_ENGINE_VERSION', '1.0.0' );
define( 'STORYOS_GENERATION_ENGINE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_GENERATION_ENGINE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_GENERATION_ENGINE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Load dependencies.
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-provider-registry.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-provider-registry.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-settings.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-settings.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-credential-store.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-credential-store.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-structure-registry.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-structure-registry.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-generation-client.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-generation-client.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-ajax-handler.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-ajax-handler.php';
}
if ( file_exists( STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-editor-button.php' ) ) {
	require_once STORYOS_GENERATION_ENGINE_PLUGIN_DIR . 'includes/class-editor-button.php';
}

/**
 * Initialize the plugin.
 */
function init(): void {
	$settings_class = __NAMESPACE__ . '\\Settings';
	$ajax_class = __NAMESPACE__ . '\\Ajax_Handler';
	$editor_button_class = __NAMESPACE__ . '\\Editor_Button';

	if ( ! class_exists( $settings_class ) ) {
		return;
	}

	$settings_class::init();

	if ( ! $settings_class::is_enabled() ) {
		return;
	}

	if ( class_exists( $ajax_class ) ) {
		$ajax_class::init();
	}

	if ( class_exists( $editor_button_class ) ) {
		$editor_button_class::init();
	}
}

if ( did_action( 'plugins_loaded' ) ) {
	init();
} else {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );
}
