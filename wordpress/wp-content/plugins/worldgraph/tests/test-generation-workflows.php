<?php
/**
 * Tests for representative-media workflows and their editor controls.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;
use WorldGraph\Utils\Generation_Workflows;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return (string) $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/utils/class-generation-workflows.php';

/** Representative workflow and metabox contract tests. */
class Test_Generation_Workflows extends TestCase {

	/** Default recipes must retain the promised image/video output counts. */
	public function test_default_workflow_output_counts(): void {
		$definitions = Generation_Workflows::definitions();

		$this->assertCount( 1, $definitions['worldgraph_project']['outputs'] );
		$this->assertCount( 1, $definitions['worldgraph_world']['outputs'] );
		$this->assertCount( 6, $definitions['worldgraph_character']['outputs'] );
		$this->assertCount( 6, $definitions['worldgraph_prop']['outputs'] );
		$this->assertCount( 6, $definitions['worldgraph_location']['outputs'] );
		$this->assertCount( 2, $definitions['worldgraph_shot']['outputs'] );
		$this->assertCount( 1, $definitions['worldgraph_scene']['outputs'] );
		$this->assertCount( 1, $definitions['worldgraph_episode']['outputs'] );
	}

	/** A Shot is the direct authoring surface that deliberately offers video. */
	public function test_shot_workflow_has_explicit_image_and_video_actions(): void {
		$outputs = Generation_Workflows::definitions()['worldgraph_shot']['outputs'];

		$this->assertSame( [ 'image', 'video' ], array_column( $outputs, 'type' ) );
		$this->assertSame( [ 'shot-representative-still', 'shot-video' ], array_column( $outputs, 'intent' ) );
	}

	/** Inherited Scene context must not flood Shot prompts with a transcript. */
	public function test_inherited_context_uses_short_visual_fields(): void {
		$this->assertSame(
			[ 'summary', 'location', 'time_of_day', 'emotional_tone' ],
			Generation_Workflows::INHERITED_PROMPT_FIELDS['worldgraph_scene']
		);
		$this->assertNotContains( 'script_content', Generation_Workflows::INHERITED_PROMPT_FIELDS['worldgraph_scene'] );
		$this->assertNotContains( 'dialogue', Generation_Workflows::INHERITED_PROMPT_FIELDS['worldgraph_scene'] );
		$this->assertNotContains( 'frame_rate', Generation_Workflows::PROMPT_FIELDS['worldgraph_project'] );
	}

	/** Direct REST generation must route the selected output, not hard-code image. */
	public function test_direct_rest_contract_supports_video(): void {
		$controller = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/asset-generation-controller.php' );
		$generator  = file_get_contents( dirname( __DIR__ ) . '/includes/utils/class-asset-generator.php' );

		$this->assertNotFalse( $controller );
		$this->assertNotFalse( $generator );
		$this->assertStringContainsString( "'enum' => [ 'image', 'video' ]", $controller );
		$this->assertStringContainsString( "'type'         => \$type", $controller );
		$this->assertStringContainsString( "'prompt_is_composed' => false", $generator );
		$this->assertStringContainsString( 'self::build_prompt( $post_id, $intent, $provided_prompt )', $generator );
	}

	/** Long-running batches publish only complete jobs and stream large media. */
	public function test_durable_batch_commit_and_media_guards_are_present(): void {
		$workflow  = file_get_contents( dirname( __DIR__ ) . '/includes/utils/class-generation-workflows.php' );
		$generator = file_get_contents( dirname( __DIR__ ) . '/includes/utils/class-asset-generator.php' );

		$this->assertNotFalse( $workflow );
		$this->assertNotFalse( $generator );
		$this->assertStringContainsString( 'private static function reserve_idempotency_key', $workflow );
		$this->assertStringContainsString( 'private static function acquire_coordinator_lock', $workflow );
		$this->assertStringContainsString( 'self::is_cancel_requested( $batch_id )', $workflow );
		$this->assertStringContainsString( "update_post_meta( \$batch_id, '_worldgraph_gen_status', 'batch_materializing' )", $workflow );
		$this->assertStringContainsString( "\$job_meta['_worldgraph_gen_status'] = \$initial_status", $generator );
		$this->assertStringContainsString( "'image' === \$requested_type && ! \$image_attachment_id", $generator );
		$this->assertStringContainsString( 'self::download_to_file( $video_url, self::MAX_VIDEO_BYTES', $generator );
		$this->assertStringContainsString( "'limit_response_size' => \$maximum_bytes + 1", $generator );
	}
}
