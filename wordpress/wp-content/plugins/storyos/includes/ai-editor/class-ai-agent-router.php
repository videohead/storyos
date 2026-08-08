<?php
/**
 * AI Agent Router — routes user requests to appropriate advisors.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent Router class.
 */
class AI_Agent_Router {

	/**
	 * Orchestrator URL for multi-agent workflows.
	 *
	 * @var string
	 */
	private $orchestrator_url;

	/**
	 * Whether to use hybrid routing (WordPress direct vs orchestrator).
	 *
	 * @var bool
	 */
	private $hybrid_enabled;

	/**
	 * Complexity threshold for routing to orchestrator.
	 *
	 * @var int
	 */
	private $complexity_threshold;

	/**
	 * Keyword-to-agent mappings.
	 *
	 * @var array
	 */
	private $keyword_mappings = [
		'story' => [
			'keywords' => [
				'character', 'dialogue', 'plot', 'scene', 'story', 'narrative',
				'arc', 'conflict', 'resolution', 'protagonist', 'antagonist',
				'write', 'draft', 'rewrite', 'edit', 'continue',
			],
			'agents' => [ 'story', 'script' ],
		],
		'prompt' => [
			'keywords' => [
				'prompt', 'image', 'generate', 'visual', 'scene', 'shot',
				'composition', 'lighting', 'camera', 'style', 'look',
				'concept', 'art', 'illustration', 'render',
			],
			'agents' => [ 'prompt', 'art' ],
		],
		'production' => [
			'keywords' => [
				'schedule', 'budget', 'crew', 'cast', 'location', 'permit',
				'call sheet', 'production', 'logistics', 'resource',
				'equipment', 'transport', 'catering', 'insurance',
			],
			'agents' => [ 'production', 'camera', 'sound', 'grip' ],
		],
		'technical' => [
			'keywords' => [
				'format', 'spec', 'template', 'technical', 'export',
				'import', 'csv', 'json', 'xml', 'edl', 'cff',
				'screenplay', 'final draft', 'fade in', 'fade out',
			],
			'agents' => [ 'technical', 'editorial' ],
		],
		'editorial' => [
			'keywords' => [
				'continuity', 'consistency', 'check', 'review', 'analyze',
				'feedback', 'critique', 'suggestion', 'improve',
				'pacing', 'rhythm', 'flow', 'structure',
			],
			'agents' => [ 'editorial', 'story' ],
		],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->orchestrator_url = get_option( 'storyos_orchestrator_url', 'http://localhost:8000' );
		$this->hybrid_enabled   = (bool) get_option( 'storyos_ai_hybrid_routing', true );
		$this->complexity_threshold = (int) get_option( 'storyos_ai_complexity_threshold', 3 );
	}

	/**
	 * Route a user request to the best agent.
	 *
	 * @param string $prompt User prompt.
	 * @param array  $options Optional routing options (force_local, force_orchestrator, context).
	 * @return array Routing result with agent name, confidence, and routing strategy.
	 */
	public function route( string $prompt, array $options = [] ): array {
		// Allow explicit override.
		if ( ! empty( $options['force_orchestrator'] ) ) {
			return [
				'agent'      => null,
				'routing'    => 'orchestrator',
				'confidence' => 1.0,
				'category'   => 'multi-agent',
				'message'    => 'Forced orchestrator routing.',
			];
		}

		if ( ! empty( $options['force_local'] ) ) {
			return $this->route_local( $prompt );
		}

		// Hybrid routing: detect complexity and choose strategy.
		if ( $this->hybrid_enabled ) {
			$complexity = $this->calculate_complexity( $prompt );
			if ( $complexity >= $this->complexity_threshold ) {
				return [
					'agent'      => null,
					'routing'    => 'orchestrator',
					'confidence' => $complexity,
					'category'   => 'multi-agent',
					'message'    => "Complexity score {$complexity} exceeds threshold {$this->complexity_threshold}. Routing to orchestrator.",
				];
			}
		}

		return $this->route_local( $prompt );
	}

