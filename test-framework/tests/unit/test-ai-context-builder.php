<?php
/**
 * Tests for the AI Context Builder class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Context Builder test class.
 */
class AI_Context_Builder_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_Context_Builder class exists.
	 */
	public function test_context_builder_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Context_Builder' ), 'AI_Context_Builder class should exist' );
	}

	/**
	 * Test that AI_Context_Builder has build_post_context method.
	 */
	public function test_context_builder_has_build_post_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Context_Builder', 'build_post_context' ), 'AI_Context_Builder should have build_post_context method' );
	}

	/**
	 * Test that AI_Context_Builder has build_character_context method.
	 */
	public function test_context_builder_has_build_character_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Context_Builder', 'build_character_context' ), 'AI_Context_Builder should have build_character_context method' );
	}

	/**
	 * Test that AI_Context_Builder has build_scene_context method.
	 */
	public function test_context_builder_has_build_scene_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Context_Builder', 'build_scene_context' ), 'AI_Context_Builder should have build_scene_context method' );
	}

	/**
	 * Test that AI_Context_Builder has build_project_context method.
	 */
	public function test_context_builder_has_build_project_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Context_Builder', 'build_project_context' ), 'AI_Context_Builder should have build_project_context method' );
	}

	/**
	 * Test that AI_Context_Builder has build_context_for_llm method.
	 */
	public function test_context_builder_has_build_context_for_llm() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Context_Builder', 'build_context_for_llm' ), 'AI_Context_Builder should have build_context_for_llm method' );
	}

	/**
	 * Test build_post_context returns array for valid post.
	 */
	public function test_build_post_context_returns_array() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_title'  => 'Test Post',
			'post_content' => 'Test content for context building',
		] );
		
		$context = $builder->build_post_context( $post_id );
		
		$this->assertIsArray( $context, 'build_post_context should return an array' );
		$this->assertArrayHasKey( 'post_id', $context, 'Context should have post_id key' );
		$this->assertArrayHasKey( 'post_type', $context, 'Context should have post_type key' );
		$this->assertArrayHasKey( 'post_title', $context, 'Context should have post_title key' );
		$this->assertArrayHasKey( 'content', $context, 'Context should have content key' );
	}

	/**
	 * Test build_post_context returns empty array for invalid post.
	 */
	public function test_build_post_context_returns_empty_for_invalid() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		$context = $builder->build_post_context( 999999 );
		
		$this->assertIsArray( $context, 'build_post_context should return an array' );
		$this->assertEmpty( $context, 'build_post_context should return empty array for invalid post' );
	}

	/**
	 * Test build_post_context includes character data for character post type.
	 */
	public function test_build_post_context_includes_character_data() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		// Create a character post
		$character_id = $this->factory->post->create( [
			'post_type'   => 'storyos_character',
			'post_title'  => 'Test Character',
			'post_status' => 'publish',
		] );
		
		// Add character metadata
		update_post_meta( $character_id, 'character_name', 'Test Character' );
		update_post_meta( $character_id, 'character_arc', 'Hero journey' );
		update_post_meta( $character_id, 'personality', 'Brave and curious' );
		update_post_meta( $character_id, 'motivation', 'Save the world' );
		
		$context = $builder->build_post_context( $character_id );
		
		$this->assertArrayHasKey( 'character_name', $context, 'Character context should have character_name' );
		$this->assertArrayHasKey( 'character_arc', $context, 'Character context should have character_arc' );
		$this->assertEquals( 'Test Character', $context['character_name'], 'Character name should match' );
	}

	/**
	 * Test build_post_context includes scene data for scene post type.
	 */
	public function test_build_post_context_includes_scene_data() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		// Create a scene post
		$scene_id = $this->factory->post->create( [
			'post_type'   => 'storyos_scene',
			'post_title'  => 'Test Scene',
			'post_status' => 'publish',
		] );
		
		// Add scene metadata
		update_post_meta( $scene_id, 'scene_title', 'Test Scene Title' );
		update_post_meta( $scene_id, 'setting', 'Coffee shop' );
		update_post_meta( $scene_id, 'time_of_day', 'Morning' );
		update_post_meta( $scene_id, 'tone', 'Romantic' );
		
		$context = $builder->build_post_context( $scene_id );
		
		$this->assertArrayHasKey( 'scene_title', $context, 'Scene context should have scene_title' );
		$this->assertArrayHasKey( 'setting', $context, 'Scene context should have setting' );
		$this->assertArrayHasKey( 'time_of_day', $context, 'Scene context should have time_of_day' );
		$this->assertArrayHasKey( 'tone', $context, 'Scene context should have tone' );
	}

	/**
	 * Test build_post_context includes project context.
	 */
	public function test_build_post_context_includes_project_context() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_title'  => 'Test Post',
			'post_content' => 'Test content',
		] );
		
		$context = $builder->build_post_context( $post_id );
		
		$this->assertArrayHasKey( 'project', $context, 'Context should include project data' );
	}

	/**
	 * Test build_context_for_llm formats context as string.
	 */
	public function test_build_context_for_llm_formats_as_string() {
		$builder = new \StoryOS\AI\AI_Context_Builder();
		
		$post_id = $this->factory->post->create( [
			'post_type'   => 'post',
			'post_title'  => 'Test Post',
			'post_content' => 'Test content',
		] );
		
		$context = $builder->build_post_context( $post_id );
		$formatted = $builder->build_context_for_llm( $context );
		
		$this->assertIsString( $formatted, 'build_context_for_llm should return a string' );
		$this->assertStringContainsString( 'Test Post', $formatted, 'Formatted context should contain post title' );
	}
}
