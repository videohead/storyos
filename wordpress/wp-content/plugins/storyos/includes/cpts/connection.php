<?php
/**
 * Provider Connection Custom Post Type.
 *
 * A Provider Connection is a StoryOS-owned control-plane record that binds a
 * provider type (e.g. comfyui, veo) to a concrete endpoint, environment,
 * credential reference, and quota configuration. Generation jobs reference
 * connections by ID: { "provider_type": "comfyui", "connection_id": 32 }.
 *
 * Raw credentials are never stored here. The credential_reference field holds
 * a pointer only (e.g. env://COMFYUI_API_KEY or secret://comfyui/local);
 * secret values live in the environment or the encrypted Credential_Store.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

/**
 * Provider Connection Custom Post Type handler.
 */
class Connection {

	/**
	 * Connection CPT slug.
	 *
	 * @var string
	 */
	const CPT = 'storyos_connection';

	/**
	 * Allowed connection statuses.
	 *
	 * @var array<int, string>
	 */
	const STATUSES = [ 'unverified', 'verified', 'error', 'disabled' ];

	/**
	 * Allowed deployment environments.
	 *
	 * @var array<int, string>
	 */
	const ENVIRONMENTS = [ 'local', 'development', 'staging', 'production' ];

	/**
	 * Known provider types (extendable via the storyos_connection_provider_types filter).
	 *
	 * @return array<int, string>
	 */
	public static function provider_types(): array {
		$types = [ 'comfyui', 'veo', 'nova_reel' ];
		return apply_filters( 'storyos_connection_provider_types', $types );
	}

