<?php
/**
 * Tests for StoryOS file-based import flow.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Import
 */
class Test_StoryOS_Import extends TestCase {

	/**
	 * The import admin page should use a file input and not require pasted JSON.
	 */
	public function test_import_admin_page_uses_file_upload() {
		$path = dirname( __DIR__ ) . '/includes/admin/import.php';
		$this->assertFileExists( $path );

		$source = file_get_contents( $path );
		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'type="file"', $source );
		$this->assertStringContainsString( 'storyos_json_file', $source );
		$this->assertStringNotContainsString( 'textarea name="storyos_json"', $source );
	}
}
