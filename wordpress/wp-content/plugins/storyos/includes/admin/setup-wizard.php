<?php
/**
 * StoryOS connection setup wizard.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setup_Wizard {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_storyos_save_setup', [ __CLASS__, 'save' ] );
		add_action( 'wp_ajax_storyos_test_llm_connection', [ __CLASS__, 'test_llm_connection' ] );
		add_action( 'wp_ajax_storyos_test_comfy_connection', [ __CLASS__, 'test_comfy_connection' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_after_activation' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_to_setup' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_setup_notice' ] );
	}

	/**
	 * One-time redirect straight to the setup wizard after plugin activation.
	 */
	public static function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'storyos_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'storyos_activation_redirect' );

		if ( wp_doing_ajax() || wp_doing_cron() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=storyos-setup' ) );
		exit;
	}

	/**
	 * Whether the first-run connection setup has been completed.
	 */
	public static function is_setup_complete(): bool {
		return (bool) get_option( 'storyos_setup_complete', false );
	}

	/**
	 * Force admins to the setup screen until StoryOS connections have been configured.
	 */
	public static function maybe_redirect_to_setup(): void {
		if ( self::is_setup_complete() ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Never intercept admin-post/admin-ajax handlers (e.g. saving the setup form itself).
		$script = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( in_array( $script, [ 'admin-post.php', 'admin-ajax.php', 'plugins.php', 'update.php', 'update-core.php' ], true ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'storyos-setup' === $page ) {
			return;
		}

		// Only gate StoryOS's own admin screens; leave the rest of wp-admin alone.
		if ( '' === $page || ! str_starts_with( $page, 'storyos' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=storyos-setup&required=1' ) );
		exit;
	}

	/**
	 * Show a persistent reminder on StoryOS admin screens until setup is complete.
	 */
	public static function render_setup_notice(): void {
		if ( self::is_setup_complete() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'storyos-setup' === $page ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong>StoryOS</strong> requires a one-time connection setup (local LLM and, optionally, ComfyUI) before use. <a href="<?php echo esc_url( admin_url( 'admin.php?page=storyos-setup' ) ); ?>">Complete setup now</a></p>
		</div>
		<?php
	}

	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
			'Setup StoryOS',
			'Setup & Settings',
			'manage_options',
			'storyos-setup',
			[ __CLASS__, 'render' ]
		);
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to configure StoryOS.' );
		}
		check_admin_referer( 'storyos_save_setup' );

		// Comfy Cloud MCP Configuration
		$comfy_mode = sanitize_key( $_POST['storyos_comfy_connection_mode'] ?? 'none' );
		if ( ! in_array( $comfy_mode, [ 'cloud', 'local_mcp', 'none' ], true ) ) {
			$comfy_mode = 'none';
		}
		update_option( 'storyos_comfy_connection_mode', $comfy_mode );

		if ( ! defined( 'STORYOS_COMFY_API_KEY' ) && isset( $_POST['storyos_comfy_api_key'] ) ) {
			update_option( 'storyos_comfy_api_key', sanitize_text_field( wp_unslash( $_POST['storyos_comfy_api_key'] ) ) );
		}
		if ( isset( $_POST['storyos_comfy_local_url'] ) ) {
			update_option( 'storyos_comfy_local_url', esc_url_raw( wp_unslash( $_POST['storyos_comfy_local_url'] ) ) );
		}
		if ( isset( $_POST['storyos_comfy_local_workflow'] ) ) {
			$workflow = json_decode( wp_unslash( $_POST['storyos_comfy_local_workflow'] ), true );
			update_option( 'storyos_comfy_local_workflow', is_array( $workflow ) && ! empty( $workflow ) ? wp_json_encode( $workflow ) : '' );
		}

		// Primary LLM Configuration
		$backend = sanitize_key( $_POST['storyos_ai_backend'] ?? 'openai_compatible' );
		if ( ! in_array( $backend, [ 'openai_compatible', 'openai', 'anthropic', 'dual' ], true ) ) {
			$backend = 'openai_compatible';
		}
		update_option( 'storyos_ai_backend', $backend );
		update_option( 'storyos_ai_url', esc_url_raw( wp_unslash( $_POST['storyos_ai_url'] ?? '' ) ) );
		update_option( 'storyos_ai_model', sanitize_text_field( wp_unslash( $_POST['storyos_ai_model'] ?? '' ) ) );
		if ( ! defined( 'STORYOS_AI_API_KEY' ) && isset( $_POST['storyos_ai_api_key'] ) ) {
			update_option( 'storyos_ai_api_key', sanitize_text_field( wp_unslash( $_POST['storyos_ai_api_key'] ) ) );
		}

		// Advanced LLM Configuration
		if ( isset( $_POST['storyos_ai_max_tokens'] ) ) {
			update_option( 'storyos_ai_max_tokens', absint( wp_unslash( $_POST['storyos_ai_max_tokens'] ) ) );
		}
		if ( isset( $_POST['storyos_ai_temperature'] ) ) {
			update_option( 'storyos_ai_temperature', floatval( wp_unslash( $_POST['storyos_ai_temperature'] ) ) );
		}

		// Fallback LLM Configuration
		$fallback_backend = sanitize_key( $_POST['storyos_ai_fallback_backend'] ?? 'openai' );
		if ( ! in_array( $fallback_backend, [ 'openai', 'anthropic' ], true ) ) {
			$fallback_backend = 'openai';
		}
		update_option( 'storyos_ai_fallback_backend', $fallback_backend );

		if ( ! defined( 'STORYOS_AI_FALLBACK_API_KEY' ) && isset( $_POST['storyos_ai_fallback_api_key'] ) ) {
			update_option( 'storyos_ai_fallback_api_key', sanitize_text_field( wp_unslash( $_POST['storyos_ai_fallback_api_key'] ) ) );
		}

		update_option( 'storyos_setup_complete', true );

		wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-setup', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Test the LLM values currently entered in the setup form.
	 *
	 * @return void
	 */
	public static function test_llm_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to test this connection.' ], 403 );
		}

		check_ajax_referer( 'storyos_test_llm_connection', 'nonce' );

		$backend = sanitize_key( $_POST['backend'] ?? 'openai_compatible' );
		if ( ! in_array( $backend, [ 'openai_compatible', 'openai', 'anthropic', 'dual' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Unsupported LLM backend.' ], 400 );
		}

		$configuration = [
			'backend' => $backend,
			'url'     => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'model'   => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
			'api_key' => defined( 'STORYOS_AI_API_KEY' ) ? \STORYOS_AI_API_KEY : sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
		];

		$result = ( new \StoryOS\AI\AI_LLM_Client() )->test_connection( $configuration );
		if ( empty( $result['healthy'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ?? 'Unable to reach the LLM endpoint.' ] );
		}

		wp_send_json_success( [
			'message' => ! empty( $result['url'] ) ? sprintf( 'Connected to %s.', $result['url'] ) : 'Provider credentials are configured.',
			'models'  => array_values( $result['models'] ?? [] ),
		] );
	}

	/**
	 * Test a local ComfyUI server from the WordPress container.
	 *
	 * @return void
	 */
	public static function test_comfy_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to test this connection.' ], 403 );
		}

		check_ajax_referer( 'storyos_test_comfy_connection', 'nonce' );
		$url = untrailingslashit( esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ) );
		if ( '' === $url ) {
			wp_send_json_error( [ 'message' => 'Enter a local ComfyUI API URL first.' ], 400 );
		}

		$response = wp_remote_get( $url . '/system_stats', [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => sprintf( 'Unable to reach ComfyUI: %s', $response->get_error_message() ) ] );
		}
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
			wp_send_json_error( [ 'message' => sprintf( 'ComfyUI returned HTTP %d from /system_stats.', wp_remote_retrieve_response_code( $response ) ) ] );
		}

		wp_send_json_success( [ 'message' => sprintf( 'Connected to ComfyUI at %s.', $url ) ] );
	}

	public static function render(): void {
		$comfy_mode = get_option( 'storyos_comfy_connection_mode', 'none' );
		$backend = get_option( 'storyos_ai_backend', 'openai_compatible' );
		$fallback_backend = get_option( 'storyos_ai_fallback_backend', 'openai' );
		?>
		<div class="wrap">
			<h1>Set Up StoryOS</h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p>StoryOS connections saved.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['required'] ) ) : ?>
				<div class="notice notice-warning"><p>Please review the connection settings below before continuing. Submitting this form (even with fields left blank) completes setup.</p></div>
			<?php endif; ?>
			<p>StoryOS requires a WordPress.org host or local Docker/Lando deployment. An API-connected LLM enables the StoryOS agents, while ComfyUI is optional for media generation.</p>
			<div class="notice notice-info inline">
				<p><strong>Do not have API access?</strong> You can still use StoryOS to develop stories, manage characters and scenes, track continuity, and organize media. Generate content in a browser-based service using its own web app, download the result, and attach it to a StoryOS post as the featured asset or in its asset gallery. Browser subscriptions and web login credentials cannot be used as server API credentials.</p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="storyos_save_setup" />
				<?php wp_nonce_field( 'storyos_save_setup' ); ?>
				<h2>1. WordPress Runtime</h2>
				<p>WordPress is connected. For production, configure a host scheduler to run WP-Cron. Local Lando users can run <code>lando wp-cron</code>.</p>
				<h2>2. Generation Connection (Optional)</h2>
				<p><label><input type="radio" name="storyos_comfy_connection_mode" value="cloud" <?php checked( $comfy_mode, 'cloud' ); ?> /> Comfy Cloud MCP</label><br />
				<label><input type="radio" name="storyos_comfy_connection_mode" value="local_mcp" <?php checked( $comfy_mode, 'local_mcp' ); ?> /> Local ComfyUI HTTP API</label><br />
				<label><input type="radio" name="storyos_comfy_connection_mode" value="none" <?php checked( $comfy_mode, 'none' ); ?> /> No ComfyUI connection yet</label></p>
				<p><label for="storyos_comfy_api_key">Comfy Cloud API Key</label><br />
				<input type="password" class="regular-text" name="storyos_comfy_api_key" id="storyos_comfy_api_key" value="<?php echo esc_attr( get_option( 'storyos_comfy_api_key' ) ); ?>" <?php disabled( defined( 'STORYOS_COMFY_API_KEY' ) ); ?> />
				<?php if ( defined( 'STORYOS_COMFY_API_KEY' ) ) : ?> <span class="description">Configured through the deployment environment.</span><?php endif; ?></p>
				<p><label for="storyos_comfy_local_url">Local ComfyUI API URL</label><br />
				<input type="url" class="regular-text" name="storyos_comfy_local_url" id="storyos_comfy_local_url" value="<?php echo esc_attr( get_option( 'storyos_comfy_local_url', 'http://host.docker.internal:8188' ) ); ?>" placeholder="http://host.docker.internal:8188" /> <span class="description">For ComfyUI running on the Lando host, use <code>http://host.docker.internal:8188</code>; do not use <code>localhost</code>.</span></p>
				<p><button type="button" class="button" id="storyos-test-comfy-connection">Test ComfyUI</button> <span id="storyos-comfy-test-result" aria-live="polite"></span></p>
				<p><label for="storyos_comfy_local_workflow">Local ComfyUI API Workflow</label><br />
				<textarea class="large-text code" name="storyos_comfy_local_workflow" id="storyos_comfy_local_workflow" rows="10"><?php echo esc_textarea( get_option( 'storyos_comfy_local_workflow', '' ) ); ?></textarea><br />
				<span class="description">In ComfyUI, export with “Save (API Format)”, replace the positive prompt text with <code>{{prompt}}</code>, then paste the JSON here.</span></p>
				<p class="description">Choose <strong>No ComfyUI connection yet</strong> when using a browser-based generator or when you only need StoryOS for writing, planning, and asset management.</p>
				<h2>3. LLM Connection (Required for AI Agents)</h2>
				<p>An API-connected LLM is required for StoryOS agents. Browser-only ChatGPT, Claude, or Claude Code subscriptions are not supported by this server integration. Without one, leave these fields empty and use StoryOS for story data, WordPress media, and external-generation asset tracking.</p>
				
				<h3>Primary LLM Configuration</h3>
				<p><label for="storyos_ai_backend">Provider</label><br /><select name="storyos_ai_backend" id="storyos_ai_backend">
					<option value="openai_compatible" <?php selected( $backend, 'openai_compatible' ); ?>>OpenAI-compatible local or hosted LLM</option>
					<option value="openai" <?php selected( $backend, 'openai' ); ?>>OpenAI API</option>
					<option value="anthropic" <?php selected( $backend, 'anthropic' ); ?>>Anthropic API</option>
					<option value="dual" <?php selected( $backend, 'dual' ); ?>>Dual (Local + Fallback Cloud)</option>
				</select></p>
				<p><label for="storyos_ai_url">Base URL or Endpoint</label><br /><input type="url" class="regular-text" name="storyos_ai_url" id="storyos_ai_url" value="<?php echo esc_attr( get_option( 'storyos_ai_url', 'http://host.docker.internal:11434/v1' ) ); ?>" /> <span class="description">For llama.cpp, Ollama, vLLM, LM Studio, or another `/v1` endpoint. In Docker or Lando, use <code>host.docker.internal</code> for an LLM running on the development host; <code>localhost</code> refers to the WordPress container. Leave blank if using OpenAI or Anthropic.</span></p>
				<p><label for="storyos_ai_model">Model Name</label><br /><input type="text" class="regular-text" name="storyos_ai_model" id="storyos_ai_model" list="storyos-ai-models" value="<?php echo esc_attr( get_option( 'storyos_ai_model', '' ) ); ?>" /> <datalist id="storyos-ai-models"></datalist> <span class="description">Examples: gpt-4, claude-3-sonnet, or local model name. Testing a local endpoint loads its available models.</span></p>
				<p><label for="storyos_ai_api_key">API Key / Token</label><br /><input type="password" class="regular-text" name="storyos_ai_api_key" id="storyos_ai_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_api_key' ) ); ?>" <?php disabled( defined( 'STORYOS_AI_API_KEY' ) ); ?> />
				<?php if ( defined( 'STORYOS_AI_API_KEY' ) ) : ?> <span class="description">Configured through the deployment environment.</span><?php else : ?> <span class="description">Required for hosted providers and some local servers.</span><?php endif; ?></p>
				<p><button type="button" class="button" id="storyos-test-llm-connection">Test LLM Connection</button> <span id="storyos-llm-test-result" aria-live="polite"></span></p>
				
				<h3>Advanced LLM Settings (Optional)</h3>
				<p><label for="storyos_ai_max_tokens">Max Tokens</label><br /><input type="number" class="small-text" name="storyos_ai_max_tokens" id="storyos_ai_max_tokens" value="<?php echo esc_attr( get_option( 'storyos_ai_max_tokens', '2048' ) ); ?>" min="256" max="32768" /> <span class="description">Maximum tokens for LLM responses.</span></p>
				<p><label for="storyos_ai_temperature">Temperature</label><br /><input type="number" class="small-text" name="storyos_ai_temperature" id="storyos_ai_temperature" value="<?php echo esc_attr( get_option( 'storyos_ai_temperature', '0.7' ) ); ?>" step="0.1" min="0" max="1" /> <span class="description">Creativity level (0.0 = deterministic, 1.0 = creative).</span></p>
				
				<h3>Fallback LLM (Optional)</h3>
				<p class="description">Configure a backup cloud provider (OpenAI or Anthropic) for failover if your primary LLM becomes unavailable.</p>
				<p><label for="storyos_ai_fallback_backend">Fallback Provider</label><br /><select name="storyos_ai_fallback_backend" id="storyos_ai_fallback_backend">
					<option value="openai" <?php selected( $fallback_backend, 'openai' ); ?>>OpenAI</option>
					<option value="anthropic" <?php selected( $fallback_backend, 'anthropic' ); ?>>Anthropic</option>
				</select></p>
				<p><label for="storyos_ai_fallback_api_key">Fallback API Key</label><br /><input type="password" class="regular-text" name="storyos_ai_fallback_api_key" id="storyos_ai_fallback_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_fallback_api_key' ) ); ?>" <?php disabled( defined( 'STORYOS_AI_FALLBACK_API_KEY' ) ); ?> />
				<?php if ( defined( 'STORYOS_AI_FALLBACK_API_KEY' ) ) : ?> <span class="description">Configured through the deployment environment.</span><?php endif; ?></p>
				
				<h2>4. External Generator Workflow</h2>
				<ol>
					<li>Generate the image, video, audio, or other media in the provider's web application.</li>
					<li>Download the final file and retain the provider, model, prompt, source URL, and usage-rights information.</li>
					<li>On the relevant StoryOS post, use <strong>StoryOS Assets</strong> to set the primary file as the featured asset and add supporting files to the gallery.</li>
					<li>Save the post. The featured asset and gallery are available in the StoryOS API.</li>
				</ol>
				<p class="description">Direct connectors for services such as Sora, Runway, Veo, Kling, Seedance, Firefly, Midjourney, and Amazon video endpoints require additional API discovery and provider-specific implementation.</p>
				<?php submit_button( 'Save All Configurations' ); ?>
			</form>
			<script>
				(function () {
					var button = document.getElementById('storyos-test-llm-connection');
					var result = document.getElementById('storyos-llm-test-result');
					if (!button || !result) {
						return;
					}
					button.addEventListener('click', function () {
						var modelInput = document.getElementById('storyos_ai_model');
						button.disabled = true;
						result.textContent = 'Testing...';
						var data = new URLSearchParams({
							action: 'storyos_test_llm_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'storyos_test_llm_connection' ) ); ?>',
							backend: document.getElementById('storyos_ai_backend').value,
							url: document.getElementById('storyos_ai_url').value,
							model: document.getElementById('storyos_ai_model').value,
							api_key: document.getElementById('storyos_ai_api_key').value
						});
						fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: data })
							.then(function (response) { return response.json(); })
							.then(function (response) {
								var models = response.data && Array.isArray(response.data.models) ? response.data.models : [];
								var modelList = document.getElementById('storyos-ai-models');
								modelList.replaceChildren();
								models.forEach(function (model) {
									modelList.append(new Option(model, model));
								});
								if (response.success && !modelInput.value.trim() && models.length === 1) {
									modelInput.value = models[0];
								}
								result.textContent = response.data && response.data.message ? response.data.message : 'Connection test failed.';
								if (response.success && !modelInput.value.trim() && models.length > 1) {
									result.textContent += ' Select a model from the Model Name field.';
								}
								result.style.color = response.success ? '#008a20' : '#b32d2e';
							})
							.catch(function () {
								result.textContent = 'Connection test could not be completed.';
								result.style.color = '#b32d2e';
							})
							.finally(function () { button.disabled = false; });
					});
					var comfyButton = document.getElementById('storyos-test-comfy-connection');
					var comfyResult = document.getElementById('storyos-comfy-test-result');
					if (comfyButton && comfyResult) {
						comfyButton.addEventListener('click', function () {
							comfyButton.disabled = true;
							comfyResult.textContent = 'Testing...';
							var data = new URLSearchParams({
								action: 'storyos_test_comfy_connection',
								nonce: '<?php echo esc_js( wp_create_nonce( 'storyos_test_comfy_connection' ) ); ?>',
								url: document.getElementById('storyos_comfy_local_url').value
							});
							fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: data })
								.then(function (response) { return response.json(); })
								.then(function (response) {
									comfyResult.textContent = response.data && response.data.message ? response.data.message : 'Connection test failed.';
									comfyResult.style.color = response.success ? '#008a20' : '#b32d2e';
								})
								.catch(function () {
									comfyResult.textContent = 'Connection test could not be completed.';
									comfyResult.style.color = '#b32d2e';
								})
								.finally(function () { comfyButton.disabled = false; });
						});
					}
				}());
			</script>
		</div>
		<?php
	}
}