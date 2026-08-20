<?php
/**
 * Sound Type Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

/**
 * Registers the extensible soundtrack-role vocabulary for Sound cues.
 */
class SoundType {

	/**
	 * Register the taxonomy and its stable seed terms.
	 */
	public static function init(): void {
		register_taxonomy(
			'storyos_sound_type',
			[ 'storyos_sound' ],
			[
				'labels'            => [
					'name'          => 'Sound Types',
					'singular_name' => 'Sound Type',
					'search_items'  => 'Search Sound Types',
					'all_items'     => 'All Sound Types',
					'edit_item'     => 'Edit Sound Type',
					'add_new_item'  => 'Add New Sound Type',
				],
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'sound-type' ],
				'hierarchical'      => false,
			]
		);

		foreach ( \StoryOS\Utils\storyos_sound_types() as $slug => $label ) {
			if ( ! get_term_by( 'slug', $slug, 'storyos_sound_type' ) ) {
				wp_insert_term( $label, 'storyos_sound_type', [ 'slug' => $slug ] );
			}
		}
	}
}
