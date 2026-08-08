<?php
/**
 * Tests for the AI Editor REST API class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * AI Editor REST test class.
 */
class AI_Editor_REST_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_Editor_REST class exists.
	 */
	public function test_rest_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Editor_REST' ), 'AI_Editor_REST class should exist' );
	}

	/**
	 * Test that AI_Editor_REST has register_routes method.
	 */
	public function test_rest_has_register_routes() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'register_routes' ), 'AI_Editor_REST should have register_routes method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_chat method.
	 */
	public function test_rest_has_handle_chat() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_chat' ), 'AI_Editor_REST should have handle_chat method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_analyze method.
	 */
	public function test_rest_has_handle_analyze() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_analyze' ), 'AI_Editor_REST should have handle_analyze method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_generate method.
	 */
	public function test_rest_has_handle_generate() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_generate' ), 'AI_Editor_REST should have handle_generate method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_continuity method.
	 */
	public function test_rest_has_handle_continuity() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_continuity' ), 'AI_Editor_REST should have handle_continuity method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_get_context method.
	 */
	public function test_rest_has_handle_get_context() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_get_context' ), 'AI_Editor_REST should have handle_get_context method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_list_agents method.
	 */
	public function test_rest_has_handle_list_agents() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_list_agents' ), 'AI_Editor_REST should have handle_list_agents method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_get_settings method.
	 */
	public function test_rest_has_handle_get_settings() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_get_settings' ), 'AI_Editor_REST should have handle_get_settings method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_update_settings method.
	 */
	public function test_rest_has_handle_update_settings() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_update_settings' ), 'AI_Editor_REST should have handle_update_settings method' );
	}

	/**
	 * Test that AI_Editor_REST has handle_health_check method.
	 */
	public function test_rest_has_handle_health_check() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'handle_health_check' ), 'AI_Editor_REST should have handle_health_check method' );
	}

	/**
	 * Test that AI_Editor_REST has get_rest_base method.
	 */
	public function test_rest_has_get_rest_base() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'get_rest_base' ), 'AI_Editor_REST should have get_rest_base method' );
	}

	/**
	 * Test that AI_Editor_REST has permission_check method.
	 */
	public function test_rest_has_permission_check() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'permission_check' ), 'AI_Editor_REST should have permission_check method' );
	}

	/**
	 * Test REST base is correct.
	 */
	public function test_rest_base_is_correct() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$base = $rest->get_rest_base();
		
		$this->assertEquals( '/ai', $base, 'REST base should be /ai' );
	}

	/**
	 * Test handle_chat returns WP_REST_Response.
	 */
	public function test_handle_chat_returns_response() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$request = new WP_REST_Request( 'POST' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body_params( [
			'prompt' => 'Test prompt',
		] );
		
		$response = $rest->handle_chat( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response, 'handle_chat should return WP_REST_Response' );
	}

	/**
	 * Test handle_health_check returns status.
	 */
	public function test_handle_health_check_returns_status() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$response = $rest->handle_health_check();
		
		$this->assertInstanceOf( 'WP_REST_Response', $response, 'handle_health_check should return WP_REST_Response' );
		$status = $response->get_data();
		$this->assertArrayHasKey( 'status', $status, 'Health check should return status key' );
	}

	/**
	 * Test handle_list_agents returns array.
	 */
	public function test_handle_list_agents_returns_response() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$response = $rest->handle_list_agents();
		
		$this->assertInstanceOf( 'WP_REST_Response', $response, 'handle_list_agents should return WP_REST_Response' );
	}

	/**
	 * Test handle_get_settings returns settings.
	 */
	public function test_handle_get_settings_returns_settings() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$response = $rest->handle_get_settings();
		
		$this->assertInstanceOf( 'WP_REST_Response', $response, 'handle_get_settings should return WP_REST_Response' );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'backend', $data, 'Settings should have backend key' );
		$this->assertArrayHasKey( 'model', $data, 'Settings should have model key' );
	}

	/**
	 * Test permission_check returns WP_Error or true.
	 */
	public function test_permission_check_returns_boolean_or_error() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		
		$result = $rest->permission_check();
		
		$this->assertTrue( is_bool( $result ) || is_a( $result, 'WP_Error' ), 'permission_check should return bool or WP_Error' );
	}
}
