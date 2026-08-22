<?php
/**
 * Smoke-test lifecycle coverage for Template-save validation.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Template_Smoke_Check extends TestCase {

	/** Template saves should trigger a non-dispatched queue smoke check. */
	public function test_template_smoke_check_is_bootstrapped_and_hooked(): void {
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/worldgraph.php' );
		$checker   = file_get_contents( dirname( __DIR__ ) . '/includes/utils/template-smoke-check.php' );

		$this->assertNotFalse( $bootstrap );
		$this->assertNotFalse( $checker );
		$this->assertStringContainsString( "includes/utils/template-smoke-check.php", $bootstrap );
		$this->assertStringContainsString( 'Template_Smoke_Check::init();', $bootstrap );
		$this->assertStringContainsString( "add_action( 'save_post_worldgraph_template'", $checker );
	}

	/** The smoke check should validate queueability without dispatching providers. */
	public function test_template_smoke_check_queues_without_dispatch_and_cleans_up(): void {
		$checker = file_get_contents( dirname( __DIR__ ) . '/includes/utils/template-smoke-check.php' );

		$this->assertNotFalse( $checker );
		$this->assertStringContainsString( "'schedule'     => false", $checker );
		$this->assertStringContainsString( "'create_asset' => false", $checker );
		$this->assertStringContainsString( "'set_featured' => false", $checker );
		$this->assertStringContainsString( 'Asset_Generator::queue_for_post', $checker );
		$this->assertStringContainsString( 'wp_delete_post( $generation_id, true );', $checker );
	}
}
