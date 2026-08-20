<?php
/**
 * Utility functions for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! function_exists( __NAMESPACE__ . '\\wp_strip_all_tags' ) ) {
	/**
	 * Lightweight fallback for environments without WordPress loaded.
	 *
	 * @param string $text Raw HTML.
	 * @return string
	 */
	function wp_strip_all_tags( string $text ): string {
		return trim( preg_replace( '/<[^>]+>/', ' ', $text ) ?? $text );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_trim_words' ) ) {
	/**
	 * Lightweight fallback for environments without WordPress loaded.
	 *
	 * @param string $text       Raw text.
	 * @param int    $num_words  Number of words to keep.
	 * @param string $more       Optional suffix.
	 * @return string
	 */
	function wp_trim_words( string $text, int $num_words = 55, string $more = '…' ): string {
		$words = preg_split( '/\s+/', trim( $text ) );
		if ( ! is_array( $words ) ) {
			return trim( $text );
		}
		if ( count( $words ) <= $num_words ) {
			return trim( $text );
		}
		return trim( implode( ' ', array_slice( $words, 0, $num_words ) ) ) . $more;
	}
}

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
 * Build the default register_post_type arguments for a StoryOS CPT.
 *
 * @param string $cpt   The CPT slug.
 * @param string $label The display label.
 * @param array  $args  Additional register_post_type args.
 * @return array
 */
function storyos_get_default_cpt_args( string $cpt, string $label, array $args = [] ): array {
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
			'all_items'          => $label,
		],
		'public'             => true,
		'show_ui'            => true,
		'has_archive'        => true,
		'rewrite'            => [ 'slug' => $cpt ],
		'show_in_menu'       => 'storyos',
		'show_in_rest'       => true,
		'rest_base'          => $cpt,
		'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
	];

	return wp_parse_args( $args, $defaults );
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
	$args = storyos_get_default_cpt_args( $cpt, $label, $args );
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

	// Keep the code-defined contract available during this request. SCF_Fields
	// persists this contract as editable SCF field groups after every CPT has
	// registered, and storyos_get_fields() then treats those groups as the
	// authoritative runtime schema.
	$GLOBALS['storyos_field_definitions'][ $cpt ] = $normalized_fields;

	// Retain the legacy option as a compatibility fallback for CLI/unit contexts
	// where SCF is unavailable. It is no longer the authoritative field store.
	if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
		$all_fields = get_option( 'storyos_fields', [] );
		$all_fields = is_array( $all_fields ) ? $all_fields : [];
		$all_fields[ $cpt ] = $normalized_fields;
		update_option( 'storyos_fields', $all_fields );
	}
}

/**
 * Get the code-defined fallback fields for a CPT.

 * SCF groups are the runtime authority. These definitions seed those groups
 * and preserve StoryOS-only relationship semantics that SCF does not model.
 *
 * @param string $cpt The CPT slug.
 * @return array
 */
function storyos_get_field_defaults( string $cpt ): array {
	$registered = $GLOBALS['storyos_field_definitions'] ?? [];
	if ( isset( $registered[ $cpt ] ) && is_array( $registered[ $cpt ] ) ) {
		return $registered[ $cpt ];
	}

	if ( ! function_exists( 'get_option' ) ) {
		return [];
	}

	$all_fields = get_option( 'storyos_fields', [] );
	return is_array( $all_fields ) && isset( $all_fields[ $cpt ] ) && is_array( $all_fields[ $cpt ] )
		? $all_fields[ $cpt ]
		: [];
}

/**
 * Get all code-defined StoryOS field contracts.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function storyos_get_all_field_defaults(): array {
	$registered = $GLOBALS['storyos_field_definitions'] ?? [];
	if ( is_array( $registered ) && ! empty( $registered ) ) {
		return $registered;
	}

	if ( ! function_exists( 'get_option' ) ) {
		return [];
	}

	$all_fields = get_option( 'storyos_fields', [] );
	return is_array( $all_fields ) ? $all_fields : [];
}

/**
 * Get registered fields for a CPT, preferring SCF's persisted field groups.
 *
 * @param string $cpt The CPT slug.
 * @return array<string, array<string, mixed>>
 */
function storyos_get_fields( string $cpt ): array {
	$defaults = storyos_get_field_defaults( $cpt );
	if ( class_exists( __NAMESPACE__ . '\\SCF_Fields' ) ) {
		return SCF_Fields::get_fields( $cpt, $defaults );
	}

	return $defaults;
}

