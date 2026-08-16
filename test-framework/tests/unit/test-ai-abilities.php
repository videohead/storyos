<?php
/**
 * Tests for AI Abilities classes.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Abilities test class.
 */
class AI_Abilities_Test extends WP_UnitTestCase {

	public function test_abilities_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\Abilities\Abilities' ) );
	}

	public function test_abilities_has_singleton_contract() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'instance' ) );
		$instance1 = \StoryOS\AI\Abilities\Abilities::instance();
		$instance2 = \StoryOS\AI\Abilities\Abilities::instance();
		$this->assertSame( $instance1, $instance2 );
	}

	public function test_abilities_has_init_and_group_accessors() {
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'init' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'get_groups' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\Abilities\Abilities', 'get_group' ) );
	}

	public function test_ai_abilities_file_exists() {
		$this->assertFileExists( STORYOS_PLUGIN_PATH . '/includes/ai-editor/class-ai-abilities.php' );
	}

	public function test_native_agent_group_registered() {
		$abilities = \StoryOS\AI\Abilities\Abilities::instance();
		$group     = $abilities->get_group( 'storyos-native-agents' );
		$this->assertNotNull( $group );
	}
}
