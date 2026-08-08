<?php
/**
 * Genre Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

function init(): void {
	register_taxonomy(
		'storyos_genre',
		[ 'storyos_project' ],
		[
			'labels' => [
				'name'          => 'Genres',
				'singular_name' => 'Genre',
				'search_items'  => 'Search Genres',
				'all_items'     => 'All Genres',
				'parent_item'   => 'Parent Genre',
				'edit_item'     => 'Edit Genre',
				'add_new_item'  => 'Add New Genre',
			],
			'public'  => true,
			'show_in_rest' => true,
			'rewrite' => [ 'slug' => 'genre' ],
		]
	);

	// Default genre terms.
	$default_genres = [
		'Drama',
		'Comedy',
		'Sci-Fi',
		'Fantasy',
		'Horror',
		'Documentary',
		'Animation',
		'Action',
		'Thriller',
		'Romance',
	];

	foreach ( $default_genres as $genre ) {
		if ( ! term_exists( $genre, 'storyos_genre' ) ) {
			wp_insert_term( $genre, 'storyos_genre' );
		}
	}
}
