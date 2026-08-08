<?php
/**
 * PHPUnit bootstrap file for StoryOS tests.
 *
 * @package StoryOS
 */

// Define plugin constants for testing.
define( 'STORYOS_VERSION', '1.0.0' );
define( 'STORYOS_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) . '/' );
define( 'STORYOS_PLUGIN_URL', 'file://' . STORYOS_PLUGIN_DIR );
define( 'STORYOS_PLUGIN_BASE', 'storyos/storyos.php' );
define( 'STORYOS_API_NAMESPACE', 'storyos/v1' );
define( 'STORYOS_CPT_PREFIX', 'storyos_' );

// Load the main plugin file.
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/storyos.php';

// Load autoloader.
require_once dirname( __DIR__ ) . '/includes/utils/helpers.php';
require_once dirname( __DIR__ ) . '/includes/utils/relationships.php';
