<?php
/**
 * Admin Plugins Manager for StoryOS.
 *
 * Manages StoryOS integrations and plugins from the admin area.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Plugins Manager class.
 */
class Plugins {

	/**
	 * Option name used to persist integration enabled states.
	 *
	 * @var string
	 */
	private const STATE_OPTION = 'storyos_plugin_states';

	/**
	 * Plugin registry.
	 *
	 * @var array
	 */
	private static $plugins = [];

	/**
	 * Initialize the plugins manager.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_storyos_toggle_plugin', [ __CLASS__, 'ajax_toggle_plugin' ] );
		add_action( 'wp_ajax_storyos_test_connection', [ __CLASS__, 'ajax_test_connection' ] );

		// Register available plugins.
		self::register_plugins();
	}

	/**
	 * Register available plugins.
	 */
	private static function register_plugins(): void {
		// Celtx Sync plugin.
		if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/celtx/celtx-sync.php' ) ) {
			self::register_plugin(
				'celtx',
				'StoryOS - Celtx Sync',
				[
					'name'        => 'Celtx Sync',
					'description' => 'Synchronize StoryOS elements with Celtx using the Celtx GEM Bi-Directional API.',
					'version'     => '1.0.0',
					'author'      => 'StoryOS Contributors',
					'icon'        => 'dashicons-external',
					'file'        => 'plugins/celtx/celtx-sync.php',
					'has_settings' => true,
					'settings_url' => admin_url( 'admin.php?page=celtx-sync' ),
				]
			);
		}

