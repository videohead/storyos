<?php
/**
 * Tests for World Graph Studio Schema.org alignment contracts.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_Schema_Alignment
 */
class Test_WorldGraph_Schema_Alignment extends TestCase {

	/**
	 * Ensure every registered World Graph Studio CPT has a Schema.org type mapping.
	 */
	public function test_every_cpt_has_schema_type_mapping() {
		$cpts = \WorldGraph\Utils\worldgraph_get_all_cpts();
		$type_map = \WorldGraph\Utils\worldgraph_schema_type_map();

		foreach ( array_keys( $cpts ) as $cpt ) {
			$this->assertArrayHasKey( $cpt, $type_map, "Missing schema type mapping for {$cpt}" );
			$this->assertNotEmpty( $type_map[ $cpt ], "Empty schema type for {$cpt}" );
		}
	}

	/**
	 * Ensure each CPT has at least one strong field alignment.
	 */
	public function test_each_cpt_has_exact_or_close_field_alignment() {
		$field_map = \WorldGraph\Utils\worldgraph_schema_field_map();

		foreach ( $field_map as $cpt => $fields ) {
			$strong_count = 0;
			foreach ( $fields as $mapping ) {
				if ( in_array( $mapping['match'] ?? '', [ 'exact', 'close' ], true ) ) {
					$strong_count++;
				}
			}

			$this->assertGreaterThan(
				0,
				$strong_count,
				"CPT {$cpt} has no exact/close Schema.org field alignments"
			);
		}
	}

	/**
	 * Ensure core relationship types map to deterministic Schema.org properties.
	 */
	public function test_core_relationship_mappings_are_deterministic() {
		$this->assertSame( 'hasPart', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'contains' ) );
		$this->assertSame( 'isPartOf', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'belongs_to' ) );
		$this->assertSame( 'mentions', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'references' ) );
		$this->assertSame( 'isBasedOn', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'derived_from' ) );
		$this->assertSame( 'contentLocation', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'located_in' ) );
	}

	/**
	 * Ensure storytelling-focused type inference for project and asset entities.
	 */
	public function test_storytelling_type_inference() {
		$project_movie = \WorldGraph\Utils\worldgraph_schema_type_for_entity(
			'worldgraph_project',
			[ 'target_medium' => 'film' ],
			[]
		);
		$this->assertSame( 'Movie', $project_movie );

		$asset_video = \WorldGraph\Utils\worldgraph_schema_type_for_entity(
			'worldgraph_asset',
			[],
			[
				'worldgraph_asset_type' => [
					[ 'slug' => 'video' ],
				],
			]
		);
		$this->assertSame( 'VideoObject', $asset_video );

		$asset_audio = \WorldGraph\Utils\worldgraph_schema_type_for_entity(
			'worldgraph_asset',
			[],
			[
				'worldgraph_asset_type' => [
					[ 'slug' => 'audio' ],
				],
			]
		);
		$this->assertSame( 'AudioObject', $asset_audio );

		$sound_music = \WorldGraph\Utils\worldgraph_schema_type_for_entity(
			'worldgraph_sound',
			[],
			[
				'worldgraph_sound_type' => [
					[ 'slug' => 'music' ],
				],
			]
		);
		$this->assertSame( 'MusicComposition', $sound_music );
		$this->assertSame( 'CreativeWork', \WorldGraph\Utils\worldgraph_schema_type_for_entity( 'worldgraph_sound' ) );
		$this->assertSame( 'encoding', \WorldGraph\Utils\worldgraph_schema_property_for_relationship( 'linked_to', 'worldgraph_sound', 'worldgraph_asset' ) );

		$sound_hints = \WorldGraph\Utils\worldgraph_schema_hints_from_meta( 'worldgraph_sound', [ 'lyrics' => "First line\nSecond line" ] );
		$this->assertSame( 'CreativeWork', $sound_hints['lyrics']['@type'] );
		$this->assertSame( "First line\nSecond line", $sound_hints['lyrics']['text'] );
	}
}
