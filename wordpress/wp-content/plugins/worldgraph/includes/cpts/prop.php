<?php
/**
 * Prop Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Prop Custom Post Type handler.
 */
class Prop {
	/**
	 * Register the Prop CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Prop CPT.
	 */
	private static function register_cpt(): void {
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
		'generation_prompt' => [
			'type'        => 'textarea',
			'label'       => 'Generation Prompt Instructions',
			'required'    => false,
			'description' => 'Prop-specific visual instructions appended to generated media prompts, for example "no watermark" or material constraints.',
		],
		'purpose'         => [
			'type'        => 'text',
			'label'       => 'Purpose',
			'required'    => false,
		],
		'owner_character' => [
			'type'              => 'relationship',
			'label'             => 'Owner Character',
			'required'          => false,
			'related_cpt'       => 'worldgraph_character',
			'relationship_type' => 'linked_to',
		],
		'notes'           => [
			'type'        => 'wysiwyg',
			'label'       => 'Notes',
			'required'    => false,
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_prop',
		'Props',
		[
			'menu_icon' => 'dashicons-cart',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_prop_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_prop_nonce'], 'worldgraph_prop_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_prop' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_prop',
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
}
add_action( 'save_post_worldgraph_prop', [ __NAMESPACE__ . '\Prop', 'save_meta' ], 10, 2 );
