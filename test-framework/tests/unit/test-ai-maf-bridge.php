<?php
/**
 * Tests for the AI MAF Bridge class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI MAF Bridge test class.
 */
class AI_MAF_Bridge_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_MAF_Bridge class exists.
	 */
	public function test_maf_bridge_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_MAF_Bridge' ), 'AI_MAF_Bridge class should exist' );
	}

	/**
	 * Test that AI_MAF_Bridge has load_agents method.
	 */
	public function test_maf_bridge_has_load_agents() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'load_agents' ), 'AI_MAF_Bridge should have load_agents method' );
	}

	/**
	 * Test that AI_MAF_Bridge has parse_agent_file method.
	 */
	public function test_maf_bridge_has_parse_agent_file() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'parse_agent_file' ), 'AI_MAF_Bridge should have parse_agent_file method' );
	}

	/**
	 * Test that AI_MAF_Bridge has get_agent method.
	 */
	public function test_maf_bridge_has_get_agent() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'get_agent' ), 'AI_MAF_Bridge should have get_agent method' );
	}

	/**
	 * Test that AI_MAF_Bridge has list_agents method.
	 */
	public function test_maf_bridge_has_list_agents() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'list_agents' ), 'AI_MAF_Bridge should have list_agents method' );
	}

	/**
	 * Test that AI_MAF_Bridge has get_agents_by_department method.
	 */
	public function test_maf_bridge_has_get_agents_by_department() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'get_agents_by_department' ), 'AI_MAF_Bridge should have get_agents_by_department method' );
	}

	/**
	 * Test that AI_MAF_Bridge has run_agent method.
	 */
	public function test_maf_bridge_has_run_agent() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'run_agent' ), 'AI_MAF_Bridge should have run_agent method' );
	}

	/**
	 * Test that AI_MAF_Bridge has parse_yaml method.
	 */
	public function test_maf_bridge_has_parse_yaml() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_MAF_Bridge', 'parse_yaml' ), 'AI_MAF_Bridge should have parse_yaml method' );
	}

	/**
	 * Test that AI_MAF_Bridge has agents property.
	 */
	public function test_maf_bridge_has_agents_property() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_MAF_Bridge' );
		$this->assertTrue( $reflection->hasProperty( 'agents' ), 'AI_MAF_Bridge should have agents property' );
		$this->assertTrue( $reflection->hasProperty( 'llm_client' ), 'AI_MAF_Bridge should have llm_client property' );
	}

	/**
	 * Test that AI_MAF_Bridge constructor accepts LLM client.
	 */
	public function test_maf_bridge_constructor_accepts_llm_client() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_MAF_Bridge' );
		$constructor = $reflection->getConstructor();
		
		$this->assertNotNull( $constructor, 'AI_MAF_Bridge should have a constructor' );
		
		$parameters = $constructor->getParameters();
		$this->assertCount( 1, $parameters, 'Constructor should accept one parameter' );
		$this->assertEquals( 'llm_client', $parameters[0]->getName(), 'Parameter should be llm_client' );
	}

	/**
	 * Test list_agents returns array when no agents exist.
	 */
	public function test_list_agents_returns_array() {
		$llm_client = new \StoryOS\AI\AI_LLM_Client();
		$bridge = new \StoryOS\AI\AI_MAF_Bridge( $llm_client );
		
		$agents = $bridge->list_agents();
		
		$this->assertIsArray( $agents, 'list_agents should return an array' );
	}

	/**
	 * Test get_agent returns null for non-existent agent.
	 */
	public function test_get_agent_returns_null_for_nonexistent() {
		$llm_client = new \StoryOS\AI\AI_LLM_Client();
		$bridge = new \StoryOS\AI\AI_MAF_Bridge( $llm_client );
		
		$agent = $bridge->get_agent( 'nonexistent_agent' );
		
		$this->assertNull( $agent, 'get_agent should return null for non-existent agent' );
	}

	/**
	 * Test parse_yaml handles simple key-value pairs.
	 */
	public function test_parse_yaml_simple_key_value() {
		$llm_client = new \StoryOS\AI\AI_LLM_Client();
		$bridge = new \StoryOS\AI\AI_MAF_Bridge( $llm_client );
		
		$reflection = new \ReflectionClass( $bridge );
		$method = $reflection->getMethod( 'parse_yaml' );
		$method->setAccessible( true );
		
		$yaml = "name: test\nvalue: 123";
		$result = $method->invoke( $bridge, $yaml );
		
		$this->assertIsArray( $result, 'parse_yaml should return an array' );
		$this->assertArrayHasKey( 'name', $result, 'Should have name key' );
		$this->assertArrayHasKey( 'value', $result, 'Should have value key' );
	}

	/**
	 * Test parse_yaml handles arrays.
	 */
	public function test_parse_yaml_handles_arrays() {
		$llm_client = new \StoryOS\AI\AI_LLM_Client();
		$bridge = new \StoryOS\AI\AI_MAF_Bridge( $llm_client );
		
		$reflection = new \ReflectionClass( $bridge );
		$method = $reflection->getMethod( 'parse_yaml' );
		$method->setAccessible( true );
		
		$yaml = "tools:\n  - tool1\n  - tool2";
		$result = $method->invoke( $bridge, $yaml );
		
		$this->assertIsArray( $result, 'parse_yaml should return an array' );
		$this->assertArrayHasKey( 'tools', $result, 'Should have tools key' );
		$this->assertIsArray( $result['tools'], 'tools should be an array' );
	}
}
