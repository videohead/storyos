<?php
/**
 * Utility functions for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

/**
 * Get the prefix for StoryOS CPTs and meta keys.
 *
 * @return string
 */
function prefix(): string {
	return STORYOS_CPT_PREFIX;
}

/**
 * Register a StoryOS CPT.
 *
 * @param string $cpt      The CPT slug.
 * @param string $label    The display label.
 * @param array  $args     Additional register_post_type args.
 * @param array  $fields   SCF field definitions.
 */
function register_cpt( string $cpt, string $label, array $args = [], array $fields = [] ): void {
	$defaults = [
		'labels'             => [
			'name'               => $label,
			'singular_name'      => $label,
			'menu_name'          => $label,
			'add_new'            => 'Add New',
			'add_new_item'       => "Add New {$label}",
			'edit_item'          => "Edit {$label}",
			'new_item'           => "New {$label}",
			'view_item'          => "View {$label}",
			'search_items'       => "Search {$label}",
			'not_found'          => "No {$label} found",
			'not_found_in_trash' => "No {$label} found in Trash",
			'all_items'          => "All {$label}",
		],
		'public'             => true,
		'has_archive'        => true,
		'rewrite'            => ['slug' => $cpt],
		'show_in_rest'       => true,
		'rest_base'          => $cpt,
		'supports'           => ['title', 'editor', 'excerpt', 'custom-fields', 'revisions'],
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
	];

	$args = wp_parse_args( $args, $defaults );
	register_post_type( $cpt, $args );

	// Store field definitions for REST API and admin.
	if ( ! empty( $fields ) ) {
		storyos_register_fields( $cpt, $fields );
	}
}

/**
 * Register structured content fields for a CPT.
 *
 * @param string $cpt    The CPT slug.
 * @param array  $fields Field definitions.
 */
function storyos_register_fields( string $cpt, array $fields ): void {
	$all_fields = get_option( 'storyos_fields', [] );
	$all_fields[ $cpt ] = $fields;
	update_option( 'storyos_fields', $all_fields );
}

/**
 * Get registered fields for a CPT.
 *
 * @param string $cpt The CPT slug.
 * @return array
 */
function storyos_get_fields( string $cpt ): array {
	$all_fields = get_option( 'storyos_fields', [] );
	return $all_fields[ $cpt ] ?? [];
}

/**
 * Get all registered StoryOS CPTs.
 *
 * @return array
 */
function storyos_get_all_cpts(): array {
	return [
		'storyos_project'         => 'Project',
		'storyos_story_world'     => 'Story World',
		'storyos_character'       => 'Character',
		'storyos_location'        => 'Location',
		'storyos_prop'            => 'Prop',
		'storyos_organization'    => 'Organization',
		'storyos_episode'         => 'Episode',
		'storyos_scene'           => 'Scene',
		'storyos_shot'            => 'Shot',
		'storyos_storyboard_frame' => 'Storyboard Frame',
		'storyos_asset'           => 'Asset',
		'storyos_editorial_artifact' => 'Editorial Artifact',
	];
}

/**
 * Sanitize a story graph ID.
 *
 * @param mixed $id The ID.
 * @return int
 */
function sanitize_story_id( $id ): int {
	return absint( $id );
}

/**
 * Get the current user's StoryOS role.
 *
 * @return string
 */
function storyos_user_role(): string {
	if ( ! is_user_logged_in() ) {
		return 'guest';
	}

	if ( current_user_can( 'manage_storyos' ) ) {
		return 'administrator';
	}

	if ( current_user_can( 'edit_storyos_projects' ) ) {
		return 'producer';
	}

	if ( current_user_can( 'edit_storyos_characters' ) ) {
		return 'writer';
	}

	return 'contributor';
}

/**
 * Check if a post exists and is of a given type.
 *
 * @param int    $post_id The post ID.
 * @param string $post_type The post type.
 * @return bool
 */
function storyos_post_exists( int $post_id, string $post_type = 'post' ): bool {
	if ( ! $post_id ) {
		return false;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	return $post->post_type === $post_type;
}

/**
 * Get StoryOS options.
 *
 * @param string $key   The option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function storyos_option( string $key, $default = null ) {
	return get_option( 'storyos_' . $key, $default );
}

/**
 * Log a StoryOS event.
 *
 * @param string $message The message.
 * @param string $level   The log level.
 */
function storyos_log( string $message, string $level = 'info' ): void {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	$log_entry = sprintf(
		'[%s] [%s] %s',
		current_time( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message
	);

	error_log( $log_entry );
}
