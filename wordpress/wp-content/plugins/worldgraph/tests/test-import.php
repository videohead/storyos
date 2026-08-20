<?php
/**
 * Tests for World Graph Studio file-based import flow.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_Import
 */
class Test_WorldGraph_Import extends TestCase {

	/**
	 * The import admin page should use a file input and not require pasted JSON.
	 */
	public function test_import_admin_page_uses_file_upload() {
		$path = dirname( __DIR__ ) . '/includes/admin/import.php';
		$this->assertFileExists( $path );

		$source = file_get_contents( $path );
		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'type="file"', $source );
		$this->assertStringContainsString( 'worldgraph_json_file', $source );
		$this->assertStringNotContainsString( 'textarea name="worldgraph_json"', $source );
	}

	/**
	 * The example document should only use resolvable external-ID references.
	 */
	public function test_example_document_relationship_references_resolve() {
		$project_root = dirname( dirname( __DIR__ ), 4 );
		$path         = $project_root . '/about/example-workflow/little-red-riding-hood.worldgraph.json';
		$document     = json_decode( file_get_contents( $path ), true );

		$this->assertIsArray( $document );

		$ids = [];
		foreach ( [ 'characters', 'locations', 'props', 'scenes', 'shots', 'sounds', 'storyboards' ] as $section ) {
			$ids[ $section ] = array_column( $document[ $section ], 'id' );
		}

		foreach ( $document['props'] as $prop ) {
			$this->assertContains( $prop['owner_character'], $ids['characters'] );
		}

		foreach ( $document['scenes'] as $scene ) {
			$this->assertContains( $scene['location'], $ids['locations'] );
			foreach ( $scene['characters'] as $character_id ) {
				$this->assertContains( $character_id, $ids['characters'] );
			}
			foreach ( $scene['props'] as $prop_id ) {
				$this->assertContains( $prop_id, $ids['props'] );
			}
		}

		foreach ( $document['shots'] as $shot ) {
			$this->assertContains( $shot['scene'], $ids['scenes'] );
		}

		$shot_scenes = array_column( $document['shots'], 'scene', 'id' );
		$this->assertSame(
			[
				'shot_1' => 'scene_1',
				'shot_2' => 'scene_1',
				'shot_3' => 'scene_1',
				'shot_4' => 'scene_2',
				'shot_5' => 'scene_2',
				'shot_6' => 'scene_2',
				'shot_7' => 'scene_3',
				'shot_8' => 'scene_3',
				'shot_9' => 'scene_3',
			],
			$shot_scenes
		);
		foreach ( $document['sounds'] as $sound ) {
			$this->assertContains( $sound['scene'], $ids['scenes'] );
			if ( ! empty( $sound['shot'] ) ) {
				$this->assertContains( $sound['shot'], $ids['shots'] );
				$this->assertSame( $sound['scene'], $shot_scenes[ $sound['shot'] ] );
			}
			if ( ! empty( $sound['character'] ) ) {
				$this->assertContains( $sound['character'], $ids['characters'] );
			}
		}

		foreach ( $document['storyboards'] as $frame ) {
			$this->assertContains( $frame['shot'], $ids['shots'] );
		}

		foreach ( $document['sequence']['order'] as $scene_id ) {
			$this->assertContains( $scene_id, $ids['scenes'] );
		}
	}

	/**
	 * The importer should consume the structured prop-owner field.
	 */
	public function test_importer_maps_prop_ownership() {
		$path   = dirname( __DIR__ ) . '/includes/importer/class-worldgraph-importer.php';
		$source = file_get_contents( $path );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "\$prop['owner_character']", $source );
		$this->assertStringContainsString( "worldgraph_update_field_value( \$prop_id, 'owner_character', \$char_id )", $source );
		$this->assertStringContainsString( "relationship_slot_matches( \$prop_id, 'worldgraph_prop', 'owner_character'", $source );
		$this->assertStringContainsString( 'validate_references', $source );
	}

	/**
	 * Imported shots should retain their required scalar Scene link.
	 */
	public function test_importer_persists_shot_scene_relationships() {
		$path   = dirname( __DIR__ ) . '/includes/importer/class-worldgraph-importer.php';
		$source = file_get_contents( $path );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "worldgraph_update_field_value( \$shot_id, 'scene', \$scene_id )", $source );
		$this->assertStringContainsString( "relationship_slot_matches( \$shot_id, 'worldgraph_shot', 'scene'", $source );
		$this->assertStringContainsString(
			"remove_relationship( \$scene_id, \$shot_id, 'worldgraph_scene', 'worldgraph_shot' )",
			$source
		);
		$this->assertStringContainsString( 'Shot %s did not retain its required Scene relationship.', $source );

		$scenes_controller = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/scenes-controller.php' );
		$this->assertNotFalse( $scenes_controller );
		$this->assertStringContainsString( "get_relationships( \$post_id, \$from_cpt, 'incoming' )", $scenes_controller );
	}

	/**
	 * The 1.1 example and importer preserve Sound cues without duplicating dialogue.
	 */
	public function test_sound_import_contract() {
		$project_root = dirname( dirname( __DIR__ ), 4 );
		$document     = json_decode( file_get_contents( $project_root . '/about/example-workflow/little-red-riding-hood.worldgraph.json' ), true );
		$types        = array_column( $document['sounds'], 'type' );

		$this->assertSame( '1.1', $document['worldgraph_version'] );
		$this->assertCount( 7, $document['sounds'] );
		$this->assertNotContains( 'dialogue', $types );
		$this->assertContains( 'narration', $types );
		$this->assertContains( 'voiceover', $types );
		$this->assertContains( 'music', $types );

		$music = current( array_filter( $document['sounds'], static fn( array $sound ): bool => 'music' === $sound['type'] ) );
		$this->assertIsArray( $music );
		$this->assertNotEmpty( $music['lyrics'] );
		$this->assertStringContainsString( "\n", $music['lyrics'] );

		$dialogue = [];
		foreach ( $document['scenes'] as $scene ) {
			$dialogue = array_merge( $dialogue, (array) ( $scene['dialogue'] ?? [] ) );
		}
		$this->assertCount( 13, $dialogue );
		$this->assertEmpty(
			array_intersect(
				array_filter( array_column( $document['sounds'], 'spoken_text' ) ),
				array_column( $dialogue, 'text' )
			)
		);

		$importer = file_get_contents( dirname( __DIR__ ) . '/includes/importer/class-worldgraph-importer.php' );
		$this->assertStringContainsString( 'private function import_sounds()', $importer );
		$this->assertStringContainsString( "'worldgraph_sound_type'", $importer );
		$this->assertStringContainsString( "worldgraph_update_field_value( \$sound_id, 'scene', \$scene_id )", $importer );
		$this->assertStringContainsString( "relationship_slot_matches( \$sound_id, 'worldgraph_sound', 'scene'", $importer );
		$this->assertStringContainsString( 'Ordinary dialogue remains', $importer );
		$this->assertStringContainsString( "! empty( \$options['dry_run'] )", $importer );
		$this->assertStringContainsString( 'worldgraph_is_reserved_sound_type', $importer );
		$this->assertStringContainsString( 'worldgraph_is_audio_asset', $importer );
	}
}
