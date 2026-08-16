<?php
/**
 * Tests for the AI Agent Router class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Agent Router test class.
 */
class AI_Agent_Router_Test extends WP_UnitTestCase {

	public function test_agent_router_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Agent_Router' ) );
	}

	public function test_router_has_route_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'route' ) );
	}

	public function test_router_has_available_agents_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'get_available_agents' ) );
	}

	public function test_router_route_returns_expected_shape() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		$result = $router->route( 'Write a scene with strong dialogue' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'agent', $result );
		$this->assertArrayHasKey( 'confidence', $result );
		$this->assertArrayHasKey( 'routing', $result );
	}

	public function test_router_preserves_legacy_alias_routing() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		$result = $router->route( 'Use the story advisor for this rewrite' );
		$this->assertEquals( 'screenwriter', $result['agent'] );
	}

	public function test_router_can_list_all_native_agents() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		$agents = $router->get_available_agents();

		$this->assertIsArray( $agents );
		$this->assertGreaterThan( 0, count( $agents ) );
	}
}
