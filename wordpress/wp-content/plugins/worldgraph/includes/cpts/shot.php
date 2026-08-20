<?php
/**
 * Shot Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Shot Custom Post Type handler.
 */
class Shot {
	/**
	 * Register the Shot CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Shot CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'shot_name'       => [
				'type'        => 'text',
				'label'       => 'Shot Name',
				'required'    => false,
				'description' => 'Short, human-friendly name for this shot. Leave empty to auto-generate one from the scene, shot type and description.',
			],
			'shot_number'     => [
				'type'        => 'number',
				'label'       => 'Shot Number',
				'required'    => true,
		],
		'shot_type'       => [
			'type'        => 'select',
			'label'       => 'Shot Type',
			'required'    => false,
			'options'     => [
				'establishing'       => 'Establishing',
				'extreme_close_up'   => 'Extreme Close Up',
				'close_up'           => 'Close Up',
				'medium_close_up'    => 'Medium Close Up',
				'medium'             => 'Medium',
				'medium_wide'        => 'Medium Wide',
				'wide'               => 'Wide',
				'extreme_wide'       => 'Extreme Wide',
				'over_the_shoulder'  => 'Over The Shoulder',
				'point_of_view'      => 'Point of View',
				'cutaway'            => 'Cutaway',
				'reaction'           => 'Reaction Shot',
				'insert'             => 'Insert',
			],
		],
		'camera_angle'    => [
			'type'        => 'select',
			'label'       => 'Camera Angle',
			'required'    => false,
			'options'     => [
				'eye_level'   => 'Eye Level',
				'low_angle'   => 'Low Angle',
				'high_angle'  => 'High Angle',
				'birdseye'    => 'Birdseye',
				'wormseye'    => 'Wormseye',
				'dutch'       => 'Dutch Angle',
			],
		],
		'lens'            => [
			'type'        => 'text',
			'label'       => 'Lens',
			'required'    => false,
		],
		'duration'        => [
			'type'        => 'text',
			'label'       => 'Duration',
			'required'    => false,
		],
		'take_number'     => [
			'type'        => 'number',
			'label'       => 'Take Number',
			'required'    => false,
		],
		'slate_id'        => [
			'type'        => 'text',
			'label'       => 'Slate ID',
			'required'    => false,
		],
		'shot_description'=> [
			'type'        => 'wysiwyg',
			'label'       => 'Shot Description',
			'required'    => false,
		],
		'editorial_notes' => [
			'type'        => 'wysiwyg',
			'label'       => 'Editorial Notes',
			'required'    => false,
		],
		'scene'           => [
			'type'        => 'relationship',
			'label'       => 'Scene',
			'required'    => true,
			'related_cpt' => 'worldgraph_scene',
		],
		'sequence'        => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'worldgraph_sequence',
			'label'       => 'Sequence',
			'required'    => false,
			'description' => 'The editorial sequence this shot belongs to.',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_shot',
		'Shots',
		[
			'menu_icon' => 'dashicons-camera',
			'show_in_menu' => 'worldgraph-editorial',
			// page-attributes exposes menu_order, the shot's position in the cut.
			'supports'  => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes' ],
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_shot_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_shot_nonce'], 'worldgraph_shot_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_shot' );

		// Ordering within the cut: page-attributes menu_order.
		if ( isset( $_POST['menu_order'] ) ) {
			wp_update_post( [
				'ID'         => $post_id,
				'menu_order' => absint( $_POST['menu_order'] ),
			] );
		}

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'taxonomy' === $field['type'] ) {
					wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
				} elseif ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_shot',
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