	/**
	 * Route to local WordPress agent.
	 *
	 * @param string $prompt User prompt.
	 * @return array Routing result.
	 */
	private function route_local( string $prompt ): array {
		$prompt_lower = strtolower( $prompt );
		$best_agent   = null;
		$best_score   = 0;

		foreach ( $this->keyword_mappings as $category => $mapping ) {
			$score = 0;
			foreach ( $mapping['keywords'] as $keyword ) {
				if ( strpos( $prompt_lower, $keyword ) !== false ) {
					$score++;
				}
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_agent = $mapping['agents'][0];
			}
		}

		// Default to story agent if no match.
		if ( ! $best_agent ) {
			$best_agent = 'story';
			$best_score = 0;
		}

		return [
			'agent'      => $best_agent,
			'routing'    => 'local',
			'confidence' => min( $best_score / 3, 1.0 ), // Normalize to 0-1.
			'category'   => $this->get_category_for_agent( $best_agent ),
		];
	}

	/**
	 * Calculate prompt complexity score.
	 *
	 * @param string $prompt User prompt.
	 * @return int Complexity score.
	 */
	private function calculate_complexity( string $prompt ): int {
		$score = 0;
		$prompt_lower = strtolower( $prompt );

		// Count multi-agent indicators.
		$multi_agent_indicators = [
			'multiple agents',
			'coordinate',
			'workflow',
			'pipeline',
			'handoff',
			'collaborate',
			'chain',
			'sequence',
			'first then',
			'after that',
			'next',
			'also need',
			'and also',
			'along with',
			'involving',
			'between',
			'across departments',
			'full production',
			'end to end',
			'complete',
		];

		foreach ( $multi_agent_indicators as $indicator ) {
			if ( strpos( $prompt_lower, $indicator ) !== false ) {
				$score++;
			}
		}

		// Count distinct department keywords.
		$departments = [
			'script' => [ 'character', 'dialogue', 'plot', 'scene', 'story', 'write', 'draft' ],
			'prompt' => [ 'prompt', 'image', 'visual', 'shot', 'composition' ],
			'production' => [ 'schedule', 'budget', 'crew', 'location', 'permit' ],
			'camera' => [ 'camera', 'lighting', 'lens', 'focus' ],
			'sound' => [ 'sound', 'audio', 'mic', 'boom' ],
			'art' => [ 'production design', 'set', 'prop', 'costume', 'wardrobe' ],
			'post' => [ 'edit', 'vfx', 'color', 'continuity' ],
		];

		foreach ( $departments as $dept => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( strpos( $prompt_lower, $keyword ) !== false ) {
					$score++;
					break; // Count each department only once
				}
			}
		}

		return $score;
	}

	/**
	 * Call the orchestrator for multi-agent routing.
	 *
	 * @param string $prompt User prompt.
	 * @param array  $context Optional context.
	 * @return array Orchestrator response.
	 */
	public function route_to_orchestrator( string $prompt, array $context = [] ): array {
		$url = trailingslashit( $this->orchestrator_url ) . 'api/agents/orchestrator';

		$args = [
			'body'    => wp_json_encode([
				'prompt'  => $prompt,
				'context' => $context,
			]),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 30,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'error'   => 'orchestrator_unreachable',
				'message' => 'Orchestrator service is not available.',
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return [
				'error'   => 'invalid_response',
				'message' => 'Invalid response from orchestrator.',
			];
		}

		return $data;
	}

	/**
	 * Get the category for an agent.
	 *
	 * @param string $agent Agent name.
	 * @return string Category name.
	 */
	private function get_category_for_agent( string $agent ): string {
		foreach ( $this->keyword_mappings as $category => $mapping ) {
			if ( in_array( $agent, $mapping['agents'], true ) ) {
				return $category;
			}
		}
		return 'story';
	}

	/**
	 * Get all available agents with their categories.
	 *
	 * @return array Available agents.
	 */
	public function get_available_agents(): array {
		$agents = [];

		foreach ( $this->keyword_mappings as $category => $mapping ) {
			foreach ( $mapping['agents'] as $agent ) {
				$agents[] = [
					'name'      => $agent,
					'category'  => $category,
					'keywords'  => $mapping['keywords'],
				];
			}
		}

		return $agents;
	}

	/**
	 * Get agents by category.
	 *
	 * @param string $category Category name.
	 * @return array Agents in category.
	 */
	public function get_agents_by_category( string $category ): array {
		if ( isset( $this->keyword_mappings[ $category ] ) ) {
			return $this->keyword_mappings[ $category ]['agents'];
		}
		return [];
	}
}
