<?php
/**
 * Provider Connection Custom Post Type.
 *
 * A Provider Connection is a World Graph Studio-owned control-plane record that binds a
 * provider type (e.g. comfyui, veo) to a concrete endpoint, environment,
 * credential reference, and quota configuration. Generation jobs reference
 * connections by ID: { "provider_type": "comfyui", "connection_id": 32 }.
 *
 * Raw credentials are never stored here. The credential-reference fields hold
 * pointers only (e.g. env://COMFYUI_API_KEY or secret://comfyui/local);
 * secret values live in the environment or the encrypted Credential_Store.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Provider Connection Custom Post Type handler.
 */
class Connection {

	/**
	 * Connection CPT slug.
	 *
	 * @var string
	 */
	const CPT = 'worldgraph_conn';

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
	 * Known provider types (extendable via the worldgraph_conn_provider_types filter).
	 *
	 * @return array<int, string>
	 */
	public static function provider_types(): array {
		$types = \WorldGraph\Utils\Connection_Adapters::provider_types();
		return apply_filters( 'worldgraph_conn_provider_types', $types );
	}

	/**
	 * Register the Provider Connection CPT and admin UI.
	 */
	public static function init(): void {
		self::register_cpt();
		self::register_meta_boxes();
		add_filter( 'acf/update_value', [ __CLASS__, 'sanitize_scf_value' ], 30, 4 );
		add_filter( 'acf/validate_value', [ __CLASS__, 'validate_scf_value' ], 20, 4 );
		add_filter( 'acf/load_field/key=field_worldgraph_conn_provider_type', [ __CLASS__, 'load_provider_choices' ] );
		add_action( 'acf/save_post', [ __CLASS__, 'after_scf_save' ], 20 );
		add_action( 'worldgraph_after_rest_entity_save', [ __CLASS__, 'after_rest_save' ], 10, 3 );
		add_action( 'wp_ajax_worldgraph_sync_connection_catalog', [ __CLASS__, 'ajax_sync_catalog' ] );
		add_action( 'wp_ajax_worldgraph_enable_connection_catalog_entry', [ __CLASS__, 'ajax_enable_catalog_entry' ] );
		add_action( 'wp_ajax_worldgraph_disable_connection_catalog_entry', [ __CLASS__, 'ajax_disable_catalog_entry' ] );
		add_action( 'wp_ajax_worldgraph_materialize_connection_catalog_entry', [ __CLASS__, 'ajax_materialize_catalog_entry' ] );
		add_action( 'wp_ajax_worldgraph_download_connection_catalog_entry', [ __CLASS__, 'ajax_download_catalog_entry' ] );
	}

	/**
	 * Keep the archived Provider Type field aligned with adapter extensions.
	 *
	 * @param array<string, mixed> $field SCF field.
	 * @return array<string, mixed>
	 */
	public static function load_provider_choices( array $field ): array {
		$provider_types   = self::provider_types();
		$field['choices'] = empty( $provider_types ) ? [] : array_combine( $provider_types, $provider_types );
		return $field;
	}

	/**
	 * Apply Connection-specific normalization after SCF's field-type handling.
	 *
	 * @param mixed                $value    Submitted value.
	 * @param int|string           $post_id  SCF object ID.
	 * @param array<string, mixed> $field    SCF field.
	 * @param mixed                $original Original submitted value.
	 * @return mixed
	 */
	public static function sanitize_scf_value( $value, $post_id, array $field, $original ) {
		if ( ! is_numeric( $post_id ) || self::CPT !== get_post_type( (int) $post_id ) || 0 !== strpos( (string) ( $field['key'] ?? '' ), 'field_worldgraph_conn_' ) ) {
			return $value;
		}

		switch ( (string) ( $field['name'] ?? '' ) ) {
			case 'provider_type':
				$value = sanitize_key( (string) $value );
				return in_array( $value, self::provider_types(), true ) ? $value : '';

			case 'environment':
				$value = sanitize_key( (string) $value );
				return in_array( $value, self::ENVIRONMENTS, true ) ? $value : 'local';

			case 'status':
				$value = sanitize_key( (string) $value );
				return in_array( $value, self::STATUSES, true ) ? $value : 'unverified';

			case 'is_default':
				$value = sanitize_key( (string) $value );
				return 'yes' === $value ? 'yes' : 'no';

			case 'endpoint_url':
			case 'mcp_endpoint_url':
				return esc_url_raw( trim( (string) $value ) );

			case 'max_tokens':
				return '' === trim( (string) $value ) ? '' : (string) absint( $value );

			case 'temperature':
				return '' === trim( (string) $value ) ? '' : (string) (float) $value;

			case 'model_access':
			case 'enabled_structures':
			case 'enabled_templates':
			case 'capabilities':
			case 'mcp_configuration':
			case 'rate_limits':
			case 'cost_controls':
				$normalized = self::sanitize_json_field( (string) $value );
				return null === $normalized
					? get_post_meta( (int) $post_id, (string) $field['name'], true )
					: $normalized;

			case 'connection_name':
			case 'credential_reference':
			case 'mcp_credential_reference':
			case 'model':
				return sanitize_text_field( (string) $value );
		}

		return $value;
	}

