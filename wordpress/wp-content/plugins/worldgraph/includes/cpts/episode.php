<?php
/**
 * Episode Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Episode Custom Post Type handler.
 */
class Episode {
	/**
	 * Register the Episode CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Episode CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'episode_number'  => [
				'type'        => 'number',
				'label'       => 'Episode Number',
				'required'    => true,
		],
		'title'           => [
			'type'        => 'text',
			'label'       => 'Episode Title',
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
			'description' => 'Episode-specific visual instructions appended to generated media prompts, for example "no watermark" or title-card guidance.',
		],
		'status'          => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'worldgraph_status',
			'label'       => 'Status',
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
		'worldgraph_episode',
		'Episodes',
		[
			'menu_icon' => 'dashicons-video-alt2',
			'show_in_menu' => 'worldgraph-editorial',
		],
		$fields
	);
	}
}
