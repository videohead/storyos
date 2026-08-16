<?php
/**
 * StoryOS Native Agent Registry and Executor.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native agent registry for WordPress-local .agent.md files.
 */
class AI_Agent_Registry {

	/**
	 * Backward-compatible advisor aliases mapped to native agents.
	 */
	public const LEGACY_ALIASES = [
		'story'      => 'screenwriter',
		'prompt'     => 'art_director',
		'production' => 'producer',
		'technical'  => 'editor',
		'editorial'  => 'script_supervisor',
	];

	/**
	 * LLM client instance.
	 *
	 * @var AI_LLM_Client
	 */
	private $llm_client;

	/**
	 * Agent definitions loaded from plugin-local .agent.md files.
	 *
	 * @var array
	 */
	private $agents = [];

	/**
	 * Legacy advisor aliases mapped to native agents.
	 *
	 * @var array
	 */
	private $legacy_aliases = self::LEGACY_ALIASES;

	/**
	 * Constructor.
	 *
	 * @param AI_LLM_Client $llm_client LLM client instance.
	 */
	public function __construct( AI_LLM_Client $llm_client ) {
		$this->llm_client = $llm_client;
		$this->load_agents();
	}

	/**
	 * Load plugin-local agent definitions from .agent.md files.
	 *
	 * @return void
	 */
	private function load_agents(): void {
		$this->agents = [];
		$agents_dir   = STORYOS_PLUGIN_DIR . 'includes/agents/';

		if ( ! is_dir( $agents_dir ) ) {
			return;
		}

		$files = glob( $agents_dir . '*.agent.md' );
		if ( ! is_array( $files ) ) {
			return;
		}

		sort( $files );

		foreach ( $files as $file ) {
			$agent = $this->parse_agent_file( $file );
			if ( $agent ) {
				$this->agents[ $agent['slug'] ] = $agent;
			}
		}
	}

	/**
	 * Parse an .agent.md file into registry data.
	 *
	 * @param string $file Path to .agent.md file.
	 * @return array|false Parsed agent data or false.
	 */
	private function parse_agent_file( string $file ) {
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return false;
		}

		$slug     = basename( $file, '.agent.md' );
		$yaml     = '';
		$markdown = trim( $content );

		if ( preg_match( '/\A---\R(.*?)\R---\R(.*)\z/s', $content, $matches ) ) {
			$yaml     = trim( $matches[1] );
			$markdown = trim( $matches[2] );
		}

		$agent = [
			'slug'          => $slug,
			'name'          => $slug,
			'description'   => '',
			'system_prompt' => $markdown,
			'tools'         => [],
			'model'         => '',
			'handoffs'      => [],
			'department'    => '',
		];

		if ( '' !== $yaml ) {
			$agent = array_merge( $agent, $this->parse_yaml( $yaml ) );
		}

		if ( empty( $agent['name'] ) ) {
			$agent['name'] = $slug;
		}

		return $agent;
	}

	/**
	 * Parse YAML-like frontmatter.
	 *
	 * @param string $yaml YAML string.
	 * @return array Parsed key/value data.
	 */
	private function parse_yaml( string $yaml ): array {
		$result        = [];
		$lines         = explode( "\n", $yaml );
		$current_key   = null;
		$current_array = [];

		foreach ( $lines as $line ) {
			if ( preg_match( '/^([a-zA-Z0-9_]+):\s*(.*)$/', trim( $line ), $matches ) ) {
				if ( $current_key && ! empty( $current_array ) ) {
					$result[ $current_key ] = $current_array;
				}

				$current_key   = null;
				$current_array = [];
				$key           = $matches[1];
				$value         = trim( $matches[2] );

				if ( '' === $value ) {
					$current_key = $key;
				} else {
					$result[ $key ] = trim( $value, "\"'" );
				}
				continue;
			}

			if ( $current_key && preg_match( '/^\s*-\s*(.+)$/', $line, $matches ) ) {
				$current_array[] = trim( $matches[1], "\"'" );
			}
		}

		if ( $current_key && ! empty( $current_array ) ) {
			$result[ $current_key ] = $current_array;
		}

		return $result;
	}

	/**
	 * Resolve legacy aliases and return normalized agent slug.
	 *
	 * @param string $name Agent slug or legacy alias.
	 * @return string
	 */
	public function resolve_agent_slug( string $name ): string {
		$name = strtolower( sanitize_key( $name ) );
		if ( isset( $this->legacy_aliases[ $name ] ) ) {
			return $this->legacy_aliases[ $name ];
		}
		return $name;
	}

	/**
	 * Get one agent by slug or legacy alias.
	 *
	 * @param string $name Agent slug or alias.
	 * @return array|null
	 */
	public function get_agent( string $name ): ?array {
		$slug = $this->resolve_agent_slug( $name );
		return $this->agents[ $slug ] ?? null;
	}

	/**
	 * List all registered agents.
	 *
	 * @return array
	 */
	public function list_agents(): array {
		return $this->agents;
	}

	/**
	 * Get all supported slugs, including backward-compatible aliases.
	 *
	 * @return array
	 */
	public function get_supported_agent_slugs(): array {
		$slugs = array_keys( $this->agents );
		return array_values( array_unique( array_merge( $slugs, array_keys( $this->legacy_aliases ) ) ) );
	}

	/**
	 * Get enabled agents based on settings.
	 *
	 * @return array
	 */
	public function get_enabled_agents(): array {
		$enabled_str = (string) get_option( 'storyos_ai_enabled_agents', 'all' );
		$enabled_str = trim( $enabled_str );

		if ( '' === $enabled_str || 'all' === strtolower( $enabled_str ) ) {
			return $this->agents;
		}

		$enabled = array_map( 'trim', explode( ',', strtolower( $enabled_str ) ) );
		$result  = [];

		foreach ( $enabled as $entry ) {
			$slug = $this->resolve_agent_slug( $entry );
			if ( isset( $this->agents[ $slug ] ) ) {
				$result[ $slug ] = $this->agents[ $slug ];
			}
		}

		return $result;
	}

	/**
	 * Execute a specific agent.
	 *
	 * @param string $agent_name Agent slug or alias.
	 * @param string $prompt User prompt.
	 * @param array  $context Optional context payload.
	 * @param string $additional_system_prompt Optional extra system content.
	 * @return array
	 */
	public function run_agent( string $agent_name, string $prompt, array $context = [], string $additional_system_prompt = '' ): array {
		$agent = $this->get_agent( $agent_name );
		if ( ! $agent ) {
			return [
				'content' => "Agent not found: {$agent_name}",
				'error'   => 'agent_not_found',
			];
		}

		$system_prompt = (string) ( $agent['system_prompt'] ?? '' );

		if ( '' !== $additional_system_prompt ) {
			$system_prompt .= "\n\n" . $additional_system_prompt;
		}

		if ( ! empty( $context ) ) {
			$context_builder = new AI_Context_Builder();
			$context_text    = $context_builder->build_context_for_llm( $context );
			if ( ! empty( $context_text ) ) {
				$system_prompt .= "\n\n" . $context_text;
			}
		}

		return $this->llm_client->chat(
			$prompt,
			[
				'system_prompt' => $system_prompt,
			]
		);
	}

	/**
	 * Retrieve declared handoff targets by agent.
	 *
	 * @return array
	 */
	public function get_agent_handoffs(): array {
		$handoffs = [];
		foreach ( $this->agents as $slug => $agent ) {
			if ( ! empty( $agent['handoffs'] ) && is_array( $agent['handoffs'] ) ) {
				$handoffs[ $slug ] = $agent['handoffs'];
			}
		}
		return $handoffs;
	}
}