	/**
	 * Report domain validation errors in SCF before any Connection values save.
	 *
	 * @param bool|string          $valid Whether the value is valid so far.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field SCF field.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_scf_value( $valid, $value, array $field, string $input ) {
		if ( true !== $valid || 0 !== strpos( (string) ( $field['key'] ?? '' ), 'field_worldgraph_conn_' ) ) {
			return $valid;
		}

		$name = (string) ( $field['name'] ?? '' );
		if ( 'provider_type' === $name && ! in_array( sanitize_key( (string) $value ), self::provider_types(), true ) ) {
			return __( 'Select a supported provider type.', 'worldgraph' );
		}
		if ( 'environment' === $name && ! in_array( sanitize_key( (string) $value ), self::ENVIRONMENTS, true ) ) {
			return __( 'Select a supported environment.', 'worldgraph' );
		}
		if ( 'status' === $name && ! in_array( sanitize_key( (string) $value ), self::STATUSES, true ) ) {
			return __( 'Select a supported connection status.', 'worldgraph' );
		}
		if ( 'is_default' === $name && ! in_array( sanitize_key( (string) $value ), [ 'yes', 'no' ], true ) ) {
			return __( 'Select yes or no for the active connection flag.', 'worldgraph' );
		}
		if ( in_array( $name, [ 'max_tokens', 'temperature' ], true ) && '' !== trim( (string) $value ) && ! is_numeric( $value ) ) {
			return __( 'Enter a numeric value.', 'worldgraph' );
		}
		if ( in_array( $name, [ 'model_access', 'enabled_structures', 'enabled_templates', 'capabilities', 'mcp_configuration', 'rate_limits', 'cost_controls' ], true ) && null === self::sanitize_json_field( (string) $value ) ) {
			return __( 'Enter a valid JSON array or object.', 'worldgraph' );
		}

		return $valid;
	}

	/**
	 * Load the selected adapter and schedule provider catalog refreshes after
	 * SCF has persisted a Connection edit.
	 *
	 * @param int|string $post_id SCF object ID.
	 */
	public static function after_scf_save( $post_id ): void {
		if ( ! is_numeric( $post_id ) || self::CPT !== get_post_type( (int) $post_id ) ) {
			return;
		}

		$post_id       = (int) $post_id;
		$provider_type = (string) \WorldGraph\Utils\worldgraph_get_field_value( $post_id, 'provider_type' );
		$status        = (string) \WorldGraph\Utils\worldgraph_get_field_value( $post_id, 'status' );
		$environment   = (string) \WorldGraph\Utils\worldgraph_get_field_value( $post_id, 'environment' );
		self::enforce_single_default( $post_id, $provider_type, $environment );
		if ( 'disabled' === $status ) {
			return;
		}

		\WorldGraph\Utils\Connection_Adapters::load( $provider_type );

		if ( 'fal' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\Fal_Catalog::HOOK, [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Fal_Catalog::HOOK, [ $post_id ] );
		} elseif ( 'elevenlabs' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] );
		} elseif ( 'suno' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\Suno_Catalog::HOOK, [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Suno_Catalog::HOOK, [ $post_id ] );
		} elseif ( 'videodraft' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $post_id ] ) ) {
			wp_schedule_single_event( time() + 5, \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $post_id ] );
		}
	}

	/**
	 * Only one Connection can be the active default per provider type and
	 * environment, so Generate has an unambiguous choice when a Template
	 * does not pin a Connection. Clear the flag on every sibling when a
	 * Connection is saved as the active one.
	 *
	 * @param int    $post_id       Saved Connection post ID.
	 * @param string $provider_type Provider type of the saved Connection.
	 * @param string $environment   Environment of the saved Connection.
	 */
	private static function enforce_single_default( int $post_id, string $provider_type, string $environment ): void {
		if ( 'yes' !== (string) \WorldGraph\Utils\worldgraph_get_field_value( $post_id, 'is_default' ) || '' === $provider_type ) {
			return;
		}

		$siblings = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'exclude'        => [ $post_id ],
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'provider_type',
					'value' => $provider_type,
				],
				[
					'key'   => 'environment',
					'value' => $environment,
				],
			],
		] );

		foreach ( $siblings as $sibling_id ) {
			if ( 'yes' === get_post_meta( $sibling_id, 'is_default', true ) ) {
				update_post_meta( $sibling_id, 'is_default', 'no' );
			}
		}
	}

	/**
	 * Run the same Connection lifecycle after custom World Graph Studio REST writes.
	 *
	 * @param int              $post_id Post ID.
	 * @param string           $cpt     CPT slug.
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function after_rest_save( int $post_id, string $cpt, \WP_REST_Request $request ): void {
		if ( self::CPT === $cpt ) {
			self::after_scf_save( $post_id );
		}
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
				'description' => 'Provider adapter used by the paired Templates, such as ComfyUI, FAL, Google Gemini, or Veo.',
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
			'is_default'           => [
				'type'        => 'select',
				'label'       => 'Active Connection',
				'required'    => false,
				'options'     => [
					'no'  => 'No',
					'yes' => 'Yes',
				],
				'description' => 'Marks this the active Connection Generate uses for its provider type and environment when a Template does not pin one. Only one Connection per provider type and environment can be active; setting this saves the others as No.',
			],
			'endpoint_url'         => [
				'type'        => 'text',
				'label'       => 'Endpoint URL',
				'required'    => true,
				'description' => 'Provider endpoint. For SunoAPI.org use https://api.sunoapi.org; for ElevenLabs use https://api.elevenlabs.io/v1; for local ComfyUI use its HTTP API base URL.',
			],
			'mcp_endpoint_url'     => [
				'type'        => 'text',
				'label'       => 'MCP Endpoint URL',
				'required'    => false,
				'description' => 'Streamable HTTP MCP endpoint. Required for fal; use https://suno.mcp.acedata.cloud/mcp for Suno MCP; optional for local ComfyUI discovery and downloads.',
			],
			'credential_reference' => [
				'type'        => 'text',
				'label'       => 'API Key / OAuth (Reference)',
				'required'    => false,
				'description' => 'REST/API credential or environment reference, e.g. env://SUNO_API_KEY, env://ELEVENLABS_API_KEY, or env://COMFYUI_API_KEY.',
			],
			'mcp_credential_reference' => [
				'type'        => 'text',
				'label'       => 'MCP API Key / OAuth (Reference)',
				'required'    => false,
				'description' => 'Optional separate credential for the MCP endpoint. Suno MCP requires an AceData Cloud token such as env://ACEDATACLOUD_API_TOKEN, which is distinct from a SunoAPI.org key.',
			],
			'mcp_configuration'     => [
				'type'        => 'textarea',
				'label'       => 'MCP Configuration (JSON)',
				'required'    => false,
				'description' => 'Optional non-secret MCP deployment settings as a JSON object, such as transport, host, port, path, Docker service, or startup health-check details. Keep credentials in the MCP API Key / OAuth reference field.',
			],
			'capabilities'         => [
				'type'        => 'textarea',
				'label'       => 'Capabilities (JSON)',
				'required'    => false,
				'description' => 'Optional non-secret capability profile. Use chat, vision, asset_generation, and modalities to describe what this Connection and model can do; pair asset modalities with Templates.',
			],
			'model'                => [
				'type'        => 'text',
				'label'       => 'Model',
				'required'    => false,
				'description' => 'Optional default model. For SunoAPI.org use V5_5 (mapped to chirp-v5-5 for MCP); for fal use an endpoint ID; for ElevenLabs use a speech model ID.',
			],
			'max_tokens'           => [
				'type'        => 'text',
				'label'       => 'Max Tokens',
				'required'    => false,
				'description' => 'Maximum tokens for LLM responses.',
			],
			'temperature'          => [
				'type'        => 'text',
				'label'       => 'Temperature',
				'required'    => false,
				'description' => 'Creativity level (0.0 = deterministic, 1.0 = creative).',
			],
			'model_access'         => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Model Access',
				'required'    => false,
				'description' => 'Optional JSON allowlist. fal uses model endpoint IDs; ElevenLabs uses voice IDs; Suno uses model version IDs. Empty lets the adapter select a default.',
			],
			'enabled_structures'   => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Enabled Structures',
				'required'    => false,
				'description' => 'JSON array of generation structures enabled for this connection, e.g. ["character-sheet","scene-image"].',
			],
			'enabled_templates'    => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Enabled Templates',
				'required'    => false,
				'description' => 'JSON array of provider templates enabled on this connection. Managed by the Template Catalog panel; edit only to recover from a bad sync.',
			],
			'rate_limits'          => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Rate Limits',
				'required'    => false,
				'description' => 'JSON object, e.g. {"max_concurrent":1,"requests_per_minute":10}.',
			],
			'cost_controls'        => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Cost Controls',
				'required'    => false,
				'description' => 'JSON object, e.g. {"max_cost_per_job":0.5,"monthly_budget":50}.',
			],
		];

		\WorldGraph\Utils\register_cpt(
			self::CPT,
			'Connections',
			[
				'menu_icon'          => 'dashicons-admin-network',
				'public'             => false,
				'publicly_queryable' => false,
				'show_in_rest'       => false,
				// The native post list is intentionally not registered in the admin menu;
				// WorldGraph\Admin\Connections::render_page() is the single Connections view.
				'show_in_menu'       => false,
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
				'worldgraph_conn_configurator',
				'Connection Configurator',
				[ self::class, 'render_configurator_meta_box' ],
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
		wp_nonce_field( 'worldgraph_conn_details', 'worldgraph_conn_nonce' );
		$fields = \WorldGraph\Utils\worldgraph_get_fields( self::CPT );
		?>
		<p><em><?php echo esc_html__( 'Credentials are stored by reference only. Use the Generation Engine settings page or environment variables for secret values; they are never persisted in this record.', 'worldgraph' ); ?></em></p>
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
									<option value=""><?php echo esc_html__( 'Select...', 'worldgraph' ); ?></option>
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
		<script>
			(function () {
				var provider = document.getElementById('provider_type');
				var endpoint = document.getElementById('endpoint_url');
				var mcpEndpoint = document.getElementById('mcp_endpoint_url');
				var endpoints = <?php echo wp_json_encode( array_map( [ '\WorldGraph\Utils\Connection_Adapters', 'endpoint' ], array_combine( self::provider_types(), self::provider_types() ) ) ); ?>;
				var mcpEndpoints = <?php echo wp_json_encode( array_map( [ '\WorldGraph\Utils\Connection_Adapters', 'mcp_endpoint' ], array_combine( self::provider_types(), self::provider_types() ) ) ); ?>;
				if (!provider || !endpoint || !mcpEndpoint) { return; }
				provider.addEventListener('change', function () {
					if (!endpoint.value.trim() && endpoints[provider.value]) { endpoint.value = endpoints[provider.value]; }
					if (!mcpEndpoint.value.trim() && mcpEndpoints[provider.value]) { mcpEndpoint.value = mcpEndpoints[provider.value]; }
				});
			}());
		</script>
		<?php
	}

	/**
	 * Render provider catalog controls that sync MCP capabilities and materialize
	 * discoverable provider templates into World Graph Studio Template posts.
	 *
	 * @param \WP_Post $post Connection post.
	 */
	public static function render_configurator_meta_box( \WP_Post $post ): void {
		$provider_type = sanitize_key( (string) get_post_meta( $post->ID, 'provider_type', true ) );
		if ( '' === $provider_type ) {
			echo '<p>' . esc_html__( 'Save this Connection with a provider type to configure discoverable templates.', 'worldgraph' ) . '</p>';
			return;
		}

		if ( 'fal' === $provider_type ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'fal' );
			$synced_at = (string) get_post_meta( $post->ID, 'fal_catalog_synced_at', true );
			$error     = (string) get_post_meta( $post->ID, 'fal_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'World Graph Studio asks fal MCP to select or inspect models and automatically maintains the paired Templates. Saving or testing this Connection schedules a schema sync; users do not need to copy provider schemas by hand.', 'worldgraph' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Model Access, when set, is the authoritative endpoint allowlist and provisions one Template per endpoint.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Model is the preferred default endpoint. With neither field set, fal MCP supplies a current text-to-image model.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'MCP model schemas and their defaults are stored on the generated Templates; World Graph Studio supplies prompts and bound media at runtime.', 'worldgraph' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'worldgraph' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'elevenlabs' === $provider_type ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'elevenlabs' );
			$synced_at = (string) get_post_meta( $post->ID, 'elevenlabs_catalog_synced_at', true );
			$error = (string) get_post_meta( $post->ID, 'elevenlabs_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'World Graph Studio discovers ElevenLabs voices and models and maintains endpoint-specific Templates for speech, dialogue, sound effects, music, and voice design. Saving or testing this Connection triggers a catalog sync.', 'worldgraph' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Model selects the ElevenLabs speech model. When empty, World Graph Studio prefers eleven_multilingual_v2.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Model Access may contain a JSON array of voice IDs. When empty, World Graph Studio provisions one available voice to minimize setup.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Each generated audio response is imported into WordPress before its generation job completes.', 'worldgraph' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'worldgraph' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'suno' === $provider_type ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'suno' );
			$synced_at = (string) get_post_meta( $post->ID, 'suno_catalog_synced_at', true );
			$error     = (string) get_post_meta( $post->ID, 'suno_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'World Graph Studio maintains transport-specific music and lyrics Templates for SunoAPI.org REST and the AceData Cloud Suno MCP server. Saving or testing this Connection refreshes those Templates.', 'worldgraph' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'API Key authenticates api.sunoapi.org; MCP API Key authenticates suno.mcp.acedata.cloud. These services issue different bearer tokens.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Model selects the preferred Suno version. World Graph Studio maps API model names such as V5_5 to MCP model names such as chirp-v5-5.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Suno normally returns two tracks. Every final track URL is imported into WordPress before a generation job completes.', 'worldgraph' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'worldgraph' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'videodraft' === $provider_type ) {
			\WorldGraph\Utils\Connection_Adapters::load( 'videodraft' );
			$synced_at = (string) get_post_meta( $post->ID, 'videodraft_catalog_synced_at', true );
			$error     = (string) get_post_meta( $post->ID, 'videodraft_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'World Graph Studio discovers VideoDraft MCP tools and maintains provider-backed Templates for image, video, voiceover, music, and sound effects. This Connection is also shared with the bundled VideoDraft Sync plugin.', 'worldgraph' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Use a dedicated VideoDraft personal access token or an env://VIDEODRAFT_API_KEY reference.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Tool schemas are read live and stored with each generated Template.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Generated media is downloaded into the WordPress Media Library before the job completes.', 'worldgraph' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'worldgraph' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'descript' === $provider_type ) {
			?>
			<p><?php echo esc_html__( 'This Connection authenticates the Descript REST API and is shared with the bundled Descript Sync plugin.', 'worldgraph' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Use a Descript personal API token or an env://DESCRIPT_API_TOKEN reference. Each token is scoped to one Descript drive.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Descript Sync exports composition transcripts into Story Graph Scenes and imports bound video/audio media as new Descript projects.', 'worldgraph' ); ?></li>
				<li><?php echo esc_html__( 'Descript has no editable project schema, so this integration is one-way per direction rather than a structural mirror.', 'worldgraph' ); ?></li>
			</ul>
			<?php
			return;
		}

		if ( 'comfyui' !== $provider_type ) {
			echo '<p>' . esc_html__( 'This provider type does not expose a configurator yet. ComfyUI is the reference implementation for catalog sync, template materialization, and MCP-triggered model downloads.', 'worldgraph' ) . '</p>';
			return;
		}
		\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );

		$snapshot = \WorldGraph\Utils\Comfy_Catalog::get( (int) $post->ID );
		?>
		<p class="description"><?php echo esc_html__( 'Sync this Connection against its MCP-advertised capabilities, then enable and materialize provider templates into World Graph Studio Templates. Download actions trigger provider-side model fetches where available.', 'worldgraph' ); ?></p>
		<p>
			<button type="button" class="button" id="worldgraph-connection-sync-catalog"><?php echo esc_html__( 'Sync Catalog', 'worldgraph' ); ?></button>
			<button type="button" class="button button-primary" id="worldgraph-connection-guided-setup" style="margin-left:6px;"><?php echo esc_html__( 'Auto-Prepare Mappable Templates', 'worldgraph' ); ?></button>
			<span class="description" style="margin-left:8px;"><?php
			printf(
				/* translators: %s: catalog timestamp. */
				esc_html__( 'Last synced: %s', 'worldgraph' ),
				esc_html( (string) ( $snapshot['synced_at'] ?: '—' ) )
			);
			?></span>
		</p>
		<p class="description" id="worldgraph-connection-process-guide"><?php echo esc_html__( 'Recommended flow: 1) Sync Catalog, 2) Auto-Prepare Mappable Templates, 3) Download Requirements for templates that advertise URLs.', 'worldgraph' ); ?></p>
		<div id="worldgraph-connection-configurator-status" aria-live="polite" class="description"></div>
		<div id="worldgraph-connection-configurator-summary" class="description" style="margin:6px 0 10px;"></div>
		<div id="worldgraph-connection-configurator-log" class="description" style="margin:6px 0 10px;"></div>
		<div id="worldgraph-connection-configurator-results"></div>
		<script>
			(function () {
				var nonce = '<?php echo esc_js( wp_create_nonce( 'worldgraph_conn_configurator' ) ); ?>';
				var connectionId = '<?php echo esc_js( (string) $post->ID ); ?>';
				var status = document.getElementById('worldgraph-connection-configurator-status');
				var summary = document.getElementById('worldgraph-connection-configurator-summary');
				var log = document.getElementById('worldgraph-connection-configurator-log');
				var results = document.getElementById('worldgraph-connection-configurator-results');
				var syncButton = document.getElementById('worldgraph-connection-sync-catalog');
				var guidedButton = document.getElementById('worldgraph-connection-guided-setup');
				var isBusy = false;
				var actionLog = [];

				function timestamp() {
					var now = new Date();
					return now.toLocaleTimeString();
				}

				function pushLog(message) {
					actionLog.unshift('[' + timestamp() + '] ' + message);
					actionLog = actionLog.slice(0, 8);
					log.textContent = actionLog.join(' | ');
				}

				function setBusy(flag, message) {
					isBusy = flag;
					if (message) {
						status.textContent = message;
					}
					syncButton.disabled = flag;
					guidedButton.disabled = flag;
					Array.prototype.forEach.call(results.querySelectorAll('button'), function (button) {
						button.disabled = flag;
					});
				}

				function post(action, extra) {
					var payload = { action: action, nonce: nonce, connection_id: connectionId };
					Object.assign(payload, extra || {});
					return fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams(payload)
					}).then(function (response) { return response.json(); });
				}

				function badgeText(entry) {
					var modality = entry.modality || 'unmappable';
					var state = entry.status || 'unknown';
					return modality + ' | ' + state;
				}

				function setSummary(entries) {
					var total = entries.length;
					var mappable = entries.filter(function (entry) { return !!entry.modality; }).length;
					var enabled = entries.filter(function (entry) { return !!entry.enabled; }).length;
					var materialized = entries.filter(function (entry) { return !!entry.template_id; }).length;
					summary.textContent = 'Templates: ' + total + ' total, ' + mappable + ' mappable, ' + enabled + ' enabled, ' + materialized + ' materialized.';
				}

				function withResponseMessage(response, fallback) {
					if (response && response.data && response.data.message) {
						return response.data.message;
					}

					return fallback;
				}

				function render(entries) {
					results.replaceChildren();
					if (!entries || !entries.length) {
						setSummary([]);
						results.textContent = '<?php echo esc_js( __( 'No provider templates discovered yet. Sync the catalog first.', 'worldgraph' ) ); ?>';
						return;
					}

					setSummary(entries);

					entries.forEach(function (entry) {
						var row = document.createElement('p');
						row.style.margin = '0 0 10px';
						var title = document.createElement('strong');
						title.textContent = (entry.name || entry.id) + ' ';
						row.appendChild(title);

						var badge = document.createElement('span');
						badge.textContent = '[' + badgeText(entry) + '] ';
						badge.className = 'description';
						row.appendChild(badge);

						var enable = document.createElement('button');
						enable.type = 'button';
						enable.className = 'button button-small';
						enable.textContent = entry.enabled ? '<?php echo esc_js( __( 'Disable', 'worldgraph' ) ); ?>' : '<?php echo esc_js( __( 'Enable', 'worldgraph' ) ); ?>';
						enable.addEventListener('click', function () {
							if (isBusy) {
								return;
							}
							var action = entry.enabled ? 'worldgraph_disable_connection_catalog_entry' : 'worldgraph_enable_connection_catalog_entry';
							setBusy(true, '<?php echo esc_js( __( 'Updating catalog entry…', 'worldgraph' ) ); ?>');
							post(action, { entry_id: entry.id }).then(function (response) {
								status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Catalog entry updated.', 'worldgraph' ) ); ?>');
								pushLog(status.textContent);
								if (response.success && response.data && response.data.snapshot) {
									render(response.data.snapshot.entries || []);
								}
							}).finally(function () {
								setBusy(false);
							});
						});
						row.appendChild(enable);

						var prepare = document.createElement('button');
						prepare.type = 'button';
						prepare.className = 'button button-small';
						prepare.style.marginLeft = '6px';
						prepare.textContent = '<?php echo esc_js( __( 'Enable + Materialize', 'worldgraph' ) ); ?>';
						prepare.disabled = !entry.modality;
						prepare.addEventListener('click', function () {
							if (isBusy || !entry.modality) {
								return;
							}

							setBusy(true, '<?php echo esc_js( __( 'Preparing provider template…', 'worldgraph' ) ); ?>');
							var sequence = Promise.resolve();
							if (!entry.enabled) {
								sequence = sequence.then(function () {
									return post('worldgraph_enable_connection_catalog_entry', { entry_id: entry.id });
								});
							}
							sequence
								.then(function () {
									return post('worldgraph_materialize_connection_catalog_entry', { entry_id: entry.id });
								})
								.then(function (response) {
									status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Template prepared.', 'worldgraph' ) ); ?>');
									pushLog(status.textContent);
									if (response.success && response.data && response.data.snapshot) {
										render(response.data.snapshot.entries || []);
									}
								})
								.catch(function () {
									status.textContent = '<?php echo esc_js( __( 'Template preparation failed.', 'worldgraph' ) ); ?>';
									pushLog(status.textContent);
								})
								.finally(function () {
									setBusy(false);
								});
						});
						row.appendChild(prepare);

						var materialize = document.createElement('button');
						materialize.type = 'button';
						materialize.className = 'button button-small';
						materialize.style.marginLeft = '6px';
						materialize.textContent = '<?php echo esc_js( __( 'Materialize Template', 'worldgraph' ) ); ?>';
						materialize.addEventListener('click', function () {
							if (isBusy) {
								return;
							}
							setBusy(true, '<?php echo esc_js( __( 'Materializing Template…', 'worldgraph' ) ); ?>');
							post('worldgraph_materialize_connection_catalog_entry', { entry_id: entry.id }).then(function (response) {
								status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Template materialized.', 'worldgraph' ) ); ?>');
								pushLog(status.textContent);
								if (response.success && response.data && response.data.snapshot) {
									render(response.data.snapshot.entries || []);
								}
							}).finally(function () {
								setBusy(false);
							});
						});
						row.appendChild(materialize);

						var download = document.createElement('button');
						download.type = 'button';
						download.className = 'button button-small';
						download.style.marginLeft = '6px';
						download.textContent = '<?php echo esc_js( __( 'Download Requirements', 'worldgraph' ) ); ?>';
						download.addEventListener('click', function () {
							if (isBusy) {
								return;
							}
							setBusy(true, '<?php echo esc_js( __( 'Requesting provider downloads…', 'worldgraph' ) ); ?>');
							post('worldgraph_download_connection_catalog_entry', { entry_id: entry.id }).then(function (response) {
								status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Download request sent.', 'worldgraph' ) ); ?>');
								pushLog(status.textContent);
							}).finally(function () {
								setBusy(false);
							});
						});
						row.appendChild(download);

						results.appendChild(row);
					});
				}

				syncButton.addEventListener('click', function () {
					if (isBusy) {
						return;
					}
					setBusy(true, '<?php echo esc_js( __( 'Syncing provider catalog…', 'worldgraph' ) ); ?>');
					post('worldgraph_sync_connection_catalog').then(function (response) {
						if (!response.success) {
							status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Catalog sync failed.', 'worldgraph' ) ); ?>');
							pushLog(status.textContent);
							return;
						}
						status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Catalog synced.', 'worldgraph' ) ); ?>');
						pushLog(status.textContent);
						render((response.data.snapshot && response.data.snapshot.entries) || []);
					}).finally(function () {
						setBusy(false);
					});
				});

				guidedButton.addEventListener('click', function () {
					if (isBusy) {
						return;
					}

					setBusy(true, '<?php echo esc_js( __( 'Starting guided setup: syncing catalog…', 'worldgraph' ) ); ?>');
					post('worldgraph_sync_connection_catalog').then(function (response) {
						if (!response.success) {
							status.textContent = withResponseMessage(response, '<?php echo esc_js( __( 'Catalog sync failed.', 'worldgraph' ) ); ?>');
							pushLog(status.textContent);
							return Promise.reject();
						}

						var entries = (response.data.snapshot && response.data.snapshot.entries) || [];
						render(entries);
						pushLog('<?php echo esc_js( __( 'Catalog synced. Preparing mappable templates…', 'worldgraph' ) ); ?>');

						var queue = entries.filter(function (entry) { return !!entry.modality; });
						if (!queue.length) {
							status.textContent = '<?php echo esc_js( __( 'No mappable templates were discovered.', 'worldgraph' ) ); ?>';
							pushLog(status.textContent);
							return Promise.resolve(response);
						}

						var prepared = 0;
						var failures = 0;

						return queue.reduce(function (promise, entry, index) {
							return promise.then(function () {
								status.textContent = 'Preparing template ' + (index + 1) + ' of ' + queue.length + ': ' + (entry.name || entry.id);
								var sequence = Promise.resolve();
								if (!entry.enabled) {
									sequence = sequence.then(function () {
										return post('worldgraph_enable_connection_catalog_entry', { entry_id: entry.id });
									});
								}

								sequence = sequence.then(function () {
									return post('worldgraph_materialize_connection_catalog_entry', { entry_id: entry.id });
								});

								return sequence.then(function (opResponse) {
									if (opResponse && opResponse.success) {
										prepared++;
									} else {
										failures++;
									}
									if (opResponse && opResponse.data && opResponse.data.snapshot) {
										render(opResponse.data.snapshot.entries || []);
									}
								}).catch(function () {
									failures++;
								});
							});
						}, Promise.resolve()).then(function () {
							status.textContent = 'Guided setup finished. Prepared ' + prepared + ' template(s); ' + failures + ' failed.';
							pushLog(status.textContent);
							return post('worldgraph_sync_connection_catalog').then(function (finalResponse) {
								if (finalResponse && finalResponse.success && finalResponse.data && finalResponse.data.snapshot) {
									render(finalResponse.data.snapshot.entries || []);
								}
							});
						});
					}).catch(function () {
						if (!status.textContent) {
							status.textContent = '<?php echo esc_js( __( 'Guided setup did not complete.', 'worldgraph' ) ); ?>';
							pushLog(status.textContent);
						}
					}).finally(function () {
						setBusy(false);
					});
				});

				render(<?php echo wp_json_encode( (array) ( $snapshot['entries'] ?? [] ) ); ?>);
				pushLog('<?php echo esc_js( __( 'Configurator ready.', 'worldgraph' ) ); ?>');
			}());
		</script>
		<?php
	}

	/** Sync the selected Connection's provider catalog. */
	public static function ajax_sync_catalog(): void {
		$connection_id = self::authorize_configurator_request();
		$result = self::catalog_sync( $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/** Enable one provider catalog entry. */
	public static function ajax_enable_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'worldgraph' ) ] );
		}

		$result = self::catalog_enable_entry( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/** Disable one provider catalog entry. */
	public static function ajax_disable_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'worldgraph' ) ] );
		}

		$result = self::catalog_disable_entry( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/** Materialize one provider catalog entry into a World Graph Studio Template post. */
	public static function ajax_materialize_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'worldgraph' ) ] );
		}

		$result = self::catalog_materialize_entry( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/** Request provider-side downloads for one catalog entry. */
	public static function ajax_download_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'worldgraph' ) ] );
		}

		$result = self::catalog_download_entry( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Sync provider catalog for a ComfyUI connection.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_sync( int $connection_id ) {
		$result = \WorldGraph\Utils\Comfy_Catalog::sync( $connection_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'message'  => sprintf( __( 'Catalog synced: %d provider templates discovered.', 'worldgraph' ), count( (array) ( $result['entries'] ?? [] ) ) ),
			'snapshot' => \WorldGraph\Utils\Comfy_Catalog::get( $connection_id ),
		];
	}

	/**
	 * Enable one catalog entry.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id Catalog entry ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_enable_entry( int $connection_id, string $entry_id ) {
		$result = \WorldGraph\Utils\Comfy_Catalog::enable( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'message'  => sprintf( __( 'Enabled provider template %s.', 'worldgraph' ), $entry_id ),
			'snapshot' => \WorldGraph\Utils\Comfy_Catalog::get( $connection_id ),
		];
	}

	/**
	 * Disable one catalog entry.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id Catalog entry ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_disable_entry( int $connection_id, string $entry_id ) {
		\WorldGraph\Utils\Comfy_Catalog::disable( $connection_id, $entry_id );

		return [
			'message'  => sprintf( __( 'Disabled provider template %s.', 'worldgraph' ), $entry_id ),
			'snapshot' => \WorldGraph\Utils\Comfy_Catalog::get( $connection_id ),
		];
	}

	/**
	 * Materialize one catalog entry into a Template.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id Catalog entry ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_materialize_entry( int $connection_id, string $entry_id ) {
		$template_id = self::materialize_catalog_entry( $connection_id, $entry_id );
		if ( is_wp_error( $template_id ) ) {
			return $template_id;
		}

		return [
			'message'     => sprintf( __( 'Materialized provider template %1$s as World Graph Studio Template #%2$d.', 'worldgraph' ), $entry_id, $template_id ),
			'template_id' => (int) $template_id,
			'edit_url'    => get_edit_post_link( (int) $template_id, '' ),
			'snapshot'    => \WorldGraph\Utils\Comfy_Catalog::get( $connection_id ),
		];
	}

	/**
	 * Trigger provider-side requirement downloads for one catalog entry.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id Catalog entry ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_download_entry( int $connection_id, string $entry_id ) {
		$result = \WorldGraph\Utils\Comfy_Manifest::request_provider_template_downloads( $entry_id, $connection_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'message' => sprintf( __( 'Requested %d provider requirement download(s).', 'worldgraph' ), count( (array) ( $result['requested'] ?? [] ) ) ),
			'result'  => $result,
		];
	}

	/**
	 * Sync, then auto-enable and materialize every mappable catalog entry.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array|\WP_Error
	 */
	public static function catalog_prepare_mappable( int $connection_id ) {
		$sync = self::catalog_sync( $connection_id );
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}

		$entries  = (array) ( $sync['snapshot']['entries'] ?? [] );
		$prepared = [];
		$failed   = [];

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['modality'] ) ) {
				continue;
			}

			$entry_id = sanitize_text_field( (string) ( $entry['id'] ?? '' ) );
			if ( '' === $entry_id ) {
				continue;
			}

			if ( empty( $entry['enabled'] ) ) {
				$enabled = self::catalog_enable_entry( $connection_id, $entry_id );
				if ( is_wp_error( $enabled ) ) {
					$failed[] = [ 'entry_id' => $entry_id, 'message' => $enabled->get_error_message() ];
					continue;
				}
			}

			$materialized = self::catalog_materialize_entry( $connection_id, $entry_id );
			if ( is_wp_error( $materialized ) ) {
				$failed[] = [ 'entry_id' => $entry_id, 'message' => $materialized->get_error_message() ];
				continue;
			}

			$prepared[] = [
				'entry_id'    => $entry_id,
				'template_id' => (int) ( $materialized['template_id'] ?? 0 ),
			];
		}

		$snapshot = \WorldGraph\Utils\Comfy_Catalog::get( $connection_id );
		return [
			'message'  => sprintf( __( 'Prepared %1$d mappable template(s). %2$d failed.', 'worldgraph' ), count( $prepared ), count( $failed ) ),
			'prepared' => $prepared,
			'failed'   => $failed,
			'snapshot' => $snapshot,
		];
	}

	/**
	 * Permission and nonce gate for configurator actions.
	 *
	 * @return int Connection post ID.
	 */
	private static function authorize_configurator_request(): int {
		check_ajax_referer( 'worldgraph_conn_configurator', 'nonce' );
		$connection_id = absint( $_POST['connection_id'] ?? 0 );
		if ( ! $connection_id || ! current_user_can( 'edit_post', $connection_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to configure this Connection.', 'worldgraph' ) ], 403 );
		}

		$post = get_post( $connection_id );
		if ( ! $post instanceof \WP_Post || self::CPT !== $post->post_type ) {
			wp_send_json_error( [ 'message' => __( 'That Connection record no longer exists.', 'worldgraph' ) ], 404 );
		}
		\WorldGraph\Utils\Connection_Adapters::load( (string) get_post_meta( $connection_id, 'provider_type', true ) );

		return $connection_id;
	}

	/**
	 * Materialize one provider entry into a World Graph Studio Template post.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return int|\WP_Error Template post ID.
	 */
	private static function materialize_catalog_entry( int $connection_id, string $entry_id ) {
		$entry = \WorldGraph\Utils\Comfy_Catalog::find( $connection_id, $entry_id );
		if ( ! is_array( $entry ) ) {
			return new \WP_Error( 'worldgraph_catalog_entry_missing', __( 'Sync the catalog before materializing this entry.', 'worldgraph' ) );
		}
		if ( empty( $entry['modality'] ) ) {
			return new \WP_Error( 'worldgraph_catalog_entry_unmappable', __( 'This provider template cannot be mapped to a World Graph Studio modality.', 'worldgraph' ) );
		}

		$existing = get_posts( [
			'post_type'      => 'worldgraph_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[ 'key' => 'connection_id', 'value' => (string) $connection_id ],
				[ 'key' => 'provider_template_id', 'value' => $entry_id ],
			],
		] );

		$template_id = $existing ? (int) $existing[0] : 0;
		$template_id = wp_insert_post( [
			'ID'          => $template_id,
			'post_type'   => 'worldgraph_template',
			'post_title'  => (string) ( $entry['name'] ?? $entry_id ),
			'post_status' => 'publish',
		], true );
		if ( is_wp_error( $template_id ) || ! $template_id ) {
			return new \WP_Error( 'worldgraph_template_materialize_failed', __( 'Unable to materialize a Template post for that provider entry.', 'worldgraph' ) );
		}

		$raw = \WorldGraph\Utils\Comfy_Cloud_MCP::get_template( $entry_id, [], $connection_id );
		$raw = is_array( $raw ) ? $raw : [];
		$workflow = is_array( $raw['workflow'] ?? null ) ? $raw['workflow'] : [];

		update_post_meta( $template_id, 'template_name', (string) ( $entry['name'] ?? $entry_id ) );
		update_post_meta( $template_id, 'provider_type', 'comfyui' );
		update_post_meta( $template_id, 'status', 'active' );
		update_post_meta( $template_id, 'modality', (string) $entry['modality'] );
		update_post_meta( $template_id, 'generation_structure', \WorldGraph\Utils\Generation_Modality::output_type( (string) $entry['modality'] ) );
		update_post_meta( $template_id, 'connection_id', (string) $connection_id );
		update_post_meta( $template_id, 'provider_template_id', $entry_id );
		update_post_meta( $template_id, 'model_family', \WorldGraph\Utils\Model_Family::sanitize( (string) ( $entry['model_family'] ?? '' ) ) );

		if ( ! empty( $workflow ) ) {
			update_post_meta( $template_id, 'workflow_json', wp_slash( (string) wp_json_encode( $workflow ) ) );
		}
		if ( ! empty( $entry['parameters'] ) && is_array( $entry['parameters'] ) ) {
			update_post_meta( $template_id, 'configuration_json', wp_slash( (string) wp_json_encode( [ 'parameters' => $entry['parameters'] ] ) ) );
		}

		$requirements = self::requirements_from_entry( $entry );
		if ( ! empty( $requirements ) ) {
			update_post_meta( $template_id, 'model_requirements', wp_slash( (string) wp_json_encode( $requirements ) ) );
		}

		$checkpoint = '';
		foreach ( (array) ( $entry['models'] ?? [] ) as $model ) {
			if ( is_array( $model ) && 'checkpoints' === (string) ( $model['folder'] ?? '' ) && ! empty( $model['filename'] ) ) {
				$checkpoint = (string) $model['filename'];
				break;
			}
		}
		if ( '' !== $checkpoint ) {
			update_post_meta( $template_id, 'checkpoint', $checkpoint );
		}

		\WorldGraph\Utils\Comfy_Catalog::enable( $connection_id, $entry_id );
		\WorldGraph\Utils\Comfy_Catalog::link_template( $connection_id, $entry_id, (int) $template_id );

		return (int) $template_id;
	}

	/**
	 * Convert provider entry model metadata into model_requirements JSON.
	 *
	 * @param array $entry Catalog entry.
	 * @return array<int, array<string, string>>
	 */
	private static function requirements_from_entry( array $entry ): array {
		$requirements = [];
		$urls = array_values( array_filter( array_map( 'strval', (array) ( $entry['model_urls'] ?? [] ) ) ) );
		$models = array_values( array_filter( (array) ( $entry['models'] ?? [] ), static function ( $model ): bool {
			return is_array( $model ) && ! empty( $model['filename'] ) && ! empty( $model['folder'] );
		} ) );

		foreach ( $models as $index => $model ) {
			$filename = (string) $model['filename'];
			$folder   = (string) $model['folder'];
			$url      = '';

			foreach ( $urls as $candidate ) {
				if ( false !== stripos( $candidate, $filename ) ) {
					$url = $candidate;
					break;
				}
			}
			if ( '' === $url && isset( $urls[ $index ] ) ) {
				$url = $urls[ $index ];
			}

			if ( '' === $url ) {
				continue;
			}

			$requirements[] = [ 'filename' => $filename, 'folder' => $folder, 'url' => $url ];
		}

		return $requirements;
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

		if ( ! isset( $_POST['worldgraph_conn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['worldgraph_conn_nonce'] ) ), 'worldgraph_conn_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( self::CPT );
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
				case 'mcp_endpoint_url':
					$value = esc_url_raw( trim( (string) $raw ) );
					break;

				case 'credential_reference':
				case 'mcp_credential_reference':
					$value = sanitize_text_field( $raw );
					break;

				case 'model':
					$value = sanitize_text_field( $raw );
					break;

				case 'max_tokens':
					$value = '' === trim( (string) $raw ) ? '' : (string) absint( $raw );
					break;

				case 'temperature':
					$value = '' === trim( (string) $raw ) ? '' : (string) floatval( $raw );
					break;

				case 'model_access':
				case 'enabled_structures':
				case 'enabled_templates':
				case 'capabilities':
				case 'mcp_configuration':
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

		// Loading after provider_type is persisted lets a newly selected adapter
		// register any higher-priority save hooks during this request.
		$provider_type = (string) get_post_meta( $post_id, 'provider_type', true );
		\WorldGraph\Utils\Connection_Adapters::load( $provider_type );
		if ( 'disabled' !== get_post_meta( $post_id, 'status', true ) ) {
			if ( 'fal' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\Fal_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Fal_Catalog::HOOK, [ $post_id ] );
			} elseif ( 'elevenlabs' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] );
			} elseif ( 'suno' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\Suno_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\Suno_Catalog::HOOK, [ $post_id ] );
			} elseif ( 'videodraft' === $provider_type && ! wp_next_scheduled( \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \WorldGraph\Utils\VideoDraft_Catalog::HOOK, [ $post_id ] );
			}
		}
	}

	/**
	 * Create or update the single Connection post managed for a given setup-wizard slot.
	 *
	 * Used by the setup wizard so that saving "Generation Connection" or "LLM
	 * Connection" populates a real Connection record instead of only options.
	 *
	 * @param string $slot  Wizard slot marker, e.g. 'generation' or 'llm'.
	 * @param string $title Post title / connection name.
	 * @param array  $meta  Meta fields to set (subset of the registered fields).
	 * @return int Connection post ID.
	 */
	public static function upsert_managed( string $slot, string $title, array $meta ): int {
		$existing = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'worldgraph_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slot, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		] );

		$post_id = $existing ? (int) $existing[0] : 0;
		$post_id = wp_insert_post( [
			'ID'          => $post_id ?: 0,
			'post_type'   => self::CPT,
			'post_title'  => $title,
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, 'worldgraph_wizard_slot', $slot );
		update_post_meta( $post_id, 'connection_name', $title );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return (int) $post_id;
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

		$decoded = json_decode( $trimmed );
		if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_array( $decoded ) && ! is_object( $decoded ) ) ) {
			return null;
		}

		return wp_json_encode( $decoded );
	}
}
