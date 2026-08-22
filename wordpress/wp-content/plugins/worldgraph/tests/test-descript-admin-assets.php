<?php
/**
 * Descript admin asset source-contract tests.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/** Descript settings-page asset contracts. */
class Test_Descript_Admin_Assets extends TestCase {

	/** The unsync confirmation must use a page-scoped, localized script asset. */
	public function test_unsync_confirmation_uses_enqueued_localized_asset(): void {
		$plugin   = file_get_contents( dirname( __DIR__ ) . '/plugins/descript/descript-sync.php' );
		$settings = file_get_contents( dirname( __DIR__ ) . '/plugins/descript/includes/class-descript-settings.php' );
		$script   = file_get_contents( dirname( __DIR__ ) . '/plugins/descript/js/descript-settings.js' );

		$this->assertNotFalse( $plugin );
		$this->assertNotFalse( $settings );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString( 'WORLDGRAPH_DESCRIPT_PLUGIN_URL', $plugin );
		$this->assertStringContainsString( "add_action( 'admin_enqueue_scripts', [ \$this, 'enqueue_assets' ] )", $settings );
		$this->assertStringContainsString( '$this->settings_page_hook !== $hook_suffix', $settings );
		$this->assertStringContainsString( "'worldgraph-descript-settings'", $settings );
		$this->assertStringContainsString( "'js/descript-settings.js'", $settings );
		$this->assertStringContainsString( 'wp_localize_script(', $settings );
		$this->assertStringContainsString( "'worldgraphDescriptSettings'", $settings );
		$this->assertStringContainsString( 'data-worldgraph-descript-confirm-unsync', $settings );
		$this->assertStringNotContainsString( 'onclick=', $settings );
		$this->assertStringNotContainsString( 'esc_js(', $settings );
		$this->assertStringContainsString( "document.querySelector( '[data-worldgraph-descript-confirm-unsync]' )", $script );
		$this->assertStringContainsString( 'window.worldgraphDescriptSettings', $script );
		$this->assertStringContainsString( 'config.i18n.confirmUnsync', $script );
		$this->assertStringContainsString( 'window.confirm(', $script );
		$this->assertStringContainsString( 'event.preventDefault();', $script );
	}
}
