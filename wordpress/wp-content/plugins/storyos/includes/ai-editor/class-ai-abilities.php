<?php
/**
 * StoryOS AI Abilities Registration
 *
 * Registers StoryOS AI abilities with WordPress core Abilities API
 * for exposure via MCP Adapter and other AI tooling.
 *
 * @package StoryOS
 * @since 0.1.0
 */

namespace StoryOS\AI\Abilities;

use WP_Error;

/**
 * Abstract base class for StoryOS ability groups.
 *
 * Provides a registration helper method and manages
 * the group's ability definitions.
 */
abstract class AbstractAbilityGroup {
    /**
     * Group slug.
     *
     * @var string
     */
    protected $slug = '';

    /**
     * Group label.
     *
     * @var string
     */
    protected $label = '';

    /**
     * Group description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Group category slug for WP_Ability_Category.
     *
     * @var string
     */
    protected $category_slug = 'storyos-ai-editor';

    /**
     * Abilities defined by this group.
     *
     * @var array
     */
    protected $abilities = [];

    /**
     * Register the ability group.
     *
     * Called by the main Abilities class during init.
     */
    abstract public function register(): void;

    /**
     * Register a single ability.
     *
     * @param string $name      Ability name (e.g., 'storyos/chat').
     * @param array  $args      Ability arguments.
     * @return WP_Error|int Result of wp_register_ability.
     */
    protected function register_ability( string $name, array $args ) {
        // Merge default meta with provided args.
        $args = wp_parse_args( $args, [
            'label'          => '',
            'description'    => '',
            'input_schema'   => [],
            'output_schema'  => [],
            'execute_callback' => null,
            'permission_callback' => null,
            'meta'           => [],
        ] );

        // Ensure meta array exists.
        $args['meta'] = wp_parse_args( $args['meta'], [
            'public' => true,
            'mcp'    => [ 'type' => 'tool' ],
        ] );

        // Set default annotations if not provided.
        if ( ! isset( $args['meta']['annotations'] ) ) {
            $args['meta']['annotations'] = [
                'readonly'   => true,
                'destructive' => false,
                'idempotent'  => true,
            ];
        }

        // Default permission: logged in.
        if ( ! $args['permission_callback'] ) {
            $args['permission_callback'] = function() {
                return is_user_logged_in();
            };
        }

        return \wp_register_ability( $name, $args );
    }

    /**
     * Get group slug.
     *
     * @return string
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Get group label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }
}

/**
 * Chat & Generation abilities.
 *
 * Provides chat, content analysis, generation, and continuity checking.
 */
class Chat_Abilities extends AbstractAbilityGroup {
    protected $slug       = 'storyos-chat';
    protected $label      = 'Chat & Generation';
    protected $description = 'AI chat, content analysis, generation, and continuity checking for story editors.';

    public function register(): void {
        // storyos/chat - Main AI chat endpoint.
        $this->register_ability( 'storyos/chat', [
            'label'       => 'AI Chat',
            'description' => 'Send a prompt to the StoryOS AI agent and receive a response.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'prompt' => [
                        'type'        => 'string',
                        'description' => 'The user prompt or question.',
                    ],
                    'agent'  => [
                        'type'        => 'string',
                        'description' => 'Agent slug to route to (optional). Auto-detected if omitted.',
                    ],
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Current post ID for context (optional).',
                    ],
                ],
                'required' => ['prompt'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'response' => ['type' => 'string'],
                    'agent'    => ['type' => 'string'],
                    'success'  => ['type' => 'boolean'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Editor::instance()->chat(
                    $input['prompt'],
                    isset( $input['agent'] ) ? $input['agent'] : null,
                    isset( $input['post_id'] ) ? (int) $input['post_id'] : 0
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'tool' ],
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => false,
                ],
            ],
        ] );

        // storyos/analyze - Content analysis.
        $this->register_ability( 'storyos/analyze', [
            'label'       => 'Analyze Content',
            'description' => 'Analyze post content for story quality, tone, and structure.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Post ID to analyze.',
                    ],
                    'focus' => [
                        'type'        => 'string',
                        'description' => 'Analysis focus: story, dialogue, pacing, character (optional).',
                    ],
                ],
                'required' => ['post_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'score'     => ['type' => 'number'],
                    'feedback'  => ['type' => 'array'],
                    'suggestions' => ['type' => 'array'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Editor::instance()->analyze(
                    (int) $input['post_id'],
                    isset( $input['focus'] ) ? $input['focus'] : null
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'tool' ],
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );

        // storyos/generate - Content generation.
        $this->register_ability( 'storyos/generate', [
            'label'       => 'Generate Content',
            'description' => 'Generate story content such as dialogue, scenes, or descriptions.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'prompt'    => [
                        'type'        => 'string',
                        'description' => 'Generation prompt.',
                    ],
                    'type'      => [
                        'type'        => 'string',
                        'description' => 'Content type: dialogue, scene, description (optional).',
                    ],
                    'post_id'   => [
                        'type'        => 'integer',
                        'description' => 'Current post for context (optional).',
                    ],
                ],
                'required' => ['prompt'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'content' => ['type' => 'string'],
                    'type'    => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Editor::instance()->generate(
                    $input['prompt'],
                    isset( $input['type'] ) ? $input['type'] : null,
                    isset( $input['post_id'] ) ? (int) $input['post_id'] : 0
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'tool' ],
                'annotations' => [
                    'readonly'    => false,
                    'destructive' => false,
                    'idempotent'  => false,
                ],
            ],
        ] );

        // storyos/continuity-check - Continuity checking.
        $this->register_ability( 'storyos/continuity-check', [
            'label'       => 'Continuity Check',
            'description' => 'Check content for continuity errors against Story Graph data.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Post ID to check.',
                    ],
                ],
                'required' => ['post_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'issues'    => ['type' => 'array'],
                    'severity'  => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Editor::instance()->continuity_check(
                    (int) $input['post_id']
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'tool' ],
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );
    }
}

