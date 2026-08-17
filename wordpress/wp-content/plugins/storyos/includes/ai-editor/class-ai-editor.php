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
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_story_element_workflow_metabox' ], 10, 2 );
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

		register_setting( 'storyos_ai', 'storyos_comfy_local_url', [
			'type'              => 'string',
			'default'           => 'http://host.docker.internal:8188',
			'sanitize_callback' => 'esc_url_raw',
		] );

		register_setting( 'storyos_ai', 'storyos_comfy_local_workflow', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => [ __CLASS__, 'sanitize_comfy_workflow' ],
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

		register_setting( 'storyos_ai', 'storyos_ai_image_url', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_image_api_key', [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_image_model', [
			'type'              => 'string',
			'default'           => AI_Image_Client::DEFAULT_MODEL,
			'sanitize_callback' => 'sanitize_text_field',
		] );

		register_setting( 'storyos_ai', 'storyos_ai_image_size', [
			'type'              => 'string',
			'default'           => AI_Image_Client::DEFAULT_SIZE,
			'sanitize_callback' => [ __CLASS__, 'sanitize_image_size' ],
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
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		] );
	}

	/**
	 * Restrict the stored image size to the supported list.
	 *
	 * @param string $value Submitted size.
	 * @return string
	 */
	public static function sanitize_image_size( $value ): string {
		$value = sanitize_text_field( (string) $value );

		return in_array( $value, AI_Image_Client::ALLOWED_SIZES, true ) ? $value : AI_Image_Client::DEFAULT_SIZE;
	}

	/**
	 * Store only valid ComfyUI API-format workflow JSON.
	 *
	 * @param string $value Workflow JSON.
	 * @return string
	 */
	public static function sanitize_comfy_workflow( $value ): string {
		$workflow = json_decode( (string) $value, true );

		return is_array( $workflow ) && ! empty( $workflow ) ? wp_json_encode( $workflow ) : '';
	}

	/**
	 * Add AI Settings page to admin menu.
	 *
	 * @return void
	 */
	public static function add_settings_page(): void {
		add_submenu_page(
			'storyos',
			'StoryOS AI Settings',
			'AI Settings',
			'manage_options',
			'storyos-ai-settings',
			[ __CLASS__, 'redirect_to_setup_page' ]
		);
		remove_submenu_page( 'storyos', 'storyos-ai-settings' );
	}

	/**
	 * Redirect legacy AI settings URLs to the single setup page.
	 *
	 * @return void
	 */
	public static function redirect_to_setup_page(): void {
		$url = admin_url( 'admin.php?page=storyos-setup' );
		if ( isset( $_GET['required'] ) ) {
			$url = add_query_arg( [ 'required' => '1' ], $url );
		}
		wp_safe_redirect( $url );
		exit;
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
						<td>
							<label for="storyos_comfy_local_url">ComfyUI API URL</label><br />
							<input type="url" name="storyos_comfy_local_url" id="storyos_comfy_local_url" value="<?php echo esc_attr( get_option( 'storyos_comfy_local_url', 'http://host.docker.internal:8188' ) ); ?>" class="regular-text" placeholder="http://host.docker.internal:8188" />
							<p class="description">The address reachable from the WordPress container, not the browser's localhost.</p>
							<label for="storyos_comfy_local_workflow">ComfyUI API Workflow</label><br />
							<textarea name="storyos_comfy_local_workflow" id="storyos_comfy_local_workflow" rows="10" class="large-text code"><?php echo esc_textarea( get_option( 'storyos_comfy_local_workflow' ) ); ?></textarea>
							<p class="description">Export the workflow with ComfyUI's “Save (API Format)”, replace the positive prompt text with <code>{{prompt}}</code>, then paste the JSON here. StoryOS posts it to <code>/prompt</code>, polls <code>/history</code>, and imports the generated image.</p>
						</td>
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
							<p class="description">Use OpenAI-compatible for llama.cpp, Ollama, vLLM, LM Studio, OpenRouter, or another compatible BYOK endpoint. Browser-only ChatGPT, Claude, and Claude Code subscriptions are not supported.</p>
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
							<p class="description">Required for hosted providers. A browser subscription without an API key cannot connect to StoryOS; local servers may be left blank only when they do not require authentication.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_image_url">Image Base URL</label></th>
						<td>
							<input type="url" name="storyos_ai_image_url" id="storyos_ai_image_url" value="<?php echo esc_attr( get_option( 'storyos_ai_image_url' ) ); ?>" class="regular-text" />
							<p class="description">OpenAI-compatible base URL for `/images/generations`, used by the Generate Asset tools. Leave blank to reuse the LLM base URL.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_image_model">Image Model</label></th>
						<td>
							<input type="text" name="storyos_ai_image_model" id="storyos_ai_image_model" value="<?php echo esc_attr( get_option( 'storyos_ai_image_model' ) ); ?>" class="regular-text" />
							<p class="description">Text-to-image model name (default: <?php echo esc_html( AI_Image_Client::DEFAULT_MODEL ); ?>).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_image_size">Image Size</label></th>
						<td>
							<select name="storyos_ai_image_size" id="storyos_ai_image_size">
								<?php foreach ( AI_Image_Client::ALLOWED_SIZES as $size ) : ?>
									<option value="<?php echo esc_attr( $size ); ?>" <?php selected( get_option( 'storyos_ai_image_size', AI_Image_Client::DEFAULT_SIZE ), $size ); ?>><?php echo esc_html( $size ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Default size for generated story element images.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="storyos_ai_image_api_key">Image API Key</label></th>
						<td>
							<input type="password" name="storyos_ai_image_api_key" id="storyos_ai_image_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_image_api_key' ) ); ?>" class="regular-text" <?php disabled( defined( 'STORYOS_AI_IMAGE_API_KEY' ) ); ?> />
							<p class="description">Leave blank to reuse the API Key above. The `STORYOS_AI_IMAGE_API_KEY` constant takes precedence when defined.</p>
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
							<p class="description">Comma-separated agent names. Leave blank to enable all agents in the StoryOS agents directory.</p>
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
		if ( 'toplevel_page_storyos-ai-settings' === $hook || 'settings_page_storyos-ai-settings' === $hook ) {
			wp_enqueue_style( 'wp-components' );
		}

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::get_story_element_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'storyos-ai-workflow',
			STORYOS_PLUGIN_URL . 'assets/ai-editor/js/shot-workflow.js',
			[],
			STORYOS_VERSION,
			true
		);

		wp_enqueue_style(
			'storyos-ai-workflow',
			STORYOS_PLUGIN_URL . 'assets/ai-editor/css/shot-workflow.css',
			[],
			STORYOS_VERSION
		);

		wp_localize_script( 'storyos-ai-workflow', 'storyosAIWorkflow', [
			'restUrl' => rest_url( 'storyos/v1' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'postId'  => get_the_ID(),
		] );
	}

	/**
	 * Get CPTs that represent elements of a StoryOS narrative or production.
	 *
	 * Templates and provider connections configure the system; every other
	 * registered StoryOS CPT is a story or production element with graph context.
	 *
	 * @return array<int, string>
	 */
	private static function get_story_element_post_types(): array {
		$cpts = array_keys( \StoryOS\Utils\storyos_get_all_cpts() );

		return array_values( array_diff( $cpts, [ 'storyos_template', 'storyos_connection' ] ) );
	}

	/**
	 * Register the agent workflow in classic story-element editors.
	 *
	 * @param string   $post_type Current post type.
	 * @param \WP_Post $post      Current post.
	 * @return void
	 */
	public static function register_story_element_workflow_metabox( string $post_type, \WP_Post $post ): void {
		if ( ! in_array( $post_type, self::get_story_element_post_types(), true ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_type );
		$label            = $post_type_object ? $post_type_object->labels->singular_name : __( 'Story Element', 'storyos' );

		add_meta_box(
			'storyos_ai_workflow',
			sprintf( __( 'AI %s Workflow', 'storyos' ), $label ),
			[ __CLASS__, 'render_story_element_workflow_metabox' ],
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Render the agent workflow UI for a StoryOS story element.
	 *
	 * @param \WP_Post $post Current Shot post.
	 * @return void
	 */
	public static function render_story_element_workflow_metabox( \WP_Post $post ): void {
		?>
		<div class="storyos-ai-workflow" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<p class="description"><?php esc_html_e( 'Run an agent with this Story Graph element as context. Generated output is a suggestion until you apply it.', 'storyos' ); ?></p>
			<div class="storyos-ai-workflow__controls">
				<label for="storyos-ai-workflow-agent-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Agent', 'storyos' ); ?></label>
				<select class="storyos-ai-workflow__agent" id="storyos-ai-workflow-agent-<?php echo esc_attr( $post->ID ); ?>" disabled>
					<option><?php esc_html_e( 'Loading agents...', 'storyos' ); ?></option>
				</select>
			</div>
			<label for="storyos-ai-workflow-prompt-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Instruction', 'storyos' ); ?></label>
			<textarea class="widefat storyos-ai-workflow__prompt" id="storyos-ai-workflow-prompt-<?php echo esc_attr( $post->ID ); ?>" rows="4" placeholder="<?php esc_attr_e( 'Describe the work you want the agent to do with this story element.', 'storyos' ); ?>"></textarea>
			<div class="storyos-ai-workflow__actions">
				<button type="button" class="button button-primary storyos-ai-workflow__run" data-action="generate"><?php esc_html_e( 'Run agent', 'storyos' ); ?></button>
				<button type="button" class="button storyos-ai-workflow__run" data-action="analyze"><?php esc_html_e( 'Analyze element', 'storyos' ); ?></button>
				<button type="button" class="button storyos-ai-workflow__run" data-action="continuity"><?php esc_html_e( 'Check continuity', 'storyos' ); ?></button>
			</div>
			<div class="storyos-ai-workflow__status" role="status" aria-live="polite"></div>
			<div class="storyos-ai-workflow__result" hidden></div>
		</div>
		<?php
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
