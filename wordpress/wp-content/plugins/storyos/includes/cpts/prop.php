<?php
/**
 * Prop Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

function init(): void {
	$fields = [
		'prop_name'       => [
			'type'        => 'text',
			'label'       => 'Prop Name',
			'required'    => true,
		],
		'description'     => [
			'type'        => 'wysiwyg',
			'label'       => 'Description',
			'required'    => false,
		],
		'purpose'         => [
			'type'        => 'text',
			'label'       => 'Purpose',
			'required'    => false,
		],
		'owner_character' => [
			'type'        => 'relationship',
			'label'       => 'Owner Character',
			'required'    => false,
			'related_cpt' => 'storyos_character',
		],
		'notes'           => [
			'type'        => 'wysiwyg',
			'label'       => 'Notes',
			'required'    => false,
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_prop',
		'Props',
		[
			'menu_icon' => 'dashicons-cart',
		],
		$fields
	);
}

function save_meta( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['storyos_prop_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_prop_nonce'], 'storyos_prop_details' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_prop' );

	foreach ( $fields as $key => $field ) {
		if ( isset( $_POST[ $key ] ) ) {
			if ( 'relationship' === $field['type'] ) {
				\StoryOS\Utils\add_relationship(
					$post_id,
					'storyos_prop',
					absint( $_POST[ $key ] ),
					$field['related_cpt'],
					'linked_to'
				);
			} else {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ $key ] ) );
			}
		}
	}
}
add_action( 'save_post_storyos_prop', __NAMESPACE__ . '\\save_meta', 10, 2 );
