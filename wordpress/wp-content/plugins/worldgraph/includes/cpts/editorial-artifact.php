<?php
/**
 * Editorial Artifact Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Editorial Artifact Custom Post Type handler.
 */
class EditorialArtifact {
	/**
	 * Register the Editorial Artifact CPT.
	 */
	public static function init(): void {
		self::register_cpt();
	}

	/**
	 * Register the Editorial Artifact CPT.
	 */
	private static function register_cpt(): void {
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
			'type'              => 'relationship',
			'label'             => 'Source Scene',
			'required'          => false,
			'related_cpt'       => 'worldgraph_scene',
			'relationship_type' => 'references',
		],
		'source_shot'     => [
			'type'              => 'relationship',
			'label'             => 'Source Shot',
			'required'          => false,
			'related_cpt'       => 'worldgraph_shot',
			'relationship_type' => 'references',
		],
		'notes'           => [
			'type'        => 'wysiwyg',
			'label'       => 'Notes',
			'required'    => false,
		],
		'project'         => [
			'type'              => 'relationship',
			'label'             => 'Project',
			'required'          => false,
			'related_cpt'       => 'worldgraph_project',
			'relationship_type' => 'references',
		],
	];

	\WorldGraph\Utils\register_cpt(
		'worldgraph_editorial',
		'Editorial Artifacts',
		[
			'menu_icon'    => 'dashicons-media-video',
			'rest_base'    => 'worldgraph_editorial_artifact',
			'show_in_menu' => 'worldgraph-editorial',
		],
		$fields
	);
	}
}
