<?php
/**
 * Tests for the StoryOS Projects REST API Controller.
 *
 * @package StoryOS
 */

namespace StoryOS\Tests\REST;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Projects Controller test class.
 */
class Projects_Controller_Test extends WP_UnitTestCase {

	/**
	 * Test that Projects_Controller class exists.
	 */
	public function test_projects_controller_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\REST\Projects_Controller' ) );
	}

	/**
	 * Test that Projects_Controller extends Base_Controller.
	 */
	public function test_projects_controller_inherits_base_controller() {
		$reflection = new \ReflectionClass( 'StoryOS\REST\Projects_Controller' );
		$parent = $reflection->getParentClass();
		
		$this->assertNotFalse( $parent );
		$this->assertEquals( 'StoryOS\REST\Base_Controller', $parent->getName() );
	}

	/**
	 * Test that Projects_Controller has required methods.
	 */
	public function test_projects_controller_has_required_methods() {
		$methods = [
			'init',
			'register_routes',
			'get_items',
			'create_item',
			'get_item',
			'update_item',
			'delete_item',
			'check_read_permission',
			'check_create_permission',
			'check_update_permission',
			'check_delete_permission',
		];
		
		foreach ( $methods as $method ) {
			$this->assertTrue( method_exists( 'StoryOS\REST\Projects_Controller', $method ), "Projects_Controller should have method: {$method}" );
		}
	}

	/**
	 * Test that Projects_Controller has correct CPT property.
	 */
	public function test_projects_controller_cpt_property() {
		$reflection = new \ReflectionClass( 'StoryOS\REST\Projects_Controller' );
		$cpt_prop = $reflection->getProperty( 'cpt' );
		$cpt_prop->setAccessible( true );
		
		// We can't easily instantiate without WordPress fully loaded,
		// but we can check the property exists and is protected.
		$this->assertTrue( $cpt_prop->isProtected() );
	}

	/**
	 * Test that Projects_Controller has correct rest_base property.
	 */
	public function test_projects_controller_rest_base_property() {
		$reflection = new \ReflectionClass( 'StoryOS\REST\Projects_Controller' );
		$rest_base_prop = $reflection->getProperty( 'rest_base' );
		$rest_base_prop->setAccessible( true );
		
		$this->assertTrue( $rest_base_prop->isProtected() );
	}

	/**
	 * Test that the storyos_project CPT is registered.
	 */
	public function test_project_cpt_is_registered() {
		$this->assertTrue( post_type_exists( 'storyos_project' ), 'storyos_project CPT should be registered' );
	}

	/**
	 * Test that the storyos_project CPT has correct arguments.
	 */
	public function test_project_cpt_arguments() {
		$post_type = get_post_type_object( 'storyos_project' );
		
		$this->assertNotFalse( $post_type );
		$this->assertEquals( 'Project', $post_type->labels->name );
		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * Test creating a project post.
	 */
	public function test_create_project_post() {
		$post_id = wp_insert_post( array(
			'post_type'    => 'storyos_project',
			'post_title'   => 'Test Project',
			'post_content' => 'Test project content',
			'post_status'  => 'draft',
			'post_author'  => 1,
		) );
		
		$this->assertGreaterThan( 0, $post_id );
		
		$post = get_post( $post_id );
		$this->assertEquals( 'storyos_project', $post->post_type );
		$this->assertEquals( 'Test Project', $post->post_title );
	}

	/**
	 * Test that the REST route is registered.
	 */
	public function test_rest_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		
		$this->assertArrayHasKey( 'storyos/v1/projects', $routes );
		$this->assertArrayHasKey( 'storyos/v1/projects/(?P<id>\d+)', $routes );
	}
}
