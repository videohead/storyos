<?php
/**
 * StoryOS AI Abilities Registration
 *
 * @package StoryOS
 */

namespace StoryOS\AI\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAbilityGroup {
	protected $slug = '';
	protected $label = '';
	protected $description = '';
	protected $category_slug = 'storyos-ai-editor';

	abstract public function register(): void;

	protected function register_ability( string $name, array $args ) {
		$args = wp_parse_args( $args, [
			'label' => '',
			'description' => '',
			'input_schema' => [],
			'output_schema' => [],
			'execute_callback' => null,
			'permission_callback' => null,
			'meta' => [],
		] );

		$args['meta'] = wp_parse_args( $args['meta'], [
			'public' => true,
			'mcp' => [ 'type' => 'tool' ],
			'annotations' => [
				'readonly' => true,
				'destructive' => false,
				'idempotent' => true,
			],
		] );

		if ( ! $args['permission_callback'] ) {
			$args['permission_callback'] = function() {
				return is_user_logged_in();
			};
		}

		return \wp_register_ability( $name, $args );
	}

	public function get_slug(): string {
		return $this->slug;
	}
}

class Chat_Abilities extends AbstractAbilityGroup {
	protected $slug = 'storyos-chat';

	public function register(): void {
		$this->register_ability( 'storyos/chat', [
			'label' => 'AI Chat',
			'description' => 'Send a prompt to StoryOS AI.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'prompt' => [ 'type' => 'string' ],
					'agent' => [ 'type' => 'string' ],
					'post_id' => [ 'type' => 'integer' ],
				],
				'required' => [ 'prompt' ],
			],
			'execute_callback' => function( $input ) {
				return \StoryOS\AI\AI_Editor::instance()->chat(
					$input['prompt'],
					$input['agent'] ?? null,
					isset( $input['post_id'] ) ? (int) $input['post_id'] : 0
				);
			},
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
			'meta' => [
				'mcp' => [ 'type' => 'tool' ],
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => false ],
			],
		] );

		$this->register_ability( 'storyos/analyze', [
			'label' => 'Analyze Content',
			'description' => 'Analyze post content.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'focus' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id' ],
			],
			'execute_callback' => function( $input ) {
				return \StoryOS\AI\AI_Editor::instance()->analyze( (int) $input['post_id'], $input['focus'] ?? null );
			},
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'storyos/generate', [
			'label' => 'Generate Content',
			'description' => 'Generate content with StoryOS AI.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'prompt' => [ 'type' => 'string' ],
					'type' => [ 'type' => 'string' ],
					'post_id' => [ 'type' => 'integer' ],
					'agent' => [ 'type' => 'string' ],
				],
				'required' => [ 'prompt' ],
			],
			'execute_callback' => function( $input ) {
				return \StoryOS\AI\AI_Editor::instance()->generate(
					$input['prompt'],
					$input['type'] ?? null,
					isset( $input['post_id'] ) ? (int) $input['post_id'] : 0,
					$input['agent'] ?? null
				);
			},
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
			'meta' => [
				'mcp' => [ 'type' => 'tool' ],
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
			],
		] );

		$this->register_ability( 'storyos/continuity-check', [
			'label' => 'Continuity Check',
			'description' => 'Run continuity checking.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'required' => [ 'post_id' ],
			],
			'execute_callback' => function( $input ) {
				return \StoryOS\AI\AI_Editor::instance()->continuity_check( (int) $input['post_id'] );
			},
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		] );
	}
}

class Context_Resources extends AbstractAbilityGroup {
	protected $slug = 'storyos-context';

	public function register(): void {
		$this->register_ability( 'storyos/post-context', [
			'label' => 'Post Context',
			'description' => 'Get Story Graph context for a post.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'required' => [ 'post_id' ],
			],
			'execute_callback' => function( $input ) {
				$builder = new \StoryOS\AI\AI_Context_Builder();
				return $builder->build_post_context( (int) $input['post_id'] );
			},
			'meta' => [
				'mcp' => [ 'type' => 'resource' ],
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
			],
		] );
	}
}

class Prompt_Templates extends AbstractAbilityGroup {
	protected $slug = 'storyos-prompts';

