<?php
/**
 * Provider-neutral generation structure registry for the WordPress UI.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies editorial structure definitions for dynamic controls.
 */
class Structure_Registry {

	/**
	 * Return structures exposed by the current StoryOS UI.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_structures(): array {
		$structures = [
			'text_to_image' => [
				'label'       => __( 'Text to Image', 'storyos-generation-engine' ),
				'description' => __( 'Create an image from story context and a prompt.', 'storyos-generation-engine' ),
				'fields'      => [
					[ 'name' => 'prompt', 'scf_field' => 'positive_prompt', 'label' => __( 'Prompt', 'storyos-generation-engine' ), 'type' => 'textarea', 'required' => true ],
					[ 'name' => 'negative_prompt', 'scf_field' => 'negative_prompt', 'label' => __( 'Negative Prompt', 'storyos-generation-engine' ), 'type' => 'textarea' ],
				],
			],
			'image_to_video' => [
				'label'       => __( 'Image to Video', 'storyos-generation-engine' ),
				'description' => __( 'Animate a selected story image using provider-supported motion controls.', 'storyos-generation-engine' ),
				'fields'      => [
					[ 'name' => 'prompt', 'scf_field' => 'positive_prompt', 'label' => __( 'Prompt', 'storyos-generation-engine' ), 'type' => 'textarea', 'required' => true ],
					[ 'name' => 'duration_seconds', 'label' => __( 'Desired Duration (seconds)', 'storyos-generation-engine' ), 'type' => 'number', 'min' => 1 ],
					[ 'name' => 'aspect_ratio', 'label' => __( 'Desired Aspect Ratio', 'storyos-generation-engine' ), 'type' => 'text', 'placeholder' => '16:9' ],
				],
			],
		];

		return apply_filters( 'storyos_generation_engine_structures', $structures );
	}
}
