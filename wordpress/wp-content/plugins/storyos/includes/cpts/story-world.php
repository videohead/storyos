<?php
/**
 * Story World Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

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
			'related_cpt' => 'storyos_project',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_story_world',
		'Story Worlds',
		[
			'menu_icon' => 'dashicons-earth',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['storyos_story_world_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_story_world_nonce'], 'storyos_story_world_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_story_world' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\StoryOS\Utils\add_relationship(
						$post_id,
						'storyos_story_world',
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
