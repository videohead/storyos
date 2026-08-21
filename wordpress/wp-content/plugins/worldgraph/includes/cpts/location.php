<?php
/**
 * Location Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Location Custom Post Type handler.
 */
class Location {
	/**
	 * Register the Location CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Location CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'location_name'   => [
				'type'        => 'text',
				'label'       => 'Location Name',
				'required'    => true,
		],
		'description'     => [
			'type'        => 'wysiwyg',
			'label'       => 'Description',
			'required'    => false,
		],
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'Location-specific visual instructions appended to generated media prompts, for example "no watermark" or architectural constraints.',
		],
		'environment_type'=> [
			'type'        => 'select',
			'label'       => 'Environment Type',
			'required'    => false,
			'options'     => [
				'indoor'      => 'Indoor',
				'outdoor'     => 'Outdoor',
				'urban'       => 'Urban',
				'rural'       => 'Rural',
				'fantasy'     => 'Fantasy',
				'sci_fi'      => 'Sci-Fi',
				'abstract'    => 'Abstract',
			],
		],
		'geography'       => [
			'type'        => 'text',
			'label'       => 'Geography',
			'required'    => false,
		],
		'mood'            => [
			'type'        => 'text',
			'label'       => 'Mood / Atmosphere',
			'required'    => false,
		],
		'visual_reference'=> [
			'type'              => 'relationship',
			'label'             => 'Visual Reference Asset',
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
		'worldgraph_location',
		'Locations',
		[
			'menu_icon' => 'dashicons-location-alt',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_location_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_location_nonce'], 'worldgraph_location_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_location' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_location',
						absint( $_POST[ $key ] ),
						$field['related_cpt'],
						'belongs_to'
					);
				} else {
					update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ $key ] ) );
				}
			}
		}
	}
}
