<?php
/**
 * Scene Tag Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

function init(): void {
	register_taxonomy(
		'storyos_scene_tag',
		[ 'storyos_scene' ],
		[
			'labels' => [
				'name'          => 'Scene Tags',
				'singular_name' => 'Scene Tag',
				'search_items'  => 'Search Scene Tags',
				'all_items'     => 'All Scene Tags',
				'edit_item'     => 'Edit Scene Tag',
				'add_new_item'  => 'Add New Scene Tag',
			],
			'public'  => true,
			'show_in_rest' => true,
			'rewrite' => [ 'slug' => 'scene-tag' ],
			'hierarchical' => false,
		]
	);

	// Default scene tag types.
	$default_tags = [
		'Action',
		'Drama',
		'Comedy',
		'Tension',
		'Revelation',
		'Exposition',
		'Emotional',
		'Quiet',
		'Chaotic',
		'Flashback',
		'Voiceover',
		'Montage',
	];

	foreach ( $default_tags as $tag ) {
		if ( ! term_exists( $tag, 'storyos_scene_tag' ) ) {
			wp_insert_term( $tag, 'storyos_scene_tag' );
		}
	}
}
