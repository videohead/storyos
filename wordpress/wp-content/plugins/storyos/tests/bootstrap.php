<?php
/**
 * PHPUnit bootstrap file for StoryOS tests.
 *
 * @package StoryOS
 */

// Define plugin constants for testing.
define( 'STORYOS_VERSION', '1.0.0' );
define( 'STORYOS_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) . '/' );

// Plugin files guard against direct access with `exit`, which would end the
// PHPUnit run silently, so stand in for the WordPress root constant.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( STORYOS_PLUGIN_DIR, 3 ) . '/' );
}
define( 'STORYOS_PLUGIN_URL', 'file://' . STORYOS_PLUGIN_DIR );
define( 'STORYOS_PLUGIN_BASE', 'storyos/storyos.php' );
define( 'STORYOS_API_NAMESPACE', 'storyos/v1' );
define( 'STORYOS_CPT_PREFIX', 'storyos_' );

// Load the StoryOS helper layer directly for unit tests.
require_once dirname( __DIR__ ) . '/includes/utils/helpers.php';
require_once dirname( __DIR__ ) . '/includes/utils/relationships.php';
require_once dirname( __DIR__ ) . '/includes/exporter/class-storyos-exporter.php';

// Test files reference the global helper names used in older StoryOS tests.
if ( ! function_exists( 'prefix' ) ) {
	function prefix( string $name = '', string $custom_prefix = '' ): string {
		return \StoryOS\Utils\prefix( $name, $custom_prefix );
	}
}

if ( ! function_exists( 'sanitize_story_id' ) ) {
	function sanitize_story_id( $id ): string {
		return \StoryOS\Utils\sanitize_story_id( $id );
	}
}