		// Generation Engine plugin.
		if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/comfy-generate/comfy-generate.php' ) ) {
			self::register_plugin(
				'comfy-generate',
				'StoryOS - Generation Engine',
				[
					'name'        => 'Generation Engine',
					'description' => 'Adds a generation button to WordPress posts and queues Comfy Cloud MCP jobs through WP-Cron.',
					'version'     => '1.0.0',
					'author'      => 'StoryOS Contributors',
					'icon'        => 'dashicons-video-alt3',
					'file'        => 'plugins/comfy-generate/comfy-generate.php',
					'has_settings' => true,
					'settings_url' => admin_url( 'admin.php?page=storyos-generation-engine' ),
				]
			);
		}

		// EDL Import/Export plugin.
		if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/edl/edl-import-export.php' ) ) {
			self::register_plugin(
				'edl',
				'StoryOS - EDL Import/Export',
				[
					'name'        => 'EDL Import/Export',
					'description' => 'Import and export Edit Decision Lists (ASCII/CMX 3600 & XML) for StoryOS projects and episodes.',
					'version'     => '1.0.0',
					'author'      => 'StoryOS Contributors',
					'icon'        => 'dashicons-media-video',
					'file'        => 'plugins/edl/edl-import-export.php',
					'has_settings' => true,
					'settings_url' => admin_url( 'admin.php?page=storyos-edl' ),
				]
			);
		}

		// Future integrations can be registered here:
		// self::register_plugin( 'integration-name', 'Plugin Name', [ ... ] );

		self::hydrate_plugin_state();
	}

	/**
	 * Hydrate plugin status from persisted options.
	 */
	private static function hydrate_plugin_state(): void {
		foreach ( self::$plugins as $slug => $plugin ) {
			self::$plugins[ $slug ]['active'] = self::is_plugin_enabled( $slug );
			self::$plugins[ $slug ]['configured'] = self::is_plugin_configured( $slug );
		}
	}

	/**
	 * Get persisted plugin state map.
	 *
	 * @return array
	 */
	private static function get_saved_states(): array {
		$states = get_option( self::STATE_OPTION, [] );

		if ( ! is_array( $states ) ) {
			return [];
		}

		return $states;
	}

	/**
	 * Determine whether a plugin is enabled.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	private static function is_plugin_enabled( string $slug ): bool {
		$states = self::get_saved_states();

		if ( array_key_exists( $slug, $states ) ) {
			return (bool) $states[ $slug ];
		}

		switch ( $slug ) {
			case 'celtx':
				return (bool) get_option( 'celtx_enabled', false );

			case 'comfy-generate':
					if ( class_exists( '\\StoryOSGenerationEngine\\Settings' ) ) {
						return \StoryOSGenerationEngine\Settings::is_enabled();
				}
					$settings = get_option( 'storyos_generation_engine_settings', [] );
					return is_array( $settings ) && ! empty( $settings['enabled'] );

			case 'edl':
				return (bool) get_option( 'storyos_edl_enabled', true );

			default:
				return false;
		}
	}

	/**
	 * Determine whether a plugin has required configuration.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	private static function is_plugin_configured( string $slug ): bool {
		switch ( $slug ) {
			case 'celtx':
				if ( class_exists( '\\StoryOSCeltx\\Settings' ) ) {
					return \StoryOSCeltx\Settings::has_credentials();
				}
				$credentials = get_option( 'celtx_credentials', [] );
				return is_array( $credentials ) && ! empty( $credentials['api_key'] );

			case 'comfy-generate':
					if ( class_exists( '\\StoryOSGenerationEngine\\Settings' ) ) {
						return \StoryOSGenerationEngine\Settings::is_configured();
				}
					foreach ( \StoryOS\Utils\Connection_Repository::get_all( [ 'provider_type' => 'comfyui' ] ) as $connection ) {
						if ( 'local' === $connection['environment'] || '' !== trim( (string) $connection['credential_reference'] ) ) {
							return true;
						}
					}
					return false;

			case 'edl':
				return true;

			default:
				return false;
		}
	}

	/**
	 * Persist plugin enabled state.
	 *
	 * @param string $slug Plugin slug.
	 * @param bool   $enabled New enabled state.
	 */
	private static function persist_plugin_state( string $slug, bool $enabled ): void {
		$states = self::get_saved_states();
		$states[ $slug ] = $enabled;
		update_option( self::STATE_OPTION, $states );

		switch ( $slug ) {
			case 'celtx':
				if ( class_exists( '\\StoryOSCeltx\\Settings' ) ) {
					if ( $enabled ) {
						\StoryOSCeltx\Settings::enable();
					} else {
						\StoryOSCeltx\Settings::disable();
					}
				} else {
					update_option( 'celtx_enabled', $enabled );
				}
				break;

			case 'comfy-generate':
					if ( class_exists( '\\StoryOSGenerationEngine\\Settings' ) ) {
					if ( $enabled ) {
							\StoryOSGenerationEngine\Settings::enable();
					} else {
							\StoryOSGenerationEngine\Settings::disable();
					}
				}
				break;

			case 'edl':
				update_option( 'storyos_edl_enabled', $enabled );
				break;
		}
	}

	/**
	 * Register a plugin.
	 *
	 * @param string $slug
	 * @param string $name
	 * @param array  $args
	 */
	public static function register_plugin( string $slug, string $name, array $args = [] ): void {
		self::$plugins[ $slug ] = [
			'slug'        => $slug,
			'name'        => $name,
			'description' => $args['description'] ?? '',
			'version'     => $args['version'] ?? '1.0.0',
			'author'      => $args['author'] ?? '',
			'icon'        => $args['icon'] ?? 'dashicons-admin-plugins',
			'file'        => $args['file'] ?? '',
			'active'      => false,
			'configured'  => false,
			'has_settings' => ! empty( $args['has_settings'] ),
			'settings_url' => $args['settings_url'] ?? '',
		];
	}

	/**
	 * Get all registered plugins.
	 *
	 * @return array
	 */
	public static function get_plugins(): array {
		return self::$plugins;
	}

	/**
	 * Get a single plugin by slug.
	 *
	 * @param string $slug
	 * @return array|null
	 */
	public static function get_plugin( string $slug ): ?array {
		return self::$plugins[ $slug ] ?? null;
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos-administration',
			'Plugins',
			'Plugins',
			'manage_options',
			'storyos-plugins',
			[ __CLASS__, 'render_plugins_page' ]
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, 'storyos-plugins' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'storyos-plugins',
			STORYOS_PLUGIN_URL . 'assets/js/plugins.js',
			[ 'jquery' ],
			STORYOS_VERSION,
			true
		);

		wp_localize_script( 'storyos-plugins', 'storyosPlugins', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'storyos_admin' ),
		] );
	}

	/**
	 * Render the plugins management page.
	 */
	public static function render_plugins_page(): void {
		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
		$message_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'success';
		?>
		<div class="wrap storyos-plugins">
			<h1>StoryOS Plugins</h1>

			<?php if ( ! empty( $message ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( self::$plugins ) ) : ?>
				<div class="notice notice-info">
					<p>No plugins registered yet. Integrations will appear here.</p>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Plugin</th>
						<th>Status</th>
						<th>Configuration</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( self::$plugins as $slug => $plugin ) : ?>
						<tr data-plugin="<?php echo esc_attr( $slug ); ?>">
							<td>
								<strong>
									<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>" style="margin-right: 5px;"></span>
									<?php echo esc_html( $plugin['name'] ); ?>
								</strong>
								<br>
								<small>
									<?php echo esc_html( $plugin['description'] ); ?>
									<br>
									<span class="storyos-plugin-meta">
										Version <?php echo esc_html( $plugin['version'] ); ?> &middot; <?php echo esc_html( $plugin['author'] ); ?>
									</span>
								</small>
							</td>
							<td>
								<?php if ( $plugin['active'] ) : ?>
									<span class="status-active">Active</span>
								<?php else : ?>
									<span class="status-inactive">Inactive</span>
								<?php endif; ?>
								<?php if ( $plugin['configured'] ) : ?>
									<br><span class="status-configured">✓ Configured</span>
								<?php else : ?>
									<br><span class="status-unconfigured">Not configured</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $plugin['has_settings'] ) : ?>
									<a href="<?php echo esc_url( $plugin['settings_url'] ); ?>" class="button button-small">
										<span class="dashicons dashicons-admin-generic"></span> Settings
									</a>
								<?php else : ?>
									<span class="dashicons dashicons-info" style="color: #999;"></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$requires_configuration = ! $plugin['active'] && ! $plugin['configured'] && $plugin['has_settings'];
								?>
								<button class="button button-small storyos-toggle-plugin" data-plugin="<?php echo esc_attr( $slug ); ?>" <?php disabled( $requires_configuration ); ?>>
									<?php echo $plugin['active'] ? 'Disable' : 'Enable'; ?>
								</button>
								<?php if ( $requires_configuration ) : ?>
									<a href="<?php echo esc_url( $plugin['settings_url'] ); ?>" class="button button-small button-primary">Configure First</a>
								<?php endif; ?>
								<?php if ( $plugin['active'] && $plugin['configured'] ) : ?>
									<button class="button button-small storyos-test-connection" data-plugin="<?php echo esc_attr( $slug ); ?>">
										Test Connection
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr class="storyos-plugin-tool-row">
						<td>
							<strong><span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>JSON Import</strong>
							<br><small>Import a StoryOS project document and its story graph data.</small>
						</td>
						<td><span class="status-active">Available</span></td>
						<td><span class="status-configured">Built in</span></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=storyos-import' ) ); ?>" class="button button-small button-primary">Import JSON</a></td>
					</tr>
					<tr class="storyos-plugin-tool-row">
						<td>
							<strong><span class="dashicons dashicons-download" style="margin-right: 5px;"></span>Markdown Export</strong>
							<br><small>Export a StoryOS project as a Markdown screenplay file.</small>
						</td>
						<td><span class="status-active">Available</span></td>
						<td><span class="status-configured">Built in</span></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=storyos-export' ) ); ?>" class="button button-small button-primary">Export Markdown</a></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX handler for toggling plugins.
	 */
	public static function ajax_toggle_plugin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You are not allowed to perform this action.' ], 403 );
		}

		check_ajax_referer( 'storyos_admin', 'nonce' );

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
		$plugin = self::get_plugin( $slug );

		if ( ! $plugin ) {
			wp_send_json_error( [ 'message' => 'Plugin not found.' ] );
		}

		// Toggle and persist plugin state.
		$new_state = ! self::is_plugin_enabled( $slug );

		if ( $new_state && ! self::is_plugin_configured( $slug ) && ! empty( $plugin['has_settings'] ) ) {
			wp_send_json_error( [
				'message' => 'Please configure this plugin before enabling it.',
				'settings_url' => $plugin['settings_url'],
			] );
		}

		self::persist_plugin_state( $slug, $new_state );
		self::$plugins[ $slug ]['active'] = $new_state;
		self::$plugins[ $slug ]['configured'] = self::is_plugin_configured( $slug );

		wp_send_json_success( [
			'message' => sprintf( '%s %s.', $plugin['name'], $new_state ? 'enabled' : 'disabled' ),
			'active'  => $new_state,
			'configured' => self::$plugins[ $slug ]['configured'],
			'reload_required' => true,
		] );
	}

	/**
	 * AJAX handler for testing connections.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'storyos_admin', 'nonce' );

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

		if ( $slug === 'celtx' ) {
			// Use the Celtx API client to test connection.
			if ( class_exists( 'StoryOSCeltx\API\Client' ) ) {
				$client = \StoryOSCeltx\API\Client::from_credentials();

				if ( is_wp_error( $client ) ) {
					wp_send_json_error( [ 'message' => 'Missing credentials. Please configure the plugin first.' ] );
				}

				$status = $client->get_status();

				if ( is_wp_error( $status ) ) {
					wp_send_json_error( [ 'message' => 'Connection failed: ' . $status->get_error_message() ] );
				}

				wp_send_json_success( [
					'message' => 'Connection successful!',
					'status'  => $status,
				] );
			} else {
				wp_send_json_error( [ 'message' => 'Celtx API client not available.' ] );
			}
		} else {
			wp_send_json_error( [ 'message' => 'No test handler for this plugin.' ] );
		}
	}
}
