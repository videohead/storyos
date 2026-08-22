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
	 * A local Comfy connection should retry through Local_ComfyUI when MCP
	 * submission fails.
	 */
	public function test_generation_batch_keeps_local_comfy_fallback_path(): void {
		$batch = file_get_contents( dirname( __DIR__ ) . '/includes/utils/generation-batch.php' );

		$this->assertNotFalse( $batch );
		$this->assertStringContainsString( "if ( is_wp_error( \$result ) && Comfy_Cloud_MCP::class === \$client && 'local' === ( \$connection['environment'] ?? '' ) )", $batch );
		$this->assertStringContainsString( 'Retrying via local ComfyUI API', $batch );
		$this->assertStringContainsString( "update_post_meta( \$job_id, '_worldgraph_gen_adapter', 'local_comfyui' );", $batch );
	}
}
