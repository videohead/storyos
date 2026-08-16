<?php
/**
 * AI Agent Router — routes user requests to native StoryOS agents.
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
	 * Orchestrator URL for optional external workflows.
	 *
	 * @var string
	 */
	private $orchestrator_url;

	/**
	 * Whether optional orchestrator routing is enabled.
	 *
	 * @var bool
	 */
	private $hybrid_enabled;

	/**
	 * Complexity threshold for orchestrator routing.
	 *
	 * @var int
	 */
	private $complexity_threshold;

	/**
	 * Native registry.
	 *
	 * @var AI_Agent_Registry
	 */
	private $agent_registry;

	/**
	 * Backward-compatible advisor aliases.
	 *
	 * @var array
	 */
	private $legacy_aliases = [];

	/**
	 * High-signal keywords for major advisor categories.
	 *
	 * @var array
	 */
	private $category_keyword_mappings = [
		'screenwriter' => [ 'character', 'dialogue', 'plot', 'scene', 'story', 'narrative', 'arc', 'script', 'rewrite', 'draft' ],
		'art_director' => [ 'prompt', 'image', 'visual', 'scene', 'shot', 'composition', 'lighting', 'style', 'look', 'concept' ],
		'producer' => [ 'schedule', 'budget', 'crew', 'cast', 'location', 'permit', 'production', 'logistics', 'resource' ],
		'editor' => [ 'format', 'spec', 'template', 'technical', 'export', 'import', 'edl', 'xml', 'json' ],
		'script_supervisor' => [ 'continuity', 'consistency', 'review', 'analyze', 'feedback', 'pacing', 'structure' ],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->orchestrator_url     = get_option( 'storyos_orchestrator_url', 'http://localhost:8000' );
		$this->hybrid_enabled       = (bool) get_option( 'storyos_ai_hybrid_routing', false );
		$this->complexity_threshold = (int) get_option( 'storyos_ai_complexity_threshold', 6 );
		$this->agent_registry       = new AI_Agent_Registry( new AI_LLM_Client() );
		$this->legacy_aliases       = AI_Agent_Registry::LEGACY_ALIASES;
	}

	/**
	 * Route a user request to the best native agent.
	 *
	 * @param string $prompt User prompt.
	 * @param array  $options Optional routing options (force_local, force_orchestrator, context).
	 * @return array
	 */
	public function route( string $prompt, array $options = [] ): array {
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

		if ( $this->hybrid_enabled ) {
			$complexity = $this->calculate_complexity( $prompt );
			if ( $complexity >= $this->complexity_threshold ) {
				return [
					'agent'      => null,
					'routing'    => 'orchestrator',
					'confidence' => min( 1.0, $complexity / 10 ),
					'category'   => 'multi-agent',
					'message'    => "Complexity score {$complexity} exceeds threshold {$this->complexity_threshold}. Routing to orchestrator.",
				];
			}
		}

		return $this->route_local( $prompt );
	}

	/**
	 * Route locally against full native agent set.
	 *
	 * @param string $prompt User prompt.
	 * @return array
	 */
	private function route_local( string $prompt ): array {
		$prompt_lower = strtolower( $prompt );
		$agents       = $this->agent_registry->list_agents();

		if ( empty( $agents ) ) {
			return [
				'agent'      => 'screenwriter',
				'routing'    => 'local',
				'confidence' => 0,
				'category'   => 'fallback',
			];
		}

		foreach ( $this->legacy_aliases as $alias => $target ) {
			if ( preg_match( '/\\b' . preg_quote( $alias, '/' ) . '\\b/', $prompt_lower ) ) {
				return [
					'agent'      => $target,
					'routing'    => 'local',
					'confidence' => 1.0,
					'category'   => $alias,
				];
			}
		}

		$best_agent = 'screenwriter';
		$best_score = -1;

		foreach ( $agents as $slug => $agent ) {
			$score = $this->score_agent_match( $slug, $agent, $prompt_lower );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_agent = $slug;
			}
		}

		if ( $best_score <= 0 ) {
			foreach ( $this->category_keyword_mappings as $slug => $keywords ) {
				$score = 0;
				foreach ( $keywords as $keyword ) {
					if ( false !== strpos( $prompt_lower, $keyword ) ) {
						$score++;
					}
				}
				if ( $score > $best_score ) {
					$best_score = $score;
					$best_agent = $slug;
				}
			}
		}

		return [
			'agent'      => $best_agent,
			'routing'    => 'local',
			'confidence' => max( 0, min( 1.0, $best_score / 4 ) ),
			'category'   => $this->get_category_for_agent( $best_agent ),
		];
	}

	/**
	 * Score prompt/agent match.
	 *
	 * @param string $slug Agent slug.
	 * @param array  $agent Agent data.
	 * @param string $prompt_lower Lowercase prompt.
	 * @return int
	 */
	private function score_agent_match( string $slug, array $agent, string $prompt_lower ): int {
		$score = 0;

		$keywords = array_unique(
			array_filter(
				array_merge(
					explode( '_', $slug ),
					preg_split( '/[^a-z0-9]+/i', strtolower( (string) ( $agent['description'] ?? '' ) ) ) ?: []
				)
			)
		);

		foreach ( $keywords as $keyword ) {
			if ( strlen( $keyword ) < 4 ) {
				continue;
			}
			if ( false !== strpos( $prompt_lower, $keyword ) ) {
				$score += 2;
			}
		}

		return $score;
	}

	/**
	 * Calculate prompt complexity score.
	 *
	 * @param string $prompt User prompt.
	 * @return int
	 */
	private function calculate_complexity( string $prompt ): int {
		$score             = 0;
		$prompt_lower      = strtolower( $prompt );
		$complexity_tokens = [
			'multiple agents',
			'coordinate',
			'workflow',
			'pipeline',
			'handoff',
			'collaborate',
			'sequence',
			'end to end',
			'full production',
		];

		foreach ( $complexity_tokens as $indicator ) {
			if ( false !== strpos( $prompt_lower, $indicator ) ) {
				$score++;
			}
		}

		return $score;
	}

	/**
	 * Optional orchestrator call for complex workflows.
	 *
	 * @param string $prompt User prompt.
	 * @param array  $context Optional context.
	 * @return array
	 */
	public function route_to_orchestrator( string $prompt, array $context = [] ): array {
		$url = trailingslashit( $this->orchestrator_url ) . 'api/agents/orchestrator';

		$args = [
			'body'    => wp_json_encode(
				[
					'prompt'  => $prompt,
					'context' => $context,
				]
			),
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
	 * Get category for an agent slug.
	 *
	 * @param string $agent Agent slug.
	 * @return string
	 */
	private function get_category_for_agent( string $agent ): string {
		foreach ( $this->legacy_aliases as $category => $target ) {
			if ( $target === $agent ) {
				return $category;
			}
		}
		return 'specialist';
	}

	/**
	 * Get all available agents with categories.
	 *
	 * @return array
	 */
	public function get_available_agents(): array {
		$agents = [];
		foreach ( $this->agent_registry->list_agents() as $slug => $agent ) {
			$agents[] = [
				'name'        => $slug,
				'category'    => $this->get_category_for_agent( $slug ),
				'description' => $agent['description'] ?? '',
			];
		}
		return $agents;
	}

	/**
	 * Get agents by category alias.
	 *
	 * @param string $category Category name.
	 * @return array
	 */
	public function get_agents_by_category( string $category ): array {
		$category = strtolower( $category );
		if ( isset( $this->legacy_aliases[ $category ] ) ) {
			return [ $this->legacy_aliases[ $category ] ];
		}
		if ( 'all' === $category ) {
			return array_keys( $this->agent_registry->list_agents() );
		}
		return [];
	}
}
