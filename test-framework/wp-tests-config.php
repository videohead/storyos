<?php
/**
 * WordPress Test Configuration File
 * 
 * This file is used by PHPUnit to configure the WordPress test environment.
 */

// ** MySQL settings ** //
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'database' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'DB_PORT', '3306' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

// Set the plugin path for StoryOS
define( 'STORYOS_PLUGIN_PATH', '/app/wordpress/wp-content/plugins/storyos' );

// Path to the WordPress code. You do not need to change this.
define( 'ABSPATH', '/usr/src/wordpress/' );

// Load WordPress test library.
require_once ABSPATH . 'wp-includes/functions.php';
require_once ABSPATH . 'wp-includes/class-wp-error.php';
require_once ABSPATH . 'wp-includes/plugin.php';

// Load StoryOS plugin.
require_once STORYOS_PLUGIN_PATH . '/storyos.php';

// Load WordPress test case classes.
require_once '/usr/src/wordpress-tests/include/wpTestCase.php';
