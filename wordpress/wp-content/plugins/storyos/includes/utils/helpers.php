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
 * @param string $name Optional name to append to the prefix.
 * @param string $custom_prefix Optional custom prefix override.
 * @return string
 */
function prefix( string $name = '', string $custom_prefix = '' ): string {
	$prefix = '' === $custom_prefix ? STORYOS_CPT_PREFIX : $custom_prefix;

	if ( '' === $name ) {
		return $prefix;
	}

	return $prefix . $name;
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
	$normalized_fields = [];
	foreach ( $fields as $field_name => $field_config ) {
		$normalized_fields[ $field_name ] = array_merge( [ 'name' => $field_name ], $field_config );
	}

	$all_fields = get_option( 'storyos_fields', [] );
	$all_fields[ $cpt ] = $normalized_fields;
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
 * Get the expected field names for a StoryOS CPT from the canonical schema contract.
 *
 * @param string $cpt CPT slug.
 * @return array<int, string>
 */
function storyos_expected_fields_for_cpt( string $cpt ): array {
	$expected_fields = [
		'storyos_project'            => [ 'project_name', 'project_slug', 'description', 'genre', 'target_medium', 'status', 'owner', 'start_date', 'end_date', 'team_members', 'production_stage' ],
		'storyos_story_world'        => [ 'world_name', 'synopsis', 'timeline', 'rules', 'themes', 'geography', 'references', 'project' ],
		'storyos_character'          => [ 'display_name', 'biography', 'age', 'appearance', 'personality', 'motivation', 'backstory', 'voice_profile', 'avatar_asset', 'story_world' ],
		'storyos_location'           => [ 'location_name', 'description', 'environment_type', 'geography', 'mood', 'visual_reference', 'story_world' ],
		'storyos_prop'               => [ 'prop_name', 'description', 'purpose', 'owner_character', 'notes' ],
		'storyos_organization'       => [ 'organization_name', 'organization_type', 'description', 'leadership', 'goals', 'story_world' ],
		'storyos_episode'            => [ 'episode_number', 'title', 'synopsis', 'status', 'project' ],
		'storyos_scene'              => [ 'scene_number', 'title', 'summary', 'script_content', 'location', 'time_of_day', 'emotional_tone', 'production_notes', 'sequence', 'episode' ],
		'storyos_shot'               => [ 'shot_number', 'shot_type', 'camera_angle', 'lens', 'duration', 'take_number', 'slate_id', 'shot_description', 'editorial_notes', 'scene' ],
		'storyos_storyboard_frame'   => [ 'frame_number', 'frame_description', 'image_asset', 'prompt_text', 'camera_notes', 'scene', 'shot' ],
		'storyos_asset'              => [ 'asset_title', 'asset_type', 'workflow_name', 'prompt', 'model_name', 'seed', 'generation_parameters', 'version', 'status', 'storage_uri', 'character', 'location', 'scene', 'storyboard' ],
		'storyos_editorial_artifact' => [ 'artifact_type', 'export_format', 'generated_date', 'source_scene', 'source_shot', 'notes', 'project' ],
		'storyos_template'           => [ 'template_name', 'description', 'generation_structure', 'configuration_json', 'default_values', 'provider_type', 'version', 'status' ],
	];

	return $expected_fields[ $cpt ] ?? [];
}

/**
 * Validate that a StoryOS CPT's registered fields match the canonical schema contract.
 *
 * @return array<string, array<string, mixed>>
 */
function storyos_validate_schema_alignment(): array {
	$report = [];
	foreach ( array_keys( storyos_get_all_cpts() ) as $cpt ) {
		$registered_fields = storyos_get_fields( $cpt );
		$registered_field_names = array_keys( $registered_fields );
		$expected_fields = storyos_expected_fields_for_cpt( $cpt );
		$missing_fields = array_values( array_diff( $expected_fields, $registered_field_names ) );

		$report[ $cpt ] = [
			'expected'   => $expected_fields,
			'registered' => $registered_field_names,
			'missing'    => $missing_fields,
			'has_alignment' => empty( $missing_fields ),
		];
	}

	return $report;
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
		'storyos_template'        => 'Template',
	];
}