	/**
	 * Register the Provider Connection CPT and admin UI.
	 */
	public static function init(): void {
		self::register_cpt();
		self::register_meta_boxes();
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10, 2 );
	}

	/**
	 * Register the Provider Connection CPT.
	 */
	private static function register_cpt(): void {
		$provider_types = self::provider_types();

		$fields = [
			'connection_name'      => [
				'type'        => 'text',
				'label'       => 'Connection Name',
				'required'    => true,
				'description' => 'Human-readable name for this provider connection.',
			],
			'provider_type'        => [
				'type'        => 'select',
				'label'       => 'Provider Type',
				'required'    => true,
				'options'     => array_combine( $provider_types, $provider_types ),
				'description' => 'Provider type (currently: Comfy Cloud MCP).',
			],
			'environment'          => [
				'type'        => 'select',
				'label'       => 'Environment',
				'required'    => true,
				'options'     => [
					'local'       => 'Local',
					'development' => 'Development',
					'staging'     => 'Staging',
					'production'  => 'Production',
				],
			],
			'status'               => [
				'type'        => 'select',
				'label'       => 'Status',
				'required'    => true,
				'options'     => [
					'unverified' => 'Unverified',
					'verified'   => 'Verified',
					'error'      => 'Error',
					'disabled'   => 'Disabled',
				],
				'description' => 'Status is normally maintained by connection validation; set manually only to disable a connection.',
			],
			'endpoint_url'         => [
				'type'        => 'text',
				'label'       => 'Endpoint URL',
				'required'    => true,
				'description' => 'Base URL of the provider endpoint, e.g. http://comfyui:8188.',
			],
			'credential_reference' => [
				'type'        => 'text',
				'label'       => 'API Key / OAuth (Reference)',
				'required'    => false,
				'description' => 'Reference only, e.g. env://COMFYUI_API_KEY or secret://comfyui/local. Raw credentials must never be stored in WordPress.',
			],
			'model_access'         => [
				'type'        => 'textarea',
				'label'       => 'Model Access',
				'required'    => false,
				'description' => 'JSON array of model IDs this connection may use, e.g. ["sd_xl_base_1.0"]. Empty means workflow-defined.',
			],
			'enabled_structures'   => [
				'type'        => 'textarea',
				'label'       => 'Enabled Structures',
				'required'    => false,
				'description' => 'JSON array of generation structures enabled for this connection, e.g. ["character-sheet","scene-image"].',
			],
			'rate_limits'          => [
				'type'        => 'textarea',
				'label'       => 'Rate Limits',
				'required'    => false,
				'description' => 'JSON object, e.g. {"max_concurrent":1,"requests_per_minute":10}.',
			],
			'cost_controls'        => [
				'type'        => 'textarea',
				'label'       => 'Cost Controls',
				'required'    => false,
				'description' => 'JSON object, e.g. {"max_cost_per_job":0.5,"monthly_budget":50}.',
			],
		];

		\StoryOS\Utils\register_cpt(
			self::CPT,
			'Connections',
			[
				'menu_icon' => 'dashicons-admin-network',
			],
			$fields
		);
	}

	/**
	 * Register admin UI for connection configuration.
	 */
	private static function register_meta_boxes(): void {
		add_action( 'add_meta_boxes', function (): void {
			add_meta_box(
				'storyos_connection_details',
				'Connection Details',
				[ self::class, 'render_connection_meta_box' ],
				self::CPT,
				'normal',
				'default'
			);
		} );
	}

	/**
	 * Render the connection details meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_connection_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'storyos_connection_details', 'storyos_connection_nonce' );
		$fields = \StoryOS\Utils\storyos_get_fields( self::CPT );
		?>
		<p><em><?php echo esc_html__( 'Credentials are stored by reference only. Use the Generation Engine settings page or environment variables for secret values; they are never persisted in this record.', 'storyos' ); ?></em></p>
		<table class="form-table">
			<?php foreach ( $fields as $field_name => $field ) : ?>
				<?php $value = get_post_meta( $post->ID, $field_name, true ); ?>
				<tr>
					<th><label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<?php
						switch ( $field['type'] ) {
							case 'textarea':
								?>
								<textarea name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" class="large-text code" rows="5"><?php echo esc_textarea( $value ); ?></textarea>
								<?php
								break;
							case 'select':
								?>
								<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>">
									<option value=""><?php echo esc_html__( 'Select...', 'storyos' ); ?></option>
									<?php foreach ( (array) $field['options'] as $option_value => $option_label ) : ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php
								break;
							default:
								?>
								<input type="text" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
								<?php
								break;
						}
						if ( ! empty( $field['description'] ) ) {
							echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
						}
					?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Save connection meta fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['storyos_connection_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storyos_connection_nonce'] ) ), 'storyos_connection_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( self::CPT );
		foreach ( $fields as $field_name => $field ) {
			if ( ! array_key_exists( $field_name, $_POST ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $field_name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below.

			switch ( $field_name ) {
				case 'connection_name':
					$value = sanitize_text_field( $raw );
					break;

				case 'provider_type':
					$value = sanitize_key( $raw );
					if ( ! in_array( $value, self::provider_types(), true ) ) {
						$value = '';
					}
					break;

				case 'environment':
					$value = sanitize_key( $raw );
					if ( ! in_array( $value, self::ENVIRONMENTS, true ) ) {
						$value = 'local';
					}
					break;

				case 'status':
					$value = sanitize_key( $raw );
					if ( ! in_array( $value, self::STATUSES, true ) ) {
						$value = 'unverified';
					}
					break;

				case 'endpoint_url':
					$value = esc_url_raw( trim( (string) $raw ) );
					break;

				case 'credential_reference':
					$value = sanitize_text_field( $raw );
					break;

				case 'model_access':
				case 'enabled_structures':
				case 'rate_limits':
				case 'cost_controls':
					$value = self::sanitize_json_field( $raw );
					if ( null === $value ) {
						// Invalid JSON: keep the previously stored value.
						continue 2;
					}
					break;

				default:
					$value = sanitize_textarea_field( $raw );
			}

			update_post_meta( $post_id, $field_name, $value );
		}
	}

	/**
	 * Sanitize a JSON textarea field.
	 *
	 * @param string $raw Raw input.
	 * @return string|null Normalized JSON string, or null when the input is
	 *                     non-empty but not valid JSON.
	 */
	private static function sanitize_json_field( string $raw ): ?string {
		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return '';
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}

		return wp_json_encode( $decoded );
	}
}
