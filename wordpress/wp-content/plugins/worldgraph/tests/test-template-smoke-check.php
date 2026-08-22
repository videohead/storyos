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

	/** The Template list screen should summarize the generation contract for operators. */
	public function test_template_list_screen_exposes_summary_columns(): void {
		$template = file_get_contents( dirname( __DIR__ ) . '/includes/cpts/template.php' );

		$this->assertNotFalse( $template );
		$this->assertStringContainsString( "manage_worldgraph_template_posts_columns", $template );
		$this->assertStringContainsString( "manage_worldgraph_template_posts_custom_column", $template );
		$this->assertStringContainsString( "Template Status", $template );
		$this->assertStringContainsString( "Smoke Test", $template );
	}

	/** Operators should be able to trigger a smoke test manually and refresh the status. */
	public function test_template_smoke_test_has_manual_runner_and_ajax_action(): void {
		$template = file_get_contents( dirname( __DIR__ ) . '/includes/cpts/template.php' );

		$this->assertNotFalse( $template );
		$this->assertStringContainsString( 'worldgraph_run_template_smoke_test', $template );
		$this->assertStringContainsString( 'Run smoke test', $template );
	}
}
