<?php
/**
 * Tests for StoryOS file-based import flow.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Import
 */
class Test_StoryOS_Import extends TestCase {

	/**
	 * The import admin page should use a file input and not require pasted JSON.
	 */
	public function test_import_admin_page_uses_file_upload() {
		$path = dirname( __DIR__ ) . '/includes/admin/import.php';
		$this->assertFileExists( $path );

		$source = file_get_contents( $path );
		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'type="file"', $source );
		$this->assertStringContainsString( 'storyos_json_file', $source );
		$this->assertStringNotContainsString( 'textarea name="storyos_json"', $source );
	}

	/**
	 * The example document should only use resolvable external-ID references.
	 */
	public function test_example_document_relationship_references_resolve() {
		$project_root = dirname( dirname( __DIR__ ), 4 );
		$path         = $project_root . '/about/example-workflow/little-red-riding-hood.storyos.json';
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
		$path   = dirname( __DIR__ ) . '/includes/importer/class-storyos-importer.php';
		$source = file_get_contents( $path );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "\$prop['owner_character']", $source );
		$this->assertStringContainsString( "'storyos_prop', \$char_id, 'storyos_character', 'linked_to'", $source );
		$this->assertStringContainsString( 'validate_references', $source );
	}

	/**
	 * The 1.1 example and importer preserve Sound cues without duplicating dialogue.
	 */
	public function test_sound_import_contract() {
		$project_root = dirname( dirname( __DIR__ ), 4 );
		$document     = json_decode( file_get_contents( $project_root . '/about/example-workflow/little-red-riding-hood.storyos.json' ), true );
		$types        = array_column( $document['sounds'], 'type' );

		$this->assertSame( '1.1', $document['storyos_version'] );
		$this->assertNotEmpty( $document['sounds'] );
		$this->assertNotContains( 'dialogue', $types );
		$this->assertContains( 'narration', $types );
		$this->assertContains( 'voiceover', $types );
		$this->assertContains( 'music', $types );

		$music = current( array_filter( $document['sounds'], static fn( array $sound ): bool => 'music' === $sound['type'] ) );
		$this->assertIsArray( $music );
		$this->assertNotEmpty( $music['lyrics'] );

		$importer = file_get_contents( dirname( __DIR__ ) . '/includes/importer/class-storyos-importer.php' );
		$this->assertStringContainsString( 'private function import_sounds()', $importer );
		$this->assertStringContainsString( "'storyos_sound_type'", $importer );
		$this->assertStringContainsString( "[ 'field' => 'scene' ]", $importer );
		$this->assertStringContainsString( 'Ordinary dialogue remains', $importer );
	}
}
