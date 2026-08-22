<?php
/**
 * Organization Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Organization Custom Post Type handler.
 */
class Organization {
	/**
	 * Register the Organization CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Organization CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'organization_name' => [
				'type'        => 'text',
				'label'       => 'Organization Name',
				'required'    => true,
		],
		'organization_type' => [
			'type'        => 'text',
			'label'       => 'Type',
			'required'    => false,
		],
		'description'       => [
			'type'        => 'wysiwyg',
			'label'       => 'Description',
			'required'    => false,
		],
		'leadership'        => [
			'type'        => 'relationship',
			'label'       => 'Leadership',
			'required'    => false,
			'related_cpt' => 'worldgraph_character',
		],
		'goals'             => [
			'type'        => 'wysiwyg',
			'label'       => 'Goals',
			'required'    => false,
		],
		'story_world'       => [
			'type'        => 'relationship',
			'label'       => 'Story World',
			'required'    => false,
			'related_cpt' => 'worldgraph_world',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_org',
		'Organizations',
		[
			'menu_icon' => 'dashicons-building',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}
}
