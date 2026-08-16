<?php
/**
 * Tests for the AI Editor main class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Editor test class.
 */
class AI_Editor_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_Editor class exists.
	 */
	public function test_ai_editor_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Editor' ), 'AI_Editor class should exist' );
	}

	/**
	 * Test that AI_Editor has init method.
	 */
	public function test_ai_editor_has_init_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'init' ), 'AI_Editor should have init method' );
	}

	/**
	 * Test that AI_Editor has register_rest_routes method.
	 */
	public function test_ai_editor_has_register_rest_routes() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'register_rest_routes' ), 'AI_Editor should have register_rest_routes method' );
	}

	/**
	 * Test that AI_Editor has register_settings method.
	 */
	public function test_ai_editor_has_register_settings() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'register_settings' ), 'AI_Editor should have register_settings method' );
	}

	/**
	 * Test that AI_Editor has add_settings_page method.
	 */
	public function test_ai_editor_has_add_settings_page() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'add_settings_page' ), 'AI_Editor should have add_settings_page method' );
	}

	/**
	 * Test that AI_Editor has enqueue_editor_assets method.
	 */
	public function test_ai_editor_has_enqueue_editor_assets() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'enqueue_editor_assets' ), 'AI_Editor should have enqueue_editor_assets method' );
	}

	/**
	 * Test that AI_Editor has add_ai_context method.
	 */
	public function test_ai_editor_has_add_ai_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor', 'add_ai_context' ), 'AI_Editor should have add_ai_context method' );
	}

	/**
	 * Test that AI_Editor constructor creates dependencies.
	 */
	public function test_ai_editor_constructor_creates_dependencies() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_Editor' );
		$constructor = $reflection->getConstructor();
		
		$this->assertNotNull( $constructor, 'AI_Editor should have a constructor' );
		
		// Check for dependency properties.
		$properties = [
			'llm_client',
			'agent_registry',
			'maf_bridge',
			'context_builder',
			'agent_router',
			'agent_skills',
		];
		
		foreach ( $properties as $prop ) {
			$this->assertTrue( $reflection->hasProperty( $prop ), "AI_Editor should have property: {$prop}" );
		}
	}

	/**
	 * Test that AI_Editor files exist.
	 */
	public function test_ai_editor_files_exist() {
		$plugin_dir = STORYOS_PLUGIN_PATH . '/includes/ai-editor/';
		
		$this->assertDirectoryExists( $plugin_dir, 'AI Editor directory should exist' );
		
		$files = [
			'class-ai-editor.php',
			'class-ai-llm-client.php',
			'class-ai-agent-registry.php',
			'class-ai-maf-bridge.php',
			'class-ai-context-builder.php',
			'class-ai-agent-router.php',
			'class-ai-agent-skills.php',
			'class-ai-editor-rest.php',
		];
		
		foreach ( $files as $file ) {
			$file_path = $plugin_dir . $file;
			$this->assertFileExists( $file_path, "AI Editor file should exist: {$file}" );
		}
	}
}
