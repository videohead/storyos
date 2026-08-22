<?php
/**
 * Template requirements admin asset contract tests.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Template_Requirements_Assets extends TestCase {

	/** The requirements controller must be enqueued and receive dynamic data safely. */
	public function test_template_requirements_uses_an_enqueued_localized_script(): void {
		$controller = file_get_contents( dirname( __DIR__ ) . '/includes/cpts/template.php' );
		$script     = file_get_contents( dirname( __DIR__ ) . '/assets/js/template-requirements.js' );

		$this->assertNotFalse( $controller );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString(
			"add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_requirements_script' ] )",
			$controller
		);
		$this->assertStringContainsString( "'assets/js/template-requirements.js'", $controller );
		$this->assertStringContainsString( "'worldgraphTemplateRequirements'", $controller );
		$this->assertStringContainsString( "'ajaxUrl'", $controller );
		$this->assertStringContainsString( "'providerTemplateFieldIds'", $controller );
		$this->assertStringNotContainsString( '<script', $controller );
		$this->assertStringNotContainsString( 'esc_js(', $controller );

		foreach ( [
			'worldgraph_check_template_requirements',
			'worldgraph_install_template_models',
			'worldgraph_discover_comfy_templates',
			'worldgraph_download_comfy_template_requirements',
			'worldgraph_import_provider_template_definition',
			'worldgraph_run_template_smoke_test',
		] as $action ) {
			$this->assertStringContainsString( $action, $script );
		}
	}
}
