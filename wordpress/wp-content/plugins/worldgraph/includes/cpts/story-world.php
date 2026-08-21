<?php
/**
 * Story World Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

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
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'World-specific visual instructions appended to generated media prompts, for example "no watermark" or a house style.',
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
			'related_cpt' => 'worldgraph_project',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_world',
		'Story Worlds',
		[
			'menu_icon' => 'dashicons-earth',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_world_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_world_nonce'], 'worldgraph_world_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_world' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_world',
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
