<?php
/**
 * Character Relation Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

class CharacterRelation {
	public static function init(): void {
		register_taxonomy(
			'storyos_character_relation',
			[ 'storyos_character' ],
			[
				'labels' => [
					'name'          => 'Character Relations',
					'singular_name' => 'Character Relation',
					'search_items'  => 'Search Relations',
					'all_items'     => 'All Relations',
					'edit_item'     => 'Edit Relation',
					'add_new_item'  => 'Add New Relation',
				],
				'public'  => true,
				'show_in_rest' => true,
				'rewrite' => [ 'slug' => 'character-relation' ],
				'hierarchical' => false,
			]
		);

		// Default character relation types.
		$default_relations = [
			'Protagonist',
			'Antagonist',
			'Mentor',
			'Ally',
			'Family',
			'Love Interest',
			'Rival',
			'Sidekick',
			'Neutral',
			'Unknown',
		];

		foreach ( $default_relations as $relation ) {
			if ( ! term_exists( $relation, 'storyos_character_relation' ) ) {
				wp_insert_term( $relation, 'storyos_character_relation' );
			}
		}
	}
}
