<?php
/**
 * PHPUnit Bootstrap File
 * 
 * This file is loaded before running tests.
 */

// Define test directories.
define( 'TESTS_DIR', __DIR__ );
define( 'PLUGIN_DIR', STORYOS_PLUGIN_PATH );

// Load WordPress test configuration.
$wp_tests_config = getenv( 'WP_TESTS_CONFIG' );
if ( ! $wp_tests_config ) {
	$wp_tests_config = __DIR__ . '/wp-tests-config.php';
}

if ( file_exists( $wp_tests_config ) ) {
	require_once $wp_tests_config;
}

// Autoloader for StoryOS.
spl_autoload_register( function ( $class ) {
	$prefix = 'StoryOS\\';
	$base_dir = PLUGIN_DIR . '/includes/';
	
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}
	
	$relative_class = substr( $class, $len );
	
	// Handle special namespace mappings.
	$special_mappings = [
		'CPT\\' => 'cpts/',
		'REST\\' => 'rest-api/',
		'Taxonomies\\' => 'taxonomies/',
		'Admin\\' => 'admin/',
		'Utils\\' => 'utils/',
	];
	foreach ( $special_mappings as $ns => $dir ) {
		if ( strpos( $relative_class, $ns ) === 0 ) {
			$relative_class = $dir . substr( $relative_class, strlen( $ns ) );
			break;
		}
	}
	
	// Convert class names to filenames.
	$path_parts = explode( '/', $relative_class );
	$filename = array_pop( $path_parts );
	
	// Check if this is a REST controller.
	if ( strpos( $relative_class, 'rest-api/' ) !== false ) {
		$filename = str_replace( '_', '-', strtolower( $filename ) ) . '.php';
	} else {
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $filename ) ) . '.php';
	}
	
	$path_parts[] = $filename;
	$file = $base_dir . implode( '/', $path_parts );
	
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Helper function to create a test post.
if ( ! function_exists( 'create_test_post' ) ) {
	function create_test_post( $args = array() ) {
		$defaults = array(
			'post_type'   => 'post',
			'post_title'  => 'Test Post',
			'post_content' => 'Test content',
			'post_status' => 'publish',
			'post_author' => 1,
		);
		
		$args = wp_parse_args( $args, $defaults );
		return wp_insert_post( $args );
	}
}

// Helper function to create a test user.
if ( ! function_exists( 'create_test_user' ) ) {
	function create_test_user( $role = 'administrator' ) {
		$user_id = wp_create_user( 'testuser', 'password', 'testuser@example.com' );
		if ( $user_id && ! is_wp_error( $user_id ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( $role );
		}
		return $user_id;
	}
}