/**
 * Get Schema.org base type for each StoryOS CPT.
 *
 * This is a non-destructive semantic alignment layer used for interoperability.
 *
 * @return array<string, string>
 */
function storyos_schema_type_map(): array {
	return [
		'storyos_project'           => 'CreativeWork',
		'storyos_story_world'       => 'CreativeWork',
		'storyos_character'         => 'Person',
		'storyos_location'          => 'Place',
		'storyos_prop'              => 'Thing',
		'storyos_organization'      => 'Organization',
		'storyos_episode'           => 'Episode',
		'storyos_scene'             => 'Clip',
		'storyos_shot'              => 'Clip',
		'storyos_storyboard_frame'  => 'ImageObject',
		'storyos_asset'             => 'MediaObject',
		'storyos_editorial_artifact'=> 'CreativeWork',
	];
}

/**
 * Resolve Schema.org type for a specific entity using available metadata.
 *
 * This remains non-destructive and only affects semantic interpretation.
 *
 * @param string $cpt        StoryOS CPT slug.
 * @param array  $meta       StoryOS meta values.
 * @param array  $taxonomies StoryOS taxonomy values.
 * @return string
 */
function storyos_schema_type_for_entity( string $cpt, array $meta = [], array $taxonomies = [] ): string {
	$type_map = storyos_schema_type_map();
	$base_type = $type_map[ $cpt ] ?? 'Thing';

	if ( 'storyos_project' === $cpt ) {
		$target_medium = strtolower( (string) ( $meta['target_medium'] ?? '' ) );
		if ( in_array( $target_medium, [ 'film', 'short_film' ], true ) ) {
			return 'Movie';
		}
	}

	if ( 'storyos_asset' === $cpt ) {
		$asset_terms = $taxonomies['storyos_asset_type'] ?? [];
		$asset_slugs = array_map(
			static function( $term ) {
				return strtolower( (string) ( $term['slug'] ?? '' ) );
			},
			$asset_terms
		);

		if ( in_array( 'video', $asset_slugs, true ) ) {
			return 'VideoObject';
		}
		if ( in_array( 'audio', $asset_slugs, true ) ) {
			return 'AudioObject';
		}
		if ( array_intersect( $asset_slugs, [ 'character', 'environment', 'prop', 'storyboard', 'lookbook', 'concept-art' ] ) ) {
			return 'ImageObject';
		}
	}

	return $base_type;
}

/**
 * Get per-CPT field mappings to closest Schema.org properties.
 *
 * Match levels:
 * - exact: direct semantic equivalent
 * - close: strong practical equivalent
 * - weak: partial or context-dependent equivalent
 *
 * @return array<string, array<string, array<string, string>>>
 */
