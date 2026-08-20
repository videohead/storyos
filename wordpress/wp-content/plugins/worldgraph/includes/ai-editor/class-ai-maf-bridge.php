<?php
/**
 * Filmmaking ability registry backed by WordPress-owned agent definitions.
 *
 * @package WorldGraph
 */

namespace WorldGraph\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filmmaking ability registry class.
 */
class AI_MAF_Bridge {

	/**
	 * LLM client instance.
	 *
	 * @var AI_LLM_Client
	 */
	private $llm_client;

	/**
	 * Agent definitions loaded from .agent.md files.
	 *
	 * @var array
	 */
	private $agents = [];

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
	 * Load agent definitions from .agent.md files.
	 *
	 * @return void
	 */
	private function load_agents(): void {
		$agents_dir = WORLDGRAPH_PLUGIN_DIR . 'includes/agents/';
		
		if ( is_dir( $agents_dir ) ) {
			$files = glob( $agents_dir . '*.agent.md' );
			foreach ( $files as $file ) {
				$agent = $this->parse_agent_file( $file );
				if ( $agent ) {
					$this->agents[ $agent['name'] ] = $agent;
				}
			}
		}

	}

	/**
	 * Parse an .agent.md file.
	 *
	 * @param string $file Path to the file.
	 * @return array|false Parsed agent data or false.
	 */
	private function parse_agent_file( string $file ) {
		$content = file_get_contents( $file );
		if ( false === $content ) {
			return false;
		}

		// Extract YAML frontmatter.
		$yaml = '';
		$markdown = '';
		$parts = preg_split( '/^---\s*$/m', $content, 2 );
		if ( isset( $parts[1] ) ) {
			$yaml = $parts[0];
			$markdown = $parts[1];
		} else {
			$markdown = $content;
		}

		$agent = [
			'name'         => basename( $file, '.agent.md' ),
			'description'  => '',
			'system_prompt' => '',
			'tools'        => [],
			'model'        => '',
			'handoffs'     => [],
		];

		// Parse YAML frontmatter.
		if ( ! empty( $yaml ) ) {
			$agent = array_merge( $agent, $this->parse_yaml( $yaml ) );
		}

		// Parse markdown body as system prompt.
		if ( ! empty( $markdown ) ) {
			$agent['system_prompt'] = trim( $markdown );
		}

		return $agent;
	}

	/**
	 * Simple YAML parser for frontmatter.
	 *
	 * @param string $yaml YAML string.
	 * @return array Parsed data.
	 */
	private function parse_yaml( string $yaml ): array {
		$result = [];
		$lines = explode( "\n", $yaml );
		$current_key = null;
		$current_array = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) || str_starts_with( $line, '#' ) ) {
				continue;
			}

			// Check for key: value pair.
			if ( preg_match( '/^(\w+):\s*(.*)$/', $line, $matches ) ) {
				// Save previous array if exists.
				if ( $current_key && ! empty( $current_array ) ) {
					$result[ $current_key ] = $current_array;
					$current_array = [];
				}

				$key = $matches[1];
				$value = trim( $matches[2] );

				if ( '' === $value ) {
					// Start of an array.
					$current_key = $key;
					$current_array = [];
				} else {
					// Simple key-value.
					$result[ $key ] = $value;
					$current_key = null;
				}
			} elseif ( preg_match( '/^\s*-\s+(.+)$/', $line, $matches ) && $current_key ) {
				// Array item.
				$current_array[] = trim( $matches[1] );
			}
		}

		// Save last array.
		if ( $current_key && ! empty( $current_array ) ) {
			$result[ $current_key ] = $current_array;
		}

		return $result;
	}

	/**
	 * Get an agent by name.
	 *
	 * @param string $name Agent name.
	 * @return array|false Agent data or false.
	 */
	public function get_agent( string $name ) {
		return $this->agents[ $name ] ?? false;
	}

	/**
	 * List all available agents.
	 *
	 * @return array List of agents.
	 */
	public function list_agents(): array {
		return $this->agents;
	}

	/**
	 * Get agents by department.
	 *
	 * @param string $department Department name.
	 * @return array Agents in department.
	 */
	public function get_agents_by_department( string $department ): array {
		$agents = [];
		foreach ( $this->agents as $name => $agent ) {
			if ( isset( $agent['department'] ) && $agent['department'] === $department ) {
				$agents[ $name ] = $agent;
			}
		}
		return $agents;
	}

	/**
	 * Get agent handoffs.
	 *
	 * @return array Agent handoff mappings.
	 */
	public function get_agent_handoffs(): array {
		$handoffs = [];
		foreach ( $this->agents as $name => $agent ) {
			if ( isset( $agent['handoffs'] ) && is_array( $agent['handoffs'] ) ) {
				$handoffs[ $name ] = $agent['handoffs'];
			}
		}
		return $handoffs;
	}

	/**
	 * Run an agent with a prompt.
	 *
	 * @param string $agent_name Agent name.
	 * @param string $prompt User prompt.
	 * @param array  $context Additional context.
	 * @return array Response.
	 */
	public function run_agent( string $agent_name, string $prompt, array $context = [] ): array {
		$agent = $this->get_agent( $agent_name );
		if ( ! $agent ) {
			return [
				'content' => "Agent not found: {$agent_name}",
				'error'   => 'agent_not_found',
			];
		}

		$system_prompt = $agent['system_prompt'] ?? '';
		
		// Add context to system prompt.
		if ( ! empty( $context ) ) {
			$context_builder = new AI_Context_Builder();
			$context_text = $context_builder->build_context_for_llm( $context );
			if ( ! empty( $context_text ) ) {
				$system_prompt .= "\n\n" . $context_text;
			}
		}

		// Call the LLM with the agent's system prompt.
		return $this->llm_client->chat( $prompt, [
			'system_prompt' => $system_prompt,
		] );
	}

	/**
	 * Get enabled agents based on settings.
	 *
	 * @return array Enabled agents.
	 */
	public function get_enabled_agents(): array {
		$enabled_str = get_option( 'worldgraph_ai_enabled_agents', '' );
		$legacy_default = 'story,prompt,production,technical,editorial';

		// An empty setting, or the old category-based default, means all loaded agents.
		if ( empty( trim( $enabled_str ) ) || $legacy_default === trim( $enabled_str ) ) {
			return $this->agents;
		}

		$enabled = array_map( 'trim', explode( ',', $enabled_str ) );

		$result = [];
		foreach ( $this->agents as $name => $agent ) {
			if ( in_array( $name, $enabled, true ) || in_array( $agent['department'] ?? '', $enabled, true ) ) {
				$result[ $name ] = $agent;
			}
		}

		return $result;
	}
}
