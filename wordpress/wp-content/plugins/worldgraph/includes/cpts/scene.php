<?php
/**
 * Scene Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Scene Custom Post Type handler.
 */
class Scene {
	/**
	 * Register the Scene CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Scene CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'scene_number'    => [
				'type'        => 'number',
				'label'       => 'Scene Number',
				'required'    => true,
		],
		'title'           => [
			'type'        => 'text',
			'label'       => 'Scene Title',
			'required'    => true,
		],
		'summary'         => [
			'type'        => 'wysiwyg',
			'label'       => 'Summary',
			'required'    => false,
		],
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'Scene-specific visual instructions appended to generated media prompts, for example "no watermark" or color-script guidance.',
		],
		'script_content'  => [
			'type'        => 'wysiwyg',
			'label'       => 'Script Content',
			'required'    => false,
		],
		'dialogue'        => [
			'type'        => 'structured',
			'label'       => 'Dialogue',
			'required'    => false,
			'admin_ui'    => false,
			'read_only'   => true,
			'description' => 'Importer-managed dialogue entries with speaker, line, description, and sequence fields.',
		],
		'location'        => [
			'type'              => 'relationship',
			'label'             => 'Location',
			'required'          => false,
			'related_cpt'       => 'worldgraph_location',
			'relationship_type' => 'located_in',
		],
		'time_of_day'     => [
			'type'        => 'select',
			'label'       => 'Time of Day',
			'required'    => false,
			'options'     => [
				'dawn'        => 'Dawn',
				'morning'     => 'Morning',
				'midday'      => 'Midday',
				'afternoon'   => 'Afternoon',
				'dusk'        => 'Dusk',
				'evening'     => 'Evening',
				'night'       => 'Night',
			],
		],
		'emotional_tone'  => [
			'type'        => 'text',
			'label'       => 'Emotional Tone',
			'required'    => false,
		],
		'production_notes'=> [
			'type'        => 'wysiwyg',
			'label'       => 'Production Notes',
			'required'    => false,
		],
		'sequence'        => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'worldgraph_sequence',
			'label'       => 'Sequence',
			'required'    => false,
		],
		'episode'         => [
			'type'        => 'relationship',
			'label'       => 'Episode',
			'required'    => false,
			'related_cpt' => 'worldgraph_episode',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_scene',
		'Scenes',
		[
			'menu_icon' => 'dashicons-screenoptions',
			'show_in_menu' => 'worldgraph-editorial',
		],
		$fields
	);
	}
}
