<?php
/**
 * Smoke tests for Comfy MCP submission and fallback safety rails.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Comfy_MCP_Smoke extends TestCase {

	/**
	 * MCP tool payloads with isError=true must be normalized into WP_Error so
	 * Generation_Batch can trigger local fallback when available.
	 */
	public function test_mcp_in_band_tool_errors_are_normalized(): void {
		$client = file_get_contents( dirname( __DIR__ ) . '/includes/utils/comfy-cloud-mcp.php' );

		$this->assertNotFalse( $client );
		$this->assertStringContainsString( "if ( ! empty( \$result['isError'] ) )", $client );
		$this->assertStringContainsString( 'return new WP_Error(', $client );
		$this->assertStringContainsString( "'comfy_mcp_tool_error'", $client );
	}

	/**
	 * A local Comfy connection generates through its own HTTP API. Its MCP
	 * endpoint serves discovery and downloads, never generation.
	 */
	public function test_generation_batch_routes_local_comfy_to_local_api(): void {
		$batch = file_get_contents( dirname( __DIR__ ) . '/includes/utils/generation-batch.php' );

		$this->assertNotFalse( $batch );
		$this->assertStringContainsString( "if ( 'local' === ( \$connection['environment'] ?? '' ) ) {", $batch );
		$this->assertStringContainsString( "update_post_meta( \$job_id, '_worldgraph_gen_adapter', 'local_comfyui' );", $batch );
		$this->assertStringNotContainsString( 'Retrying via local ComfyUI API', $batch );
	}
}
