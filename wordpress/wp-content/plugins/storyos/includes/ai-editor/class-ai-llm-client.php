<?php
/**
 * AI LLM Client — handles connections to local and cloud LLM backends.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LLM Client class.
 */
class AI_LLM_Client {

	/**
	 * Cache for LLM responses.
	 *
	 * @var array
	 */
	private $response_cache = [];

	/**
	 * Request timestamp tracking for rate limiting.
	 *
	 * @var array
	 */
	private $request_log = [];

	/**
	 * Send a chat request to the configured LLM backend.
	 *
	 * @param string $prompt The user prompt.
	 * @param array  $options Optional parameters (model, max_tokens, temperature, system_prompt, context).
	 * @return array {
	 *     @type string $content The LLM response content.
	 *     @type string $backend Which backend was used.
	 *     @type int    $tokens Approximate token count.
	 * }
	 */
	public function chat( string $prompt, array $options = [] ): array {
		$backend   = $options['backend'] ?? get_option( 'storyos_ai_backend', 'local' );
		$model     = $options['model'] ?? get_option( 'storyos_ai_model', 'qwen3.6:35b-a3b-q4_K_M' );
		$max_tokens = $options['max_tokens'] ?? get_option( 'storyos_ai_max_tokens', 4096 );
		$temperature = $options['temperature'] ?? get_option( 'storyos_ai_temperature', 0.7 );
		$system_prompt = $options['system_prompt'] ?? '';
		$context   = $options['context'] ?? [];

		// Check rate limit.
		if ( ! $this->check_rate_limit() ) {
			return [
				'content'  => 'Rate limit exceeded. Please try again later.',
				'backend'  => $backend,
				'tokens'   => 0,
				'error'    => 'rate_limit_exceeded',
			];
		}

		// Check cache (both in-memory and transient).
		$cache_key = md5( $prompt . $model . $system_prompt );
		$cache_ttl = get_option( 'storyos_ai_cache_ttl', 3600 );

		// First check in-memory cache.
		if ( isset( $this->response_cache[ $cache_key ] ) ) {
			$cached = $this->response_cache[ $cache_key ];
			if ( time() - $cached['timestamp'] < $cache_ttl ) {
				return [
					'content' => $cached['content'],
					'backend' => $cached['backend'],
					'tokens'  => $cached['tokens'],
				];
			}
			unset( $this->response_cache[ $cache_key ] );
		}

		// Then check WordPress transient cache (persists across requests).
		$transient_key = 'storyos_ai_' . $cache_key;
		$cached_content = get_transient( $transient_key );
		if ( false !== $cached_content && is_array( $cached_content ) ) {
			// Validate transient data.
			if ( isset( $cached_content['content'], $cached_content['backend'], $cached_content['tokens'] ) ) {
				// Update in-memory cache with transient data.
				$this->response_cache[ $cache_key ] = [
					'content'   => $cached_content['content'],
					'backend'   => $cached_content['backend'],
					'tokens'    => $cached_content['tokens'],
					'timestamp' => time(),
				];
				return [
					'content' => $cached_content['content'],
					'backend' => $cached_content['backend'],
					'tokens'  => $cached_content['tokens'],
				];
			}
		}

		// Try primary backend.
		$result = $this->call_backend( $backend, $prompt, $model, $max_tokens, $temperature, $system_prompt, $context );

		if ( $result && empty( $result['error'] ) ) {
			// Cache the result in both in-memory and transient storage.
			$cache_data = [
				'content'   => $result['content'],
				'backend'   => $result['backend'],
				'tokens'    => $result['tokens'] ?? 0,
			];
			$this->response_cache[ $cache_key ] = [
				'content'   => $result['content'],
				'backend'   => $result['backend'],
				'tokens'    => $result['tokens'] ?? 0,
				'timestamp' => time(),
			];
			// Store in WordPress transient for persistence across requests.
			set_transient( $transient_key, $cache_data, $cache_ttl );
			return $result;
		}

		// Try fallback if available.
		$fallback_enabled = get_option( 'storyos_ai_fallback_enabled', true );
		if ( $fallback_enabled && 'dual' !== $backend ) {
			$fallback_backend = get_option( 'storyos_ai_fallback_backend', 'openai' );
			$result = $this->call_backend( $fallback_backend, $prompt, $model, $max_tokens, $temperature, $system_prompt, $context, $this->fallback_api_key() );
			if ( $result && empty( $result['error'] ) ) {
				return $result;
			}
		}

		// Dual mode: try both.
		if ( 'dual' === $backend ) {
			$fallback_backend = get_option( 'storyos_ai_fallback_backend', 'openai' );
			$result = $this->call_backend( $fallback_backend, $prompt, $model, $max_tokens, $temperature, $system_prompt, $context, $this->fallback_api_key() );
			if ( $result && empty( $result['error'] ) ) {
				return $result;
			}
		}

		// All backends failed.
		return [
			'content' => 'Unable to reach any LLM backend. Please check your configuration.',
			'backend' => $backend,
			'tokens'  => 0,
			'error'   => 'all_backends_failed',
		];
	}

