<?php
/**
 * Production Status Taxonomy.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Taxonomies;

class ProductionStatus {
	public static function init(): void {
		register_taxonomy(
			'worldgraph_status',
			[ 'worldgraph_project', 'worldgraph_episode', 'worldgraph_sound', 'worldgraph_asset' ],
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
			if ( ! term_exists( $status, 'worldgraph_status' ) ) {
				wp_insert_term( $status, 'worldgraph_status' );
			}
		}
	}
}
