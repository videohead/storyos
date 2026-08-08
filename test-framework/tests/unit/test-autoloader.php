<?php
/**
 * Tests for the StoryOS autoloader.
 *
 * @package StoryOS
 */

namespace StoryOS\Tests;

use WP_UnitTestCase;

/**
 * Autoloader test class.
 */
class Autoloader_Test extends WP_UnitTestCase {

	/**
	 * Test that the autoloader loads Base_Controller.
	 */
	public function test_autoloader_loads_base_controller() {
		$this->assertTrue( class_exists( 'StoryOS\REST\Base_Controller' ), 'Base_Controller should be loaded by autoloader' );
	}

	/**
	 * Test that the autoloader loads Projects_Controller.
	 */
	public function test_autoloader_loads_projects_controller() {
		$this->assertTrue( class_exists( 'StoryOS\REST\Projects_Controller' ), 'Projects_Controller should be loaded by autoloader' );
	}

	/**
	 * Test that the autoloader loads a CPT class.
	 */
	public function test_autoloader_loads_cpt_class() {
		$this->assertTrue( class_exists( 'StoryOS\CPT\Project' ), 'Project CPT class should be loaded by autoloader' );
	}

	/**
	 * Test that the autoloader loads Character CPT.
	 */
	public function test_autoloader_loads_character_cpt() {
		$this->assertTrue( class_exists( 'StoryOS\CPT\Character' ), 'Character CPT class should be loaded by autoloader' );
	}

	/**
	 * Test that the autoloader does not load unrelated classes.
	 */
	public function test_autoloader_ignores_unrelated_classes() {
		$this->assertFalse( class_exists( 'Unrelated\Class' ), 'Unrelated classes should not be loaded' );
	}

	/**
	 * Test that Base_Controller is the correct parent class.
	 */
	public function test_projects_controller_inherits_base_controller() {
		$this->assertTrue( class_exists( 'StoryOS\REST\Base_Controller' ) );
		$this->assertTrue( class_exists( 'StoryOS\REST\Projects_Controller' ) );
		
		$reflection = new \ReflectionClass( 'StoryOS\REST\Projects_Controller' );
		$parent = $reflection->getParentClass();
		
		$this->assertNotFalse( $parent, 'Projects_Controller should have a parent class' );
		$this->assertEquals( 'StoryOS\REST\Base_Controller', $parent->getName(), 'Projects_Controller should extend Base_Controller' );
	}

	/**
	 * Test that the StoryOS plugin file exists.
	 */
	public function test_plugin_file_exists() {
		$plugin_file = STORYOS_PLUGIN_PATH . '/storyos.php';
		$this->assertFileExists( $plugin_file, 'StoryOS plugin file should exist' );
	}

	/**
	 * Test that the includes directory exists.
	 */
	public function test_includes_directory_exists() {
		$includes_dir = STORYOS_PLUGIN_PATH . '/includes';
		$this->assertDirectoryExists( $includes_dir, 'StoryOS includes directory should exist' );
	}

	/**
	 * Test that the rest-api directory exists.
	 */
	public function test_rest_api_directory_exists() {
		$rest_dir = STORYOS_PLUGIN_PATH . '/includes/rest-api';
		$this->assertDirectoryExists( $rest_dir, 'REST API directory should exist' );
	}

	/**
	 * Test that the cpts directory exists.
	 */
	public function test_cpts_directory_exists() {
		$cpts_dir = STORYOS_PLUGIN_PATH . '/includes/cpts';
		$this->assertDirectoryExists( $cpts_dir, 'CPTs directory should exist' );
	}
}
