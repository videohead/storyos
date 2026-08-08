<?php
/**
 * Character Role Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

class CharacterRole {
	public static function init(): void {
		register_taxonomy(
			'storyos_character_role',
			[ 'storyos_character' ],
			[
				'labels' => [
					'name'          => 'Character Roles',
					'singular_name' => 'Character Role',
					'search_items'  => 'Search Character Roles',
					'all_items'     => 'All Character Roles',
					'edit_item'     => 'Edit Character Role',
					'add_new_item'  => 'Add New Character Role',
				],
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => [ 'slug' => 'character-role' ],
				'hierarchical' => false,
			]
		);

		// Default narrative role terms aligned with common story vocabulary.
		$default_roles = [
			'Protagonist',
			'Antagonist',
			'Deuteragonist',
			'Mentor',
			'Ally',
			'Foil',
			'Love Interest',
			'Comic Relief',
			'Ensemble',
			'Unknown',
		];

		foreach ( $default_roles as $role ) {
			if ( ! term_exists( $role, 'storyos_character_role' ) ) {
				wp_insert_term( $role, 'storyos_character_role' );
			}
		}
	}
}
