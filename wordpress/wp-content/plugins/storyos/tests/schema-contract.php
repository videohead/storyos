<?php
/**
 * Minimal schema contract smoke test for StoryOS Phase 8.
 *
 * This intentionally exercises the helper layer without requiring a full WordPress bootstrap.
 */

if ( ! defined( 'STORYOS_CPT_PREFIX' ) ) {
	define( 'STORYOS_CPT_PREFIX', 'storyos_' );
}

function get_option( $name, $default = [] ) {
	static $options = [];
	return $options[ $name ] ?? $default;
}

function update_option( $name, $value ): void {
	static $options = [];
	$options[ $name ] = $value;
}

function register_post_type( $cpt, $args ): void {
	// no-op for smoke test
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

require_once __DIR__ . '/../includes/utils/helpers.php';

$expected_project_fields = \StoryOS\Utils\storyos_expected_fields_for_cpt( 'storyos_project' );
if ( ! in_array( 'project_name', $expected_project_fields, true ) ) {
	fwrite( STDERR, "Expected project_name in schema manifest.\n" );
	exit( 1 );
}

\StoryOS\Utils\storyos_register_fields( 'storyos_project', [
	'project_name' => [ 'type' => 'text' ],
	'project_slug' => [ 'type' => 'text' ],
] );

$report = \StoryOS\Utils\storyos_validate_schema_alignment();
if ( empty( $report['storyos_project']['missing'] ) ) {
	fwrite( STDERR, "Expected missing fields report for storyos_project.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Schema contract smoke test passed.\n" );
