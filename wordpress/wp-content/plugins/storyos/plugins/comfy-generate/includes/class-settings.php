<?php
/**
 * Settings handler for ComfyUI Generate plugin.
 *
 * @package StoryOSComfyGenerate
 */

namespace StoryOSComfyGenerate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings registration, sanitization, and retrieval.
 */
class Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'comfy_generate_button_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'comfy_generate_button_settings_group';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private static $defaults = [
		'enabled'      => false,
		'endpoint_url' => '',
		'username'     => '',
		'password'     => '',
		'auth_token'   => '',
	];

	/**
	 * Initialize the settings handler.
	 */
	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return wp_parse_args( $settings, self::$defaults );
	}

	/**
	 * Get whether ComfyUI integration is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Enable ComfyUI integration.
	 */
	public static function enable(): void {
		$settings = self::get_settings();
		$settings['enabled'] = true;
		update_option( self::OPTION_NAME, self::sanitize_settings( $settings ) );
	}

	/**
	 * Disable ComfyUI integration.
	 */
	public static function disable(): void {
		$settings = self::get_settings();
		$settings['enabled'] = false;
		update_option( self::OPTION_NAME, self::sanitize_settings( $settings ) );
	}

	/**
	 * Check if ComfyUI endpoint is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['endpoint_url'] );
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input The raw input.
	 * @return array
	 */
	public static function sanitize_settings( array $input ): array {
		$output = self::$defaults;

		$output['enabled'] = ! empty( $input['enabled'] );

		if ( empty( $input['endpoint_url'] ) ) {
			return $output;
		}

		$output['endpoint_url'] = esc_url_raw( trim( $input['endpoint_url'] ) );

		if ( ! empty( $input['username'] ) ) {
			$output['username'] = sanitize_text_field( $input['username'] );
		}

		if ( ! empty( $input['password'] ) ) {
			$output['password'] = sanitize_text_field( $input['password'] );
		}

		if ( ! empty( $input['auth_token'] ) ) {
			$output['auth_token'] = sanitize_text_field( $input['auth_token'] );
		}

		return $output;
	}

	/**
	 * Register settings with WordPress.
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'default'           => self::$defaults,
			]
		);
	}

	/**
	 * Add settings page to admin menu.
	 */
	public static function add_settings_page(): void {
		add_submenu_page(
			'storyos',
			__( 'ComfyUI Generate', 'storyos-comfy-generate' ),
			__( 'ComfyUI Generate', 'storyos-comfy-generate' ),
			'manage_options',
			'comfy-generate',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'storyos-comfy-generate' ) );
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ComfyUI Generate Settings', 'storyos-comfy-generate' ); ?></h1>
			<p><?php esc_html_e( 'Configure the ComfyUI endpoint and optional authentication.', 'storyos-comfy-generate' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="comfy-enabled"><?php esc_html_e( 'Enable ComfyUI Generate', 'storyos-comfy-generate' ); ?></label>
							</th>
							<td>
								<input type="hidden" name="comfy_generate_button_settings[enabled]" value="0" />
								<label>
									<input
										id="comfy-enabled"
										name="comfy_generate_button_settings[enabled]"
										type="checkbox"
										value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?>
									/>
									<?php esc_html_e( 'Enable the editor button and ComfyUI AJAX actions.', 'storyos-comfy-generate' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="comfy-endpoint-url"><?php esc_html_e( 'ComfyUI Endpoint URL', 'storyos-comfy-generate' ); ?></label>
							</th>
							<td>
								<input
									id="comfy-endpoint-url"
									name="comfy_generate_button_settings[endpoint_url]"
									type="url"
									class="regular-text"
									placeholder="http://127.0.0.1:8000/generate"
									value="<?php echo esc_attr( $settings['endpoint_url'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Example: http://10.0.0.34:8000/generate', 'storyos-comfy-generate' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="comfy-username"><?php esc_html_e( 'Username', 'storyos-comfy-generate' ); ?></label>
							</th>
							<td>
								<input
									id="comfy-username"
									name="comfy_generate_button_settings[username]"
									type="text"
									class="regular-text"
									autocomplete="off"
									value="<?php echo esc_attr( $settings['username'] ); ?>"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="comfy-password"><?php esc_html_e( 'Password', 'storyos-comfy-generate' ); ?></label>
							</th>
							<td>
								<input
									id="comfy-password"
									name="comfy_generate_button_settings[password]"
									type="password"
									class="regular-text"
									autocomplete="new-password"
									value="<?php echo esc_attr( $settings['password'] ); ?>"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="comfy-auth-token"><?php esc_html_e( 'Auth Token', 'storyos-comfy-generate' ); ?></label>
							</th>
							<td>
								<input
									id="comfy-auth-token"
									name="comfy_generate_button_settings[auth_token]"
									type="text"
									class="regular-text"
									autocomplete="off"
									value="<?php echo esc_attr( $settings['auth_token'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'If token is present, Bearer auth is used. Otherwise, Basic auth is used when username/password are set.', 'storyos-comfy-generate' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'storyos-comfy-generate' ) ); ?>
			</form>
		</div>
		<?php
	}
}
