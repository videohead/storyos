<?php
/**
 * Asset Custom Post Type with versioning and lineage.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Asset Custom Post Type handler.
 */
class Asset {
	/**
	 * Register the Asset CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Asset CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'asset_title'       => [
				'type'        => 'text',
				'label'       => 'Asset Title',
				'required'    => true,
		],
		'asset_type'        => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'worldgraph_asset_type',
			'label'       => 'Asset Type',
			'required'    => true,
		],
		'workflow_name'     => [
			'type'        => 'text',
			'label'       => 'Source Workflow',
			'required'    => false,
		],
		'prompt'            => [
			'type'        => 'wysiwyg',
			'label'       => 'Generation Prompt',
			'required'    => false,
		],
		'model_name'        => [
			'type'        => 'text',
			'label'       => 'Model Used',
			'required'    => false,
		],
		'seed'              => [
			'type'        => 'number',
			'label'       => 'Seed',
			'required'    => false,
		],
		'generation_parameters' => [
			'type'        => 'wysiwyg',
			'label'       => 'Generation Parameters (JSON)',
			'required'    => false,
		],
		'version'           => [
			'type'        => 'text',
			'label'       => 'Version',
			'required'    => false,
		],
		'status'            => [
			'type'        => 'select',
			'label'       => 'Status',
			'required'    => false,
			'options'     => [
				'pending'     => 'Pending',
				'processing'  => 'Processing',
				'done'        => 'Complete',
				'error'       => 'Error',
			],
		],
		'storage_uri'       => [
			'type'        => 'text',
			'label'       => 'Storage Location',
			'required'    => false,
		],
		'character'         => [
			'type'              => 'relationship',
			'label'             => 'Source Character',
			'required'          => false,
			'related_cpt'       => 'worldgraph_character',
			'relationship_type' => 'linked_to',
		],
		'location'          => [
			'type'              => 'relationship',
			'label'             => 'Source Location',
			'required'          => false,
			'related_cpt'       => 'worldgraph_location',
			'relationship_type' => 'linked_to',
		],
		'scene'             => [
			'type'              => 'relationship',
			'label'             => 'Source Scene',
			'required'          => false,
			'related_cpt'       => 'worldgraph_scene',
			'relationship_type' => 'linked_to',
		],
		'storyboard'        => [
			'type'              => 'relationship',
			'label'             => 'Source Storyboard Frame',
			'required'          => false,
			'related_cpt'       => 'worldgraph_board',
			'relationship_type' => 'linked_to',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_asset',
		'Assets',
		[
			'menu_icon' => 'dashicons-portfolio',
			'show_in_menu' => 'worldgraph-editorial',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['worldgraph_asset_nonce'] ) || ! wp_verify_nonce( $_POST['worldgraph_asset_nonce'], 'worldgraph_asset_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_asset' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'taxonomy' === $field['type'] ) {
					wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
				} elseif ( 'relationship' === $field['type'] ) {
					\WorldGraph\Utils\add_relationship(
						$post_id,
						'worldgraph_asset',
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
add_action( 'save_post_worldgraph_asset', [ __NAMESPACE__ . '\Asset', 'save_meta' ], 10, 2 );
