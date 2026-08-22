<?php
/**
 * Story World Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Story World Custom Post Type handler.
 */
class StoryWorld {
	/**
	 * Register the Story World CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Story World CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'world_name'      => [
				'type'        => 'text',
				'label'       => 'World Name',
				'required'    => true,
		],
		'synopsis'        => [
			'type'        => 'wysiwyg',
			'label'       => 'Synopsis',
			'required'    => false,
		],
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'World-specific visual instructions appended to generated media prompts, for example "no watermark" or a house style.',
		],
		'timeline'        => [
			'type'        => 'wysiwyg',
			'label'       => 'Timeline',
			'required'    => false,
		],
		'rules'           => [
			'type'        => 'wysiwyg',
			'label'       => 'World Rules',
			'required'    => false,
		],
		'themes'          => [
			'type'        => 'wysiwyg',
			'label'       => 'Themes',
			'required'    => false,
		],
		'geography'       => [
			'type'        => 'wysiwyg',
			'label'       => 'Geography',
			'required'    => false,
		],
		'references'      => [
			'type'        => 'wysiwyg',
			'label'       => 'References',
			'required'    => false,
		],
		'project'         => [
			'type'        => 'relationship',
			'label'       => 'Parent Project',
			'required'    => true,
			'related_cpt' => 'worldgraph_project',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_world',
		'Story Worlds',
		[
			'menu_icon' => 'dashicons-earth',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}
}
