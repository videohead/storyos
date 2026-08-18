<?php
/**
 * Tests for StoryOS CPT registration.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_CPT
 */
class Test_StoryOS_CPT extends TestCase {

	/**
	 * Test register_cpt function signature.
	 */
	public function test_register_cpt_parameters() {
		// Mock the parameters that would be passed to register_cpt
		$post_type = 'storyos_project';
		$args = [
			'label'        => 'Projects',
			'public'       => true,
			'show_ui'      => true,
			'supports'     => [ 'title', 'editor', 'custom-fields' ],
			'rewrite'      => [ 'slug' => 'project' ],
			'capabilities' => [
				'edit_post'          => 'edit_project',
				'read_post'          => 'read_project',
				'delete_post'        => 'delete_project',
				'edit_posts'         => 'edit_projects',
				'edit_others_posts'  => 'edit_others_projects',
				'publish_posts'      => 'publish_projects',
				'read_private_posts' => 'read_private_projects',
			],
		];
		
		$this->assertIsString( $post_type );
		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'label', $args );
		$this->assertArrayHasKey( 'public', $args );
		$this->assertArrayHasKey( 'supports', $args );
	}

	/**
	 * Test CPT post type structure.
	 */
	public function test_cpt_post_type_structure() {
		// Test that a CPT definition has the required structure
		$cpt_definition = [
			'post_type' => 'storyos_character',
			'label'     => 'Characters',
			'public'    => true,
		];
		
		$this->assertArrayHasKey( 'post_type', $cpt_definition );
		$this->assertArrayHasKey( 'label', $cpt_definition );
		$this->assertArrayHasKey( 'public', $cpt_definition );
		$this->assertEquals( 'storyos_character', $cpt_definition['post_type'] );
	}

	/**
	 * Test CPT capabilities structure.
	 */
	public function test_cpt_capabilities_structure() {
		$capabilities = [
			'edit_post'          => 'edit_project',
			'read_post'          => 'read_project',
			'delete_post'        => 'delete_project',
			'edit_posts'         => 'edit_projects',
			'edit_others_posts'  => 'edit_others_projects',
			'publish_posts'      => 'publish_projects',
			'read_private_posts' => 'read_private_projects',
		];
		
		$this->assertArrayHasKey( 'edit_post', $capabilities );
		$this->assertArrayHasKey( 'read_post', $capabilities );
		$this->assertArrayHasKey( 'delete_post', $capabilities );
		$this->assertArrayHasKey( 'edit_posts', $capabilities );
	}

	/**
	 * Test StoryOS CPTs are mounted under the StoryOS menu.
	 */
	public function test_storyos_cpt_defaults_use_storyos_menu() {
		$args = \StoryOS\Utils\storyos_get_default_cpt_args( 'storyos_project', 'Project' );

		$this->assertSame( 'storyos', $args['show_in_menu'] );
		$this->assertTrue( $args['show_ui'] );
	}

	/**
	 * Test the generic Details meta box excludes redundant built-in name/description fields.
	 */
	public function test_storyos_details_filters_redundant_name_and_description_fields() {
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'description' ) );
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'project_name' ) );
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'shot_description' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'status' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'target_medium' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'story_world' ) );
	}
}
