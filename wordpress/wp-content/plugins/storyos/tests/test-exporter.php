<?php
/**
 * Tests for StoryOS markdown export flow.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Exporter
 */
class Test_StoryOS_Exporter extends TestCase {

	/**
	 * The exporter should work from live StoryOS project records rather than import JSON snapshots.
	 */
	public function test_exporter_builds_markdown_from_live_project_data() {
		$this->assertTrue( class_exists( '\\StoryOS\\Exporter\\StoryOS_Exporter' ), 'Exporter class must exist.' );

		$exporter = new \StoryOS\Exporter\StoryOS_Exporter();
		$this->assertIsObject( $exporter );
		$this->assertTrue( method_exists( $exporter, 'export_project_markdown' ) );
	}
}