function storyos_schema_field_map(): array {
	return [
		'storyos_project' => [
			'project_name'     => [ 'property' => 'name', 'match' => 'exact' ],
			'project_slug'     => [ 'property' => 'identifier', 'match' => 'close' ],
			'description'      => [ 'property' => 'description', 'match' => 'exact' ],
			'genre'            => [ 'property' => 'genre', 'match' => 'exact' ],
			'target_medium'    => [ 'property' => 'additionalType', 'match' => 'close' ],
			'status'           => [ 'property' => 'creativeWorkStatus', 'match' => 'close' ],
			'owner'            => [ 'property' => 'creator', 'match' => 'close' ],
			'start_date'       => [ 'property' => 'dateCreated', 'match' => 'close' ],
			'end_date'         => [ 'property' => 'expires', 'match' => 'weak' ],
			'team_members'     => [ 'property' => 'contributor', 'match' => 'close' ],
			'production_stage' => [ 'property' => 'creativeWorkStatus', 'match' => 'close' ],
		],
		'storyos_story_world' => [
			'world_name'  => [ 'property' => 'name', 'match' => 'exact' ],
			'synopsis'    => [ 'property' => 'description', 'match' => 'close' ],
			'timeline'    => [ 'property' => 'temporalCoverage', 'match' => 'close' ],
			'rules'       => [ 'property' => 'text', 'match' => 'weak' ],
			'themes'      => [ 'property' => 'about', 'match' => 'close' ],
			'geography'   => [ 'property' => 'spatialCoverage', 'match' => 'close' ],
			'references'  => [ 'property' => 'citation', 'match' => 'close' ],
			'project'     => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
		'storyos_character' => [
			'display_name'  => [ 'property' => 'name', 'match' => 'exact' ],
			'biography'     => [ 'property' => 'description', 'match' => 'close' ],
			'age'           => [ 'property' => 'description', 'match' => 'weak' ],
			'appearance'    => [ 'property' => 'description', 'match' => 'weak' ],
			'personality'   => [ 'property' => 'description', 'match' => 'weak' ],
			'motivation'    => [ 'property' => 'knowsAbout', 'match' => 'weak' ],
			'backstory'     => [ 'property' => 'description', 'match' => 'close' ],
			'voice_profile' => [ 'property' => 'description', 'match' => 'weak' ],
			'avatar_asset'  => [ 'property' => 'image', 'match' => 'close' ],
			'story_world'   => [ 'property' => 'subjectOf', 'match' => 'weak' ],
		],
		'storyos_location' => [
			'location_name'    => [ 'property' => 'name', 'match' => 'exact' ],
			'description'      => [ 'property' => 'description', 'match' => 'exact' ],
			'environment_type' => [ 'property' => 'additionalType', 'match' => 'close' ],
			'geography'        => [ 'property' => 'address', 'match' => 'close' ],
			'mood'             => [ 'property' => 'description', 'match' => 'weak' ],
			'visual_reference' => [ 'property' => 'photo', 'match' => 'close' ],
			'story_world'      => [ 'property' => 'containedInPlace', 'match' => 'close' ],
		],
		'storyos_prop' => [
			'prop_name'        => [ 'property' => 'name', 'match' => 'exact' ],
			'description'      => [ 'property' => 'description', 'match' => 'exact' ],
			'purpose'          => [ 'property' => 'about', 'match' => 'close' ],
			'owner_character'  => [ 'property' => 'owner', 'match' => 'close' ],
			'notes'            => [ 'property' => 'text', 'match' => 'weak' ],
		],
		'storyos_organization' => [
			'organization_name' => [ 'property' => 'name', 'match' => 'exact' ],
			'organization_type' => [ 'property' => 'additionalType', 'match' => 'close' ],
			'description'       => [ 'property' => 'description', 'match' => 'exact' ],
			'leadership'        => [ 'property' => 'member', 'match' => 'close' ],
			'goals'             => [ 'property' => 'slogan', 'match' => 'weak' ],
			'story_world'       => [ 'property' => 'subjectOf', 'match' => 'weak' ],
		],
		'storyos_episode' => [
			'episode_number' => [ 'property' => 'episodeNumber', 'match' => 'exact' ],
			'title'          => [ 'property' => 'name', 'match' => 'exact' ],
			'synopsis'       => [ 'property' => 'description', 'match' => 'close' ],
			'status'         => [ 'property' => 'creativeWorkStatus', 'match' => 'close' ],
			'project'        => [ 'property' => 'isPartOf', 'match' => 'exact' ],
		],
		'storyos_scene' => [
			'scene_number'      => [ 'property' => 'position', 'match' => 'close' ],
			'title'             => [ 'property' => 'name', 'match' => 'exact' ],
			'summary'           => [ 'property' => 'description', 'match' => 'close' ],
			'script_content'    => [ 'property' => 'text', 'match' => 'close' ],
			'location'          => [ 'property' => 'contentLocation', 'match' => 'exact' ],
			'time_of_day'       => [ 'property' => 'temporal', 'match' => 'close' ],
			'emotional_tone'    => [ 'property' => 'about', 'match' => 'weak' ],
			'production_notes'  => [ 'property' => 'text', 'match' => 'weak' ],
			'episode'           => [ 'property' => 'isPartOf', 'match' => 'exact' ],
		],
		'storyos_shot' => [
			'shot_number'       => [ 'property' => 'position', 'match' => 'close' ],
			'shot_type'         => [ 'property' => 'additionalType', 'match' => 'close' ],
			'camera_angle'      => [ 'property' => 'description', 'match' => 'weak' ],
			'lens'              => [ 'property' => 'description', 'match' => 'weak' ],
			'duration'          => [ 'property' => 'duration', 'match' => 'exact' ],
			'shot_description'  => [ 'property' => 'description', 'match' => 'exact' ],
			'editorial_notes'   => [ 'property' => 'text', 'match' => 'weak' ],
			'scene'             => [ 'property' => 'isPartOf', 'match' => 'exact' ],
		],
		'storyos_storyboard_frame' => [
			'frame_number'      => [ 'property' => 'position', 'match' => 'close' ],
			'frame_description' => [ 'property' => 'description', 'match' => 'exact' ],
			'image_asset'       => [ 'property' => 'image', 'match' => 'close' ],
			'prompt_text'       => [ 'property' => 'text', 'match' => 'close' ],
			'camera_notes'      => [ 'property' => 'description', 'match' => 'weak' ],
			'scene'             => [ 'property' => 'isPartOf', 'match' => 'close' ],
			'shot'              => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
		'storyos_asset' => [
			'asset_title'            => [ 'property' => 'name', 'match' => 'exact' ],
			'asset_type'             => [ 'property' => 'additionalType', 'match' => 'close' ],
			'workflow_name'          => [ 'property' => 'producer', 'match' => 'weak' ],
			'prompt'                 => [ 'property' => 'text', 'match' => 'close' ],
			'model_name'             => [ 'property' => 'producer', 'match' => 'weak' ],
			'seed'                   => [ 'property' => 'identifier', 'match' => 'weak' ],
			'generation_parameters'  => [ 'property' => 'additionalProperty', 'match' => 'close' ],
			'version'                => [ 'property' => 'version', 'match' => 'exact' ],
			'status'                 => [ 'property' => 'creativeWorkStatus', 'match' => 'close' ],
			'storage_uri'            => [ 'property' => 'contentUrl', 'match' => 'close' ],
			'character'              => [ 'property' => 'about', 'match' => 'close' ],
			'location'               => [ 'property' => 'contentLocation', 'match' => 'close' ],
			'scene'                  => [ 'property' => 'isPartOf', 'match' => 'close' ],
			'storyboard'             => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
		'storyos_editorial_artifact' => [
			'artifact_type'   => [ 'property' => 'additionalType', 'match' => 'close' ],
			'export_format'   => [ 'property' => 'encodingFormat', 'match' => 'exact' ],
			'generated_date'  => [ 'property' => 'dateCreated', 'match' => 'close' ],
			'source_scene'    => [ 'property' => 'isBasedOn', 'match' => 'close' ],
			'source_shot'     => [ 'property' => 'isBasedOn', 'match' => 'close' ],
			'notes'           => [ 'property' => 'text', 'match' => 'close' ],
			'project'         => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
	];
}

/**
 * Resolve the closest Schema.org property for a StoryOS field.
 *
 * @param string $cpt        StoryOS CPT slug.
 * @param string $field_name StoryOS field name.
 * @return array<string, string>|null
 */
function storyos_schema_property_for_field( string $cpt, string $field_name ): ?array {
	$map = storyos_schema_field_map();
	if ( empty( $map[ $cpt ] ) || empty( $map[ $cpt ][ $field_name ] ) ) {
		return null;
	}

	return $map[ $cpt ][ $field_name ];
}

/**
 * Summarize exact/close/weak match counts for each CPT.
 *
 * @return array<string, array<string, int>>
 */
function storyos_schema_similarity_summary(): array {
	$map = storyos_schema_field_map();
	$summary = [];

	foreach ( $map as $cpt => $fields ) {
		$summary[ $cpt ] = [
			'exact' => 0,
			'close' => 0,
			'weak'  => 0,
		];

		foreach ( $fields as $field ) {
			$match = $field['match'] ?? 'weak';
			if ( isset( $summary[ $cpt ][ $match ] ) ) {
				$summary[ $cpt ][ $match ]++;
			}
		}
	}

	return $summary;
}

/**
 * Map an internal StoryOS relationship type to a Schema.org property.
 *
 * @param string $relationship_type Internal StoryOS relationship type.
 * @param string $from_cpt          Source StoryOS CPT slug.
 * @param string $to_cpt            Target StoryOS CPT slug.
 * @return string
 */
function storyos_schema_property_for_relationship( string $relationship_type, string $from_cpt = '', string $to_cpt = '' ): string {
	$relationship_type = strtolower( $relationship_type );

	switch ( $relationship_type ) {
		case 'contains':
			return 'hasPart';

		case 'belongs_to':
			return 'isPartOf';

		case 'derived_from':
			return 'isBasedOn';

		case 'references':
			return 'mentions';

		case 'related_to':
			return 'isRelatedTo';

		case 'located_in':
			return 'contentLocation';

		case 'used_in':
			return 'isPartOf';

		case 'generated_by':
			return 'creator';

		case 'appears_in':
			if ( 'storyos_character' === $from_cpt ) {
				return 'subjectOf';
			}
			if ( 'storyos_character' === $to_cpt ) {
				return 'character';
			}
			return 'mentions';

		case 'linked_to':
			if ( 'storyos_character' === $to_cpt ) {
				if ( in_array( $from_cpt, [ 'storyos_project', 'storyos_episode', 'storyos_scene', 'storyos_shot' ], true ) ) {
					return 'character';
				}
				return 'about';
			}

			if ( 'storyos_location' === $to_cpt ) {
				return 'contentLocation';
			}

			if ( in_array( $to_cpt, [ 'storyos_project', 'storyos_episode', 'storyos_scene', 'storyos_shot', 'storyos_storyboard_frame' ], true ) ) {
				return 'isPartOf';
			}

			return 'about';

		default:
			return 'mentions';
	}
}

/**
 * Build canonical Schema.org property hints from StoryOS field metadata.
 *
 * @param string $cpt  StoryOS CPT slug.
 * @param array  $meta StoryOS meta key-value map.
 * @return array<string, mixed>
 */
function storyos_schema_hints_from_meta( string $cpt, array $meta ): array {
	$field_map = storyos_schema_field_map();
	$cpt_map = $field_map[ $cpt ] ?? [];
	$hints = [];

	foreach ( $cpt_map as $field_name => $mapping ) {
		if ( ! array_key_exists( $field_name, $meta ) ) {
			continue;
		}

		$property = $mapping['property'] ?? '';
		if ( '' === $property ) {
			continue;
		}

		if ( ! isset( $hints[ $property ] ) ) {
			$hints[ $property ] = $meta[ $field_name ];
			continue;
		}

		if ( ! is_array( $hints[ $property ] ) ) {
			$hints[ $property ] = [ $hints[ $property ] ];
		}

		$hints[ $property ][] = $meta[ $field_name ];
	}

	return $hints;
}

/**
 * Sanitize a story graph ID into a stable slug-like identifier.
 *
 * @param mixed $id The ID.
 * @return string
 */
function sanitize_story_id( $id ): string {
	$raw = (string) $id;
	$sanitized = strtolower( preg_replace( '/[^a-z0-9]+/', '-', $raw ) ?? $raw );
	$sanitized = trim( $sanitized, '-' );

	return $sanitized !== '' ? $sanitized : 'story';
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
