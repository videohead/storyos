<?php
/**
 * Sound Type Taxonomy.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Taxonomies;

/**
 * Registers the extensible soundtrack-role vocabulary for Sound cues.
 */
class SoundType {

	/**
	 * Register the taxonomy and its stable seed terms.
	 */
	public static function init(): void {
		add_filter( 'pre_insert_term', [ __CLASS__, 'reject_reserved_term' ], 10, 3 );

		register_taxonomy(
			'worldgraph_sound_type',
			[ 'worldgraph_sound' ],
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
				'meta_box_cb'       => false,
				'rewrite'           => [ 'slug' => 'sound-type' ],
				'hierarchical'      => false,
			]
		);

		foreach ( \WorldGraph\Utils\worldgraph_sound_types() as $slug => $label ) {
			if ( ! get_term_by( 'slug', $slug, 'worldgraph_sound_type' ) ) {
				wp_insert_term( $label, 'worldgraph_sound_type', [ 'slug' => $slug ] );
			}
		}
	}

	/**
	 * Prevent creation of a Sound Type that duplicates Scene dialogue.
	 *
	 * @param string|\WP_Error $term     Proposed term name.
	 * @param string           $taxonomy Taxonomy slug.
	 * @param array            $args     Term creation arguments.
	 * @return string|\WP_Error
	 */
	public static function reject_reserved_term( $term, string $taxonomy, array $args = [] ) {
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		if ( 'worldgraph_sound_type' !== $taxonomy ) {
			return $term;
		}

		$slug = $args['slug'] ?? $term;
		if ( \WorldGraph\Utils\worldgraph_is_reserved_sound_type( $slug ) ) {
			return new \WP_Error( 'worldgraph_sound_type_reserved', 'Dialogue is structured Scene metadata and cannot be a Sound Type.' );
		}

		return $term;
	}
}
