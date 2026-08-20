<?php
/**
 * Tests for the fal MCP adapter contract.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;
use StoryOS\Utils\Fal_MCP;
use StoryOS\Utils\Fal_Catalog;

require_once dirname( __DIR__ ) . '/includes/utils/fal-mcp.php';
require_once dirname( __DIR__ ) . '/includes/utils/fal-catalog.php';

/** fal MCP adapter tests. */
class Test_Fal_MCP extends TestCase {

	/** The adapter uses fal's documented hosted Streamable HTTP endpoint. */
	public function test_endpoint_and_generation_tools_match_fal_contract(): void {
		$this->assertSame( 'https://mcp.fal.ai/mcp', Fal_MCP::ENDPOINT );
		$this->assertSame( [ 'submit_job', 'check_job' ], Fal_MCP::GENERATION_TOOLS );
	}

	/** fal queue states are mapped onto the states consumed by Generation_Batch. */
	public function test_queue_status_normalization(): void {
		$method = new ReflectionMethod( Fal_MCP::class, 'normalize_status' );
		$method->setAccessible( true );

		$this->assertSame( 'submitted', $method->invoke( null, 'IN_QUEUE' ) );
		$this->assertSame( 'submitted', $method->invoke( null, 'IN_PROGRESS' ) );
		$this->assertSame( 'completed', $method->invoke( null, 'COMPLETED' ) );
		$this->assertSame( 'failed', $method->invoke( null, 'FAILED' ) );
		$this->assertSame( 'cancelled', $method->invoke( null, 'CANCELED' ) );
	}

	/** An optional Connection allowlist restricts the fal endpoints Templates can run. */
	public function test_model_access_allowlist(): void {
		$this->assertTrue( Fal_MCP::endpoint_is_allowed( [ 'model_access' => '' ], 'fal-ai/flux/dev' ) );
		$this->assertTrue( Fal_MCP::endpoint_is_allowed( [ 'model_access' => '["fal-ai/flux/dev"]' ], 'fal-ai/flux/dev' ) );
		$this->assertFalse( Fal_MCP::endpoint_is_allowed( [ 'model_access' => '["fal-ai/flux/dev"]' ], 'fal-ai/kling-video/v3' ) );
	}

	/** MCP schema defaults are provisioned while prompt remains runtime-owned. */
	public function test_catalog_extracts_schema_defaults_without_prompt(): void {
		$method = new ReflectionMethod( Fal_Catalog::class, 'schema_defaults' );
		$method->setAccessible( true );
		$defaults = $method->invoke( null, [
			'input_schema' => [
				'properties' => [
					'prompt'     => [ 'type' => 'string' ],
					'image_size' => [ 'type' => 'string', 'default' => 'landscape_16_9' ],
					'num_images' => [ 'type' => 'integer', 'default' => 1 ],
				],
			],
		] );

		$this->assertSame( [ 'image_size' => 'landscape_16_9', 'num_images' => 1 ], $defaults );
		$this->assertArrayNotHasKey( 'prompt', $defaults );
	}

	/** Completion is downstream of WordPress media import, never remote-URL-only. */
	public function test_generation_completion_contract_requires_wordpress_import(): void {
		$batch = file_get_contents( dirname( __DIR__ ) . '/includes/utils/generation-batch.php' );
		$assets = file_get_contents( dirname( __DIR__ ) . '/includes/utils/class-asset-generator.php' );

		$this->assertNotFalse( $batch );
		$this->assertNotFalse( $assets );
		$this->assertLessThan(
			strpos( $batch, "update_post_meta( \$job_id, '_storyos_generation_status', \$status )" ),
			strpos( $batch, 'Asset_Generator::import_completed_job' )
		);
		$this->assertStringContainsString( 'find_result_urls( $result )', $assets );
		$this->assertStringContainsString( "'attachment_ids' => \$generated_attachment_ids", $assets );
	}

	/** Connection adapters are manifest-driven and the wizard renders its choices as a dropdown. */
	public function test_connection_adapter_loading_and_setup_ui_contract(): void {
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/storyos.php' );
		$registry = file_get_contents( dirname( __DIR__ ) . '/includes/utils/connection-adapters.php' );
		$wizard = file_get_contents( dirname( __DIR__ ) . '/includes/admin/setup-wizard.php' );
		$plugins = file_get_contents( dirname( __DIR__ ) . '/includes/admin/plugins.php' );

		$this->assertNotFalse( $bootstrap );
		$this->assertNotFalse( $registry );
		$this->assertNotFalse( $wizard );
		$this->assertNotFalse( $plugins );
		$this->assertStringContainsString( "includes/utils/connection-adapters.php", $bootstrap );
		$this->assertStringNotContainsString( "require_once STORYOS_PLUGIN_DIR . 'includes/utils/fal-mcp.php'", $bootstrap );
		$this->assertStringNotContainsString( "require_once STORYOS_PLUGIN_DIR . 'includes/utils/comfy-cloud-mcp.php'", $bootstrap );
		$this->assertStringContainsString( 'load_configured()', $registry );
		$this->assertStringContainsString( "'setup_options'", $registry );
		$this->assertStringContainsString( 'name="storyos_generation_connection_mode"', $wizard );
		$this->assertStringNotContainsString( 'type="radio" name="storyos_comfy_connection_mode"', $wizard );
		$this->assertStringContainsString( '<h2>Connection Adapters</h2>', $plugins );
		$this->assertStringContainsString( 'there is no separate plugin toggle', $plugins );
	}
}
