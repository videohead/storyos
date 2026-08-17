<?php
/**
 * AI Editor — Main module class.
 *
 * Bootstraps the AI Editor subsystem: REST endpoints, admin UI, Gutenberg panel,
 * LLM client, MAF bridge, agent router, and agent-skills loader.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main AI Editor class.
 */
class AI_Editor {

	/**
	 * LLM client instance.
	 *
	 * @var AI_LLM_Client
	 */
	private $llm_client;

	/**
	 * MAF bridge instance.
	 *
	 * @var AI_MAF_Bridge
	 */
	private $maf_bridge;

	/**
	 * Context builder instance.
	 *
	 * @var AI_Context_Builder
	 */
	private $context_builder;

	/**
	 * Agent router instance.
	 *
	 * @var AI_Agent_Router
	 */
	private $agent_router;

	/**
	 * Agent skills loader instance.
	 *
	 * @var AI_Agent_Skills
	 */
	private $agent_skills;

	/**
	 * Initialize the AI Editor module.
	 *
	 * @return void
	 */
	public static function init(): void {
		$instance = new self();
		add_action( 'rest_api_init', [ $instance, 'register_rest_routes' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_filter( 'storyos_rest_context', [ __CLASS__, 'add_ai_context' ], 10, 2 );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->llm_client     = new AI_LLM_Client();
		$this->maf_bridge     = new AI_MAF_Bridge( $this->llm_client );
		$this->context_builder = new AI_Context_Builder();
		$this->agent_router   = new AI_Agent_Router();
		$this->agent_skills   = new AI_Agent_Skills();
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$controller = new AI_Editor_REST();
		$controller->register_routes();
	}

	/**
	 * Register WordPress settings.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting( 'storyos_ai', 'storyos_comfy_api_key', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_backend', [
			'type'              => 'string',
			'default'           => 'openai_compatible',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_url', [
			'type'              => 'string',
			'default'           => 'http://localhost:11434/v1',
			'sanitize_callback' => 'esc_url_raw',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_api_key', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_model', [
			'type'              => 'string',
			'default'           => 'qwen3.6:35b-a3b-q4_K_M',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_max_tokens', [
			'type'              => 'integer',
			'default'           => 4096,
			'sanitize_callback' => 'absint',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_temperature', [
			'type'              => 'number',
			'default'           => 0.7,
			'sanitize_callback' => 'floatval',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_fallback_enabled', [
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_fallback_backend', [
			'type'              => 'string',
			'default'           => 'openai',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_fallback_api_key', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_rate_limit', [
			'type'              => 'integer',
			'default'           => 10,
			'sanitize_callback' => 'absint',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_cache_ttl', [
			'type'              => 'integer',
			'default'           => 3600,
			'sanitize_callback' => 'absint',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_agent_skills_path', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_enabled_agents', [
			'type'              => 'string',
			'default'           => 'story,prompt,production,technical,editorial',
			'sanitize_callback' => 'sanitize_text_field',
		] );
	}

	/**
	 * Add AI Settings page to admin menu.
	 *
	 * @return void
	 */
	public static function add_settings_page(): void {
		add_submenu_page(
			'storyos',
			'AI Settings',
			'AI Settings',
			'manage_options',
			'storyos-ai-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Render the AI Settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1>StoryOS AI Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'storyos_ai' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="storyos_comfy_api_key">Comfy Cloud API Key</label></th>
						<td>
							<input type="password" name="storyos_comfy_api_key" id="storyos_comfy_api_key" value="<?php echo esc_attr( get_option( 'storyos_comfy_api_key' ) ); ?>" class="regular-text" <?php disabled( defined( 'STORYOS_COMFY_API_KEY' ) ); ?> />
							<?php if ( defined( 'STORYOS_COMFY_API_KEY' ) ) : ?>
								<p class="description">Configured through the `STORYOS_COMFY_API_KEY` environment variable.</p>
							<?php else : ?>
								<p class="description">Used by WordPress WP-Cron batches to call Comfy Cloud MCP. An environment variable is preferred for deployed sites.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Local ComfyUI MCP</th>
						<td><p class="description">Connect local `comfy-mcp` from an MCP-compatible desktop or coding agent. It is a local stdio service and is not configured in WordPress.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_backend">LLM Backend</label></th>
						<td>
							<select name="storyos_ai_backend" id="storyos_ai_backend">
								<option value="openai_compatible" <?php selected( get_option( 'storyos_ai_backend' ), 'openai_compatible' ); ?>>OpenAI-Compatible / Local LLM</option>
								<option value="openai" <?php selected( get_option( 'storyos_ai_backend' ), 'openai' ); ?>>OpenAI API</option>
								<option value="anthropic" <?php selected( get_option( 'storyos_ai_backend' ), 'anthropic' ); ?>>Anthropic API</option>
								<option value="dual" <?php selected( get_option( 'storyos_ai_backend' ), 'dual' ); ?>>Dual (Local + Fallback)</option>
							</select>
							<p class="description">Use OpenAI-compatible for Ollama, vLLM, LM Studio, OpenRouter, or another compatible BYOK endpoint.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_url">OpenAI-Compatible Base URL</label></th>
						<td>
							<input type="url" name="storyos_ai_url" id="storyos_ai_url" value="<?php echo esc_attr( get_option( 'storyos_ai_url' ) ); ?>" class="regular-text" />
							<p class="description">Examples: http://host.docker.internal:11434/v1, http://host.docker.internal:1234/v1, or a compatible hosted endpoint.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_model">Model</label></th>
						<td>
							<input type="text" name="storyos_ai_model" id="storyos_ai_model" value="<?php echo esc_attr( get_option( 'storyos_ai_model' ) ); ?>" class="regular-text" />
							<p class="description">Model name to use (default: qwen3.6:35b-a3b-q4_K_M)</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_api_key">API Key</label></th>
						<td>
							<input type="password" name="storyos_ai_api_key" id="storyos_ai_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_api_key' ) ); ?>" class="regular-text" />
							<p class="description">API key for cloud LLM providers (leave blank for local).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_max_tokens">Max Tokens</label></th>
						<td>
							<input type="number" name="storyos_ai_max_tokens" id="storyos_ai_max_tokens" value="<?php echo esc_attr( get_option( 'storyos_ai_max_tokens' ) ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_temperature">Temperature</label></th>
						<td>
							<input type="number" name="storyos_ai_temperature" id="storyos_ai_temperature" value="<?php echo esc_attr( get_option( 'storyos_ai_temperature' ) ); ?>" step="0.1" min="0" max="1" class="small-text" />
							<p class="description">Creativity setting (0.0 = deterministic, 1.0 = creative).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_fallback_enabled">Enable Cloud Fallback</label></th>
						<td>
							<input type="checkbox" name="storyos_ai_fallback_enabled" id="storyos_ai_fallback_enabled" value="1" <?php checked( get_option( 'storyos_ai_fallback_enabled' ), true ); ?> />
							<p class="description">Fall back to cloud LLM if local instance is unavailable.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_fallback_backend">Fallback Backend</label></th>
						<td>
							<select name="storyos_ai_fallback_backend" id="storyos_ai_fallback_backend">
								<option value="openai" <?php selected( get_option( 'storyos_ai_fallback_backend' ), 'openai' ); ?>>OpenAI</option>
								<option value="anthropic" <?php selected( get_option( 'storyos_ai_fallback_backend' ), 'anthropic' ); ?>>Anthropic</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_fallback_api_key">Fallback API Key</label></th>
						<td>
							<input type="password" name="storyos_ai_fallback_api_key" id="storyos_ai_fallback_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_fallback_api_key' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_rate_limit">Rate Limit (requests/min)</label></th>
						<td>
							<input type="number" name="storyos_ai_rate_limit" id="storyos_ai_rate_limit" value="<?php echo esc_attr( get_option( 'storyos_ai_rate_limit' ) ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_cache_ttl">Cache TTL (seconds)</label></th>
						<td>
							<input type="number" name="storyos_ai_cache_ttl" id="storyos_ai_cache_ttl" value="<?php echo esc_attr( get_option( 'storyos_ai_cache_ttl' ) ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_agent_skills_path">Agent Skills Path</label></th>
						<td>
							<input type="text" name="storyos_ai_agent_skills_path" id="storyos_ai_agent_skills_path" value="<?php echo esc_attr( get_option( 'storyos_ai_agent_skills_path' ) ); ?>" class="regular-text" />
							<p class="description">Path to WordPress/agent-skills directory (e.g., /path/to/agent-skills/skills).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_enabled_agents">Enabled Agents</label></th>
						<td>
							<input type="text" name="storyos_ai_enabled_agents" id="storyos_ai_enabled_agents" value="<?php echo esc_attr( get_option( 'storyos_ai_enabled_agents' ) ); ?>" class="regular-text" />
							<p class="description">Comma-separated agent names (default: story,prompt,production,technical,editorial).</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue Gutenberg block editor assets.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		$asset_file = include STORYOS_PLUGIN_DIR . 'assets/ai-editor/js/ai-editor.asset.php';

		wp_enqueue_script(
			'storyos-ai-editor',
			STORYOS_PLUGIN_URL . 'assets/ai-editor/js/ai-editor.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'storyos-ai-editor',
			STORYOS_PLUGIN_URL . 'assets/ai-editor/css/ai-editor.css',
			[],
			$asset_file['version']
		);

		wp_localize_script( 'storyos-ai-editor', 'storyosAI', [
			'restUrl'   => rest_url( 'storyos/v1' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'backend'   => get_option( 'storyos_ai_backend', 'local' ),
			'model'     => get_option( 'storyos_ai_model', 'qwen3.6:35b-a3b-q4_K_M' ),
			'maxTokens' => get_option( 'storyos_ai_max_tokens', 4096 ),
			'temperature' => get_option( 'storyos_ai_temperature', 0.7 ),
		] );
	}

	/**
	 * Enqueue admin assets for AI Settings page.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_storyos-ai-settings' !== $hook && 'settings_page_storyos-ai-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-components' );
	}

	/**
	 * Add AI-related data to StoryOS REST context.
	 *
	 * @param array  $context Existing context.
	 * @param string $post_type Post type being queried.
	 * @return array Modified context.
	 */
	public static function add_ai_context( array $context, string $post_type ): array {
		$context['ai_enabled'] = true;
		$context['ai_backend'] = get_option( 'storyos_ai_backend', 'local' );
		return $context;
	}
}
