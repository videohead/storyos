<?php
/**
 * Editorial Artifact Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

function init(): void {
	$fields = [
		'artifact_type'   => [
			'type'        => 'select',
			'label'       => 'Artifact Type',
			'required'    => true,
			'options'     => [
				'edl'               => 'EDL (Edit Decision List)',
				'timeline_metadata' => 'Timeline Metadata',
				'xml'               => 'XML Export',
				'aaf'               => 'AAF Export',
				'shot_list'         => 'Shot List',
				'production_report' => 'Production Report',
			],
		],
		'export_format'   => [
			'type'        => 'text',
			'label'       => 'Export Format',
			'required'    => false,
		],
		'generated_date'  => [
			'type'        => 'date',
			'label'       => 'Generated Date',
			'required'    => false,
		],
		'source_scene'    => [
			'type'        => 'relationship',
			'label'       => 'Source Scene',
			'required'    => false,
			'related_cpt' => 'storyos_scene',
		],
		'source_shot'     => [
			'type'        => 'relationship',
			'label'       => 'Source Shot',
			'required'    => false,
			'related_cpt' => 'storyos_shot',
		],
		'notes'           => [
			'type'        => 'wysiwyg',
			'label'       => 'Notes',
			'required'    => false,
		],
		'project'         => [
			'type'        => 'relationship',
			'label'       => 'Project',
			'required'    => false,
			'related_cpt' => 'storyos_project',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_editorial_artifact',
		'Editorial Artifacts',
		[
			'menu_icon' => 'dashicons-media-video',
		],
		$fields
	);
}

function save_meta( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['storyos_editorial_artifact_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_editorial_artifact_nonce'], 'storyos_editorial_artifact_details' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_editorial_artifact' );

	foreach ( $fields as $key => $field ) {
		if ( isset( $_POST[ $key ] ) ) {
			if ( 'taxonomy' === $field['type'] ) {
				wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
			} elseif ( 'relationship' === $field['type'] ) {
				\StoryOS\Utils\add_relationship(
					$post_id,
					'storyos_editorial_artifact',
					absint( $_POST[ $key ] ),
					$field['related_cpt'],
					'references'
				);
			} else {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ $key ] ) );
			}
		}
	}
}
add_action( 'save_post_storyos_editorial_artifact', __NAMESPACE__ . '\\save_meta', 10, 2 );
