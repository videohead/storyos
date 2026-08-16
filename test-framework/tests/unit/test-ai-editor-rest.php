<?php
/**
 * Tests for the AI Editor REST API class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI Editor REST test class.
 */
class AI_Editor_REST_Test extends WP_UnitTestCase {

	public function test_rest_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_Editor_REST' ) );
	}

	public function test_rest_has_expected_endpoints() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'chat' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'analyze' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'generate' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'continuity_check' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'get_agents' ) );
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_Editor_REST', 'health_check' ) );
	}

	public function test_get_agents_returns_rest_response() {
		$rest     = new \StoryOS\AI\AI_Editor_REST();
		$request  = new \WP_REST_Request( 'GET' );
		$response = $rest->get_agents( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
	}

	public function test_permission_check_returns_bool() {
		$rest = new \StoryOS\AI\AI_Editor_REST();
		$this->assertTrue( is_bool( $rest->check_permission() ) );
	}
}
