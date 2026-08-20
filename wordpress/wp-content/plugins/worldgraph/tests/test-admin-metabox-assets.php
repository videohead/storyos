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
}
