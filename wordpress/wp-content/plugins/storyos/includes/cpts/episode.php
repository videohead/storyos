<?php
/**
 * Episode Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

function init(): void {
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
		'status'          => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'storyos_status',
			'label'       => 'Status',
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
		'storyos_episode',
		'Episodes',
		[
			'menu_icon' => 'dashicons-video-alt2',
		],
		$fields
	);
}

function save_meta( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['storyos_episode_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_episode_nonce'], 'storyos_episode_details' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_episode' );

	foreach ( $fields as $key => $field ) {
		if ( isset( $_POST[ $key ] ) ) {
			if ( 'taxonomy' === $field['type'] ) {
				wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
			} elseif ( 'relationship' === $field['type'] ) {
				\StoryOS\Utils\add_relationship(
					$post_id,
					'storyos_episode',
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
add_action( 'save_post_storyos_episode', __NAMESPACE__ . '\\save_meta', 10, 2 );
