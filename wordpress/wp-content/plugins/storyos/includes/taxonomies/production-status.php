<?php
/**
 * Production Status Taxonomy.
 *
 * @package StoryOS
 */

namespace StoryOS\Taxonomies;

function init(): void {
	register_taxonomy(
		'storyos_status',
		[ 'storyos_project', 'storyos_episode', 'storyos_asset' ],
		[
			'labels' => [
				'name'          => 'Statuses',
				'singular_name' => 'Status',
				'search_items'  => 'Search Statuses',
				'all_items'     => 'All Statuses',
				'edit_item'     => 'Edit Status',
				'add_new_item'  => 'Add New Status',
				'parent_item'   => 'Parent Status',
			],
			'public'  => true,
			'show_in_rest' => true,
			'rewrite' => [ 'slug' => 'status' ],
			'hierarchical' => false,
		]
	);

	// Default status terms.
	$default_statuses = [
		'Draft',
		'In Development',
		'In Production',
		'In Post-Production',
		'Approved',
		'Archived',
		'On Hold',
	];

	foreach ( $default_statuses as $status ) {
		if ( ! term_exists( $status, 'storyos_status' ) ) {
			wp_insert_term( $status, 'storyos_status' );
		}
	}
}
