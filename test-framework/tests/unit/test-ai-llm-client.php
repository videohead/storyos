<?php
/**
 * Tests for the AI LLM Client class.
 *
 * @package StoryOS\Tests\AI
 */

namespace StoryOS\Tests\AI;

use WP_UnitTestCase;

/**
 * AI LLM Client test class.
 */
class AI_LLM_Client_Test extends WP_UnitTestCase {

	/**
	 * Test that AI_LLM_Client class exists.
	 */
	public function test_llm_client_class_exists() {
		$this->assertTrue( class_exists( 'StoryOS\AI\AI_LLM_Client' ), 'AI_LLM_Client class should exist' );
	}

	/**
	 * Test that AI_LLM_Client has chat method.
	 */
	public function test_llm_client_has_chat_method() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'chat' ), 'AI_LLM_Client should have chat method' );
	}

	/**
	 * Test that AI_LLM_Client has call_backend method.
	 */
	public function test_llm_client_has_call_backend() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'call_backend' ), 'AI_LLM_Client should have call_backend method' );
	}

	/**
	 * Test that AI_LLM_Client has call_local_llm method.
	 */
	public function test_llm_client_has_call_local_llm() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'call_local_llm' ), 'AI_LLM_Client should have call_local_llm method' );
	}

	/**
	 * Test that AI_LLM_Client has call_openai method.
	 */
	public function test_llm_client_has_call_openai() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'call_openai' ), 'AI_LLM_Client should have call_openai method' );
	}

	/**
	 * Test that AI_LLM_Client has call_anthropic method.
	 */
	public function test_llm_client_has_call_anthropic() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'call_anthropic' ), 'AI_LLM_Client should have call_anthropic method' );
	}

	/**
	 * Test that AI_LLM_Client has health_check method.
	 */
	public function test_llm_client_has_health_check() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'health_check' ), 'AI_LLM_Client should have health_check method' );
	}

	/**
	 * Test that AI_LLM_Client has check_rate_limit method.
	 */
	public function test_llm_client_has_check_rate_limit() {
		$this->assertTrue( method_exists( 'StoryOS\AI\AI_LLM_Client', 'check_rate_limit' ), 'AI_LLM_Client should have check_rate_limit method' );
	}

	/**
	 * Test that AI_LLM_Client has rate limiting properties.
	 */
	public function test_llm_client_has_rate_limiting_properties() {
		$reflection = new \ReflectionClass( 'StoryOS\AI\AI_LLM_Client' );
		
		$this->assertTrue( $reflection->hasProperty( 'response_cache' ), 'Should have response_cache property' );
		$this->assertTrue( $reflection->hasProperty( 'request_log' ), 'Should have request_log property' );
	}

	/**
	 * Test chat method returns array structure.
	 */
	public function test_chat_returns_array() {
		$client = new \StoryOS\AI\AI_LLM_Client();
		
		// Set up test options
		$options = [
			'backend' => 'local',
			'model' => 'test-model',
			'max_tokens' => 100,
			'temperature' => 0.7,
		];
		
		$result = $client->chat( 'test prompt', $options );
		
		$this->assertIsArray( $result, 'Chat should return an array' );
		$this->assertArrayHasKey( 'content', $result, 'Result should have content key' );
		$this->assertArrayHasKey( 'backend', $result, 'Result should have backend key' );
		$this->assertArrayHasKey( 'tokens', $result, 'Result should have tokens key' );
	}

	/**
	 * Test chat method with invalid backend.
	 */
	public function test_chat_invalid_backend() {
		$client = new \StoryOS\AI\AI_LLM_Client();
		
		$options = [
			'backend' => 'invalid_backend',
			'model' => 'test-model',
			'max_tokens' => 100,
			'temperature' => 0.7,
		];
		
		$result = $client->chat( 'test prompt', $options );
		
		$this->assertArrayHasKey( 'error', $result, 'Invalid backend should return error key' );
		$this->assertEquals( 'unknown_backend', $result['error'], 'Should return unknown_backend error' );
	}

	/**
	 * Test that LLM client respects rate limit settings.
	 */
	public function test_llm_client_respects_rate_limit_setting() {
		// Set a very low rate limit
		update_option( 'storyos_ai_rate_limit', 0 );
		
		$client = new \StoryOS\AI\AI_LLM_Client();
		
		$options = [
			'backend' => 'local',
			'model' => 'test-model',
			'max_tokens' => 100,
			'temperature' => 0.7,
		];
		
		$result = $client->chat( 'test prompt', $options );
		
		$this->assertArrayHasKey( 'error', $result, 'Zero rate limit should return error' );
		$this->assertEquals( 'rate_limit_exceeded', $result['error'], 'Should return rate_limit_exceeded error' );
		
		// Clean up
		delete_option( 'storyos_ai_rate_limit' );
	}
}
