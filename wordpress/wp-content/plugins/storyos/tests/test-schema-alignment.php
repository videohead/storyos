<?php
/**
 * Tests for StoryOS Schema.org alignment contracts.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Schema_Alignment
 */
class Test_StoryOS_Schema_Alignment extends TestCase {

	/**
	 * Ensure every registered StoryOS CPT has a Schema.org type mapping.
	 */
	public function test_every_cpt_has_schema_type_mapping() {
		$cpts = \StoryOS\Utils\storyos_get_all_cpts();
		$type_map = \StoryOS\Utils\storyos_schema_type_map();

		foreach ( array_keys( $cpts ) as $cpt ) {
			$this->assertArrayHasKey( $cpt, $type_map, "Missing schema type mapping for {$cpt}" );
			$this->assertNotEmpty( $type_map[ $cpt ], "Empty schema type for {$cpt}" );
		}
	}

	/**
	 * Ensure each CPT has at least one strong field alignment.
	 */
	public function test_each_cpt_has_exact_or_close_field_alignment() {
		$field_map = \StoryOS\Utils\storyos_schema_field_map();

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
		$this->assertSame( 'hasPart', \StoryOS\Utils\storyos_schema_property_for_relationship( 'contains' ) );
		$this->assertSame( 'isPartOf', \StoryOS\Utils\storyos_schema_property_for_relationship( 'belongs_to' ) );
		$this->assertSame( 'mentions', \StoryOS\Utils\storyos_schema_property_for_relationship( 'references' ) );
		$this->assertSame( 'isBasedOn', \StoryOS\Utils\storyos_schema_property_for_relationship( 'derived_from' ) );
		$this->assertSame( 'contentLocation', \StoryOS\Utils\storyos_schema_property_for_relationship( 'located_in' ) );
	}

	/**
	 * Ensure storytelling-focused type inference for project and asset entities.
	 */
	public function test_storytelling_type_inference() {
		$project_movie = \StoryOS\Utils\storyos_schema_type_for_entity(
			'storyos_project',
			[ 'target_medium' => 'film' ],
			[]
		);
		$this->assertSame( 'Movie', $project_movie );

		$asset_video = \StoryOS\Utils\storyos_schema_type_for_entity(
			'storyos_asset',
			[],
			[
				'storyos_asset_type' => [
					[ 'slug' => 'video' ],
				],
			]
		);
		$this->assertSame( 'VideoObject', $asset_video );

		$asset_audio = \StoryOS\Utils\storyos_schema_type_for_entity(
			'storyos_asset',
			[],
			[
				'storyos_asset_type' => [
					[ 'slug' => 'audio' ],
				],
			]
		);
		$this->assertSame( 'AudioObject', $asset_audio );

		$sound_music = \StoryOS\Utils\storyos_schema_type_for_entity(
			'storyos_sound',
			[],
			[
				'storyos_sound_type' => [
					[ 'slug' => 'music' ],
				],
			]
		);
		$this->assertSame( 'MusicComposition', $sound_music );
		$this->assertSame( 'CreativeWork', \StoryOS\Utils\storyos_schema_type_for_entity( 'storyos_sound' ) );
		$this->assertSame( 'encoding', \StoryOS\Utils\storyos_schema_property_for_relationship( 'linked_to', 'storyos_sound', 'storyos_asset' ) );

		$sound_hints = \StoryOS\Utils\storyos_schema_hints_from_meta( 'storyos_sound', [ 'lyrics' => "First line\nSecond line" ] );
		$this->assertSame( 'CreativeWork', $sound_hints['lyrics']['@type'] );
		$this->assertSame( "First line\nSecond line", $sound_hints['lyrics']['text'] );
	}
}
