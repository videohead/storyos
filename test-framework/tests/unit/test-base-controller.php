<?php
/**
 * Tests for the StoryOS Base REST Controller.
 *
 * @package StoryOS
 */

namespace StoryOS\Tests\REST;

use WP_UnitTestCase;

/**
 * Base Controller test class.
 */
class Base_Controller_Test extends WP_UnitTestCase {

	/**
	 * Test that Base_Controller class exists.
	 */
	public function test_base_controller_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\REST\Base_Controller' ) );
	}

	/**
	 * Test that Base_Controller has required methods.
	 */
	public function test_base_controller_has_required_methods() {
		$this->assertTrue( method_exists( 'StoryOS\REST\Base_Controller', 'init' ) );
		$this->assertTrue( method_exists( 'StoryOS\REST\Base_Controller', 'register_routes' ) );
		$this->assertTrue( method_exists( 'StoryOS\REST\Base_Controller', 'get_items' ) );
		$this->assertTrue( method_exists( 'StoryOS\REST\Base_Controller', 'get_item' ) );
		$this->assertTrue( method_exists( 'StoryOS\REST\Base_Controller', 'check_read_permission' ) );
	}

	/**
	 * Test that Base_Controller has protected properties.
	 */
	public function test_base_controller_has_required_properties() {
		$reflection = new \ReflectionClass( 'StoryOS\REST\Base_Controller' );
		
		$properties = [
			'cpt',
			'rest_base',
			'namespace',
		];
		
		foreach ( $properties as $prop ) {
			$this->assertTrue( $reflection->hasProperty( $prop ), "Base_Controller should have property: {$prop}" );
		}
	}
}
