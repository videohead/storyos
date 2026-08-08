<?php
/**
 * AI Editor REST Controller — handles /storyos/v1/ai/* endpoints.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Editor REST controller class.
 */
class AI_Editor_REST {

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route( 'storyos/v1', '/ai/chat', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'chat' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'prompt'        => [
					'required' => true,
					'type'     => 'string',
				],
				'post_id'       => [
					'required' => false,
					'type'     => 'integer',
				],
				'agent'         => [
					'required' => false,
					'type'     => 'string',
				],
				'action'        => [
					'required' => false,
					'type'     => 'string',
					'default'  => 'chat',
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/ai/analyze', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'analyze' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'prompt'    => [
					'required' => true,
					'type'     => 'string',
				],
				'post_id'   => [
					'required' => false,
					'type'     => 'integer',
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/ai/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'generate' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'prompt'    => [
					'required' => true,
					'type'     => 'string',
				],
				'post_id'   => [
					'required' => false,
					'type'     => 'integer',
				],
				'agent'     => [
					'required' => false,
					'type'     => 'string',
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/ai/continuity', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'continuity_check' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'required' => true,
					'type'     => 'integer',
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/ai/context', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'required' => false,
					'type'     => 'integer',
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/ai/agents', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_agents' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( 'storyos/v1', '/ai/settings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( 'storyos/v1', '/ai/health', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'health_check' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	/**
	 * Chat endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function chat( \WP_REST_Request $request ): \WP_REST_Response {
		$prompt    = $request->get_param( 'prompt' );
		$post_id   = $request->get_param( 'post_id' );
		$agent     = $request->get_param( 'agent' );
		$action    = $request->get_param( 'action' ) ?: 'chat';

		// Build context if post_id provided.
		$context = [];
		if ( $post_id ) {
			$context_builder = new AI_Context_Builder();
			$context = $context_builder->build_post_context( $post_id );
		}

		// Route to agent if not specified.
		if ( empty( $agent ) ) {
			$router = new AI_Agent_Router();
			$route_result = $router->route( $prompt );
			$agent = $route_result['agent'];
		}

		// Get agent skills for this context.
		$agent_skills = new AI_Agent_Skills();
		$post_type = $context['post_type'] ?? '';
		$content = $context['content'] ?? '';
		$skill_content = '';
		$relevant_skills = $agent_skills->detect_relevant_skills( $post_type, $content );
		if ( ! empty( $relevant_skills ) ) {
			$skill_sections = [];
			foreach ( $relevant_skills as $skill_name ) {
				$skill = $agent_skills->get_skill( $skill_name );
				if ( $skill && ! empty( $skill['content'] ) ) {
					$skill_sections[] = $skill['content'];
				}
			}
			$skill_content = implode( "\n\n", $skill_sections );
		}

		// Get the agent's system prompt.
		$maf_bridge = new AI_MAF_Bridge( new AI_LLM_Client() );
		$agent_data = $maf_bridge->get_agent( $agent );
		$system_prompt = $agent_data['system_prompt'] ?? '';
		
		// Add skill content to system prompt.
		if ( ! empty( $skill_content ) ) {
			$system_prompt .= "\n\n" . $skill_content;
		}

		// Call the LLM.
		$llm_client = new AI_LLM_Client();
		$result = $llm_client->chat( $prompt, [
			'system_prompt' => $system_prompt,
			'context'       => $context,
		] );

		return new \WP_REST_Response( [
			'success' => empty( $result['error'] ),
			'data'    => $result['content'] ?? '',
			'agent'   => $agent,
			'backend' => $result['backend'] ?? 'unknown',
			'error'   => $result['error'] ?? null,
		], empty( $result['error'] ) ? 200 : 500 );
	}

	/**
	 * Analyze endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function analyze( \WP_REST_Request $request ): \WP_REST_Response {
		$prompt  = $request->get_param( 'prompt' );
		$post_id = $request->get_param( 'post_id' );

		$context_builder = new AI_Context_Builder();
		$context = $post_id ? $context_builder->build_post_context( $post_id ) : [];

		$analysis_prompt = "Analyze the following content and provide detailed feedback:\n\n{$prompt}\n\n";
		if ( ! empty( $context ) ) {
			$analysis_prompt .= "\nContext:\n" . $context_builder->build_context_for_llm( $context );
		}

		$llm_client = new AI_LLM_Client();
		$result = $llm_client->chat( $analysis_prompt, [
			'system_prompt' => 'You are an expert film and content analyst. Provide detailed, constructive analysis.',
			'context'       => $context,
		] );

		return new \WP_REST_Response( [
			'success' => empty( $result['error'] ),
			'data'    => $result['content'] ?? '',
			'error'   => $result['error'] ?? null,
		], empty( $result['error'] ) ? 200 : 500 );
	}

	/**
	 * Generate endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function generate( \WP_REST_Request $request ): \WP_REST_Response {
		$prompt  = $request->get_param( 'prompt' );
		$post_id = $request->get_param( 'post_id' );
		$agent   = $request->get_param( 'agent' );

		$context_builder = new AI_Context_Builder();
		$context = $post_id ? $context_builder->build_post_context( $post_id ) : [];

		// Route to agent if not specified.
		if ( empty( $agent ) ) {
			$router = new AI_Agent_Router();
			$route_result = $router->route( $prompt );
			$agent = $route_result['agent'];
		}

		$maf_bridge = new AI_MAF_Bridge( new AI_LLM_Client() );
		$result = $maf_bridge->run_agent( $agent, $prompt, $context );

		return new \WP_REST_Response( [
			'success' => empty( $result['error'] ),
			'data'    => $result['content'] ?? '',
			'agent'   => $agent,
			'error'   => $result['error'] ?? null,
		], empty( $result['error'] ) ? 200 : 500 );
	}

	/**
	 * Continuity check endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function continuity_check( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = $request->get_param( 'post_id' );

		$context_builder = new AI_Context_Builder();
		$context = $context_builder->build_post_context( $post_id );

		$continuity_prompt = "Check the following scene for continuity errors with the overall story:\n\n";
		if ( isset( $context['scene_content'] ) ) {
			$continuity_prompt .= "Scene: {$context['scene_content']}\n\n";
		}
		$continuity_prompt .= "Check for:\n1. Character consistency\n2. Timeline errors\n3. Location inconsistencies\n4. Plot holes\n\n";
		if ( isset( $context['project_logline'] ) ) {
			$continuity_prompt .= "Project Logline: {$context['project_logline']}\n\n";
		}

		$llm_client = new AI_LLM_Client();
		$result = $llm_client->chat( $continuity_prompt, [
			'system_prompt' => 'You are a continuity expert. Identify any inconsistencies in the story.',
			'context'       => $context,
		] );

		return new \WP_REST_Response( [
			'success' => empty( $result['error'] ),
			'data'    => $result['content'] ?? '',
			'error'   => $result['error'] ?? null,
		], empty( $result['error'] ) ? 200 : 500 );
	}

	/**
	 * Get context endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function get_context( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => 'post_id required',
			], 400 );
		}

		$context_builder = new AI_Context_Builder();
		$context = $context_builder->build_post_context( $post_id );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $context,
		], 200 );
	}

	/**
	 * Get agents endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function get_agents( \WP_REST_Request $request ): \WP_REST_Response {
		$maf_bridge = new AI_MAF_Bridge( new AI_LLM_Client() );
		$agents = $maf_bridge->get_enabled_agents();

		// Format for frontend.
		$formatted = [];
		foreach ( $agents as $name => $agent ) {
			$formatted[] = [
				'name'        => $name,
				'description' => $agent['description'] ?? '',
				'department'  => $agent['department'] ?? '',
			];
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $formatted,
		], 200 );
	}

	/**
	 * Get settings endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'success' => true,
			'data'    => [
				'backend'       => get_option( 'storyos_ai_backend', 'local' ),
				'url'           => get_option( 'storyos_ai_url', 'http://localhost:11434' ),
				'model'         => get_option( 'storyos_ai_model', 'qwen3.6:35b-a3b-q4_K_M' ),
				'max_tokens'    => get_option( 'storyos_ai_max_tokens', 4096 ),
				'temperature'   => get_option( 'storyos_ai_temperature', 0.7 ),
				'fallback_enabled' => get_option( 'storyos_ai_fallback_enabled', true ),
				'rate_limit'    => get_option( 'storyos_ai_rate_limit', 10 ),
				'cache_ttl'     => get_option( 'storyos_ai_cache_ttl', 3600 ),
			],
		], 200 );
	}

	/**
	 * Health check endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response REST response.
	 */
	public function health_check( \WP_REST_Request $request ): \WP_REST_Response {
		$llm_client = new AI_LLM_Client();
		$health = $llm_client->health_check();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $health,
		], 200 );
	}

	/**
	 * Check permission for AI endpoints.
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
	}
}
