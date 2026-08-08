<?php
/**
 * Tests for the AI Abilities classes.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Abilities test class.
 */
class AI_Abilities_Test extends WP_UnitTestCase {

	/**
	 * Test that Abilities class exists.
	 */
	public function test_abilities_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\Abilities\Abilities' ), 'Abilities class should exist' );
	}

	/**
	 * Test that Abilities has instance method.
	 */
	public function test_abilities_has_instance_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'instance' ), 'Abilities should have instance method' );
	}

	/**
	 * Test that Abilities has init method.
	 */
	public function test_abilities_has_init_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'init' ), 'Abilities should have init method' );
	}

	/**
	 * Test that Abilities has register_chat_abilities method.
	 */
	public function test_abilities_has_register_chat_abilities() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'register_chat_abilities' ), 'Abilities should have register_chat_abilities method' );
	}

	/**
	 * Test that Abilities has register_context_resources method.
	 */
	public function test_abilities_has_register_context_resources() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'register_context_resources' ), 'Abilities should have register_context_resources method' );
	}

	/**
	 * Test that Abilities has register_prompt_templates method.
	 */
	public function test_abilities_has_register_prompt_templates() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'register_prompt_templates' ), 'Abilities should have register_prompt_templates method' );
	}

	/**
	 * Test that Abilities follows singleton pattern.
	 */
	public function test_abilities_is_singleton() {
		$instance1 = \StoryOS\AI\Abilities\Abilities::instance();
		$instance2 = \StoryOS\AI\Abilities\Abilities::instance();
		
		$this->assertSame( $instance1, $instance2, 'Abilities should follow singleton pattern' );
	}

	/**
	 * Test that Abilities class files exist.
	 */
	public function test_abilities_files_exist() {
		$abilities_dir = STORYOS_PLUGIN_PATH . '/includes/ai-editor/abilities/';
		
		$this->assertDirectoryExists( $abilities_dir, 'Abilities directory should exist' );
		
		$files = [
			'class-chat-abilities.php',
			'class-context-resources.php',
			'class-prompt-templates.php',
		];
		
		foreach ( $files as $file ) {
			$file_path = $abilities_dir . $file;
			$this->assertFileExists( $file_path, "Abilities file should exist: {$file}" );
		}
	}
}
