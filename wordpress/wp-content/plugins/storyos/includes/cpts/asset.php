<?php
/**
 * Asset Custom Post Type with versioning and lineage.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

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
			'taxonomy'    => 'storyos_asset_type',
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
			'type'        => 'relationship',
			'label'       => 'Source Character',
			'required'    => false,
			'related_cpt' => 'storyos_character',
		],
		'location'          => [
			'type'        => 'relationship',
			'label'       => 'Source Location',
			'required'    => false,
			'related_cpt' => 'storyos_location',
		],
		'scene'             => [
			'type'        => 'relationship',
			'label'       => 'Source Scene',
			'required'    => false,
			'related_cpt' => 'storyos_scene',
		],
		'storyboard'        => [
			'type'        => 'relationship',
			'label'       => 'Source Storyboard Frame',
			'required'    => false,
			'related_cpt' => 'storyos_storyboard_frame',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_asset',
		'Assets',
		[
			'menu_icon' => 'dashicons-portfolio',
		],
		$fields
	);
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['storyos_asset_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_asset_nonce'], 'storyos_asset_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_asset' );

		foreach ( $fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'taxonomy' === $field['type'] ) {
					wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
				} elseif ( 'relationship' === $field['type'] ) {
					\StoryOS\Utils\add_relationship(
						$post_id,
						'storyos_asset',
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
add_action( 'save_post_storyos_asset', __NAMESPACE__ . '\\save_meta', 10, 2 );
