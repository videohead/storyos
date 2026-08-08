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

	/**
	 * Test that AI_Agent_Router class exists.
	 */
	public function test_agent_router_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Agent_Router' ), 'AI_Agent_Router class should exist' );
	}

	/**
	 * Test that AI_Agent_Router has route_request method.
	 */
	public function test_agent_router_has_route_request() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'route_request' ), 'AI_Agent_Router should have route_request method' );
	}

	/**
	 * Test that AI_Agent_Router has get_relevant_agents method.
	 */
	public function test_agent_router_has_get_relevant_agents() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'get_relevant_agents' ), 'AI_Agent_Router should have get_relevant_agents method' );
	}

	/**
	 * Test that AI_Agent_Router has get_agent_by_type method.
	 */
	public function test_agent_router_has_get_agent_by_type() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'get_agent_by_type' ), 'AI_Agent_Router should have get_agent_by_type method' );
	}

	/**
	 * Test that AI_Agent_Router has get_all_agent_types method.
	 */
	public function test_agent_router_has_get_all_agent_types() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Router', 'get_all_agent_types' ), 'AI_Agent_Router should have get_all_agent_types method' );
	}

	/**
	 * Test that AI_Agent_Router has keyword matching properties.
	 */
	public function test_agent_router_has_keyword_properties() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_Agent_Router' );
		$this->assertTrue( $reflection->hasProperty( 'keyword_mappings' ), 'Should have keyword_mappings property' );
		$this->assertTrue( $reflection->hasProperty( 'agent_registry' ), 'Should have agent_registry property' );
	}

	/**
	 * Test route_request returns array with agent info.
	 */
	public function test_route_request_returns_array() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( 'Write a scene about a hero' );
		
		$this->assertIsArray( $result, 'route_request should return an array' );
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
		$this->assertArrayHasKey( 'confidence', $result, 'Result should have confidence key' );
		$this->assertArrayHasKey( 'reason', $result, 'Result should have reason key' );
	}

	/**
	 * Test route_request matches story keywords.
	 */
	public function test_route_request_matches_story_keywords() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( 'Write a compelling scene with character development' );
		
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
		// Should match story advisor keywords like 'scene', 'character', 'write'
		$this->assertGreaterThan( 0, $result['confidence'], 'Should have confidence score' );
	}

	/**
	 * Test route_request matches production keywords.
	 */
	public function test_route_request_matches_production_keywords() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( 'Create a production schedule and budget' );
		
		$this->assertIsArray( $result, 'route_request should return an array' );
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
	}

	/**
	 * Test route_request matches technical keywords.
	 */
	public function test_route_request_matches_technical_keywords() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( 'Generate camera angles and lighting setup' );
		
		$this->assertIsArray( $result, 'route_request should return an array' );
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
	}

	/**
	 * Test get_agent_by_type returns array for valid type.
	 */
	public function test_get_agent_by_type_returns_array() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$agents = $router->get_agent_by_type( 'story' );
		
		$this->assertIsArray( $agents, 'get_agent_by_type should return an array' );
	}

	/**
	 * Test get_all_agent_types returns array of strings.
	 */
	public function test_get_all_agent_types_returns_array() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$types = $router->get_all_agent_types();
		
		$this->assertIsArray( $types, 'get_all_agent_types should return an array' );
		// Should have at least some agent types
		$this->assertGreaterThan( 0, count( $types ), 'Should have at least one agent type' );
	}

	/**
	 * Test route_request with empty input.
	 */
	public function test_route_request_empty_input() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( '' );
		
		$this->assertIsArray( $result, 'route_request should return an array even with empty input' );
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
		$this->assertEquals( 0, $result['confidence'], 'Empty input should have zero confidence' );
	}

	/**
	 * Test route_request with unknown input.
	 */
	public function test_route_request_unknown_input() {
		$router = new \StoryOS\AI\AI_Agent_Router();
		
		$result = $router->route_request( 'xyz123 random gibberish' );
		
		$this->assertIsArray( $result, 'route_request should return an array' );
		$this->assertArrayHasKey( 'agent', $result, 'Result should have agent key' );
	}
}
