<?php
/**
 * Asset Type Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

function init(): void {
	register_taxonomy(
		'storyos_asset_type',
		[ 'storyos_asset' ],
		[
			'labels' => [
				'name'          => 'Asset Types',
				'singular_name' => 'Asset Type',
				'search_items'  => 'Search Asset Types',
				'all_items'     => 'All Asset Types',
				'parent_item'   => 'Parent Asset Type',
				'edit_item'     => 'Edit Asset Type',
				'add_new_item'  => 'Add New Asset Type',
				'add_item'      => 'Add New Asset Type',
			],
			'public'  => true,
			'show_in_rest' => true,
			'rewrite' => [ 'slug' => 'asset-type' ],
			'hierarchical' => false,
		]
	);

	// Default asset type terms.
	$default_types = [
		'Character',
		'Environment',
		'Prop',
		'Storyboard',
		'Video',
		'Audio',
		'Lookbook',
		'Concept Art',
	];

	foreach ( $default_types as $type ) {
		if ( ! term_exists( $type, 'storyos_asset_type' ) ) {
			wp_insert_term( $type, 'storyos_asset_type' );
		}
	}
}
