<?php
/**
 * Template Category Taxonomy.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Taxonomies;

class TemplateCategory {
	public static function init(): void {
		register_taxonomy(
			'worldgraph_template_category',
			[ 'worldgraph_template' ],
			[
				'labels' => [
					'name'                       => 'Template Categories',
					'singular_name'              => 'Template Category',
					'search_items'               => 'Search Template Categories',
					'all_items'                  => 'All Template Categories',
					'parent_item'                => 'Parent Template Category',
					'parent_item_colon'          => 'Parent Template Category:',
					'edit_item'                  => 'Edit Template Category',
					'update_item'                => 'Update Template Category',
					'add_new_item'               => 'Add New Template Category',
					'new_item_name'              => 'New Template Category Name',
					'menu_name'                  => 'Categories',
				],
				'public'             => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'hierarchical'       => true,
				'sort'               => true,
				'rewrite'            => [ 'slug' => 'template-category' ],
				'capabilities'       => [
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				],
			]
		);

		$default_categories = [
			'Character',
			'Scene',
			'Storyboard',
			'Concept',
			'Editorial',
			'Marketing',
			'Asset Variation',
			'Video',
			'Image',
		];

		foreach ( $default_categories as $category ) {
			if ( ! term_exists( $category, 'worldgraph_template_category' ) ) {
				wp_insert_term( $category, 'worldgraph_template_category' );
			}
		}
	}
}
