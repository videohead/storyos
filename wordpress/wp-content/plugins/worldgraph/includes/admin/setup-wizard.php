<?php
/**
 * World Graph Studio connection setup wizard.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setup_Wizard {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_worldgraph_save_setup', [ __CLASS__, 'save' ] );
		add_action( 'wp_ajax_worldgraph_test_llm_connection', [ __CLASS__, 'test_llm_connection' ] );
		add_action( 'wp_ajax_worldgraph_test_comfy_connection', [ __CLASS__, 'test_comfy_connection' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_after_activation' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect_to_setup' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_setup_notice' ] );
	}

	/**
	 * One-time redirect straight to the setup wizard after plugin activation.
	 */
	public static function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'worldgraph_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'worldgraph_activation_redirect' );

		if ( wp_doing_ajax() || wp_doing_cron() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=worldgraph-setup' ) );
		exit;
	}

	/**
	 * Whether the first-run connection setup has been completed.
	 */
	public static function is_setup_complete(): bool {
		return (bool) get_option( 'worldgraph_setup_complete', false );
	}

	/**
	 * Force admins to the setup screen until World Graph Studio connections have been configured.
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
		if ( 'worldgraph-setup' === $page ) {
			return;
		}

		// Only gate World Graph Studio's own admin screens; leave the rest of wp-admin alone.
		if ( '' === $page || ! str_starts_with( $page, 'worldgraph' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=worldgraph-setup&required=1' ) );
		exit;
	}

	/**
	 * Show a persistent reminder on World Graph Studio admin screens until setup is complete.
	 */
	public static function render_setup_notice(): void {
		if ( self::is_setup_complete() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'worldgraph-setup' === $page ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong>World Graph Studio</strong> requires a one-time connection setup (an LLM and, optionally, a generation provider) before use. <a href="<?php echo esc_url( admin_url( 'admin.php?page=worldgraph-setup' ) ); ?>">Complete setup now</a></p>
		</div>
		<?php
	}

	public static function add_menu(): void {
		add_submenu_page(
			'worldgraph-administration',
			'Setup World Graph Studio',
			'Setup & Settings',
			'manage_options',
			'worldgraph-setup',
			[ __CLASS__, 'render' ]
		);
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to configure World Graph Studio.' );
		}
		check_admin_referer( 'worldgraph_save_setup' );

		// Preferred generation Connection. Accept the legacy field while older
		// setup forms may still be cached in a browser.
		$comfy_mode = sanitize_key( $_POST['worldgraph_gen_connection_mode'] ?? $_POST['worldgraph_comfy_connection_mode'] ?? 'none' );
		$generation_choice = \WorldGraph\Utils\Connection_Adapters::setup_choice( $comfy_mode );
		if ( 'none' !== $comfy_mode && null === $generation_choice ) {
			$comfy_mode = 'none';
		}
		update_option( 'worldgraph_gen_connection_mode', $comfy_mode );
		// Retained for compatibility with existing installations and extensions.
		update_option( 'worldgraph_comfy_connection_mode', $comfy_mode );

		if ( isset( $_POST['worldgraph_comfy_local_url'] ) ) {
			update_option( 'worldgraph_comfy_local_url', esc_url_raw( wp_unslash( $_POST['worldgraph_comfy_local_url'] ) ) );
			if ( 'local_mcp' === $comfy_mode ) {
				\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );
				\WorldGraph\Utils\Comfy_Bootstrap::flush();
			}
		}
		if ( isset( $_POST['worldgraph_comfy_local_mcp_url'] ) ) {
			update_option( 'worldgraph_comfy_local_mcp_url', esc_url_raw( wp_unslash( $_POST['worldgraph_comfy_local_mcp_url'] ) ) );
		}

		// Populate the "Generation" Connection record from this section.
		$generation_api_key = sanitize_text_field( wp_unslash( $_POST['worldgraph_gen_credential_reference'] ?? '' ) );
		$generation_mcp_api_key = sanitize_text_field( wp_unslash( $_POST['worldgraph_gen_mcp_credential_reference'] ?? '' ) );
		$comfy_local_url = esc_url_raw( wp_unslash( $_POST['worldgraph_comfy_local_url'] ?? '' ) );
		$comfy_local_mcp_url = esc_url_raw( wp_unslash( $_POST['worldgraph_comfy_local_mcp_url'] ?? '' ) );
		if ( 'none' !== $comfy_mode ) {
			$provider_type = sanitize_key( (string) ( $generation_choice['provider_type'] ?? '' ) );
			$is_fal = 'fal' === $provider_type;
			$is_local_comfy = 'comfyui' === $provider_type && 'local' === ( $generation_choice['environment'] ?? '' );
			$provider_endpoint = \WorldGraph\Utils\Connection_Adapters::endpoint( $provider_type );
			$provider_mcp_endpoint = \WorldGraph\Utils\Connection_Adapters::mcp_endpoint( $provider_type );
			if ( '' === $provider_mcp_endpoint && ! empty( $generation_choice['mcp_endpoint'] ) ) {
				$provider_mcp_endpoint = $provider_endpoint;
			}
			\WorldGraph\Utils\Connection_Adapters::load( $provider_type );
			$connection_id = \WorldGraph\CPT\Connection::upsert_managed(
				'generation',
				sprintf( '%s (Setup Wizard)', (string) ( $generation_choice['label'] ?? $provider_type ) ),
				[
					'provider_type'        => $provider_type,
					'environment'          => sanitize_key( (string) ( $generation_choice['environment'] ?? 'production' ) ),
					'endpoint_url'         => $is_local_comfy ? $comfy_local_url : $provider_endpoint,
					'mcp_endpoint_url'     => $is_local_comfy ? $comfy_local_mcp_url : $provider_mcp_endpoint,
					'credential_reference' => $generation_api_key,
					'mcp_credential_reference' => ! empty( $generation_choice['separate_mcp_credential'] ) ? $generation_mcp_api_key : '',
					'status'               => 'unverified',
				]
			);

			// A local ComfyUI has to be able to run text-to-image before any
			// story element can generate an asset, so provision that Template
			// here and let the readiness checklist report what is still missing.
			if ( $is_local_comfy ) {
				\WorldGraph\Utils\Comfy_Bootstrap::ensure_template( $connection_id );
			} elseif ( $is_fal && $connection_id && ! wp_next_scheduled( \WorldGraph\Utils\Fal_Catalog::HOOK, [ $connection_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Fal_Catalog::HOOK, [ $connection_id ] );
			} elseif ( 'elevenlabs' === $provider_type && $connection_id && ! wp_next_scheduled( \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $connection_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $connection_id ] );
			} elseif ( 'suno' === $provider_type && $connection_id && ! wp_next_scheduled( \WorldGraph\Utils\Suno_Catalog::HOOK, [ $connection_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Suno_Catalog::HOOK, [ $connection_id ] );
			} elseif ( 'videodraft' === $provider_type && $connection_id && ! wp_next_scheduled( \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $connection_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $connection_id ] );
			}
		}

		// Primary LLM Configuration
		$backend = sanitize_key( $_POST['worldgraph_ai_backend'] ?? 'openai_compatible' );
		if ( ! in_array( $backend, [ 'openai_compatible', 'openai', 'anthropic', 'dual' ], true ) ) {
			$backend = 'openai_compatible';
		}
		update_option( 'worldgraph_ai_backend', $backend );
		update_option( 'worldgraph_ai_url', esc_url_raw( wp_unslash( $_POST['worldgraph_ai_url'] ?? '' ) ) );
		update_option( 'worldgraph_ai_model', sanitize_text_field( wp_unslash( $_POST['worldgraph_ai_model'] ?? '' ) ) );
		if ( ! defined( 'WORLDGRAPH_AI_API_KEY' ) && isset( $_POST['worldgraph_ai_api_key'] ) ) {
			update_option( 'worldgraph_ai_api_key', sanitize_text_field( wp_unslash( $_POST['worldgraph_ai_api_key'] ) ) );
		}

		// Advanced LLM Configuration
		$ai_max_tokens = isset( $_POST['worldgraph_ai_max_tokens'] ) ? absint( wp_unslash( $_POST['worldgraph_ai_max_tokens'] ) ) : 2048;
		$ai_temperature = isset( $_POST['worldgraph_ai_temperature'] ) ? floatval( wp_unslash( $_POST['worldgraph_ai_temperature'] ) ) : 0.7;
		update_option( 'worldgraph_ai_max_tokens', $ai_max_tokens );
		update_option( 'worldgraph_ai_temperature', $ai_temperature );

		// Populate the "LLM" Connection record from this section.
		$ai_api_key = defined( 'WORLDGRAPH_AI_API_KEY' ) ? '' : sanitize_text_field( wp_unslash( $_POST['worldgraph_ai_api_key'] ?? '' ) );
		\WorldGraph\CPT\Connection::upsert_managed(
			'llm',
			'Primary LLM (Setup Wizard)',
			[
				'provider_type'        => $backend,
				'environment'          => 'local',
				'endpoint_url'         => get_option( 'worldgraph_ai_url', '' ),
				'credential_reference' => $ai_api_key,
				'model'                => get_option( 'worldgraph_ai_model', '' ),
				'max_tokens'           => (string) $ai_max_tokens,
				'temperature'          => (string) $ai_temperature,
			]
		);

		update_option( 'worldgraph_setup_complete', true );

		wp_safe_redirect( add_query_arg( [ 'page' => 'worldgraph-setup', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
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

		check_ajax_referer( 'worldgraph_test_llm_connection', 'nonce' );

		$backend = sanitize_key( $_POST['backend'] ?? 'openai_compatible' );
		if ( ! in_array( $backend, [ 'openai_compatible', 'openai', 'anthropic', 'dual' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Unsupported LLM backend.' ], 400 );
		}

		$configuration = [
			'backend' => $backend,
			'url'     => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'model'   => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
			'api_key' => defined( 'WORLDGRAPH_AI_API_KEY' ) ? \WORLDGRAPH_AI_API_KEY : sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
		];

		$result = ( new \WorldGraph\AI\AI_LLM_Client() )->test_connection( $configuration );
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

		check_ajax_referer( 'worldgraph_test_comfy_connection', 'nonce' );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'local_mcp' ) );
		if ( 'elevenlabs' === $mode ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'elevenlabs' );
			$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
			$catalog = \WorldGraph\Utils\ElevenLabs_API::test_configuration( \WorldGraph\Utils\Connection_Adapters::endpoint( 'elevenlabs' ), $key );
			if ( is_wp_error( $catalog ) ) {
				wp_send_json_error( [ 'message' => $catalog->get_error_message() ] );
			}
			wp_send_json_success( [
				'message' => sprintf(
					'Connected to ElevenLabs; %d voice(s) and %d text-to-speech model(s) available. Saving provisions endpoint-specific Templates.',
					count( (array) ( $catalog['voices'] ?? [] ) ),
					count( (array) ( $catalog['text_to_speech_models'] ?? [] ) )
				),
			] );
		}
		if ( 'fal' === $mode ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'fal' );
			$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
			$tools = \WorldGraph\Utils\Fal_MCP::test_configuration( \WorldGraph\Utils\Connection_Adapters::endpoint( 'fal' ), $key );
			if ( is_wp_error( $tools ) ) {
				wp_send_json_error( [ 'message' => $tools->get_error_message() ] );
			}
			$missing = array_values( array_diff( \WorldGraph\Utils\Fal_MCP::GENERATION_TOOLS, $tools ) );
			if ( ! empty( $missing ) ) {
				wp_send_json_error( [ 'message' => sprintf( 'fal MCP is missing required tools: %s.', implode( ', ', $missing ) ) ] );
			}
			wp_send_json_success( [ 'message' => sprintf( 'Connected to fal MCP; %d tools available.', count( $tools ) ) ] );
		}
		if ( 'suno' === $mode ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'suno' );
			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
			$mcp_api_key = sanitize_text_field( wp_unslash( $_POST['mcp_api_key'] ?? '' ) );
			$credits = \WorldGraph\Utils\Suno_API::test_configuration( \WorldGraph\Utils\Connection_Adapters::endpoint( 'suno' ), $api_key );
			if ( is_wp_error( $credits ) ) {
				wp_send_json_error( [ 'message' => $credits->get_error_message() ] );
			}

			$tools = \WorldGraph\Utils\Suno_MCP::test_configuration( \WorldGraph\Utils\Connection_Adapters::mcp_endpoint( 'suno' ), $mcp_api_key );
			if ( is_wp_error( $tools ) ) {
				wp_send_json_error( [ 'message' => $tools->get_error_message() ] );
			}

			$missing = array_values( array_diff( \WorldGraph\Utils\Suno_MCP::REQUIRED_TOOLS, $tools ) );
			if ( ! empty( $missing ) ) {
				wp_send_json_error( [ 'message' => sprintf( 'Suno MCP is missing required tools: %s.', implode( ', ', $missing ) ) ] );
			}

			wp_send_json_success( [
				'message' => sprintf( 'Connected to SunoAPI.org and AceData Cloud Suno MCP; %d MCP tools available. Saving provisions transport-specific Templates.', count( $tools ) ),
			] );
		}
		if ( 'videodraft' === $mode ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'videodraft' );
			$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
			$tools = \WorldGraph\Utils\VideoDraft_API::test_configuration( \WorldGraph\Utils\Connection_Adapters::endpoint( 'videodraft' ), $key );
			if ( is_wp_error( $tools ) ) {
				wp_send_json_error( [ 'message' => $tools->get_error_message() ] );
			}
			$missing = array_values( array_diff( \WorldGraph\Utils\VideoDraft_API::REQUIRED_TOOLS, $tools ) );
			if ( ! empty( $missing ) ) {
				wp_send_json_error( [ 'message' => sprintf( 'VideoDraft is missing required generation or sync tools: %s.', implode( ', ', $missing ) ) ] );
			}
			wp_send_json_success( [ 'message' => sprintf( 'Connected to VideoDraft; %d tools available. Saving provisions image, video, and audio Templates.', count( $tools ) ) ] );
		}

		$url = untrailingslashit( esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ) );
		\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );
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
		$comfy_mode = get_option( 'worldgraph_gen_connection_mode', get_option( 'worldgraph_comfy_connection_mode', 'none' ) );
		$generation_options = \WorldGraph\Utils\Connection_Adapters::setup_options();
		$backend = get_option( 'worldgraph_ai_backend', 'openai_compatible' );
		$generation_connections = get_posts( [
			'post_type'      => 'worldgraph_conn',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'worldgraph_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'generation', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		] );
		$generation_connection_id = $generation_connections ? (int) $generation_connections[0] : 0;
		$generation_api_key = $generation_connection_id ? (string) get_post_meta( $generation_connection_id, 'credential_reference', true ) : '';
		$generation_mcp_api_key = $generation_connection_id ? (string) get_post_meta( $generation_connection_id, 'mcp_credential_reference', true ) : '';

		?>
		<div class="wrap">
			<h1>Set Up World Graph Studio</h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p>World Graph Studio connections saved.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['required'] ) ) : ?>
				<div class="notice notice-warning"><p>Please review the connection settings below before continuing. Submitting this form (even with fields left blank) completes setup.</p></div>
			<?php endif; ?>
			<p>World Graph Studio requires a WordPress.org host or local Docker/Lando deployment. An API-connected LLM enables the World Graph Studio agents, while a generation Connection is optional for media generation.</p>
			<div class="notice notice-info inline">
				<p><strong>Do not have API access?</strong> You can still use World Graph Studio to develop stories, manage characters and scenes, track continuity, and organize media. Generate content in a browser-based service using its own web app, download the result, and attach it to a World Graph Studio post as the featured asset or in its asset gallery. Browser subscriptions and web login credentials cannot be used as server API credentials.</p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="worldgraph_save_setup" />
				<?php wp_nonce_field( 'worldgraph_save_setup' ); ?>
				<h2>WordPress Runtime</h2>
				<p>WordPress is connected. For production, configure a host scheduler to run WP-Cron. Local Lando users can run <code>lando wp-cron</code>.</p>
				<h2>Generation Connection (Optional)</h2>
				<p class="description">This section only establishes <em>how</em> World Graph Studio reaches a generation provider (ComfyUI, fal, ElevenLabs, Suno, etc.). <em>What</em> gets generated for a given asset type and provider &mdash; workflow JSON, model, parameters &mdash; is configured per combination as a <strong>Template</strong> post, not here. Saving this section creates or updates a <strong>Connection</strong> record, testable from <a href="<?php echo esc_url( admin_url( 'admin.php?page=worldgraph-connections' ) ); ?>">World Graph Studio &gt; Connections</a>.</p>
				<p><label for="worldgraph_gen_connection_mode">Preferred Connection</label><br />
				<select name="worldgraph_gen_connection_mode" id="worldgraph_gen_connection_mode">
					<?php foreach ( $generation_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $comfy_mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select> <span class="description">This list is supplied by installed Connection adapters. Additional providers can be added from World Graph Studio &gt; Connections.</span></p>
				<p id="worldgraph-generation-credential-fields"><label for="worldgraph_gen_credential_reference">Generation Provider API Key</label><br />
				<input type="password" class="regular-text" name="worldgraph_gen_credential_reference" id="worldgraph_gen_credential_reference" value="<?php echo esc_attr( $generation_api_key ); ?>" autocomplete="new-password" /> <span class="description">Use the selected hosted provider's API key. The managed Connection stores this value as its API credential reference.</span></p>
				<p id="worldgraph-generation-mcp-credential-fields"><label for="worldgraph_gen_mcp_credential_reference">Generation Provider MCP Token</label><br />
				<input type="password" class="regular-text" name="worldgraph_gen_mcp_credential_reference" id="worldgraph_gen_mcp_credential_reference" value="<?php echo esc_attr( $generation_mcp_api_key ); ?>" autocomplete="new-password" /> <span class="description">Suno MCP is operated by AceData Cloud and requires its own token; a SunoAPI.org key cannot authenticate this endpoint.</span></p>
				<p id="worldgraph-comfy-local-api-fields"><label for="worldgraph_comfy_local_url">Local ComfyUI API URL</label><br />
				<input type="url" class="regular-text" name="worldgraph_comfy_local_url" id="worldgraph_comfy_local_url" value="<?php echo esc_attr( get_option( 'worldgraph_comfy_local_url', 'http://host.lando.internal:8188' ) ); ?>" placeholder="http://host.lando.internal:8188" /> <span class="description">For ComfyUI running on the Lando host, use <code>http://host.lando.internal:8188</code> (Lando's built-in host hostname, Lando &ge; 3.22); do not use <code>localhost</code> or <code>host.docker.internal</code>.</span></p>
				<p id="worldgraph-comfy-local-mcp-fields"><label for="worldgraph_comfy_local_mcp_url">Local ComfyUI MCP URL <em>(optional)</em></label><br />
				<input type="url" class="regular-text" name="worldgraph_comfy_local_mcp_url" id="worldgraph_comfy_local_mcp_url" value="<?php echo esc_attr( get_option( 'worldgraph_comfy_local_mcp_url', '' ) ); ?>" placeholder="http://host.lando.internal:9000/mcp" /> <span class="description">Only if you run a separate Comfy MCP server process. This is <strong>not</strong> the ComfyUI API port with <code>/mcp</code> appended &mdash; ComfyUI's own HTTP API does not speak MCP. Leave empty to discover templates from the built-in modalities instead. Setting it enables automatic Template discovery and model downloads.</span></p>
				<p><button type="button" class="button" id="worldgraph-test-comfy-connection">Test Generation Connection</button> <span id="worldgraph-comfy-test-result" aria-live="polite"></span></p>
				<p class="description">Generation behavior belongs to a <strong>Template</strong>. Configure the modality, model, and workflow on the Template, then select its Connection when generating.</p>
				<p class="description">Choose <strong>No generation connection yet</strong> when using a browser-based generator or when you only need World Graph Studio for writing, planning, and asset management.</p>
				<?php if ( 'local_mcp' === $comfy_mode ) : ?>
					<?php \WorldGraph\Utils\Connection_Adapters::load( 'comfyui' ); ?>
					<h3>ComfyUI Readiness</h3>
					<p class="description">ComfyUI loads its default text-to-image workflow on first launch only when the matching nodes and a checkpoint are installed. World Graph Studio checks that here, provisions the text-to-image <strong>Template</strong> it generates against, and lists whatever is still missing.</p>
					<?php \WorldGraph\Admin\Comfy_Readiness::render_panel(); ?>
				<?php elseif ( 'fal' === $comfy_mode ) : ?>
					<h3>fal Template Configuration</h3>
					<p class="description">After saving, World Graph Studio asks fal MCP to discover a current text-to-image model, inspect its schema, and create the paired active Template automatically. Advanced users can restrict provisioning with the Connection's Model or Model Access fields.</p>
				<?php elseif ( 'elevenlabs' === $comfy_mode ) : ?>
					<h3>ElevenLabs Template Configuration</h3>
					<p class="description">After saving, World Graph Studio discovers your available voices and models, then creates active Templates for text to speech, dialogue, sound effects, music, and voice design. Advanced users can select the speech model or place voice IDs in Model Access on the Connection.</p>
				<?php elseif ( 'suno' === $comfy_mode ) : ?>
					<h3>Suno Template Configuration</h3>
					<p class="description">After saving, World Graph Studio creates transport-specific music, custom-music, and lyrics Templates for SunoAPI.org REST and the AceData Cloud Suno MCP server. The two services use separate bearer tokens. Generation is polled asynchronously and every final song returned by the provider is imported.</p>
				<?php elseif ( 'videodraft' === $comfy_mode ) : ?>
					<h3>VideoDraft Template Configuration</h3>
					<p class="description">After saving, World Graph Studio discovers VideoDraft's live MCP schemas and creates active image, video, voiceover, music, and sound-effect Templates. The same Connection can be selected by the VideoDraft Sync plugin.</p>
				<?php endif; ?>
				<h2>External Generator Workflow</h2>
				<ol>
					<li>Generate the image, video, audio, or other media in the provider's web application.</li>
					<li>Download the final file and retain the provider, model, prompt, source URL, and usage-rights information.</li>
					<li>On the relevant World Graph Studio post, use <strong>World Graph Studio Assets</strong> to set the primary file as the featured asset and add supporting files to the gallery.</li>
					<li>Save the post. The featured asset and gallery are available in the World Graph Studio API.</li>
				</ol>
				<p class="description">Direct connectors for services such as Sora, Runway, Veo, Kling, Seedance, Firefly, Midjourney, and Amazon video endpoints require additional API discovery and provider-specific implementation.</p>
				<h2>LLM Connection (Required for AI Agents)</h2>
				<p>An API-connected LLM is required for World Graph Studio agents. Browser-only ChatGPT, Claude, or Claude Code subscriptions are not supported by this server integration. Without one, leave these fields empty and use World Graph Studio for story data, WordPress media, and external-generation asset tracking.</p>
				<p class="description">Saving this section creates or updates a <strong>Connection</strong> record, testable from <a href="<?php echo esc_url( admin_url( 'admin.php?page=worldgraph-connections' ) ); ?>">World Graph Studio &gt; Connections</a>. Configure additional connections (e.g. a fallback or secondary LLM) directly on the Connections screen.</p>
				<h3>Primary LLM Configuration</h3>
				<p><label for="worldgraph_ai_backend">Provider</label><br /><select name="worldgraph_ai_backend" id="worldgraph_ai_backend">
					<option value="openai_compatible" <?php selected( $backend, 'openai_compatible' ); ?>>OpenAI-compatible local or hosted LLM</option>
					<option value="openai" <?php selected( $backend, 'openai' ); ?>>OpenAI API</option>
					<option value="anthropic" <?php selected( $backend, 'anthropic' ); ?>>Anthropic API</option>
					<option value="dual" <?php selected( $backend, 'dual' ); ?>>Dual (Local + Fallback Cloud)</option>
				</select></p>
				<p><label for="worldgraph_ai_url">Base URL or Endpoint</label><br /><input type="url" class="regular-text" name="worldgraph_ai_url" id="worldgraph_ai_url" value="<?php echo esc_attr( get_option( 'worldgraph_ai_url', 'http://host.lando.internal:11434/v1' ) ); ?>" /> <span class="description">For llama.cpp, Ollama, vLLM, LM Studio, or another `/v1` endpoint. In Lando, use <code>host.lando.internal</code> for an LLM running on the development host; <code>localhost</code> refers to the WordPress container. Leave blank if using OpenAI or Anthropic.</span></p>
				<p><label for="worldgraph_ai_model">Model Name</label><br /><input type="text" class="regular-text" name="worldgraph_ai_model" id="worldgraph_ai_model" list="worldgraph-ai-models" value="<?php echo esc_attr( get_option( 'worldgraph_ai_model', '' ) ); ?>" /> <datalist id="worldgraph-ai-models"></datalist> <span class="description">Examples: gpt-4, claude-3-sonnet, or local model name. Testing a local endpoint loads its available models.</span></p>
				<p><label for="worldgraph_ai_api_key">API Key / Token</label><br /><input type="password" class="regular-text" name="worldgraph_ai_api_key" id="worldgraph_ai_api_key" value="<?php echo esc_attr( get_option( 'worldgraph_ai_api_key' ) ); ?>" <?php disabled( defined( 'WORLDGRAPH_AI_API_KEY' ) ); ?> />
				<?php if ( defined( 'WORLDGRAPH_AI_API_KEY' ) ) : ?> <span class="description">Configured through the deployment environment.</span><?php else : ?> <span class="description">Required for hosted providers and some local servers.</span><?php endif; ?></p>
				<p><button type="button" class="button" id="worldgraph-test-llm-connection">Test LLM Connection</button> <span id="worldgraph-llm-test-result" aria-live="polite"></span></p>
				
				<h3>Advanced LLM Settings (Optional)</h3>
				<p><label for="worldgraph_ai_max_tokens">Max Tokens</label><br /><input type="number" class="small-text" name="worldgraph_ai_max_tokens" id="worldgraph_ai_max_tokens" value="<?php echo esc_attr( get_option( 'worldgraph_ai_max_tokens', '2048' ) ); ?>" min="256" max="32768" /> <span class="description">Maximum tokens for LLM responses.</span></p>
				<p><label for="worldgraph_ai_temperature">Temperature</label><br /><input type="number" class="small-text" name="worldgraph_ai_temperature" id="worldgraph_ai_temperature" value="<?php echo esc_attr( get_option( 'worldgraph_ai_temperature', '0.7' ) ); ?>" step="0.1" min="0" max="1" /> <span class="description">Creativity level (0.0 = deterministic, 1.0 = creative).</span></p>
			<?php submit_button( 'Save All Configurations' ); ?>
			</form>
			<script>
				(function () {
					var generationMode = document.getElementById('worldgraph_gen_connection_mode');
					var generationCredentialFields = document.getElementById('worldgraph-generation-credential-fields');
					var generationMcpCredentialFields = document.getElementById('worldgraph-generation-mcp-credential-fields');
					var localApiFields = document.getElementById('worldgraph-comfy-local-api-fields');
					var localMcpFields = document.getElementById('worldgraph-comfy-local-mcp-fields');
					var generationTestButton = document.getElementById('worldgraph-test-comfy-connection');
					function updateGenerationFields() {
						var mode = generationMode ? generationMode.value : 'none';
						generationCredentialFields.hidden = mode === 'none' || mode === 'local_mcp';
						generationMcpCredentialFields.hidden = mode !== 'suno';
						localApiFields.hidden = mode !== 'local_mcp';
						localMcpFields.hidden = mode !== 'local_mcp';
						generationTestButton.hidden = mode === 'none' || mode === 'cloud';
					}
					if (generationMode) {
						generationMode.addEventListener('change', updateGenerationFields);
						updateGenerationFields();
					}
					var button = document.getElementById('worldgraph-test-llm-connection');
					var result = document.getElementById('worldgraph-llm-test-result');
					if (!button || !result) {
						return;
					}
					button.addEventListener('click', function () {
						var modelInput = document.getElementById('worldgraph_ai_model');
						button.disabled = true;
						result.textContent = 'Testing...';
						var data = new URLSearchParams({
							action: 'worldgraph_test_llm_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'worldgraph_test_llm_connection' ) ); ?>',
							backend: document.getElementById('worldgraph_ai_backend').value,
							url: document.getElementById('worldgraph_ai_url').value,
							model: document.getElementById('worldgraph_ai_model').value,
							api_key: document.getElementById('worldgraph_ai_api_key').value
						});
						fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: data })
							.then(function (response) { return response.json(); })
							.then(function (response) {
								var models = response.data && Array.isArray(response.data.models) ? response.data.models : [];
								var modelList = document.getElementById('worldgraph-ai-models');
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
					var comfyButton = document.getElementById('worldgraph-test-comfy-connection');
					var comfyResult = document.getElementById('worldgraph-comfy-test-result');
					if (comfyButton && comfyResult) {
						comfyButton.addEventListener('click', function () {
							comfyButton.disabled = true;
							comfyResult.textContent = 'Testing...';
							var data = new URLSearchParams({
								action: 'worldgraph_test_comfy_connection',
								nonce: '<?php echo esc_js( wp_create_nonce( 'worldgraph_test_comfy_connection' ) ); ?>',
								mode: document.getElementById('worldgraph_gen_connection_mode').value || 'none',
								url: document.getElementById('worldgraph_comfy_local_url').value,
								api_key: document.getElementById('worldgraph_gen_credential_reference').value,
								mcp_api_key: document.getElementById('worldgraph_gen_mcp_credential_reference').value
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