/**
 * Read a StoryOS scalar field through SCF when its field definition exists.
 *
 * @param int    $post_id    Post ID.
 * @param string $field_name Field name.
 * @return mixed
 */
function storyos_get_field_value( int $post_id, string $field_name ) {
	if ( class_exists( __NAMESPACE__ . '\\SCF_Fields' ) ) {
		return SCF_Fields::get_value( $post_id, $field_name );
	}

	return get_post_meta( $post_id, $field_name, true );
}

/**
 * Update a StoryOS scalar field through SCF so its reference metadata and
 * formatting lifecycle stay intact.
 *
 * @param int    $post_id    Post ID.
 * @param string $field_name Field name.
 * @param mixed  $value      Field value.
 * @return bool
 */
function storyos_update_field_value( int $post_id, string $field_name, $value ): bool {
	if ( class_exists( __NAMESPACE__ . '\\SCF_Fields' ) ) {
		return SCF_Fields::update_value( $post_id, $field_name, $value );
	}

	return false !== update_post_meta( $post_id, $field_name, $value );
}

/**
 * Delete a StoryOS scalar field through SCF.
 *
 * @param int    $post_id    Post ID.
 * @param string $field_name Field name.
 * @return bool
 */
function storyos_delete_field_value( int $post_id, string $field_name ): bool {
	if ( class_exists( __NAMESPACE__ . '\\SCF_Fields' ) ) {
		return SCF_Fields::delete_value( $post_id, $field_name );
	}

	return delete_post_meta( $post_id, $field_name );
}

/**
 * Determine whether a field is redundant in the generic StoryOS Details meta box.
 *
 * WordPress already provides the post title and content fields, so dedicated
 * per-CPT name/description fields are duplicated and should be hidden there.
 *
 * @param string $field_name Field key.
 * @param array  $field_config Optional field definition.
 * @return bool
 */
function storyos_should_exclude_from_details( string $field_name, array $field_config = [] ): bool {
	$normalized_field_name = strtolower( $field_name );
	if ( preg_match( '/(^|_)(name|description)$/', $normalized_field_name ) ) {
		return true;
	}

	if ( empty( $field_config['label'] ) ) {
		return false;
	}

	$label = strtolower( trim( (string) $field_config['label'] ) );
	return 'name' === $label || 'description' === $label || preg_match( '/\s+(name|description)$/', $label );
}

/**
 * Get the expected field names for a StoryOS CPT from the canonical schema contract.
 *
 * @param string $cpt CPT slug.
 * @return array<int, string>
 */