/**
 * Context Resource abilities.
 *
 * Provides read-only access to Story Graph context data.
 */
class Context_Resources extends AbstractAbilityGroup {
    protected $slug       = 'storyos-context';
    protected $label      = 'Story Context';
    protected $description = 'Read-only access to Story Graph context for posts, characters, and scenes.';

    public function register(): void {
        // storyos/post-context - Get full context for a post.
        $this->register_ability( 'storyos/post-context', [
            'label'       => 'Post Context',
            'description' => 'Retrieve full Story Graph context for a post including characters, scenes, and project data.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Post ID.',
                    ],
                ],
                'required' => ['post_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post'      => ['type' => 'object'],
                    'characters'=> ['type' => 'array'],
                    'scenes'    => ['type' => 'array'],
                    'project'   => ['type' => 'object'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_post_context(
                    (int) $input['post_id']
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'resource' ],
                'uri'    => 'storyos://post-context/{post_id}',
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );

        // storyos/character-context - Get character context.
        $this->register_ability( 'storyos/character-context', [
            'label'       => 'Character Context',
            'description' => 'Retrieve character metadata, relationships, and scene appearances.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'character_id' => [
                        'type'        => 'integer',
                        'description' => 'Character post ID.',
                    ],
                ],
                'required' => ['character_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'character' => ['type' => 'object'],
                    'relationships' => ['type' => 'array'],
                    'scenes'    => ['type' => 'array'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_character_context(
                    (int) $input['character_id']
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'resource' ],
                'uri'    => 'storyos://character/{character_id}',
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );

        // storyos/scene-context - Get scene context.
        $this->register_ability( 'storyos/scene-context', [
            'label'       => 'Scene Context',
            'description' => 'Retrieve scene metadata, characters, and adjacent scenes.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'scene_id' => [
                        'type'        => 'integer',
                        'description' => 'Scene post ID.',
                    ],
                ],
                'required' => ['scene_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'scene'     => ['type' => 'object'],
                    'characters'=> ['type' => 'array'],
                    'previous'  => ['type' => 'object'],
                    'next'      => ['type' => 'object'],
                ],
            ],
            'execute_callback' => function( $input ) {
                return \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_scene_context(
                    (int) $input['scene_id']
                );
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'resource' ],
                'uri'    => 'storyos://scene/{scene_id}',
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );
    }
}

/**
 * Prompt abilities for MCP prompt templates.
 *
 * Provides structured prompt templates for common AI tasks.
 */
class Prompt_Templates extends AbstractAbilityGroup {
    protected $slug       = 'storyos-prompts';
    protected $label      = 'Prompt Templates';
    protected $description = 'Structured prompt templates for story review and continuity checking.';

