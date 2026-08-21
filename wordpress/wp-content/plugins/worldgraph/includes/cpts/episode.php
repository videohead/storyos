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

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_episode_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_episode_nonce'], 'worldgraph_episode_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_episode' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'taxonomy' === $field['type'] ) {
					wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
				} elseif ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_episode',
						absint( $_POST[ $key ] ),
						$field['related_cpt'],
						'belongs_to'
					);
				} else {
					update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
				}
			}
		}
	}
}
