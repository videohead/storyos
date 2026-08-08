<?php
/**
 * Tests for StoryOS utility functions.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Utils
 */
class Test_StoryOS_Utils extends TestCase {

	/**
	 * Test the prefix function.
	 */
	public function test_prefix() {
		$result = prefix( 'test' );
		$this->assertEquals( 'storyos_test', $result );
	}

	/**
	 * Test prefix with custom prefix.
	 */
	public function test_prefix_with_custom_prefix() {
		$result = prefix( 'test', 'custom_' );
		$this->assertEquals( 'custom_test', $result );
	}

	/**
	 * Test sanitize_story_id function.
	 */
	public function test_sanitize_story_id() {
		$result = sanitize_story_id( 'Test-Project_123' );
		$this->assertEquals( 'test-project-123', $result );
	}

	/**
	 * Test sanitize_story_id with special characters.
	 */
	public function test_sanitize_story_id_special_chars() {
		$result = sanitize_story_id( 'Test@Project#$123' );
		$this->assertEquals( 'test-project-123', $result );
	}

	/**
	 * Test sanitize_story_id with spaces.
	 */
	public function test_sanitize_story_id_spaces() {
		$result = sanitize_story_id( 'Test Project 123' );
		$this->assertEquals( 'test-project-123', $result );
	}
}
