<?php
/**
 * Character Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Character Custom Post Type handler.
 */
class Character {
	/**
	 * Register the Character CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Character CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'display_name'    => [
				'type'        => 'text',
				'label'       => 'Character Name',
				'required'    => true,
		],
		'biography'       => [
			'type'        => 'wysiwyg',
			'label'       => 'Biography',
			'required'    => false,
		],
		'age'             => [
			'type'        => 'text',
			'label'       => 'Age',
			'required'    => false,
		],
		'appearance'      => [
			'type'        => 'wysiwyg',
			'label'       => 'Visual Description',
			'required'    => false,
		],
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'Character-specific visual instructions appended to generated media prompts, for example "no watermark" or wardrobe constraints.',
		],
		'personality'     => [
			'type'        => 'wysiwyg',
			'label'       => 'Personality Traits',
			'required'    => false,
		],
		'motivation'      => [
			'type'        => 'wysiwyg',
			'label'       => 'Motivation',
			'required'    => false,
		],
		'backstory'       => [
			'type'        => 'wysiwyg',
			'label'       => 'Backstory',
			'required'    => false,
		],
		'voice_profile'   => [
			'type'        => 'text',
			'label'       => 'Voice Description',
			'required'    => false,
		],
		'avatar_asset'    => [
			'type'              => 'relationship',
			'label'             => 'Avatar Asset',
			'required'          => false,
			'related_cpt'       => 'worldgraph_asset',
			'relationship_type' => 'linked_to',
		],
		'story_world'     => [
			'type'        => 'relationship',
			'label'       => 'Story World',
			'required'    => false,
			'related_cpt' => 'worldgraph_world',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_character',
		'Characters',
		[
			'menu_icon' => 'dashicons-groups',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}
}
