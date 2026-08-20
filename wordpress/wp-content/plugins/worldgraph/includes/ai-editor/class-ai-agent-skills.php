<?php
/**
 * AI Agent Skills — loads WordPress/agent-skills for AI guidance.
 *
 * @package WorldGraph
 */

namespace WorldGraph\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agent Skills class.
 */
class AI_Agent_Skills {

	/**
	 * Cached skills.
	 *
	 * @var array
	 */
	private $skills = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_skills();
	}

	/**
	 * Load skills from the agent-skills directory.
	 *
	 * @return void
	 */
	private function load_skills(): void {
		$skills_path = $this->get_skills_path();
		
		if ( ! $skills_path || ! is_dir( $skills_path ) ) {
			return;
		}

		// Scan for skill directories.
		$directories = glob( $skills_path . '/*', GLOB_ONLYDIR );
		
		foreach ( $directories as $dir ) {
			$skill_file = $dir . '/SKILL.md';
			if ( file_exists( $skill_file ) ) {
				$skill_name = basename( $dir );
				$content = file_get_contents( $skill_file );
				if ( false !== $content ) {
					$this->skills[ $skill_name ] = [
						'name'    => $skill_name,
						'path'    => $dir,
						'content' => $content,
					];
				}
			}
		}
	}

	/**
	 * Get the skills directory path.
	 *
	 * @return string|false Path to skills directory or false.
	 */
	private function get_skills_path() {
		// First try the configured path.
		$configured_path = get_option( 'worldgraph_ai_agent_skills_path', '' );
		if ( ! empty( $configured_path ) && is_dir( $configured_path ) ) {
			return $configured_path;
		}

		// Try to find WordPress/agent-skills repository.
		$possible_paths = [
			WORLDGRAPH_PLUGIN_DIR . '../../agent-skills/skills',
			'/tmp/agent-skills/skills',
		];

		foreach ( $possible_paths as $path ) {
			if ( is_dir( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Detect relevant skills based on post type and content.
	 *
	 * @param string $post_type Post type.
	 * @param string $content Post content.
	 * @return array Relevant skills.
	 */
	public function detect_relevant_skills( string $post_type = '', string $content = '' ): array {
		$relevant = [];

		// Map post types to skills.
		$post_type_skills = [
			'character' => [ 'wordpress-best-practices', 'content-model' ],
			'scene'     => [ 'wordpress-best-practices', 'content-model' ],
			'post'      => [ 'wordpress-best-practices', 'content-creation' ],
			'page'      => [ 'wordpress-best-practices', 'page-templates' ],
		];

		if ( ! empty( $post_type ) && isset( $post_type_skills[ $post_type ] ) ) {
			foreach ( $post_type_skills[ $post_type ] as $skill_name ) {
				if ( isset( $this->skills[ $skill_name ] ) ) {
					$relevant[] = $skill_name;
				}
			}
		}

		// Scan content for skill-relevant keywords.
		if ( ! empty( $content ) ) {
			$content_lower = strtolower( $content );
			
			$skill_keywords = [
				'wordpress-best-practices' => [ 'wordpress', 'wp-', 'plugin', 'theme', 'hook', 'filter' ],
				'content-creation' => [ 'write', 'create', 'draft', 'publish' ],
				'block-editor' => [ 'block', 'gutenberg', 'wp-block', 'editor' ],
				'api' => [ 'rest', 'api', 'endpoint', 'request', 'response' ],
				'database' => [ 'database', 'wpdb', 'meta', 'taxonomy' ],
			];

			foreach ( $skill_keywords as $skill_name => $keywords ) {
				foreach ( $keywords as $keyword ) {
					if ( strpos( $content_lower, $keyword ) !== false ) {
						if ( isset( $this->skills[ $skill_name ] ) && ! in_array( $skill_name, $relevant, true ) ) {
							$relevant[] = $skill_name;
						}
						break;
					}
				}
			}
		}

		// Always include general WordPress best practices if available.
		if ( isset( $this->skills['wordpress-best-practices'] ) && ! in_array( 'wordpress-best-practices', $relevant, true ) ) {
			$relevant[] = 'wordpress-best-practices';
		}

		return $relevant;
	}

	/**
	 * Get skill content by name.
	 *
	 * @param string $skill_name Skill name.
	 * @return array|false Skill data or false.
	 */
	public function get_skill( string $skill_name ) {
		return $this->skills[ $skill_name ] ?? false;
	}

	/**
	 * List all loaded skills.
	 *
	 * @return array All skills.
	 */
	public function list_skills(): array {
		return $this->skills;
	}

	/**
	 * Augment system prompt with relevant skill content.
	 *
	 * @param string $base_prompt Base system prompt.
	 * @param string $post_type Post type.
	 * @param string $content Post content.
	 * @return string Augmented system prompt.
	 */
	public function augment_system_prompt( string $base_prompt, string $post_type = '', string $content = '' ): string {
		$relevant_skills = $this->detect_relevant_skills( $post_type, $content );
		
		if ( empty( $relevant_skills ) ) {
			return $base_prompt;
		}

		$skill_sections = [];
		foreach ( $relevant_skills as $skill_name ) {
			$skill = $this->get_skill( $skill_name );
			if ( $skill && ! empty( $skill['content'] ) ) {
				$skill_sections[] = "## WordPress Agent Skill: {$skill_name}\n\n" . $skill['content'];
			}
		}

		if ( empty( $skill_sections ) ) {
			return $base_prompt;
		}

		return $base_prompt . "\n\n" . implode( "\n\n", $skill_sections );
	}
}
