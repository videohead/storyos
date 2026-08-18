<?php
/**
 * Tests for StoryOS Assets metabox behavior.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

class Test_Admin_Metabox_Assets extends TestCase {

	/**
	 * The metabox should not add custom featured/gallery asset controls when core
	 * Gutenberg controls already cover those responsibilities.
	 */
	public function test_storyos_assets_metabox_uses_core_editor_controls_only(): void {
		$file = dirname( __DIR__ ) . '/includes/admin/metaboxes.php';
		$source = file_get_contents( $file );

		$this->assertNotFalse( $source, 'The metabox file should be readable.' );
		$this->assertStringNotContainsString( 'storyos-select-featured-asset', $source );
		$this->assertStringNotContainsString( 'storyos-select-gallery', $source );
		$this->assertStringNotContainsString( 'storyos_featured_asset_nonce', $source );
		$this->assertStringNotContainsString( 'storyos_asset_gallery_nonce', $source );
		$this->assertStringContainsString( 'block editor', strtolower( $source ) );
	}
}