function storyos_expected_fields_for_cpt( string $cpt ): array {
	$expected_fields = [
		'storyos_project'            => [ 'project_name', 'project_slug', 'description', 'genre', 'target_medium', 'status', 'owner', 'start_date', 'end_date', 'team_members', 'production_stage', 'frame_width', 'frame_height', 'aspect_ratio', 'frame_rate' ],
		'storyos_story_world'        => [ 'world_name', 'synopsis', 'timeline', 'rules', 'themes', 'geography', 'references', 'project' ],
		'storyos_character'          => [ 'display_name', 'biography', 'age', 'appearance', 'personality', 'motivation', 'backstory', 'voice_profile', 'avatar_asset', 'story_world' ],
		'storyos_location'           => [ 'location_name', 'description', 'environment_type', 'geography', 'mood', 'visual_reference', 'story_world' ],
		'storyos_prop'               => [ 'prop_name', 'description', 'purpose', 'owner_character', 'notes' ],
		'storyos_organization'       => [ 'organization_name', 'organization_type', 'description', 'leadership', 'goals', 'story_world' ],
		'storyos_episode'            => [ 'episode_number', 'title', 'synopsis', 'status', 'project' ],
		'storyos_scene'              => [ 'scene_number', 'title', 'summary', 'script_content', 'dialogue', 'location', 'time_of_day', 'emotional_tone', 'production_notes', 'sequence', 'episode' ],
		'storyos_shot'               => [ 'shot_name', 'shot_number', 'shot_type', 'camera_angle', 'lens', 'duration', 'take_number', 'slate_id', 'shot_description', 'editorial_notes', 'scene', 'sequence' ],
		'storyos_sound'              => [ 'sound_type', 'production_status', 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ],
		'storyos_storyboard'         => [ 'frame_number', 'frame_description', 'image_asset', 'prompt_text', 'camera_notes', 'scene', 'shot' ],
		'storyos_asset'              => [ 'asset_title', 'asset_type', 'workflow_name', 'prompt', 'model_name', 'seed', 'generation_parameters', 'version', 'status', 'storage_uri', 'character', 'location', 'scene', 'storyboard' ],
		'storyos_editorial'          => [ 'artifact_type', 'export_format', 'generated_date', 'source_scene', 'source_shot', 'notes', 'project' ],
		'storyos_template'           => [ 'template_name', 'description', 'generation_structure', 'modality', 'connection_id', 'checkpoint', 'model_family', 'workflow_json', 'provider_template_id', 'configuration_json', 'input_bindings', 'model_requirements', 'default_values', 'provider_type', 'version', 'status' ],
		'storyos_connection'         => [ 'connection_name', 'provider_type', 'environment', 'status', 'endpoint_url', 'mcp_endpoint_url', 'credential_reference', 'model', 'max_tokens', 'temperature', 'model_access', 'enabled_structures', 'enabled_templates', 'rate_limits', 'cost_controls' ],
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
		'storyos_sound'           => 'Sound',
		'storyos_storyboard'       => 'Storyboard Frame',
		'storyos_asset'           => 'Asset',
		'storyos_editorial'       => 'Editorial Artifact',
		'storyos_template'        => 'Template',
		'storyos_connection'      => 'Connection',
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
		'storyos_sound'             => 'CreativeWork',
		'storyos_storyboard'        => 'ImageObject',
		'storyos_asset'             => 'MediaObject',
		'storyos_editorial'         => 'CreativeWork',
		'storyos_template'           => 'CreativeWork',
		'storyos_connection'         => 'Service',
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

	if ( 'storyos_sound' === $cpt ) {
		$sound_terms = $taxonomies['storyos_sound_type'] ?? [];
		$sound_slugs = array_map(
			static function( $term ) {
				return strtolower( (string) ( $term['slug'] ?? '' ) );
			},
			$sound_terms
		);

		if ( in_array( 'music', $sound_slugs, true ) ) {
			return 'MusicComposition';
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
			'dialogue'          => [ 'property' => 'text', 'match' => 'weak' ],
			'location'          => [ 'property' => 'contentLocation', 'match' => 'exact' ],
			'time_of_day'       => [ 'property' => 'temporal', 'match' => 'close' ],
			'emotional_tone'    => [ 'property' => 'about', 'match' => 'weak' ],
			'production_notes'  => [ 'property' => 'text', 'match' => 'weak' ],
			'episode'           => [ 'property' => 'isPartOf', 'match' => 'exact' ],
		],
		'storyos_shot' => [
			'shot_name'         => [ 'property' => 'name', 'match' => 'close' ],
			'shot_number'       => [ 'property' => 'position', 'match' => 'close' ],
			'shot_type'         => [ 'property' => 'additionalType', 'match' => 'close' ],
			'camera_angle'      => [ 'property' => 'description', 'match' => 'weak' ],
			'lens'              => [ 'property' => 'description', 'match' => 'weak' ],
			'duration'          => [ 'property' => 'duration', 'match' => 'exact' ],
			'shot_description'  => [ 'property' => 'description', 'match' => 'exact' ],
			'editorial_notes'   => [ 'property' => 'text', 'match' => 'weak' ],
			'scene'             => [ 'property' => 'isPartOf', 'match' => 'exact' ],
			'sequence'          => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
		'storyos_sound' => [
			'sound_type'       => [ 'property' => 'additionalType', 'match' => 'close' ],
			'production_status'=> [ 'property' => 'creativeWorkStatus', 'match' => 'close' ],
			'spoken_text'      => [ 'property' => 'text', 'match' => 'exact' ],
			'lyrics'           => [ 'property' => 'lyrics', 'match' => 'exact' ],
			'start_timecode'   => [ 'property' => 'temporal', 'match' => 'weak' ],
			'duration'         => [ 'property' => 'duration', 'match' => 'close' ],
			'diegetic'         => [ 'property' => 'additionalType', 'match' => 'weak' ],
			'production_notes' => [ 'property' => 'text', 'match' => 'weak' ],
			'scene'            => [ 'property' => 'isPartOf', 'match' => 'exact' ],
			'shot'             => [ 'property' => 'isPartOf', 'match' => 'close' ],
			'character'        => [ 'property' => 'character', 'match' => 'close' ],
			'asset'            => [ 'property' => 'encoding', 'match' => 'close' ],
		],
		'storyos_storyboard' => [
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
		'storyos_editorial' => [
			'artifact_type'   => [ 'property' => 'additionalType', 'match' => 'close' ],
			'export_format'   => [ 'property' => 'encodingFormat', 'match' => 'exact' ],
			'generated_date'  => [ 'property' => 'dateCreated', 'match' => 'close' ],
			'source_scene'    => [ 'property' => 'isBasedOn', 'match' => 'close' ],
			'source_shot'     => [ 'property' => 'isBasedOn', 'match' => 'close' ],
			'notes'           => [ 'property' => 'text', 'match' => 'close' ],
			'project'         => [ 'property' => 'isPartOf', 'match' => 'close' ],
		],
		'storyos_connection' => [
			'connection_name'      => [ 'property' => 'name', 'match' => 'exact' ],
			'endpoint_url'         => [ 'property' => 'url', 'match' => 'exact' ],
			'status'               => [ 'property' => 'status', 'match' => 'close' ],
			'environment'          => [ 'property' => 'additionalType', 'match' => 'close' ],
			'provider_type'        => [ 'property' => 'provider', 'match' => 'close' ],
			'enabled_structures'   => [ 'property' => 'hasPart', 'match' => 'close' ],
			'cost_controls'        => [ 'property' => 'priceSpecification', 'match' => 'close' ],
			'model_access'         => [ 'property' => 'encodingFormat', 'match' => 'weak' ],
			'rate_limits'          => [ 'property' => 'additionalProperty', 'match' => 'weak' ],
			'credential_reference' => [ 'property' => 'identifier', 'match' => 'weak' ],
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
				if ( in_array( $from_cpt, [ 'storyos_project', 'storyos_episode', 'storyos_scene', 'storyos_shot', 'storyos_sound' ], true ) ) {
					return 'character';
				}
				return 'about';
			}

			if ( 'storyos_sound' === $from_cpt && 'storyos_asset' === $to_cpt ) {
				return 'encoding';
			}

			if ( 'storyos_location' === $to_cpt ) {
				return 'contentLocation';
			}

			if ( in_array( $to_cpt, [ 'storyos_project', 'storyos_episode', 'storyos_scene', 'storyos_shot', 'storyos_storyboard' ], true ) ) {
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

		$value = $meta[ $field_name ];
		if ( 'storyos_sound' === $cpt && 'lyrics' === $field_name ) {
			$value = [
				'@type' => 'CreativeWork',
				'text'  => (string) $value,
			];
		}

		if ( ! isset( $hints[ $property ] ) ) {
			$hints[ $property ] = $value;
			continue;
		}

		if ( ! is_array( $hints[ $property ] ) ) {
			$hints[ $property ] = [ $hints[ $property ] ];
		}

		$hints[ $property ][] = $value;
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
	$raw = strtolower( (string) $id );
	$sanitized = preg_replace( '/[^a-z0-9]+/', '-', $raw ) ?? $raw;
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

/**
 * Canonical seed vocabulary for planned sound cues.
 *
 * Ordinary screenplay dialogue remains structured Scene metadata. These terms
 * describe additional soundtrack cues that need their own timing, production,
 * and media relationships.
 *
 * @return array<string, string> Term slug to display label map.
 */
function storyos_sound_types(): array {
	return [
		'narration'    => 'Narration',
		'voiceover'    => 'Voice-over',
		'music'        => 'Music',
		'sound-effect' => 'Sound Effect',
		'ambience'     => 'Ambience',
		'foley'        => 'Foley',
		'silence'      => 'Intentional Silence',
		'adr'          => 'ADR',
	];
}

/**
 * Determine whether a Sound Type is reserved for Scene-owned content.
 *
 * @param mixed $value Term object, slug, name, or ID.
 * @return bool
 */
function storyos_is_reserved_sound_type( $value ): bool {
	if ( is_object( $value ) && isset( $value->slug ) ) {
		$value = $value->slug;
	} elseif ( is_numeric( $value ) && taxonomy_exists( 'storyos_sound_type' ) ) {
		$term  = get_term( absint( $value ), 'storyos_sound_type' );
		$value = ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
	}

	return 'dialogue' === sanitize_title( (string) $value );
}

/**
 * Determine whether a StoryOS Asset is classified as audio.
 *
 * @param int $asset_id Asset post ID.
 * @return bool
 */
function storyos_is_audio_asset( int $asset_id ): bool {
	return $asset_id > 0 && 'storyos_asset' === get_post_type( $asset_id ) && has_term( 'audio', 'storyos_asset_type', $asset_id );
}

/**
 * Sanitize a value using its StoryOS field definition.
 *
 * @param mixed $value Raw field value.
 * @param array $field StoryOS field definition.
 * @return mixed Sanitized value.
 */
function storyos_sanitize_field_value( $value, array $field ) {
	$type = (string) ( $field['type'] ?? 'text' );

	if ( 'number' === $type ) {
		return is_numeric( $value ) ? 0 + $value : '';
	}

	if ( 'wysiwyg' === $type ) {
		return wp_kses_post( (string) $value );
	}

	if ( 'textarea' === $type ) {
		return sanitize_textarea_field( (string) $value );
	}

	if ( 'select' === $type ) {
		$value   = sanitize_key( (string) $value );
		$options = (array) ( $field['options'] ?? [] );
		return isset( $options[ $value ] ) ? $value : '';
	}

	return sanitize_text_field( (string) $value );
}

/**
 * Canonical shot type map (slug => display label).
 *
 * Kept in one place so the CPT, name generator, exporter and UI agree.
 *
 * @return array<string, string>
 */
function storyos_shot_types(): array {
	return [
		'establishing'      => 'Establishing',
		'extreme_close_up'  => 'Extreme Close Up',
		'close_up'          => 'Close Up',
		'closeup'           => 'Close Up',
		'medium_close_up'   => 'Medium Close Up',
		'medium'            => 'Medium',
		'medium_wide'       => 'Medium Wide',
		'wide'              => 'Wide',
		'extreme_wide'      => 'Extreme Wide',
		'over_the_shoulder' => 'Over The Shoulder',
		'point_of_view'     => 'Point of View',
		'cutaway'           => 'Cutaway',
		'reaction'          => 'Reaction Shot',
		'insert'            => 'Insert',
		'close-up'          => 'Close Up',
		'closeup_shot'      => 'Close Up',
	];
}

/**
 * Human-friendly label for a shot type slug.
 *
 * @param string $slug Raw shot type value.
 * @return string
 */
function storyos_shot_type_label( string $slug ): string {
	$slug = strtolower( trim( $slug ) );
	$types = storyos_shot_types();

	if ( isset( $types[ $slug ] ) ) {
		return $types[ $slug ];
	}

	return ucwords( str_replace( [ '_', '-' ], ' ', $slug ) );
}

/**
 * Normalize a shot type slug to its canonical representation.
 *
 * @param string $slug Raw shot type value.
 * @return string Canonical slug (or a best-effort slug when unknown).
 */
function storyos_normalize_shot_type( string $slug ): string {
	$slug = strtolower( trim( $slug ) );

	$aliases = [
		'closeup'            => 'close_up',
		'close-up'           => 'close_up',
		'closeup_shot'       => 'close_up',
		'extreme-close-up'   => 'extreme_close_up',
		'point-of-view'      => 'point_of_view',
		'over-the-shoulder'  => 'over_the_shoulder',
	];

	if ( isset( $aliases[ $slug ] ) ) {
		return $aliases[ $slug ];
	}

	return in_array( $slug, array_keys( storyos_shot_types() ), true ) ? $slug : str_replace( '-', '_', $slug );
}

/**
 * Generate a useful, human-friendly name for a shot.
 *
 * Pure function so it is unit-testable without a WordPress bootstrap.
 * Example: "Shot 1: Wide — The Assignment (Village cottage exterior)".
 *
 * @param array $shot Shot data with optional keys:
 *                    - shot_number      (int|string)
 *                    - shot_type        (string) e.g. 'wide' or 'close_up'
 *                    - shot_description (string)
 *                    - scene_title      (string) scene post title
 *                    - scene_number     (int|string)
 * @return string
 */
function storyos_generate_shot_name( array $shot ): string {
	$explicit_title = isset( $shot['title'] ) ? trim( (string) $shot['title'] ) : '';
	if ( '' === $explicit_title && isset( $shot['label'] ) ) {
		$explicit_title = trim( (string) $shot['label'] );
	}
	if ( '' !== $explicit_title ) {
		return $explicit_title;
	}

	$number = isset( $shot['shot_number'] ) && '' !== $shot['shot_number'] ? $shot['shot_number'] : '';

	$type_label = isset( $shot['shot_type'] ) && '' !== $shot['shot_type']
		? storyos_shot_type_label( (string) $shot['shot_type'] )
		: '';

	$scene_title = isset( $shot['scene_title'] ) ? trim( (string) $shot['scene_title'] ) : '';

	$description = '';
	if ( ! empty( $shot['shot_description'] ) ) {
		$description = wp_strip_all_tags( (string) $shot['shot_description'] );
		$description = trim( preg_replace( '/\s+/', ' ', $description ) ?? $description );
		if ( function_exists( 'wp_trim_words' ) ) {
			$description = wp_trim_words( $description, 10, '…' );
		}
	}

	$parts = [];

	if ( '' !== $number ) {
		$parts[] = 'Shot ' . $number;
	}

	if ( '' !== $type_label ) {
		$parts[] = $type_label;
	}

	if ( '' !== $scene_title ) {
		$parts[] = $scene_title;
	}

	if ( '' !== $description ) {
		$parts[] = '(' . $description . ')';
	}

	if ( empty( $parts ) ) {
		return 'Untitled Shot';
	}

	$primary = array_slice( $parts, 0, 2 );
	$tail    = array_slice( $parts, 2 );

	if ( empty( $tail ) ) {
		return implode( ': ', $primary );
	}

	return implode( ': ', $primary ) . ' — ' . implode( ' ', $tail );
}

/**
 * Get the display name for a shot post.
 *
 * Prefers the post title when it looks intentional (not the default
 * "Shot N" placeholder), otherwise falls back to a generated name.
 *
 * @param int $shot_id Shot post ID.
 * @return string
 */
function storyos_get_shot_display_name( int $shot_id ): string {
	$post = get_post( $shot_id );
	if ( ! $post || 'storyos_shot' !== $post->post_type ) {
		return '';
	}

	$title = trim( (string) $post->post_title );
	if ( '' !== $title && ! preg_match( '/^shot \d+$/i', $title ) ) {
		return $title;
	}

	$scene_id = 0;
	foreach ( get_relationships( $shot_id, 'storyos_shot', 'outgoing' ) as $rel ) {
		if ( 'storyos_scene' === ( $rel['to_type'] ?? '' ) ) {
			$scene_id = (int) ( $rel['to_id'] ?? 0 );
			break;
		}
	}

	$scene     = $scene_id ? get_post( $scene_id ) : null;
	$shot_name = get_post_meta( $shot_id, 'shot_name', true );

	return storyos_generate_shot_name( [
		'shot_number'      => get_post_meta( $shot_id, 'shot_number', true ),
		'shot_type'        => get_post_meta( $shot_id, 'shot_type', true ),
		'shot_description' => $shot_name ?: get_post_meta( $shot_id, 'shot_description', true ),
		'scene_title'      => $scene ? $scene->post_title : '',
		'scene_number'     => $scene ? get_post_meta( $scene->ID, 'scene_number', true ) : '',
	] );
}

/**
 * Get the editorial order of a sequence term.
 *
 * @param int $term_id Sequence term ID.
 * @return int
 */
function storyos_get_sequence_order( int $term_id ): int {
	$order = get_term_meta( $term_id, \StoryOS\Taxonomies\Sequence::ORDER_META_KEY, true );
	return '' !== $order ? absint( $order ) : PHP_INT_MAX;
}

/**
 * Set the editorial order of a sequence term.
 *
 * @param int $term_id Sequence term ID.
 * @param int $order   New position (1-based).
 * @return void
 */
function storyos_set_sequence_order( int $term_id, int $order ): void {
	update_term_meta( $term_id, \StoryOS\Taxonomies\Sequence::ORDER_META_KEY, max( 1, $order ) );
}

/**
 * Get all sequence terms ordered for the editorial cut.
 *
 * @return array<int, array{id:int,name:string,slug:string,order:int}>
 */
function storyos_get_ordered_sequences(): array {
	$terms = get_terms( [
		'taxonomy'   => \StoryOS\Taxonomies\Sequence::TAXONOMY,
		'hide_empty' => false,
		'orderby'    => 'term_id',
		'order'      => 'ASC',
	] );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	$sequences = array_map( static function( $term ) {
		return [
			'id'    => (int) $term->term_id,
			'name'  => $term->name,
			'slug'  => $term->slug,
			'order' => storyos_get_sequence_order( (int) $term->term_id ),
		];
	}, $terms );

	usort( $sequences, static function( array $a, array $b ) {
		// Terms without an explicit order stay at the end, stable by term id.
		$cmp = $a['order'] <=> $b['order'];
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		return $a['id'] <=> $b['id'];
	} );

	return $sequences;
}
