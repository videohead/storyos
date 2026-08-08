<?php
/**
 * Tests for the StoryOS Character CPT.
 *
 * @package StoryOS
 */

namespace StoryOS\Tests\CPT;

use WP_UnitTestCase;

/**
 * Character CPT test class.
 */
class Character_Test extends WP_UnitTestCase {

	/**
	 * Test that Character CPT is registered.
	 */
	public function test_character_cpt_is_registered() {
		$this->assertTrue( post_type_exists( 'storyos_character' ), 'storyos_character CPT should be registered' );
	}

	/**
	 * Test that Character CPT has correct arguments.
	 */
	public function test_character_cpt_arguments() {
		$post_type = get_post_type_object( 'storyos_character' );
		
		$this->assertNotFalse( $post_type );
		$this->assertEquals( 'Character', $post_type->labels->name );
		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * Test creating a character post.
	 */
	public function test_create_character_post() {
		$post_id = wp_insert_post( array(
			'post_type'   => 'storyos_character',
			'post_title'  => 'Test Character',
			'post_status' => 'draft',
			'post_author' => 1,
		) );
		
		$this->assertGreaterThan( 0, $post_id );
		
		$post = get_post( $post_id );
		$this->assertEquals( 'storyos_character', $post->post_type );
		$this->assertEquals( 'Test Character', $post->post_title );
	}

	/**
	 * Test that Character class exists.
	 */
	public function test_character_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\CPT\Character' ) );
	}
}
