<?php
/**
 * Organization Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Organization Custom Post Type handler.
 */
class Organization {
	/**
	 * Register the Organization CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Organization CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'organization_name' => [
				'type'        => 'text',
				'label'       => 'Organization Name',
				'required'    => true,
		],
		'organization_type' => [
			'type'        => 'text',
			'label'       => 'Type',
			'required'    => false,
		],
		'description'       => [
			'type'        => 'wysiwyg',
			'label'       => 'Description',
			'required'    => false,
		],
		'leadership'        => [
			'type'        => 'relationship',
			'label'       => 'Leadership',
			'required'    => false,
			'related_cpt' => 'worldgraph_character',
		],
		'goals'             => [
			'type'        => 'wysiwyg',
			'label'       => 'Goals',
			'required'    => false,
		],
		'story_world'       => [
			'type'        => 'relationship',
			'label'       => 'Story World',
			'required'    => false,
			'related_cpt' => 'worldgraph_world',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_org',
		'Organizations',
		[
			'menu_icon' => 'dashicons-building',
			'show_in_menu' => 'worldgraph-story-elements',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_org_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_org_nonce'], 'worldgraph_org_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_org' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_org',
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
add_action( 'save_post_worldgraph_org', [ __NAMESPACE__ . '\Organization', 'save_meta' ], 10, 2 );
