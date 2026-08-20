<?php
/**
 * Storyboard Frame Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

/**
 * Storyboard Frame Custom Post Type handler.
 */
class StoryboardFrame {
	/**
	 * Register the Storyboard Frame CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Storyboard Frame CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'frame_number'    => [
				'type'        => 'number',
				'label'       => 'Frame Number',
				'required'    => true,
		],
		'frame_description' => [
			'type'        => 'wysiwyg',
			'label'       => 'Frame Description',
			'required'    => false,
		],
		'image_asset'     => [
			'type'        => 'relationship',
			'label'       => 'Image Asset',
			'required'    => false,
			'related_cpt' => 'storyos_asset',
		],
		'prompt_text'     => [
			'type'        => 'wysiwyg',
			'label'       => 'Generation Prompt',
			'required'    => false,
		],
		'camera_notes'    => [
			'type'        => 'text',
			'label'       => 'Camera Notes',
			'required'    => false,
		],
		'scene'           => [
			'type'        => 'relationship',
			'label'       => 'Scene',
			'required'    => false,
			'related_cpt' => 'storyos_scene',
		],
		'shot'            => [
			'type'        => 'relationship',
			'label'       => 'Shot',
			'required'    => false,
			'related_cpt' => 'storyos_shot',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_storyboard',
		'Storyboard Frames',
		[
			'menu_icon'    => 'dashicons-images-alt2',
			'rest_base'    => 'storyos_storyboard_frame',
			'show_in_menu' => 'storyos-editorial',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['storyos_storyboard_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_storyboard_nonce'], 'storyos_storyboard_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_storyboard' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\StoryOS\Utils\add_relationship(
						$post_id,
						'storyos_storyboard',
						absint( $_POST[ $key ] ),
						$field['related_cpt'],
						'references'
					);
				} else {
					update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ $key ] ) );
				}
			}
		}
	}
}
add_action( 'save_post_storyos_storyboard', [ __NAMESPACE__ . '\StoryboardFrame', 'save_meta' ], 10, 2 );
