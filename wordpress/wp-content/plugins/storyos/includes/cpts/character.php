<?php
/**
 * Character Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

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
			'type'        => 'relationship',
			'label'       => 'Avatar Asset',
			'required'    => false,
			'related_cpt' => 'storyos_asset',
		],
		'story_world'     => [
			'type'        => 'relationship',
			'label'       => 'Story World',
			'required'    => false,
			'related_cpt' => 'storyos_story_world',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_character',
		'Characters',
		[
			'menu_icon' => 'dashicons-groups',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['storyos_character_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_character_nonce'], 'storyos_character_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_character' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\StoryOS\Utils\add_relationship(
						$post_id,
						'storyos_character',
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
