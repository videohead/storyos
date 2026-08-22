<?php
/**
 * Tests for World Graph Studio Assets metabox behavior.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Admin_Metabox_Assets extends TestCase {

	/**
	 * The metabox should not add custom featured/gallery asset controls when core
	 * Gutenberg controls already cover those responsibilities.
	 */
	public function test_worldgraph_assets_metabox_uses_core_editor_controls_only(): void {
		$file = dirname( __DIR__ ) . '/includes/admin/metaboxes.php';
		$source = file_get_contents( $file );

		$this->assertNotFalse( $source, 'The metabox file should be readable.' );
		$this->assertStringNotContainsString( 'worldgraph-select-featured-asset', $source );
		$this->assertStringNotContainsString( 'worldgraph-select-gallery', $source );
		$this->assertStringNotContainsString( 'worldgraph_featured_asset_nonce', $source );
		$this->assertStringNotContainsString( 'worldgraph_asset_gallery_nonce', $source );
		$this->assertStringContainsString( 'block editor', strtolower( $source ) );
	}

	/** The generator buttons must map to distinct, discoverable operations. */
	public function test_generator_controls_match_their_actions(): void {
		$metabox = file_get_contents( dirname( __DIR__ ) . '/includes/admin/asset-generator-metabox.php' );
		$script  = file_get_contents( dirname( __DIR__ ) . '/assets/js/asset-generator.js' );

		$this->assertNotFalse( $metabox );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString( 'Still image (text to image)', $metabox );
		$this->assertStringContainsString( 'Video (text to video)', $metabox );
		$this->assertStringContainsString( 'Selected output', $metabox );
		$this->assertStringContainsString( 'Complete workflows', $metabox );
		$this->assertStringContainsString( 'Generate this item’s full set', $metabox );
		$this->assertStringContainsString( 'Generate all Project media', $metabox );
		$this->assertStringContainsString( 'Additional instructions for this run', $metabox );
		$this->assertStringContainsString( 'Review the automatically generated prompt', $metabox );
		$this->assertStringContainsString( "self::asset_version( 'assets/js/asset-generator.js' )", $metabox );
		$this->assertStringNotContainsString( 'Detailed prompt preview', $metabox );
		$this->assertStringNotContainsString( 'worldgraph-generate-asset__suggest', $metabox );

		$this->assertStringContainsString( 'type: type', $script );
		$this->assertStringContainsString( 'base_prompt:', $script );
		$this->assertStringContainsString( 'previousValue', $script );
		$this->assertStringContainsString( "startBatch( panel, 'item' )", $script );
		$this->assertStringContainsString( "startBatch( panel, 'project' )", $script );
	}
}