	/**
	 * Call a specific LLM backend.
	 *
	 * @param string $backend Backend type (openai_compatible, openai, anthropic).
	 * @param string $prompt The prompt.
	 * @param string $model Model name.
	 * @param int    $max_tokens Max tokens.
	 * @param float  $temperature Temperature.
	 * @param string $system_prompt System prompt.
	 * @param array  $context Additional context.
	 * @return array|false Response array or false on failure.
	 */
	private function call_backend( string $backend, string $prompt, string $model, int $max_tokens, float $temperature, string $system_prompt, array $context, string $api_key = '' ) {
		switch ( $backend ) {
			case 'local':
			case 'openai_compatible':
				return $this->call_openai_compatible( $prompt, $model, $max_tokens, $temperature, $system_prompt, $context, $api_key, $backend );
			case 'openai':
				return $this->call_openai( $prompt, $model, $max_tokens, $temperature, $system_prompt, $context, $api_key );
			case 'anthropic':
				return $this->call_anthropic( $prompt, $model, $max_tokens, $temperature, $system_prompt, $context, $api_key );
			default:
				return [
					'content' => "Unknown backend: {$backend}",
					'error'   => 'unknown_backend',
				];
		}
	}

	/**
	 * Call an OpenAI-compatible endpoint, including local LLMs and BYOK services.
	 *
	 * @param string $prompt The prompt.
	 * @param string $model Model name.
	 * @param int    $max_tokens Max tokens.
	 * @param float  $temperature Temperature.
	 * @param string $system_prompt System prompt.
	 * @param array  $context Additional context.
	 * @return array Response array.
	 */
	private function call_openai_compatible( string $prompt, string $model, int $max_tokens, float $temperature, string $system_prompt, array $context, string $api_key = '', string $backend = 'openai_compatible' ): array {
		$url = rtrim( get_option( 'storyos_ai_url', 'http://localhost:11434/v1' ), '/' );
		$url .= ( str_ends_with( $url, '/v1' ) ? '' : '/v1' ) . '/chat/completions';
		$api_key = '' !== $api_key ? $api_key : $this->primary_api_key();

		$messages = [];
		if ( ! empty( $system_prompt ) ) {
			$messages[] = [ 'role' => 'system', 'content' => $system_prompt ];
		}

		// Add context if provided.
		if ( ! empty( $context ) ) {
			$context_text = $this->format_context_for_llm( $context );
			if ( ! empty( $context_text ) ) {
				$messages[] = [ 'role' => 'system', 'content' => $context_text ];
			}
		}

		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . ( '' !== $api_key ? $api_key : 'local-dev-key' ),
			],
			'body'    => wp_json_encode( [
				'model'       => $model,
				'messages'    => $messages,
				'max_tokens'  => $max_tokens,
				'temperature' => $temperature,
				'tool_choice' => 'none',
			] ),
			'timeout' => 120,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'content' => "LLM connection error: " . $response->get_error_message(),
				'backend' => $backend,
				'error'   => 'connection_error',
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return [
				'content' => 'Invalid response from OpenAI-compatible LLM.',
				'backend' => $backend,
				'error'   => 'invalid_response',
			];
		}

		$this->log_request( $backend );

		return [
			'content' => $data['choices'][0]['message']['content'],
			'backend' => $backend,
			'tokens'  => $data['usage']['total_tokens'] ?? 0,
		];
	}

	/**
	 * Call OpenAI API.
	 *
	 * @param string $prompt The prompt.
	 * @param string $model Model name.
	 * @param int    $max_tokens Max tokens.
	 * @param float  $temperature Temperature.
	 * @param string $system_prompt System prompt.
	 * @param array  $context Additional context.
	 * @return array Response array.
	 */
	private function call_openai( string $prompt, string $model, int $max_tokens, float $temperature, string $system_prompt, array $context, string $api_key = '' ): array {
		$api_key = '' !== $api_key ? $api_key : $this->primary_api_key();
		if ( empty( $api_key ) ) {
			return [
				'content' => 'No OpenAI API key configured.',
				'backend' => 'openai',
				'error'   => 'no_api_key',
			];
		}

		$url = 'https://api.openai.com/v1/chat/completions';

		$messages = [];
		if ( ! empty( $system_prompt ) ) {
			$messages[] = [ 'role' => 'system', 'content' => $system_prompt ];
		}

		if ( ! empty( $context ) ) {
			$context_text = $this->format_context_for_llm( $context );
			if ( ! empty( $context_text ) ) {
				$messages[] = [ 'role' => 'system', 'content' => $context_text ];
			}
		}

		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body'    => wp_json_encode( [
				'model'       => $model,
				'messages'    => $messages,
				'max_tokens'  => $max_tokens,
				'temperature' => $temperature,
			] ),
			'timeout' => 120,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'content' => "OpenAI API error: " . $response->get_error_message(),
				'backend' => 'openai',
				'error'   => 'api_error',
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return [
				'content' => 'Invalid response from OpenAI.',
				'backend' => 'openai',
				'error'   => 'invalid_response',
			];
		}

		$this->log_request( 'openai' );

		return [
			'content' => $data['choices'][0]['message']['content'],
			'backend' => 'openai',
			'tokens'  => $data['usage']['total_tokens'] ?? 0,
		];
	}

	/**
	 * Call Anthropic API.
	 *
	 * @param string $prompt The prompt.
	 * @param string $model Model name.
	 * @param int    $max_tokens Max tokens.
	 * @param float  $temperature Temperature.
	 * @param string $system_prompt System prompt.
	 * @param array  $context Additional context.
	 * @return array Response array.
	 */
	private function call_anthropic( string $prompt, string $model, int $max_tokens, float $temperature, string $system_prompt, array $context, string $api_key = '' ): array {
		$api_key = '' !== $api_key ? $api_key : $this->primary_api_key();
		if ( empty( $api_key ) ) {
			return [
				'content' => 'No Anthropic API key configured.',
				'backend' => 'anthropic',
				'error'   => 'no_api_key',
			];
		}

		$url = 'https://api.anthropic.com/v1/messages';

		$messages = [ [ 'role' => 'user', 'content' => $prompt ] ];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => wp_json_encode( [
				'model'         => $model,
				'messages'      => $messages,
				'max_tokens'    => $max_tokens,
				'temperature'   => $temperature,
				'system'        => $system_prompt,
			] ),
			'timeout' => 120,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'content' => "Anthropic API error: " . $response->get_error_message(),
				'backend' => 'anthropic',
				'error'   => 'api_error',
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['content'][0]['text'] ) ) {
			return [
				'content' => 'Invalid response from Anthropic.',
				'backend' => 'anthropic',
				'error'   => 'invalid_response',
			];
		}

		$this->log_request( 'anthropic' );

		return [
			'content' => $data['content'][0]['text'],
			'backend' => 'anthropic',
			'tokens'  => $data['usage']['input_tokens'] + $data['usage']['output_tokens'] ?? 0,
		];
	}

	/**
	 * Format context array for LLM consumption.
	 *
	 * @param array $context Context data.
	 * @return string Formatted context string.
	 */
	private function format_context_for_llm( array $context ): string {
		$output = "Story Graph Context:\n\n";
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$output .= "## {$key}\n";
				$output .= $this->format_array_recursive( $value, 2 ) . "\n\n";
			} else {
				$output .= "{$key}: {$value}\n\n";
			}
		}
		return $output;
	}

	private function primary_api_key(): string {
		return defined( 'STORYOS_AI_API_KEY' ) ? STORYOS_AI_API_KEY : (string) get_option( 'storyos_ai_api_key', '' );
	}

	private function fallback_api_key(): string {
		return defined( 'STORYOS_AI_FALLBACK_API_KEY' ) ? STORYOS_AI_FALLBACK_API_KEY : (string) get_option( 'storyos_ai_fallback_api_key', '' );
	}

	/**
	 * Recursively format an array for LLM consumption.
	 *
	 * @param array  $array The array.
	 * @param int    $depth Current depth.
	 * @return string Formatted string.
	 */
	private function format_array_recursive( array $array, int $depth = 0 ): string {
		$output = '';
		$indent = str_repeat('  ', $depth);
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$output .= "{$indent}{$key}:\n";
				$output .= $this->format_array_recursive( $value, $depth + 1 );
			} else {
				$output .= "{$indent}{$key}: {$value}\n";
			}
		}
		return $output;
	}

	/**
	 * Check rate limit.
	 *
	 * @return bool True if within limit.
	 */
	private function check_rate_limit(): bool {
		$limit = get_option( 'storyos_ai_rate_limit', 10 );
		$now = time();
		$window = 60; // 1 minute window.

		// Clean old entries.
		$this->request_log = array_filter( $this->request_log, function( $timestamp ) use ( $now, $window ) {
			return $now - $timestamp < $window;
		} );

		if ( count( $this->request_log ) >= $limit ) {
			return false;
		}

		return true;
	}

	/**
	 * Log a request for rate limiting.
	 *
	 * @param string $backend Backend used.
	 * @return void
	 */
	private function log_request( string $backend ): void {
		$this->request_log[] = time();
	}

	/**
	 * Check health of configured LLM backend.
	 *
	 * @return array Health status.
	 */
	public function health_check(): array {
		$backend = get_option( 'storyos_ai_backend', 'openai_compatible' );
		if ( 'anthropic' === $backend ) {
			return [
				'healthy' => '' !== $this->primary_api_key(),
				'backend' => $backend,
				'error'   => '' === $this->primary_api_key() ? 'No Anthropic API key configured.' : '',
			];
		}

		if ( 'openai' === $backend ) {
			$url = 'https://api.openai.com/v1/models';
		} else {
			$url = rtrim( get_option( 'storyos_ai_url', 'http://localhost:11434/v1' ), '/' );
			$url .= ( str_ends_with( $url, '/v1' ) ? '' : '/v1' ) . '/models';
		}

		$args = [
			'method'  => 'GET',
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . ( '' !== $this->primary_api_key() ? $this->primary_api_key() : 'local-dev-key' ),
			],
			'timeout' => 5,
		];

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'healthy' => false,
				'backend' => $backend,
				'error'   => $response->get_error_message(),
			];
		}

		$status = wp_remote_retrieve_response_code( $response );

		return [
			'healthy' => ( 200 === $status ),
			'backend' => $backend,
			'url'     => $url,
			'status'  => $status,
		];
	}
}
