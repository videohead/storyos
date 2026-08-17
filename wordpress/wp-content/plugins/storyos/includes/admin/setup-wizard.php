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
	}

	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
			'Setup StoryOS',
			'Setup',
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

		$comfy_mode = sanitize_key( $_POST['storyos_comfy_connection_mode'] ?? 'none' );
		if ( ! in_array( $comfy_mode, [ 'cloud', 'local_mcp', 'none' ], true ) ) {
			$comfy_mode = 'none';
		}
		update_option( 'storyos_comfy_connection_mode', $comfy_mode );

		if ( ! defined( 'STORYOS_COMFY_API_KEY' ) && isset( $_POST['storyos_comfy_api_key'] ) ) {
			update_option( 'storyos_comfy_api_key', sanitize_text_field( wp_unslash( $_POST['storyos_comfy_api_key'] ) ) );
		}

		$backend = sanitize_key( $_POST['storyos_ai_backend'] ?? 'openai_compatible' );
		if ( ! in_array( $backend, [ 'openai_compatible', 'openai', 'anthropic' ], true ) ) {
			$backend = 'openai_compatible';
		}
		update_option( 'storyos_ai_backend', $backend );
		update_option( 'storyos_ai_url', esc_url_raw( wp_unslash( $_POST['storyos_ai_url'] ?? '' ) ) );
		update_option( 'storyos_ai_model', sanitize_text_field( wp_unslash( $_POST['storyos_ai_model'] ?? '' ) ) );
		if ( ! defined( 'STORYOS_AI_API_KEY' ) && isset( $_POST['storyos_ai_api_key'] ) ) {
			update_option( 'storyos_ai_api_key', sanitize_text_field( wp_unslash( $_POST['storyos_ai_api_key'] ) ) );
		}
		update_option( 'storyos_setup_complete', true );

		wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-setup', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render(): void {
		$comfy_mode = get_option( 'storyos_comfy_connection_mode', 'none' );
		$backend = get_option( 'storyos_ai_backend', 'openai_compatible' );
		?>
		<div class="wrap">
			<h1>Set Up StoryOS</h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p>StoryOS connections saved.</p></div>
			<?php endif; ?>
			<p>StoryOS requires a WordPress.org host or local Docker/Lando deployment, plus an API-connected LLM. ComfyUI is optional for story work; select a connection to enable generation workflows.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="storyos_save_setup" />
				<?php wp_nonce_field( 'storyos_save_setup' ); ?>
				<h2>1. WordPress Runtime</h2>
				<p>WordPress is connected. For production, configure a host scheduler to run WP-Cron. Local Lando users can run <code>lando wp-cron</code>.</p>
				<h2>2. Generation Connection</h2>
				<p><label><input type="radio" name="storyos_comfy_connection_mode" value="cloud" <?php checked( $comfy_mode, 'cloud' ); ?> /> Comfy Cloud MCP</label><br />
				<label><input type="radio" name="storyos_comfy_connection_mode" value="local_mcp" <?php checked( $comfy_mode, 'local_mcp' ); ?> /> Local ComfyUI through an MCP client</label><br />
				<label><input type="radio" name="storyos_comfy_connection_mode" value="none" <?php checked( $comfy_mode, 'none' ); ?> /> No ComfyUI connection yet</label></p>
				<p><label for="storyos_comfy_api_key">Comfy Cloud API Key</label><br />
				<input type="password" class="regular-text" name="storyos_comfy_api_key" id="storyos_comfy_api_key" value="<?php echo esc_attr( get_option( 'storyos_comfy_api_key' ) ); ?>" <?php disabled( defined( 'STORYOS_COMFY_API_KEY' ) ); ?> />
				<?php if ( defined( 'STORYOS_COMFY_API_KEY' ) ) : ?> Configured through the deployment environment.<?php endif; ?></p>
				<p class="description">Local <code>comfy-mcp</code> is configured in an MCP-compatible desktop or coding agent, not in WordPress. It cannot be called directly by PHP.</p>
				<h2>3. LLM Connection</h2>
				<p>An API-connected LLM is required for StoryOS agents. Browser-only ChatGPT, Claude, or Claude Code subscriptions are not supported by this server integration.</p>
				<p><label for="storyos_ai_backend">Provider</label><br /><select name="storyos_ai_backend" id="storyos_ai_backend">
					<option value="openai_compatible" <?php selected( $backend, 'openai_compatible' ); ?>>OpenAI-compatible local or hosted LLM</option>
					<option value="openai" <?php selected( $backend, 'openai' ); ?>>OpenAI API</option>
					<option value="anthropic" <?php selected( $backend, 'anthropic' ); ?>>Anthropic API</option>
				</select></p>
				<p><label for="storyos_ai_url">Compatible Base URL</label><br /><input type="url" class="regular-text" name="storyos_ai_url" id="storyos_ai_url" value="<?php echo esc_attr( get_option( 'storyos_ai_url', 'http://localhost:11434/v1' ) ); ?>" /> <span class="description">For llama.cpp, Ollama, vLLM, LM Studio, or another `/v1` endpoint.</span></p>
				<p><label for="storyos_ai_model">Model</label><br /><input type="text" class="regular-text" name="storyos_ai_model" id="storyos_ai_model" value="<?php echo esc_attr( get_option( 'storyos_ai_model', '' ) ); ?>" /></p>
				<p><label for="storyos_ai_api_key">LLM API Key</label><br /><input type="password" class="regular-text" name="storyos_ai_api_key" id="storyos_ai_api_key" value="<?php echo esc_attr( get_option( 'storyos_ai_api_key' ) ); ?>" <?php disabled( defined( 'STORYOS_AI_API_KEY' ) ); ?> />
				<?php if ( defined( 'STORYOS_AI_API_KEY' ) ) : ?> Configured through the deployment environment.<?php endif; ?></p>
				<?php submit_button( 'Save Connections' ); ?>
			</form>
		</div>
		<?php
	}
}