	public function register(): void {
		$this->register_ability( 'storyos/story-review-prompt', [
			'label' => 'Story Review Prompt',
			'description' => 'Build a structured story review prompt.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'focus' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id' ],
			],
			'execute_callback' => function( $input ) {
				$builder = new \StoryOS\AI\AI_Context_Builder();
				$context = $builder->build_post_context( (int) $input['post_id'] );
				$post    = get_post( (int) $input['post_id'] );
				if ( ! $post ) {
					return [
						'system_prompt' => '',
						'user_prompt' => 'Post not found.',
					];
				}
				$focus   = $input['focus'] ?? 'story';
				return [
					'system_prompt' => 'You are a story review expert using the StoryOS framework.',
					'user_prompt' => "Review this content with focus on {$focus}.\n\nTitle: {$post->post_title}\n\nContent:\n{$post->post_content}\n\n" . $builder->build_context_for_llm( $context ),
				];
			},
			'meta' => [
				'mcp' => [ 'type' => 'prompt' ],
			],
		] );

		$this->register_ability( 'storyos/continuity-prompt', [
			'label' => 'Continuity Prompt',
			'description' => 'Build a continuity check prompt.',
			'input_schema' => [
				'type' => 'object',
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'required' => [ 'post_id' ],
			],
			'execute_callback' => function( $input ) {
				$builder = new \StoryOS\AI\AI_Context_Builder();
				$context = $builder->build_post_context( (int) $input['post_id'] );
				$post    = get_post( (int) $input['post_id'] );
				if ( ! $post ) {
					return [
						'system_prompt' => '',
						'user_prompt' => 'Post not found.',
					];
				}
				return [
					'system_prompt' => 'You are a continuity expert using the StoryOS framework.',
					'user_prompt' => "Check this content for continuity errors.\n\nTitle: {$post->post_title}\n\nContent:\n{$post->post_content}\n\n" . $builder->build_context_for_llm( $context ),
				];
			},
			'meta' => [
				'mcp' => [ 'type' => 'prompt' ],
			],
		] );
	}
}

class Native_Agent_Abilities extends AbstractAbilityGroup {
	protected $slug = 'storyos-native-agents';

	public function register(): void {
		$registry = new \StoryOS\AI\AI_Agent_Registry( new \StoryOS\AI\AI_LLM_Client() );
		foreach ( $registry->list_agents() as $slug => $agent ) {
			$this->register_ability( 'storyos/agent/' . $slug, [
				'label' => ! empty( $agent['name'] ) ? (string) $agent['name'] : $slug,
				'description' => ! empty( $agent['description'] ) ? (string) $agent['description'] : 'Invoke ' . $slug,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'prompt' => [ 'type' => 'string' ],
						'post_id' => [ 'type' => 'integer' ],
					],
					'required' => [ 'prompt' ],
				],
				'execute_callback' => function( $input ) use ( $slug ) {
					return \StoryOS\AI\AI_Editor::instance()->chat( $input['prompt'], $slug, isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );
				},
				'permission_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
				'meta' => [
					'mcp' => [ 'type' => 'tool' ],
					'agent' => $slug,
					'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => false ],
				],
			] );
		}
	}
}

class Abilities {
	private $ability_groups = [];
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->ability_groups = [
			new Chat_Abilities(),
			new Context_Resources(),
			new Prompt_Templates(),
			new Native_Agent_Abilities(),
		];
	}

	public function init(): void {
		$this->register_category();
		foreach ( $this->ability_groups as $group ) {
			$group->register();
		}
	}

	private function register_category() {
		return \wp_register_ability_category( 'storyos-ai-editor', [
			'label'       => 'StoryOS AI Editor',
			'description' => 'Abilities for AI-powered story editing and native agent execution.',
			'meta'        => [ 'public' => true ],
		] );
	}

	public function get_groups(): array {
		return $this->ability_groups;
	}

	public function get_group( string $slug ): ?AbstractAbilityGroup {
		foreach ( $this->ability_groups as $group ) {
			if ( $group->get_slug() === $slug ) {
				return $group;
			}
		}
		return null;
	}
}
