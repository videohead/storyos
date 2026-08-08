<?php
/**
 * Tests for the Chat Abilities class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * Chat Abilities test class.
 */
class Chat_Abilities_Test extends WP_UnitTestCase {

	/**
	 * Test that Chat_Abilities class exists.
	 */
	public function test_chat_abilities_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\Abilities\Chat_Abilities' ), 'Chat_Abilities class should exist' );
	}

	/**
	 * Test that Chat_Abilities has register method.
	 */
	public function test_chat_abilities_has_register_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Chat_Abilities', 'register' ), 'Chat_Abilities should have register method' );
	}

	/**
	 * Test that Chat_Abilities has get_tools method.
	 */
	public function test_chat_abilities_has_get_tools() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Chat_Abilities', 'get_tools' ), 'Chat_Abilities should have get_tools method' );
	}

	/**
	 * Test register method returns array.
	 */
	public function test_register_returns_array() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$result = $chat->register();
		
		$this->assertIsArray( $result, 'register should return an array' );
	}

	/**
	 * Test get_tools returns array of tools.
	 */
	public function test_get_tools_returns_array() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		
		$this->assertIsArray( $tools, 'get_tools should return an array' );
		$this->assertGreaterThan( 0, count( $tools ), 'Should have at least one tool' );
	}

	/**
	 * Test tools have required keys.
	 */
	public function test_tools_have_required_keys() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		
		foreach ( $tools as $tool ) {
			$this->assertArrayHasKey( 'name', $tool, 'Tool should have name key' );
			$this->assertArrayHasKey( 'description', $tool, 'Tool should have description key' );
			$this->assertArrayHasKey( 'inputSchema', $tool, 'Tool should have inputSchema key' );
		}
	}

	/**
	 * Test chat tool exists.
	 */
	public function test_chat_tool_exists() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		$tool_names = array_column( $tools, 'name' );
		
		$this->assertContains( 'chat', $tool_names, 'Should have chat tool' );
	}

	/**
	 * Test analyze tool exists.
	 */
	public function test_analyze_tool_exists() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		$tool_names = array_column( $tools, 'name' );
		
		$this->assertContains( 'analyze', $tool_names, 'Should have analyze tool' );
	}

	/**
	 * Test generate tool exists.
	 */
	public function test_generate_tool_exists() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		$tool_names = array_column( $tools, 'name' );
		
		$this->assertContains( 'generate', $tool_names, 'Should have generate tool' );
	}

	/**
	 * Test continuity tool exists.
	 */
	public function test_continuity_tool_exists() {
		$chat = new \StoryOS\AI\Abilities\Chat_Abilities();
		
		$tools = $chat->get_tools();
		$tool_names = array_column( $tools, 'name' );
		
		$this->assertContains( 'continuity_check', $tool_names, 'Should have continuity_check tool' );
	}
}
