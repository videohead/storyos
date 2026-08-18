<?php
/**
 * Scene Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

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
		'script_content'  => [
			'type'        => 'wysiwyg',
			'label'       => 'Script Content',
			'required'    => false,
		],
		'location'        => [
			'type'        => 'relationship',
			'label'       => 'Location',
			'required'    => false,
			'related_cpt' => 'storyos_location',
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
			'taxonomy'    => 'storyos_sequence',
			'label'       => 'Sequence',
			'required'    => false,
		],
		'episode'         => [
			'type'        => 'relationship',
			'label'       => 'Episode',
			'required'    => false,
			'related_cpt' => 'storyos_episode',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_scene',
		'Scenes',
		[
			'menu_icon' => 'dashicons-screenoptions',
			'show_in_menu' => 'storyos-editorial',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['storyos_scene_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_scene_nonce'], 'storyos_scene_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_scene' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'taxonomy' === $field['type'] ) {
					wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
				} elseif ( 'relationship' === $field['type'] ) {
					\StoryOS\Utils\add_relationship(
						$post_id,
						'storyos_scene',
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
