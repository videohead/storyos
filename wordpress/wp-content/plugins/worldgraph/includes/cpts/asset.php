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
}
