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
				]
			);
		}

		// ComfyUI Generate plugin.
		if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/comfy-generate/comfy-generate.php' ) ) {
			self::register_plugin(
				'comfy-generate',
				'StoryOS - ComfyUI Generate',
				[
					'name'        => 'ComfyUI Generate',
					'description' => 'Adds a "Send to ComfyUI" button to WordPress posts and forwards jobs to a configurable ComfyUI endpoint.',
					'version'     => '1.0.0',
					'author'      => 'StoryOS Contributors',
					'icon'        => 'dashicons-video-alt3',
					'file'        => 'plugins/comfy-generate/comfy-generate.php',
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
				]
			);
		}

		// Future integrations can be registered here:
		// self::register_plugin( 'integration-name', 'Plugin Name', [ ... ] );
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
			'has_settings' => false,
			'settings_url' => '',
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
			'storyos',
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
			<?php else : ?>
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
									<button class="button button-small storyos-toggle-plugin" data-plugin="<?php echo esc_attr( $slug ); ?>">
										<?php echo $plugin['active'] ? 'Disable' : 'Enable'; ?>
									</button>
									<?php if ( $plugin['active'] && ! $plugin['configured'] ) : ?>
										<button class="button button-small storyos-test-connection" data-plugin="<?php echo esc_attr( $slug ); ?>">
											Test Connection
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for toggling plugins.
	 */
	public static function ajax_toggle_plugin(): void {
		check_ajax_referer( 'storyos_admin', 'nonce' );

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
		$plugin = self::get_plugin( $slug );

		if ( ! $plugin ) {
			wp_send_json_error( [ 'message' => 'Plugin not found.' ] );
		}

		// Toggle the plugin state.
		$new_state = ! $plugin['active'];
		self::$plugins[ $slug ]['active'] = $new_state;

		wp_send_json_success( [
			'message' => sprintf( '%s %s.', $plugin['name'], $new_state ? 'enabled' : 'disabled' ),
			'active'  => $new_state,
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
