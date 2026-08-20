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
		$types = \StoryOS\Utils\Connection_Adapters::provider_types();
		return apply_filters( 'storyos_connection_provider_types', $types );
	}

	/**
	 * Register the Provider Connection CPT and admin UI.
	 */
	public static function init(): void {
		self::register_cpt();
		self::register_meta_boxes();
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'wp_ajax_storyos_sync_connection_catalog', [ __CLASS__, 'ajax_sync_catalog' ] );
		add_action( 'wp_ajax_storyos_enable_connection_catalog_entry', [ __CLASS__, 'ajax_enable_catalog_entry' ] );
		add_action( 'wp_ajax_storyos_disable_connection_catalog_entry', [ __CLASS__, 'ajax_disable_catalog_entry' ] );
		add_action( 'wp_ajax_storyos_materialize_connection_catalog_entry', [ __CLASS__, 'ajax_materialize_catalog_entry' ] );
		add_action( 'wp_ajax_storyos_download_connection_catalog_entry', [ __CLASS__, 'ajax_download_catalog_entry' ] );
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
			'endpoint_url'         => [
				'type'        => 'text',
				'label'       => 'Endpoint URL',
				'required'    => true,
				'description' => 'Provider endpoint. For fal use https://mcp.fal.ai/mcp; for ElevenLabs use https://api.elevenlabs.io/v1; for local ComfyUI use its HTTP API base URL.',
			],
			'mcp_endpoint_url'     => [
				'type'        => 'text',
				'label'       => 'MCP Endpoint URL',
				'required'    => false,
				'description' => 'Streamable HTTP MCP endpoint. Required for fal (https://mcp.fal.ai/mcp); optional for local ComfyUI discovery and downloads.',
			],
			'credential_reference' => [
				'type'        => 'text',
				'label'       => 'API Key / OAuth (Reference)',
				'required'    => false,
				'description' => 'Credential or environment reference, e.g. env://FAL_KEY, env://ELEVENLABS_API_KEY, or env://COMFYUI_API_KEY.',
			],
			'model'                => [
				'type'        => 'text',
				'label'       => 'Model',
				'required'    => false,
				'description' => 'Optional default model. For fal use an endpoint ID; for ElevenLabs use a speech model ID such as eleven_multilingual_v2.',
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
				'label'       => 'Model Access',
				'required'    => false,
				'description' => 'Optional JSON allowlist. fal uses model endpoint IDs; ElevenLabs uses voice IDs. Empty lets the adapter select a default.',
			],
			'enabled_structures'   => [
				'type'        => 'textarea',
				'label'       => 'Enabled Structures',
				'required'    => false,
				'description' => 'JSON array of generation structures enabled for this connection, e.g. ["character-sheet","scene-image"].',
			],
			'enabled_templates'    => [
				'type'        => 'textarea',
				'label'       => 'Enabled Templates',
				'required'    => false,
				'description' => 'JSON array of provider templates enabled on this connection. Managed by the Template Catalog panel; edit only to recover from a bad sync.',
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
				'menu_icon'    => 'dashicons-admin-network',
				// The native post list is intentionally not registered in the admin menu;
				// StoryOS\Admin\Connections::render_page() is the single Connections view.
				'show_in_menu' => false,
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
			add_meta_box(
				'storyos_connection_configurator',
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
		<script>
			(function () {
				var provider = document.getElementById('provider_type');
				var endpoint = document.getElementById('endpoint_url');
				var mcpEndpoint = document.getElementById('mcp_endpoint_url');
				var endpoints = <?php echo wp_json_encode( array_map( [ '\StoryOS\Utils\Connection_Adapters', 'endpoint' ], array_combine( self::provider_types(), self::provider_types() ) ) ); ?>;
				if (!provider || !endpoint || !mcpEndpoint) { return; }
				provider.addEventListener('change', function () {
					if (!endpoint.value.trim() && endpoints[provider.value]) { endpoint.value = endpoints[provider.value]; }
					if ('fal' === provider.value && !mcpEndpoint.value.trim()) { mcpEndpoint.value = endpoints.fal; }
				});
			}());
		</script>
		<?php
	}

	/**
	 * Render provider catalog controls that sync MCP capabilities and materialize
	 * discoverable provider templates into StoryOS Template posts.
	 *
	 * @param \WP_Post $post Connection post.
	 */
	public static function render_configurator_meta_box( \WP_Post $post ): void {
		$provider_type = sanitize_key( (string) get_post_meta( $post->ID, 'provider_type', true ) );
		if ( '' === $provider_type ) {
			echo '<p>' . esc_html__( 'Save this Connection with a provider type to configure discoverable templates.', 'storyos' ) . '</p>';
			return;
		}

		if ( 'fal' === $provider_type ) {
			\StoryOS\Utils\Connection_Adapters::load( 'fal' );
			$synced_at = (string) get_post_meta( $post->ID, 'fal_catalog_synced_at', true );
			$error     = (string) get_post_meta( $post->ID, 'fal_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'StoryOS asks fal MCP to select or inspect models and automatically maintains the paired Templates. Saving or testing this Connection schedules a schema sync; users do not need to copy provider schemas by hand.', 'storyos' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Model Access, when set, is the authoritative endpoint allowlist and provisions one Template per endpoint.', 'storyos' ); ?></li>
				<li><?php echo esc_html__( 'Model is the preferred default endpoint. With neither field set, fal MCP supplies a current text-to-image model.', 'storyos' ); ?></li>
				<li><?php echo esc_html__( 'MCP model schemas and their defaults are stored on the generated Templates; StoryOS supplies prompts and bound media at runtime.', 'storyos' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'storyos' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'elevenlabs' === $provider_type ) {
			\StoryOS\Utils\Connection_Adapters::load( 'elevenlabs' );
			$synced_at = (string) get_post_meta( $post->ID, 'elevenlabs_catalog_synced_at', true );
			$error = (string) get_post_meta( $post->ID, 'elevenlabs_catalog_error', true );
			?>
			<p><?php echo esc_html__( 'StoryOS discovers ElevenLabs voices and models and maintains endpoint-specific Templates for speech, dialogue, sound effects, music, and voice design. Saving or testing this Connection triggers a catalog sync.', 'storyos' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Model selects the ElevenLabs speech model. When empty, StoryOS prefers eleven_multilingual_v2.', 'storyos' ); ?></li>
				<li><?php echo esc_html__( 'Model Access may contain a JSON array of voice IDs. When empty, StoryOS provisions one available voice to minimize setup.', 'storyos' ); ?></li>
				<li><?php echo esc_html__( 'Each generated audio response is imported into WordPress before its generation job completes.', 'storyos' ); ?></li>
			</ul>
			<p><strong><?php echo esc_html__( 'Last template sync:', 'storyos' ); ?></strong> <?php echo esc_html( $synced_at ?: '—' ); ?></p>
			<?php if ( '' !== $error ) : ?><p class="notice notice-error inline"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<?php
			return;
		}

		if ( 'comfyui' !== $provider_type ) {
			echo '<p>' . esc_html__( 'This provider type does not expose a configurator yet. ComfyUI is the reference implementation for catalog sync, template materialization, and MCP-triggered model downloads.', 'storyos' ) . '</p>';
			return;
		}
		\StoryOS\Utils\Connection_Adapters::load( 'comfyui' );

		$snapshot = \StoryOS\Utils\Comfy_Catalog::get( (int) $post->ID );
		?>
		<p class="description"><?php echo esc_html__( 'Sync this Connection against its MCP-advertised capabilities, then enable and materialize provider templates into StoryOS Templates. Download actions trigger provider-side model fetches where available.', 'storyos' ); ?></p>
		<p>
			<button type="button" class="button" id="storyos-connection-sync-catalog"><?php echo esc_html__( 'Sync Catalog', 'storyos' ); ?></button>
			<span class="description" style="margin-left:8px;"><?php
			printf(
				/* translators: %s: catalog timestamp. */
				esc_html__( 'Last synced: %s', 'storyos' ),
				esc_html( (string) ( $snapshot['synced_at'] ?: '—' ) )
			);
			?></span>
		</p>
		<div id="storyos-connection-configurator-status" aria-live="polite" class="description"></div>
		<div id="storyos-connection-configurator-results"></div>
		<script>
			(function () {
				var nonce = '<?php echo esc_js( wp_create_nonce( 'storyos_connection_configurator' ) ); ?>';
				var connectionId = '<?php echo esc_js( (string) $post->ID ); ?>';
				var status = document.getElementById('storyos-connection-configurator-status');
				var results = document.getElementById('storyos-connection-configurator-results');

				function post(action, extra) {
					var payload = { action: action, nonce: nonce, connection_id: connectionId };
					Object.assign(payload, extra || {});
					return fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams(payload)
					}).then(function (response) { return response.json(); });
				}

				function render(entries) {
					results.replaceChildren();
					if (!entries || !entries.length) {
						results.textContent = '<?php echo esc_js( __( 'No provider templates discovered yet. Sync the catalog first.', 'storyos' ) ); ?>';
						return;
					}

					entries.forEach(function (entry) {
						var row = document.createElement('p');
						var title = document.createElement('strong');
						title.textContent = (entry.name || entry.id) + ' '; 
						row.appendChild(title);
						row.appendChild(document.createTextNode('(' + (entry.modality || 'unmappable') + ', ' + (entry.status || 'unknown') + ') '));

						var enable = document.createElement('button');
						enable.type = 'button';
						enable.className = 'button button-small';
						enable.textContent = entry.enabled ? '<?php echo esc_js( __( 'Disable', 'storyos' ) ); ?>' : '<?php echo esc_js( __( 'Enable', 'storyos' ) ); ?>';
						enable.addEventListener('click', function () {
							var action = entry.enabled ? 'storyos_disable_connection_catalog_entry' : 'storyos_enable_connection_catalog_entry';
							status.textContent = '<?php echo esc_js( __( 'Updating catalog entry…', 'storyos' ) ); ?>';
							post(action, { entry_id: entry.id }).then(function (response) {
								status.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'Catalog entry updated.', 'storyos' ) ); ?>';
								if (response.success && response.data && response.data.snapshot) {
									render(response.data.snapshot.entries || []);
								}
							});
						});
						row.appendChild(enable);

						var materialize = document.createElement('button');
						materialize.type = 'button';
						materialize.className = 'button button-small';
						materialize.style.marginLeft = '6px';
						materialize.textContent = '<?php echo esc_js( __( 'Materialize Template', 'storyos' ) ); ?>';
						materialize.addEventListener('click', function () {
							status.textContent = '<?php echo esc_js( __( 'Materializing Template…', 'storyos' ) ); ?>';
							post('storyos_materialize_connection_catalog_entry', { entry_id: entry.id }).then(function (response) {
								status.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'Template materialized.', 'storyos' ) ); ?>';
								if (response.success && response.data && response.data.snapshot) {
									render(response.data.snapshot.entries || []);
								}
							});
						});
						row.appendChild(materialize);

						var download = document.createElement('button');
						download.type = 'button';
						download.className = 'button button-small';
						download.style.marginLeft = '6px';
						download.textContent = '<?php echo esc_js( __( 'Download Requirements', 'storyos' ) ); ?>';
						download.addEventListener('click', function () {
							status.textContent = '<?php echo esc_js( __( 'Requesting provider downloads…', 'storyos' ) ); ?>';
							post('storyos_download_connection_catalog_entry', { entry_id: entry.id }).then(function (response) {
								status.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'Download request sent.', 'storyos' ) ); ?>';
							});
						});
						row.appendChild(download);

						results.appendChild(row);
					});
				}

				document.getElementById('storyos-connection-sync-catalog').addEventListener('click', function () {
					status.textContent = '<?php echo esc_js( __( 'Syncing provider catalog…', 'storyos' ) ); ?>';
					post('storyos_sync_connection_catalog').then(function (response) {
						if (!response.success) {
							status.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'Catalog sync failed.', 'storyos' ) ); ?>';
							return;
						}
						status.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'Catalog synced.', 'storyos' ) ); ?>';
						render((response.data.snapshot && response.data.snapshot.entries) || []);
					});
				});

				render(<?php echo wp_json_encode( (array) ( $snapshot['entries'] ?? [] ) ); ?>);
			}());
		</script>
		<?php
	}

	/** Sync the selected Connection's provider catalog. */
	public static function ajax_sync_catalog(): void {
		$connection_id = self::authorize_configurator_request();
		$result = \StoryOS\Utils\Comfy_Catalog::sync( $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'  => sprintf( __( 'Catalog synced: %d provider templates discovered.', 'storyos' ), count( (array) ( $result['entries'] ?? [] ) ) ),
			'snapshot' => \StoryOS\Utils\Comfy_Catalog::get( $connection_id ),
		] );
	}

	/** Enable one provider catalog entry. */
	public static function ajax_enable_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'storyos' ) ] );
		}

		$result = \StoryOS\Utils\Comfy_Catalog::enable( $connection_id, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'  => sprintf( __( 'Enabled provider template %s.', 'storyos' ), $entry_id ),
			'snapshot' => \StoryOS\Utils\Comfy_Catalog::get( $connection_id ),
		] );
	}

	/** Disable one provider catalog entry. */
	public static function ajax_disable_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'storyos' ) ] );
		}

		\StoryOS\Utils\Comfy_Catalog::disable( $connection_id, $entry_id );
		wp_send_json_success( [
			'message'  => sprintf( __( 'Disabled provider template %s.', 'storyos' ), $entry_id ),
			'snapshot' => \StoryOS\Utils\Comfy_Catalog::get( $connection_id ),
		] );
	}

	/** Materialize one provider catalog entry into a StoryOS Template post. */
	public static function ajax_materialize_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'storyos' ) ] );
		}

		$template_id = self::materialize_catalog_entry( $connection_id, $entry_id );
		if ( is_wp_error( $template_id ) ) {
			wp_send_json_error( [ 'message' => $template_id->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'     => sprintf( __( 'Materialized provider template %1$s as StoryOS Template #%2$d.', 'storyos' ), $entry_id, $template_id ),
			'template_id' => (int) $template_id,
			'edit_url'    => get_edit_post_link( (int) $template_id, '' ),
			'snapshot'    => \StoryOS\Utils\Comfy_Catalog::get( $connection_id ),
		] );
	}

	/** Request provider-side downloads for one catalog entry. */
	public static function ajax_download_catalog_entry(): void {
		$connection_id = self::authorize_configurator_request();
		$entry_id = sanitize_text_field( (string) ( $_POST['entry_id'] ?? '' ) );
		if ( '' === $entry_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a catalog entry first.', 'storyos' ) ] );
		}

		$result = \StoryOS\Utils\Comfy_Manifest::request_provider_template_downloads( $entry_id, $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message' => sprintf( __( 'Requested %d provider requirement download(s).', 'storyos' ), count( (array) ( $result['requested'] ?? [] ) ) ),
			'result'  => $result,
		] );
	}

	/**
	 * Permission and nonce gate for configurator actions.
	 *
	 * @return int Connection post ID.
	 */
	private static function authorize_configurator_request(): int {
		check_ajax_referer( 'storyos_connection_configurator', 'nonce' );
		$connection_id = absint( $_POST['connection_id'] ?? 0 );
		if ( ! $connection_id || ! current_user_can( 'edit_post', $connection_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to configure this Connection.', 'storyos' ) ], 403 );
		}

		$post = get_post( $connection_id );
		if ( ! $post instanceof \WP_Post || self::CPT !== $post->post_type ) {
			wp_send_json_error( [ 'message' => __( 'That Connection record no longer exists.', 'storyos' ) ], 404 );
		}
		\StoryOS\Utils\Connection_Adapters::load( (string) get_post_meta( $connection_id, 'provider_type', true ) );

		return $connection_id;
	}

	/**
	 * Materialize one provider entry into a StoryOS Template post.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return int|\WP_Error Template post ID.
	 */
	private static function materialize_catalog_entry( int $connection_id, string $entry_id ) {
		$entry = \StoryOS\Utils\Comfy_Catalog::find( $connection_id, $entry_id );
		if ( ! is_array( $entry ) ) {
			return new \WP_Error( 'storyos_catalog_entry_missing', __( 'Sync the catalog before materializing this entry.', 'storyos' ) );
		}
		if ( empty( $entry['modality'] ) ) {
			return new \WP_Error( 'storyos_catalog_entry_unmappable', __( 'This provider template cannot be mapped to a StoryOS modality.', 'storyos' ) );
		}

		$existing = get_posts( [
			'post_type'      => 'storyos_template',
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
			'post_type'   => 'storyos_template',
			'post_title'  => (string) ( $entry['name'] ?? $entry_id ),
			'post_status' => 'publish',
		], true );
		if ( is_wp_error( $template_id ) || ! $template_id ) {
			return new \WP_Error( 'storyos_template_materialize_failed', __( 'Unable to materialize a Template post for that provider entry.', 'storyos' ) );
		}

		$raw = \StoryOS\Utils\Comfy_Cloud_MCP::get_template( $entry_id, [], $connection_id );
		$raw = is_array( $raw ) ? $raw : [];
		$workflow = is_array( $raw['workflow'] ?? null ) ? $raw['workflow'] : [];

		update_post_meta( $template_id, 'template_name', (string) ( $entry['name'] ?? $entry_id ) );
		update_post_meta( $template_id, 'provider_type', 'comfyui' );
		update_post_meta( $template_id, 'status', 'active' );
		update_post_meta( $template_id, 'modality', (string) $entry['modality'] );
		update_post_meta( $template_id, 'generation_structure', \StoryOS\Utils\Generation_Modality::output_type( (string) $entry['modality'] ) );
		update_post_meta( $template_id, 'connection_id', (string) $connection_id );
		update_post_meta( $template_id, 'provider_template_id', $entry_id );
		update_post_meta( $template_id, 'model_family', \StoryOS\Utils\Model_Family::sanitize( (string) ( $entry['model_family'] ?? '' ) ) );

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

		\StoryOS\Utils\Comfy_Catalog::enable( $connection_id, $entry_id );
		\StoryOS\Utils\Comfy_Catalog::link_template( $connection_id, $entry_id, (int) $template_id );

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
		\StoryOS\Utils\Connection_Adapters::load( $provider_type );
		if ( 'disabled' !== get_post_meta( $post_id, 'status', true ) ) {
			if ( 'fal' === $provider_type && ! wp_next_scheduled( \StoryOS\Utils\Fal_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \StoryOS\Utils\Fal_Catalog::HOOK, [ $post_id ] );
			} elseif ( 'elevenlabs' === $provider_type && ! wp_next_scheduled( \StoryOS\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] ) ) {
				wp_schedule_single_event( time() + 5, \StoryOS\Utils\ElevenLabs_Catalog::HOOK, [ $post_id ] );
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
			'meta_key'       => 'storyos_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
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

		update_post_meta( $post_id, 'storyos_wizard_slot', $slot );
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

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}

		return wp_json_encode( $decoded );
	}
}
