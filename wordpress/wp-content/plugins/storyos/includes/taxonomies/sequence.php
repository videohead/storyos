<?php
/**
 * Sequence Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

class Sequence {
	public static function init(): void {
		register_taxonomy(
			'storyos_sequence',
			[ 'storyos_scene' ],
			[
				'labels' => [
					'name'          => 'Sequences',
					'singular_name' => 'Sequence',
					'search_items'  => 'Search Sequences',
					'all_items'     => 'All Sequences',
					'edit_item'     => 'Edit Sequence',
					'add_new_item'  => 'Add New Sequence',
				],
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => [ 'slug' => 'sequence' ],
				'hierarchical' => true,
			]
		);

		// Optional defaults for common sequence categories.
		$default_sequences = [
			'Setup',
			'Rising Action',
			'Complication',
			'Midpoint',
			'Climax',
			'Resolution',
		];

		foreach ( $default_sequences as $sequence ) {
			if ( ! term_exists( $sequence, 'storyos_sequence' ) ) {
				wp_insert_term( $sequence, 'storyos_sequence' );
			}
		}
	}
}