    public function register(): void {
        // storyos/story-review-prompt - Story review prompt template.
        $this->register_ability( 'storyos/story-review-prompt', [
            'label'       => 'Story Review Prompt',
            'description' => 'Generate a structured prompt for AI story review and feedback.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Post ID to review.',
                    ],
                    'focus' => [
                        'type'        => 'string',
                        'description' => 'Review focus: dialogue, plot, pacing, character (optional).',
                    ],
                ],
                'required' => ['post_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'system_prompt' => ['type' => 'string'],
                    'user_prompt'   => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function( $input ) {
                $context = \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_post_context(
                    (int) $input['post_id']
                );
                $post = get_post( (int) $input['post_id'] );
                $focus = isset( $input['focus'] ) ? $input['focus'] : 'story';

                $system_prompt = "You are a story review expert using the StoryOS framework. "
                    . "Review the content for narrative quality, structure, and consistency.";

                $user_prompt = "Review this content with a focus on: {$focus}\n\n"
                    . "Title: {$post->post_title}\n\n"
                    . "Content:\n{$post->post_content}\n\n";

                if ( ! empty( $context ) ) {
                    $user_prompt .= "Story Graph Context:\n"
                        . \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_context_for_llm( $context ) . "\n\n";
                }

                return [
                    'system_prompt' => $system_prompt,
                    'user_prompt'   => $user_prompt,
                ];
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'prompt' ],
                'arguments' => [
                    [
                        'name'        => 'post_id',
                        'description' => 'Post ID to review.',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'focus',
                        'description' => 'Review focus area.',
                        'type'        => 'string',
                        'required'    => false,
                    ],
                ],
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );

        // storyos/continuity-prompt - Continuity check prompt template.
        $this->register_ability( 'storyos/continuity-prompt', [
            'label'       => 'Continuity Check Prompt',
            'description' => 'Generate a structured prompt for AI continuity checking.',
            'input_schema' => [
                'type'  => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Post ID to check.',
                    ],
                ],
                'required' => ['post_id'],
            ],
            'output_schema' => [
                'type'  => 'object',
                'properties' => [
                    'system_prompt' => ['type' => 'string'],
                    'user_prompt'   => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function( $input ) {
                $context = \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_post_context(
                    (int) $input['post_id']
                );
                $post = get_post( (int) $input['post_id'] );

                $system_prompt = "You are a continuity expert using the StoryOS framework. "
                    . "Check the content for continuity errors against the Story Graph data.";

                $user_prompt = "Check this content for continuity errors:\n\n"
                    . "Title: {$post->post_title}\n\n"
                    . "Content:\n{$post->post_content}\n\n";

                if ( ! empty( $context ) ) {
                    $user_prompt .= "Story Graph Context:\n"
                        . \StoryOS\AI\Editor\AI_Context_Builder::instance()->build_context_for_llm( $context ) . "\n\n";
                }

                return [
                    'system_prompt' => $system_prompt,
                    'user_prompt'   => $user_prompt,
                ];
            },
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta' => [
                'public' => true,
                'mcp'    => [ 'type' => 'prompt' ],
                'arguments' => [
                    [
                        'name'        => 'post_id',
                        'description' => 'Post ID to check.',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                ],
                'annotations' => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
        ] );
    }
}

/**
 * Main StoryOS Abilities class.
 *
 * Registers the StoryOS AI Editor category and all ability groups.
 * Follows the SCF pattern of group-based registration.
 */
class Abilities {
    /**
     * Ability group instances.
     *
     * @var array
     */
    private $ability_groups = [];

    /**
     * Singleton instance.
     *
     * @var Abilities
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Abilities
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton pattern).
     */
    private function __construct() {
        // Register ability groups.
        $this->ability_groups = [
            new Chat_Abilities(),
            new Context_Resources(),
            new Prompt_Templates(),
        ];
    }

    /**
     * Initialize abilities registration.
     *
     * Hooked into 'init' action.
     */
    public function init(): void {
        // Register the StoryOS AI Editor category.
        $this->register_category();

        // Register all ability groups.
        foreach ( $this->ability_groups as $group ) {
            $group->register();
        }
    }

    /**
     * Register the StoryOS AI Editor ability category.
     *
     * @return WP_Error|int Result of wp_register_ability_category.
     */
    private function register_category() {
        return \wp_register_ability_category( 'storyos-ai-editor', [
            'label'       => 'StoryOS AI Editor',
            'description' => 'Abilities for AI-powered story editing, content generation, and continuity checking.',
            'meta'        => [
                'public' => true,
            ],
        ] );
    }

    /**
     * Get registered ability groups.
     *
     * @return array
     */
    public function get_groups(): array {
        return $this->ability_groups;
    }

    /**
     * Get a specific ability group by slug.
     *
     * @param string $slug Group slug.
     * @return AbstractAbilityGroup|null
     */
    public function get_group( string $slug ): ?AbstractAbilityGroup {
        foreach ( $this->ability_groups as $group ) {
            if ( $group->get_slug() === $slug ) {
                return $group;
            }
        }
        return null;
    }
}
