<?php
/**
 * Tests for native agent registry and legacy bridge wrapper.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * Native Agent Registry test class.
 */
class AI_MAF_Bridge_Test extends WP_UnitTestCase {

	public function test_registry_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Agent_Registry' ), 'AI_Agent_Registry class should exist' );
	}

	public function test_legacy_bridge_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_MAF_Bridge' ), 'AI_MAF_Bridge compatibility class should exist' );
	}

	public function test_legacy_bridge_extends_registry() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_MAF_Bridge' );
		$this->assertEquals( 'StoryOS\\AI\\AI_Agent_Registry', $reflection->getParentClass()->getName(), 'AI_MAF_Bridge should extend AI_Agent_Registry' );
	}

	public function test_registry_has_core_methods() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Registry', 'list_agents' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Registry', 'get_agent' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Registry', 'get_enabled_agents' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Registry', 'run_agent' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Registry', 'resolve_agent_slug' ) );
	}

	public function test_list_agents_returns_array() {
		$registry = new \StoryOS\AI\AI_Agent_Registry( new \StoryOS\AI\AI_LLM_Client() );
		$this->assertIsArray( $registry->list_agents(), 'list_agents should return an array' );
	}

	public function test_supported_agent_slugs_include_legacy_aliases() {
		$registry = new \StoryOS\AI\AI_Agent_Registry( new \StoryOS\AI\AI_LLM_Client() );
		$slugs    = $registry->get_supported_agent_slugs();
		$this->assertContains( 'story', $slugs );
		$this->assertContains( 'prompt', $slugs );
	}
}
