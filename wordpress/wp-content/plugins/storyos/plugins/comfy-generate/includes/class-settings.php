<?php
/**
 * Settings handler for StoryOS Generation Engine plugin.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

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
	const OPTION_NAME = 'storyos_generation_engine_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'storyos_generation_engine_settings_group';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private static $defaults = [
		'enabled'          => false,
		'mcp_server_url'   => '',
		'orchestrator_url' => 'http://orchestrator:8000',
		'workflow'         => 'character-sheet',
		'provider_type'    => '',
		'connection_id'    => 1,
		'endpoint_url'     => '',
		'environment'      => 'local',
		'credential_reference' => '',
		'secret_backend'   => 'env',
		'enabled_models'   => '',
		'enabled_capabilities' => '',
		'connection_status' => 'unverified',
		'last_validated_at' => '',
		'request_timeout'  => 60,
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
	 * Get whether generation integration is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Enable Generation Engine integration.
	 */
	public static function enable(): void {
		$settings = self::get_settings();
		$settings['enabled'] = true;
		update_option( self::OPTION_NAME, self::sanitize_settings( $settings ) );
	}

	/**
	 * Disable Generation Engine integration.
	 */
	public static function disable(): void {
		$settings = self::get_settings();
		$settings['enabled'] = false;
		update_option( self::OPTION_NAME, self::sanitize_settings( $settings ) );
	}

	/**
	 * Check if orchestrator endpoint is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['mcp_server_url'] ) || ! empty( $settings['orchestrator_url'] );
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

		if ( ! empty( $input['mcp_server_url'] ) ) {
			$output['mcp_server_url'] = esc_url_raw( trim( $input['mcp_server_url'] ) );
		} elseif ( ! empty( $input['orchestrator_url'] ) ) {
			$output['mcp_server_url'] = esc_url_raw( trim( $input['orchestrator_url'] ) );
		}

		if ( ! empty( $input['orchestrator_url'] ) ) {
			$output['orchestrator_url'] = esc_url_raw( trim( $input['orchestrator_url'] ) );
		}

		if ( ! empty( $input['workflow'] ) ) {
			$output['workflow'] = sanitize_text_field( trim( $input['workflow'] ) );
		}

		if ( ! empty( $input['provider_type'] ) ) {
			$output['provider_type'] = Provider_Registry::normalize( sanitize_text_field( trim( $input['provider_type'] ) ) );
		}

		if ( isset( $input['connection_id'] ) ) {
			$output['connection_id'] = max( 1, absint( $input['connection_id'] ) );
		}

		if ( ! empty( $input['credential_value'] ) && class_exists( __NAMESPACE__ . '\\Credential_Store' ) ) {
			Credential_Store::store(
				(int) $output['connection_id'],
				sanitize_text_field( (string) $input['credential_value'] )
			);
		}

		if ( ! empty( $input['endpoint_url'] ) ) {
			$output['endpoint_url'] = esc_url_raw( trim( $input['endpoint_url'] ) );
		}

		if ( ! empty( $input['environment'] ) ) {
			$output['environment'] = sanitize_key( $input['environment'] );
		}

		if ( ! empty( $input['credential_reference'] ) ) {
			$output['credential_reference'] = sanitize_text_field( trim( $input['credential_reference'] ) );
		}

		if ( ! empty( $input['secret_backend'] ) ) {
			$output['secret_backend'] = sanitize_key( $input['secret_backend'] );
		}

		foreach ( [ 'enabled_models', 'enabled_capabilities' ] as $list_key ) {
			if ( ! empty( $input[ $list_key ] ) ) {
				$output[ $list_key ] = sanitize_text_field( trim( $input[ $list_key ] ) );
			}
		}

		// Status is written by connection validation, never accepted from the form.
		$output['connection_status'] = sanitize_key( self::get_settings()['connection_status'] ?? 'unverified' );
		$output['last_validated_at'] = sanitize_text_field( self::get_settings()['last_validated_at'] ?? '' );

		if ( isset( $input['request_timeout'] ) ) {
			$output['request_timeout'] = max( 5, min( 300, absint( $input['request_timeout'] ) ) );
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
			__( 'Generation Engine', 'storyos-generation-engine' ),
			__( 'Generation Engine', 'storyos-generation-engine' ),
			'manage_options',
			'storyos-generation-engine',
			[ __CLASS__, 'render_settings_page' ]
		);

	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'storyos-generation-engine' ) );
		}

		$settings = self::get_settings();
		$credential_metadata = class_exists( __NAMESPACE__ . '\\Credential_Store' )
			? Credential_Store::get_metadata( (int) $settings['connection_id'] )
			: [ 'configured' => false, 'updated_at' => '' ];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Generation Engine Settings', 'storyos-generation-engine' ); ?></h1>
			<p><?php esc_html_e( 'Configure ComfyUI MCP and provider routing defaults.', 'storyos-generation-engine' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="storyos-ge-enabled"><?php esc_html_e( 'Enable Generation Engine', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input type="hidden" name="storyos_generation_engine_settings[enabled]" value="0" />
								<label>
									<input
										id="storyos-ge-enabled"
										name="storyos_generation_engine_settings[enabled]"
										type="checkbox"
										value="1"
										<?php checked( ! empty( $settings['enabled'] ) ); ?>
									/>
									<?php esc_html_e( 'Enable editor button and generation AJAX actions.', 'storyos-generation-engine' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-endpoint-url"><?php esc_html_e( 'Provider Endpoint URL', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input id="storyos-ge-endpoint-url" name="storyos_generation_engine_settings[endpoint_url]" type="url" class="regular-text" placeholder="http://comfyui:8188" value="<?php echo esc_attr( $settings['endpoint_url'] ); ?>" />
								<p class="description"><?php esc_html_e( 'The endpoint used by the configured provider connection. Credentials are not stored here.', 'storyos-generation-engine' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-environment"><?php esc_html_e( 'Environment', 'storyos-generation-engine' ); ?></label></th>
							<td>
								<select id="storyos-ge-environment" name="storyos_generation_engine_settings[environment]">
									<?php foreach ( [ 'local' => 'Local', 'development' => 'Development', 'staging' => 'Staging', 'production' => 'Production' ] as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['environment'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-credential-reference"><?php esc_html_e( 'Credential Reference', 'storyos-generation-engine' ); ?></label></th>
							<td>
								<input id="storyos-ge-credential-reference" name="storyos_generation_engine_settings[credential_reference]" type="text" class="regular-text" placeholder="env://COMFYUI_API_KEY" value="<?php echo esc_attr( $settings['credential_reference'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Reference only, for example env://COMFYUI_API_KEY or secret://comfyui/local. Raw credentials must never be entered or stored in WordPress.', 'storyos-generation-engine' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-credential-value"><?php esc_html_e( 'Provider Credential', 'storyos-generation-engine' ); ?></label></th>
							<td>
								<input id="storyos-ge-credential-value" name="storyos_generation_engine_settings[credential_value]" type="password" class="regular-text" autocomplete="new-password" value="" />
								<p class="description"><?php esc_html_e( 'Enter a new credential to replace the encrypted database value. The value is never displayed after saving.', 'storyos-generation-engine' ); ?></p>
								<p class="description"><strong><?php echo esc_html( $credential_metadata['configured'] ? __( 'Credential configured', 'storyos-generation-engine' ) : __( 'No credential configured', 'storyos-generation-engine' ) ); ?></strong><?php if ( ! empty( $credential_metadata['updated_at'] ) ) : ?> &middot; <?php echo esc_html( $credential_metadata['updated_at'] ); ?><?php endif; ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-secret-backend"><?php esc_html_e( 'Secret Backend', 'storyos-generation-engine' ); ?></label></th>
							<td>
								<select id="storyos-ge-secret-backend" name="storyos_generation_engine_settings[secret_backend]">
									<?php foreach ( [ 'env' => 'Environment variable', 'wpsecret' => 'Encrypted local backend', 'vault' => 'Managed secret service' ] as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['secret_backend'], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-enabled-models"><?php esc_html_e( 'Enabled Models', 'storyos-generation-engine' ); ?></label></th>
							<td><input id="storyos-ge-enabled-models" name="storyos_generation_engine_settings[enabled_models]" type="text" class="regular-text" placeholder="workflow-defined" value="<?php echo esc_attr( $settings['enabled_models'] ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated model IDs or workflow-defined.', 'storyos-generation-engine' ); ?></p></td>
						</tr>

						<tr>
							<th scope="row"><label for="storyos-ge-enabled-capabilities"><?php esc_html_e( 'Enabled Capabilities', 'storyos-generation-engine' ); ?></label></th>
							<td><input id="storyos-ge-enabled-capabilities" name="storyos_generation_engine_settings[enabled_capabilities]" type="text" class="regular-text" placeholder="text_to_image,image_to_image" value="<?php echo esc_attr( $settings['enabled_capabilities'] ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated capability names allowed for this connection.', 'storyos-generation-engine' ); ?></p></td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Connection Status', 'storyos-generation-engine' ); ?></th>
							<td><strong><?php echo esc_html( ucfirst( $settings['connection_status'] ) ); ?></strong><?php if ( ! empty( $settings['last_validated_at'] ) ) : ?><p class="description"><?php echo esc_html( sprintf( __( 'Last validated: %s', 'storyos-generation-engine' ), $settings['last_validated_at'] ) ); ?></p><?php endif; ?><p class="description"><?php esc_html_e( 'Status is controlled by connection validation and cannot be set manually.', 'storyos-generation-engine' ); ?></p></td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-mcp-server-url"><?php esc_html_e( 'ComfyUI MCP Server URL', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input
									id="storyos-ge-mcp-server-url"
									name="storyos_generation_engine_settings[mcp_server_url]"
									type="url"
									class="regular-text"
									placeholder="http://localhost:8000"
									value="<?php echo esc_attr( $settings['mcp_server_url'] ?: $settings['orchestrator_url'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Base URL for the ComfyUI MCP bridge used by generation submit/status/cancel operations.', 'storyos-generation-engine' ); ?></p>
								<p class="description"><?php esc_html_e( 'Legacy orchestrator URL settings remain supported as fallback.', 'storyos-generation-engine' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-workflow"><?php esc_html_e( 'Workflow Template', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input
									id="storyos-ge-workflow"
									name="storyos_generation_engine_settings[workflow]"
									type="text"
									class="regular-text"
									placeholder="character-sheet"
									value="<?php echo esc_attr( $settings['workflow'] ); ?>"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-provider-type"><?php esc_html_e( 'Provider Type', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input
									id="storyos-ge-provider-type"
									name="storyos_generation_engine_settings[provider_type]"
									type="text"
									class="regular-text"
									placeholder="provider-type"
									value="<?php echo esc_attr( $settings['provider_type'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Provider type registered by the StoryOS orchestrator.', 'storyos-generation-engine' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-request-timeout"><?php esc_html_e( 'Request Timeout (seconds)', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input
									id="storyos-ge-request-timeout"
									name="storyos_generation_engine_settings[request_timeout]"
									type="number"
									class="small-text"
									min="5"
									max="300"
									value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="storyos-ge-connection-id"><?php esc_html_e( 'Connection ID', 'storyos-generation-engine' ); ?></label>
							</th>
							<td>
								<input
									id="storyos-ge-connection-id"
									name="storyos_generation_engine_settings[connection_id]"
									type="number"
									class="small-text"
									min="1"
									value="<?php echo esc_attr( (string) $settings['connection_id'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'References the Provider Connection record in control plane data.', 'storyos-generation-engine' ); ?></p>
							</td>
						</tr>

					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'storyos-generation-engine' ) ); ?>
			</form>

		</div>
		<?php
	}

	/**
	 * Get the default provider for new jobs.
	 *
	 * @return string
	 */
	public static function get_default_provider(): string {
		$settings = self::get_settings();
		return Provider_Registry::normalize( (string) ( $settings['provider_type'] ?? '' ) );
	}
}
