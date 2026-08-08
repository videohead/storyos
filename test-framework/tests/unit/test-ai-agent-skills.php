<?php
/**
 * Tests for the AI Agent Skills class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Agent Skills test class.
 */
class AI_Agent_Skills_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_Agent_Skills class exists.
	 */
	public function test_agent_skills_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Agent_Skills' ), 'AI_Agent_Skills class should exist' );
	}

	/**
	 * Test that AI_Agent_Skills has load_skills method.
	 */
	public function test_agent_skills_has_load_skills() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Skills', 'load_skills' ), 'AI_Agent_Skills should have load_skills method' );
	}

	/**
	 * Test that AI_Agent_Skills has get_skills_for_post_type method.
	 */
	public function test_agent_skills_has_get_skills_for_post_type() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Skills', 'get_skills_for_post_type' ), 'AI_Agent_Skills should have get_skills_for_post_type method' );
	}

	/**
	 * Test that AI_Agent_Skills has get_relevant_skills method.
	 */
	public function test_agent_skills_has_get_relevant_skills() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Skills', 'get_relevant_skills' ), 'AI_Agent_Skills should have get_relevant_skills method' );
	}

	/**
	 * Test that AI_Agent_Skills has get_skill_content method.
	 */
	public function test_agent_skills_has_get_skill_content() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Agent_Skills', 'get_skill_content' ), 'AI_Agent_Skills should have get_skill_content method' );
	}

	/**
	 * Test that AI_Agent_Skills has skills property.
	 */
	public function test_agent_skills_has_skills_property() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_Agent_Skills' );
		$this->assertTrue( $reflection->hasProperty( 'skills' ), 'AI_Agent_Skills should have skills property' );
	}

	/**
	 * Test load_skills returns array.
	 */
	public function test_load_skills_returns_array() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		$result = $skills->load_skills();
		
		$this->assertIsArray( $result, 'load_skills should return an array' );
	}

	/**
	 * Test get_skills_for_post_type returns array.
	 */
	public function test_get_skills_for_post_type_returns_array() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		$result = $skills->get_skills_for_post_type( 'post' );
		
		$this->assertIsArray( $result, 'get_skills_for_post_type should return an array' );
	}

	/**
	 * Test get_skills_for_post_type returns array for custom post types.
	 */
	public function test_get_skills_for_post_type_returns_array_for_cpt() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		$result = $skills->get_skills_for_post_type( 'storyos_character' );
		
		$this->assertIsArray( $result, 'get_skills_for_post_type should return an array for custom post types' );
	}

	/**
	 * Test get_relevant_skills returns array.
	 */
	public function test_get_relevant_skills_returns_array() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		$result = $skills->get_relevant_skills( 'Write a scene' );
		
		$this->assertIsArray( $result, 'get_relevant_skills should return an array' );
	}

	/**
	 * Test get_skill_content returns string or null.
	 */
	public function test_get_skill_content_returns_string_or_null() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		$result = $skills->get_skill_content( 'nonexistent_skill' );
		
		$this->assertNull( $result, 'get_skill_content should return null for non-existent skill' );
	}

	/**
	 * Test load_skills handles missing directory gracefully.
	 */
	public function test_load_skills_handles_missing_directory() {
		$skills = new \StoryOS\AI\AI_Agent_Skills();
		
		// Should not throw an error
		$result = $skills->load_skills();
		
		$this->assertIsArray( $result, 'load_skills should return an array even if directory is missing' );
	}
}
