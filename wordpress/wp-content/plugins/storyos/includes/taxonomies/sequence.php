<?php
/**
 * Sequence Taxonomy.
 *
 * A sequence is an editorial unit composed of scenes and/or shots. Sequences
 * are ordered via the `storyos_sequence_order` term meta so editors can arrange
 * the assembly (cut) order.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

class Sequence {

	/**
	 * Term meta key storing the editorial (cut) order of a sequence term.
	 *
	 * @var string
	 */
	public const ORDER_META_KEY = 'storyos_sequence_order';

	/**
	 * Taxonomy slug.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'storyos_sequence';

	public static function init(): void {
		register_taxonomy(
			self::TAXONOMY,
			[ 'storyos_scene', 'storyos_shot' ],
			[
				'labels' => [
					'name'          => 'Sequences',
					'singular_name' => 'Sequence',
					'search_items'  => 'Search Sequences',
					'all_items'     => 'All Sequences',
					'edit_item'     => 'Edit Sequence',
					'add_new_item'  => 'Add New Sequence',
				],
				'public'             => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_ui'            => true,
				'rewrite'            => [ 'slug' => 'sequence' ],
				'hierarchical'       => true,
				'default_term'       => null,
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

		foreach ( $default_sequences as $index => $sequence ) {
			$term = term_exists( $sequence, self::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $sequence, self::TAXONOMY );
			}

			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				// Seed ordering so defaults keep their narrative position.
				if ( '' === get_term_meta( $term_id, self::ORDER_META_KEY, true ) ) {
					update_term_meta( $term_id, self::ORDER_META_KEY, $index + 1 );
				}
			}
		}
	}
}
