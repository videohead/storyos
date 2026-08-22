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

	/** Generation type drives one purpose-built selector and contextual action. */
	public function test_generator_controls_match_their_actions(): void {
		$metabox = file_get_contents( dirname( __DIR__ ) . '/includes/admin/asset-generator-metabox.php' );
		$script  = file_get_contents( dirname( __DIR__ ) . '/assets/js/asset-generator.js' );

		$this->assertNotFalse( $metabox );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString( 'Choose a generation type', $metabox );
		$this->assertStringContainsString( 'Create one selected still image', $metabox );
		$this->assertStringContainsString( 'Create every image or video in a defined set', $metabox );
		$this->assertStringContainsString( 'Create one selected moving shot', $metabox );
		$this->assertStringContainsString( 'worldgraph-generate-asset__action-select', $metabox );
		$this->assertStringContainsString( 'worldgraph-generate-asset__image-template-option', $metabox );
		$this->assertStringContainsString( 'worldgraph-generate-asset__video-template-option', $metabox );
		$this->assertStringContainsString( 'Additional instructions for this run', $metabox );
		$this->assertStringContainsString( 'Review the generated prompt or workflow plan', $metabox );
		$this->assertStringContainsString( "self::asset_version( 'assets/js/asset-generator.js' )", $metabox );
		$this->assertStringContainsString( "'generationRestUrl' => rest_url( 'worldgraph/v1/generation' )", $metabox );
		$this->assertStringNotContainsString( 'Detailed prompt preview', $metabox );
		$this->assertStringNotContainsString( 'worldgraph-generate-asset__suggest', $metabox );
		$this->assertStringNotContainsString( 'Automatic per intent', $metabox );
		$this->assertStringNotContainsString( 'Direct output', $metabox );
		$this->assertStringNotContainsString( 'Generate this item’s full set', $metabox );

		$this->assertStringContainsString( 'body.actions || legacyActions( body )', $script );
		$this->assertStringContainsString( "image: actions.some", $script );
		$this->assertStringContainsString( "sequence: ( parseInt( body.total_jobs", $script );
		$this->assertStringContainsString( "video: actions.some", $script );
		$this->assertStringContainsString( 'type: action.type', $script );
		$this->assertStringContainsString( 'intent: action.intent', $script );
		$this->assertStringContainsString( 'function watchSingleJob( panel, generationId, type )', $script );
		$this->assertStringContainsString( 'generationStatusBaseUrl() + \'/\' + encodeURIComponent( generationId )', $script );
		$this->assertStringContainsString( 'if ( body.generation_id ) {', $script );
		$this->assertStringContainsString( 'watchSingleJob( panel, body.generation_id, action.type );', $script );
		$this->assertStringContainsString( 'base_prompt:', $script );
		$this->assertStringContainsString( 'startBatch( panel, info.scope )', $script );
		$this->assertStringContainsString( 'selectHasEnabledOption( template )', $script );
		$this->assertStringContainsString( "panel.querySelector( '.worldgraph-generate-asset__prompt' ).disabled = controlsLocked", $script );
		$this->assertStringContainsString( 'panel._worldgraphKnownBatches = activeBatchesFromPrompt( body )', $script );
		$this->assertStringContainsString( "select.textContent = '';", $script );
		$this->assertStringNotContainsString( 'worldgraph-generate-asset__run-set', $script );
		$this->assertStringNotContainsString( 'worldgraph-generate-asset__run-project', $script );
	}
}
