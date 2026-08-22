<?php
/**
 * Shot Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Shot Custom Post Type handler.
 */
class Shot {
	/**
	 * Register the Shot CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Shot CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'shot_name'       => [
				'type'        => 'text',
				'label'       => 'Shot Name',
				'required'    => false,
				'description' => 'Short, human-friendly name for this shot. Leave empty to auto-generate one from the scene, shot type and description.',
			],
			'shot_number'     => [
				'type'        => 'number',
				'label'       => 'Shot Number',
				'required'    => true,
		],
		'shot_type'       => [
			'type'        => 'select',
			'label'       => 'Shot Type',
			'required'    => false,
			'options'     => [
				'establishing'       => 'Establishing',
				'extreme_close_up'   => 'Extreme Close Up',
				'close_up'           => 'Close Up',
				'medium_close_up'    => 'Medium Close Up',
				'medium'             => 'Medium',
				'medium_wide'        => 'Medium Wide',
				'wide'               => 'Wide',
				'extreme_wide'       => 'Extreme Wide',
				'over_the_shoulder'  => 'Over The Shoulder',
				'point_of_view'      => 'Point of View',
				'cutaway'            => 'Cutaway',
				'reaction'           => 'Reaction Shot',
				'insert'             => 'Insert',
			],
		],
		'camera_angle'    => [
			'type'        => 'select',
			'label'       => 'Camera Angle',
			'required'    => false,
			'options'     => [
				'eye_level'   => 'Eye Level',
				'low_angle'   => 'Low Angle',
				'high_angle'  => 'High Angle',
				'birdseye'    => 'Birdseye',
				'wormseye'    => 'Wormseye',
				'dutch'       => 'Dutch Angle',
			],
		],
		'lens'            => [
			'type'        => 'text',
			'label'       => 'Lens',
			'required'    => false,
		],
		'duration'        => [
			'type'        => 'text',
			'label'       => 'Duration',
			'required'    => false,
		],
		'take_number'     => [
			'type'        => 'number',
			'label'       => 'Take Number',
			'required'    => false,
		],
		'slate_id'        => [
			'type'        => 'text',
			'label'       => 'Slate ID',
			'required'    => false,
		],
		'shot_description'=> [
			'type'        => 'wysiwyg',
			'label'       => 'Shot Description',
			'required'    => false,
		],
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'Shot-specific image and motion instructions appended to generated media prompts, for example "no watermark" or camera movement.',
		],
		'editorial_notes' => [
			'type'        => 'wysiwyg',
			'label'       => 'Editorial Notes',
			'required'    => false,
		],
		'scene'           => [
			'type'        => 'relationship',
			'label'       => 'Scene',
			'required'    => true,
			'related_cpt' => 'worldgraph_scene',
		],
		'sequence'        => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'worldgraph_sequence',
			'label'       => 'Sequence',
			'required'    => false,
			'description' => 'The editorial sequence this shot belongs to.',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_shot',
		'Shots',
		[
			'menu_icon' => 'dashicons-camera',
			'show_in_menu' => 'worldgraph-editorial',
			'supports'  => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
		],
		$fields
	);
	}
}
