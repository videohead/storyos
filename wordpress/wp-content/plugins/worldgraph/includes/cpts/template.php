<?php
/**
 * Generation Template Custom Post Type.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CPT;

/**
 * Generation Template Custom Post Type handler.
 */
class Template {
	/**
	 * Register the Generation Template CPT and admin UI.
	 */
	public static function init(): void {
		self::register_cpt();
		self::register_meta_boxes();
		add_filter( 'acf/update_value', [ __CLASS__, 'sanitize_scf_value' ], 30, 4 );
		add_filter( 'acf/validate_value', [ __CLASS__, 'validate_scf_value' ], 20, 4 );
		add_filter( 'acf/load_field/key=field_worldgraph_template_modality', [ __CLASS__, 'load_modality_choices' ] );
		add_filter( 'acf/load_field/key=field_worldgraph_template_model_family', [ __CLASS__, 'load_model_family_choices' ] );
		add_action( 'wp_ajax_worldgraph_check_template_requirements', [ __CLASS__, 'ajax_check_requirements' ] );
		add_action( 'wp_ajax_worldgraph_install_template_models', [ __CLASS__, 'ajax_install_models' ] );
		add_action( 'wp_ajax_worldgraph_discover_comfy_templates', [ __CLASS__, 'ajax_discover_comfy_templates' ] );
		add_action( 'wp_ajax_worldgraph_download_comfy_template_requirements', [ __CLASS__, 'ajax_download_comfy_template_requirements' ] );
		add_action( 'wp_ajax_worldgraph_import_provider_template_definition', [ __CLASS__, 'ajax_import_provider_template_definition' ] );
	}

	/**
	 * Refresh registry-backed choices without requiring a JSON rewrite when an
	 * adapter extends the available modality or model-family registry.
	 *
	 * @param array<string, mixed> $field SCF field.
	 * @return array<string, mixed>
	 */
	public static function load_modality_choices( array $field ): array {
		$field['choices'] = \WorldGraph\Utils\Generation_Modality::labels();
		return $field;
	}

	/**
	 * Refresh model-family choices from the runtime registry.
	 *
	 * @param array<string, mixed> $field SCF field.
	 * @return array<string, mixed>
	 */
	public static function load_model_family_choices( array $field ): array {
		$field['choices'] = \WorldGraph\Utils\Model_Family::labels();
		return $field;
	}

	/**
	 * Apply Template-specific normalization after SCF field-type handling.
	 *
	 * @param mixed                $value    Submitted value.
	 * @param int|string           $post_id  SCF object ID.
	 * @param array<string, mixed> $field    SCF field.
	 * @param mixed                $original Original submitted value.
	 * @return mixed
	 */
	public static function sanitize_scf_value( $value, $post_id, array $field, $original ) {
		if ( ! is_numeric( $post_id ) || 'worldgraph_template' !== get_post_type( (int) $post_id ) || 0 !== strpos( (string) ( $field['key'] ?? '' ), 'field_worldgraph_template_' ) ) {
			return $value;
		}

		$name = (string) ( $field['name'] ?? '' );
		if ( 'modality' === $name ) {
			return \WorldGraph\Utils\Generation_Modality::sanitize( (string) $value );
		}
		if ( 'model_family' === $name ) {
			return \WorldGraph\Utils\Model_Family::sanitize( (string) $value );
		}
		if ( 'connection_id' === $name ) {
			return '' === trim( (string) $value ) ? '' : (string) absint( $value );
		}
		if ( 'status' === $name ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, [ 'draft', 'active', 'archived' ], true ) ? $value : 'draft';
		}
		if ( in_array( $name, [ 'workflow_json', 'configuration_json', 'input_bindings', 'model_requirements', 'default_values' ], true ) ) {
			$normalized = self::normalize_json( (string) $value );
			return null === $normalized
				? get_post_meta( (int) $post_id, $name, true )
				: $normalized;
		}

		return $value;
	}

	/**
	 * Validate Template-specific SCF values before save.
	 *
	 * @param bool|string          $valid Whether the value is valid so far.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field SCF field.
	 * @param string               $input Input name.
	 * @return bool|string
	 */
	public static function validate_scf_value( $valid, $value, array $field, string $input ) {
		if ( true !== $valid || 0 !== strpos( (string) ( $field['key'] ?? '' ), 'field_worldgraph_template_' ) ) {
			return $valid;
		}

		$name = (string) ( $field['name'] ?? '' );
		if ( 'connection_id' === $name && '' !== trim( (string) $value ) && ( ! ctype_digit( (string) $value ) || 'worldgraph_conn' !== get_post_type( (int) $value ) ) ) {
			return __( 'Select an existing World Graph Studio Connection ID.', 'worldgraph' );
		}
		if ( in_array( $name, [ 'workflow_json', 'configuration_json', 'input_bindings', 'model_requirements', 'default_values' ], true ) && null === self::normalize_json( (string) $value ) ) {
			return __( 'Enter valid JSON.', 'worldgraph' );
		}

		return $valid;
	}

	/**
	 * Normalize JSON text while preserving arrays, objects, and escaping.
	 *
	 * @return string|null Normalized JSON, blank, or null when invalid.
	 */
	private static function normalize_json( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$decoded = json_decode( $value );
		if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_array( $decoded ) && ! is_object( $decoded ) ) ) {
			return null;
		}

		return (string) wp_json_encode( $decoded );
	}

	/**
	 * Register the Generation Template CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'template_name'       => [
				'type'        => 'text',
				'label'       => 'Template Name',
				'required'    => true,
			],
			'description'         => [
				'type'        => 'wysiwyg',
				'label'       => 'Description',
				'required'    => false,
			],
			'generation_structure' => [
				'type'        => 'text',
				'label'       => 'Generation Structure',
				'required'    => true,
			],
			'modality'            => [
				'type'        => 'select',
				'label'       => 'Modality',
				'required'    => true,
				'options'     => \WorldGraph\Utils\Generation_Modality::labels(),
				'description' => 'What this Template generates and which inputs it consumes. Determines the built-in workflow and the ComfyUI nodes and models World Graph Studio checks for.',
			],
			'connection_id'       => [
				'type'        => 'text',
				'label'       => 'Connection ID',
				'required'    => false,
				'description' => 'The worldgraph_conn post ID this template runs against (a Connection can back many Templates/checkpoints).',
			],
			'checkpoint'          => [
				'type'        => 'text',
				'label'       => 'Checkpoint / Model',
				'required'    => false,
				'description' => 'Checkpoint filename installed on the Connection, e.g. LTX-2.3/ltx-2.3-22b-dev-fp8.safetensors.',
			],
			'model_family'        => [
				'type'        => 'select',
				'label'       => 'Model Family',
				'required'    => false,
				'options'     => \WorldGraph\Utils\Model_Family::labels(),
				'description' => 'The generative model this Template runs, e.g. LTX 2.3, MiniMax, SCAIL, or Wan 2.1. Used to group Templates and cross-check against the Connection\'s allowed models.',
			],
			'workflow_json'       => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'ComfyUI API Workflow (optional)',
				'required'    => false,
				'description' => 'Leave blank to use the built-in text-to-image workflow with the checkpoint above. To use a custom graph, export it with ComfyUI\'s “Save (API Format)”, then replace prompt values with placeholders such as {{prompt}} and {{negative_prompt}}.',
			],
			'provider_template_id' => [
				'type'        => 'text',
				'label'       => 'Provider Template / Model Endpoint ID',
				'required'    => false,
				'description' => 'Provider identifier paired with the Connection. For fal use a model endpoint ID, for ElevenLabs use a voice ID, and for ComfyUI use the discovered MCP Template ID.',
			],
			'configuration_json'  => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Configuration JSON',
				'required'    => true,
				'description' => 'Provider-neutral JSON for optional parameter overrides, references, and SCF field mappings. Provider inputs live under {"input": {...}}; World Graph Studio adds the prompt and resolved bindings at runtime.',
			],
			'input_bindings'      => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Input Bindings JSON',
				'required'    => false,
				'description' => 'Optional JSON mapping prompt-related fields to Story Graph sources for the text-to-image workflow.',
			],
			'model_requirements'  => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Model Requirements JSON',
				'required'    => false,
				'description' => 'Optional JSON array of download sources for the models this Template loads: [{"filename":"ltx-2.3.safetensors","folder":"checkpoints","url":"https://…"}]. Used by the requirement check to offer a one-click install.',
			],
			'default_values'     => [
				'type'        => 'textarea',
				'format'      => 'json',
				'label'       => 'Default Values',
				'required'    => false,
			],
			'provider_type'      => [
				'type'        => 'text',
				'label'       => 'Provider Type',
				'required'    => false,
			],
			'version'            => [
				'type'        => 'text',
				'label'       => 'Version',
				'required'    => false,
			],
			'status'             => [
				'type'        => 'select',
				'label'       => 'Status',
				'required'    => true,
				'options'     => [
					'draft'     => 'Draft',
					'active'    => 'Active',
					'archived'  => 'Archived',
				],
			],
		];

		\WorldGraph\Utils\register_cpt(
			'worldgraph_template',
			'Templates',
			[
				'menu_icon' => 'dashicons-media-document',
				'show_in_menu' => 'worldgraph-administration',
			],
			$fields
		);
	}

	/**
	 * Register admin UI for template configuration.
	 */
	private static function register_meta_boxes(): void {
		add_action( 'add_meta_boxes', function (): void {
			add_meta_box(
				'worldgraph_template_requirements',
				'ComfyUI Requirements',
				[ self::class, 'render_requirements_meta_box' ],
				'worldgraph_template',
				'side',
				'default'
			);
		} );
	}

	/**
	 * Render the template details meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_template_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'worldgraph_template_details', 'worldgraph_template_nonce' );
		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_template' );
		?>
		<p><em><?php echo esc_html__( 'Use SCF-backed field names in the configuration JSON when a template should preload from Story Graph content.', 'worldgraph' ); ?></em></p>
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
								<textarea name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" class="large-text" rows="5"><?php echo esc_textarea( $value ); ?></textarea>
								<?php
								break;
							case 'wysiwyg':
								wp_editor(
									$value,
									$field_name,
									[
										'tinymce'      => true,
										'quicktags'    => true,
										'editor_height' => 140,
									]
								);
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
		<?php
	}

	/**
	 * Render the ComfyUI requirements panel: what this Template will ask
	 * ComfyUI for, and whether the connected instance can supply it.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_requirements_meta_box( \WP_Post $post ): void {
		$connection_id = absint( get_post_meta( $post->ID, 'connection_id', true ) );
		$connection = \WorldGraph\Utils\Connection_Repository::get( $connection_id );
		if ( ! $connection || 'comfyui' !== $connection['provider_type'] ) {
			echo '<p>' . esc_html__( 'This Template is paired with a non-ComfyUI provider. Use that provider connection\'s adapter to discover and download its requirements.', 'worldgraph' ) . '</p>';
			return;
		}
		\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );
		$manifest = \WorldGraph\Utils\Comfy_Manifest::for_template( $post->ID );
		if ( is_wp_error( $manifest ) ) {
			echo '<p>' . esc_html__( 'Save this Template to see its ComfyUI requirements.', 'worldgraph' ) . '</p>';
			return;
		}
		?>
		<p>
			<strong><?php echo esc_html( $manifest['modality_label'] ); ?></strong><br />
			<span class="description">
				<?php
				printf(
					/* translators: 1: output media kind, 2: workflow source. */
					esc_html__( 'Outputs %1$s using the %2$s workflow.', 'worldgraph' ),
					esc_html( $manifest['output_type'] ),
					esc_html( 'custom' === $manifest['workflow_source'] ? __( 'pasted custom', 'worldgraph' ) : __( 'built-in', 'worldgraph' ) )
				);
				?>
			</span>
		</p>
		<p><strong><?php echo esc_html__( 'Inputs', 'worldgraph' ); ?></strong><br />
		<?php foreach ( $manifest['inputs'] as $slot => $input ) : ?>
			<code>{{<?php echo esc_html( $slot ); ?>}}</code>
			<?php echo esc_html( empty( $input['required'] ) ? __( '(optional)', 'worldgraph' ) : __( '(required)', 'worldgraph' ) ); ?><br />
		<?php endforeach; ?>
		</p>
		<p><strong><?php echo esc_html__( 'Models', 'worldgraph' ); ?></strong><br />
		<?php if ( empty( $manifest['models'] ) ) : ?>
			<span class="description"><?php echo esc_html__( 'None detected. Set a checkpoint above.', 'worldgraph' ); ?></span>
		<?php else : ?>
			<?php foreach ( $manifest['models'] as $model ) : ?>
				<code><?php echo esc_html( $model['filename'] ); ?></code> &rarr; <code>models/<?php echo esc_html( $model['folder'] ); ?></code><br />
			<?php endforeach; ?>
		<?php endif; ?>
		</p>
		<p>
			<button type="button" class="button" id="worldgraph-check-requirements"><?php echo esc_html__( 'Check ComfyUI', 'worldgraph' ); ?></button>
			<button type="button" class="button" id="worldgraph-install-models"><?php echo esc_html__( 'Install missing models', 'worldgraph' ); ?></button>
		</p>
		<p><strong><?php echo esc_html__( 'ComfyUI MCP Template', 'worldgraph' ); ?></strong></p>
		<p><input type="search" class="regular-text" id="worldgraph-comfy-template-search" placeholder="<?php echo esc_attr__( 'Search provider templates', 'worldgraph' ); ?>" />
		<button type="button" class="button" id="worldgraph-discover-comfy-templates"><?php echo esc_html__( 'Discover', 'worldgraph' ); ?></button></p>
		<div id="worldgraph-comfy-template-results"></div>
		<p><button type="button" class="button" id="worldgraph-import-provider-template"><?php echo esc_html__( 'Import definition into this Template', 'worldgraph' ); ?></button></p>
		<p><button type="button" class="button" id="worldgraph-download-comfy-requirements"><?php echo esc_html__( 'Download selected requirements', 'worldgraph' ); ?></button></p>
		<div id="worldgraph-requirements-result" aria-live="polite"></div>
		<script>
			(function () {
				var result = document.getElementById('worldgraph-requirements-result');
				var nonce = '<?php echo esc_js( wp_create_nonce( 'worldgraph_template_requirements' ) ); ?>';
				var postId = '<?php echo esc_js( (string) $post->ID ); ?>';
				var providerTemplateId = document.getElementById('provider_template_id') || document.getElementById('comfy_template_id');

				function call(action, button) {
					button.disabled = true;
					result.textContent = '<?php echo esc_js( __( 'Checking…', 'worldgraph' ) ); ?>';
					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams({ action: action, nonce: nonce, post_id: postId, provider_template_id: providerTemplateId ? providerTemplateId.value : '' })
					})
						.then(function (response) { return response.json(); })
						.then(function (response) {
							result.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'The requirement check could not be completed.', 'worldgraph' ) ); ?>';
							result.style.color = response.success ? '#008a20' : '#b32d2e';
						})
						.catch(function () {
							result.textContent = '<?php echo esc_js( __( 'The requirement check could not be completed.', 'worldgraph' ) ); ?>';
							result.style.color = '#b32d2e';
						})
						.finally(function () { button.disabled = false; });
				}

				document.getElementById('worldgraph-check-requirements').addEventListener('click', function () {
					call('worldgraph_check_template_requirements', this);
				});
				document.getElementById('worldgraph-install-models').addEventListener('click', function () {
					call('worldgraph_install_template_models', this);
				});
				 document.getElementById('worldgraph-discover-comfy-templates').addEventListener('click', function () {
					var button = this;
					var results = document.getElementById('worldgraph-comfy-template-results');
					button.disabled = true;
					results.textContent = '<?php echo esc_js( __( 'Searching Comfy MCP...', 'worldgraph' ) ); ?>';
					fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'worldgraph_discover_comfy_templates', nonce: nonce, post_id: postId, search: document.getElementById('worldgraph-comfy-template-search').value }) })
						.then(function (response) { return response.json(); })
						.then(function (response) {
							results.replaceChildren();
							if (!response.success) { results.textContent = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Template discovery failed.', 'worldgraph' ) ); ?>'; return; }
							(response.data.templates || []).forEach(function (template) {
								var row = document.createElement('p');
								var select = document.createElement('button');
								select.type = 'button'; select.className = 'button-link'; select.textContent = template.name || template.id;
								select.addEventListener('click', function () { providerTemplateId.value = template.id; results.querySelectorAll('.button-link').forEach(function (item) { item.classList.remove('current'); }); select.classList.add('current'); });
								row.append(select, document.createTextNode(' (' + template.id + ')')); results.append(row);
							});
						})
						.catch(function () { results.textContent = '<?php echo esc_js( __( 'Template discovery failed.', 'worldgraph' ) ); ?>'; })
						.finally(function () { button.disabled = false; });
				});
				document.getElementById('worldgraph-download-comfy-requirements').addEventListener('click', function () {
					if (!providerTemplateId.value) { result.textContent = '<?php echo esc_js( __( 'Select a ComfyUI MCP Template first.', 'worldgraph' ) ); ?>'; return; }
					call('worldgraph_download_comfy_template_requirements', this);
				});
				document.getElementById('worldgraph-import-provider-template').addEventListener('click', function () {
					if (!providerTemplateId.value) { result.textContent = '<?php echo esc_js( __( 'Select a ComfyUI MCP Template first.', 'worldgraph' ) ); ?>'; return; }
					call('worldgraph_import_provider_template_definition', this);
				});
			}());
		</script>
		<?php
	}

	/**
	 * Report whether the connected ComfyUI can run this Template.
	 */
	public static function ajax_check_requirements(): void {
		$post_id = self::authorize_requirements_request();
		\WorldGraph\Utils\Comfy_Manifest::flush_catalog();
		$report = \WorldGraph\Utils\Comfy_Manifest::validate( $post_id );
		if ( is_wp_error( $report ) ) {
			wp_send_json_error( [ 'message' => $report->get_error_message() ] );
		}

		if ( ! empty( $report['ok'] ) ) {
			wp_send_json_success( [
				'message' => sprintf(
					/* translators: %s: ComfyUI base URL. */
					__( 'ComfyUI at %s has every node and model this Template needs.', 'worldgraph' ),
					$report['endpoint']
				),
				'report'  => $report,
			] );
		}

		$problems = [];
		if ( ! empty( $report['missing_nodes'] ) ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated node class names. */
				__( 'Missing nodes: %s.', 'worldgraph' ),
				implode( ', ', $report['missing_nodes'] )
			);
		}
		foreach ( $report['missing_models'] as $model ) {
			$problems[] = sprintf(
				/* translators: 1: model filename, 2: ComfyUI models sub-directory. */
				__( 'Missing model %1$s in models/%2$s.', 'worldgraph' ),
				$model['filename'],
				$model['folder']
			);
		}

		wp_send_json_error( [ 'message' => implode( ' ', $problems ), 'report' => $report ] );
	}

	/**
	 * Ask Comfy MCP to fetch the model files this Template is missing.
	 */
	public static function ajax_install_models(): void {
		$post_id = self::authorize_requirements_request();
		$result  = \WorldGraph\Utils\Comfy_Manifest::request_downloads( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		\WorldGraph\Utils\Comfy_Manifest::flush_catalog();
		wp_send_json_success( [
			'message' => empty( $result['requested'] )
				? (string) $result['message']
				: sprintf(
					/* translators: %d: number of model downloads requested. */
					_n( 'Requested %d model download.', 'Requested %d model downloads.', count( $result['requested'] ), 'worldgraph' ),
					count( $result['requested'] )
				),
			'result'  => $result,
		] );
	}

	/** Search the connected Comfy MCP template catalog. */
	public static function ajax_discover_comfy_templates(): void {
		$post_id = self::authorize_requirements_request();
		$connection_id = absint( get_post_meta( $post_id, 'connection_id', true ) );
		$result = \WorldGraph\Utils\Comfy_Manifest::discover_provider_templates( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ), $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'templates' => $result ] );
	}

	/** Download requirements advertised by the selected Comfy MCP template. */
	public static function ajax_download_comfy_template_requirements(): void {
		$post_id = self::authorize_requirements_request();
		$provider_template_id = sanitize_text_field( (string) ( $_POST['provider_template_id'] ?? get_post_meta( $post_id, 'provider_template_id', true ) ?: get_post_meta( $post_id, 'comfy_template_id', true ) ) );
		if ( '' === $provider_template_id ) {
			wp_send_json_error( [ 'message' => __( 'Save a ComfyUI MCP Template ID first.', 'worldgraph' ) ] );
		}

		$connection_id = absint( get_post_meta( $post_id, 'connection_id', true ) );
		$result = \WorldGraph\Utils\Comfy_Manifest::request_provider_template_downloads( $provider_template_id, $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => sprintf( __( 'Requested %d provider Template requirement downloads.', 'worldgraph' ), count( $result['requested'] ?? [] ) ), 'result' => $result ] );
	}

	/** Import a provider template definition into this World Graph Studio Template post. */
	public static function ajax_import_provider_template_definition(): void {
		$post_id = self::authorize_requirements_request();
		$provider_template_id = sanitize_text_field( (string) ( $_POST['provider_template_id'] ?? get_post_meta( $post_id, 'provider_template_id', true ) ?: get_post_meta( $post_id, 'comfy_template_id', true ) ) );
		if ( '' === $provider_template_id ) {
			wp_send_json_error( [ 'message' => __( 'Select a provider Template first.', 'worldgraph' ) ] );
		}

		$connection_id = absint( get_post_meta( $post_id, 'connection_id', true ) );
		$raw = \WorldGraph\Utils\Comfy_Cloud_MCP::get_template( $provider_template_id, [], $connection_id );
		if ( is_wp_error( $raw ) ) {
			wp_send_json_error( [ 'message' => $raw->get_error_message() ] );
		}

		$normalized = \WorldGraph\Utils\Comfy_Manifest::normalize_entry( array_merge( [
			'id'   => $provider_template_id,
			'name' => (string) get_post_meta( $post_id, 'template_name', true ),
		], is_array( $raw ) ? $raw : [] ) );
		if ( ! is_array( $normalized ) ) {
			wp_send_json_error( [ 'message' => __( 'Provider template payload was unreadable.', 'worldgraph' ) ] );
		}

		$workflow = is_array( $raw['workflow'] ?? null ) ? $raw['workflow'] : [];
		if ( ! empty( $workflow ) ) {
			update_post_meta( $post_id, 'workflow_json', wp_slash( (string) wp_json_encode( $workflow ) ) );
		}

		if ( ! empty( $normalized['parameters'] ) && is_array( $normalized['parameters'] ) ) {
			update_post_meta( $post_id, 'configuration_json', wp_slash( (string) wp_json_encode( [ 'parameters' => $normalized['parameters'] ] ) ) );
		}

		$requirements = self::requirements_from_provider_entry( $normalized );
		if ( ! empty( $requirements ) ) {
			update_post_meta( $post_id, 'model_requirements', wp_slash( (string) wp_json_encode( $requirements ) ) );
		}

		if ( ! empty( $normalized['modality'] ) ) {
			$modality = \WorldGraph\Utils\Generation_Modality::sanitize( (string) $normalized['modality'] );
			update_post_meta( $post_id, 'modality', $modality );
			update_post_meta( $post_id, 'generation_structure', \WorldGraph\Utils\Generation_Modality::output_type( $modality ) );
		}

		update_post_meta( $post_id, 'provider_type', 'comfyui' );
		update_post_meta( $post_id, 'provider_template_id', $provider_template_id );
		if ( ! empty( $normalized['model_family'] ) ) {
			update_post_meta( $post_id, 'model_family', \WorldGraph\Utils\Model_Family::sanitize( (string) $normalized['model_family'] ) );
		}

		foreach ( (array) ( $normalized['models'] ?? [] ) as $model ) {
			if ( is_array( $model ) && 'checkpoints' === (string) ( $model['folder'] ?? '' ) && ! empty( $model['filename'] ) ) {
				update_post_meta( $post_id, 'checkpoint', (string) $model['filename'] );
				break;
			}
		}

		wp_send_json_success( [ 'message' => __( 'Provider template definition imported into this Template. Save the post to persist any unsaved field edits.', 'worldgraph' ) ] );
	}

	/**
	 * Shared permission and nonce gate for the requirements panel actions.
	 *
	 * @return int Template post ID.
	 */
	private static function authorize_requirements_request(): int {
		check_ajax_referer( 'worldgraph_template_requirements', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to inspect this Template.', 'worldgraph' ) ], 403 );
		}
		\WorldGraph\Utils\Connection_Adapters::load( 'comfyui' );

		return $post_id;
	}

	/**
	 * Convert normalized provider entry metadata to model requirements JSON.
	 *
	 * @param array $entry Normalized provider entry.
	 * @return array<int, array<string, string>>
	 */
	private static function requirements_from_provider_entry( array $entry ): array {
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
	 * Save template meta fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['worldgraph_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['worldgraph_template_nonce'] ) ), 'worldgraph_template_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_template' );
		foreach ( $fields as $field_name => $field ) {
			if ( ! array_key_exists( $field_name, $_POST ) ) {
				continue;
			}

			$value = sanitize_textarea_field( wp_unslash( $_POST[ $field_name ] ) );
			if ( 'status' === $field_name || 'provider_type' === $field_name || 'version' === $field_name || 'generation_structure' === $field_name || 'checkpoint' === $field_name ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
			}
			if ( 'modality' === $field_name ) {
				$value = \WorldGraph\Utils\Generation_Modality::sanitize( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) );
			}
			if ( 'model_family' === $field_name ) {
				$value = \WorldGraph\Utils\Model_Family::sanitize( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) );
			}
			if ( 'connection_id' === $field_name ) {
				$value = (string) absint( wp_unslash( $_POST[ $field_name ] ) );
			}

			if ( 'status' === $field_name && ! in_array( $value, [ 'draft', 'active', 'archived' ], true ) ) {
				$value = 'draft';
			}

			update_post_meta( $post_id, $field_name, $value );
		}
	}

	/**
	 * Create or update the single Template post managed for a given setup-wizard
	 * slot, so a Connection's checkpoint/workflow configuration lives on one
	 * default Template instead of a separate global option.
	 *
	 * @param string $slot  Wizard slot marker, e.g. 'local_comfyui_default'.
	 * @param string $title Post title / template name.
	 * @param array  $meta  Meta fields to set (subset of the registered fields).
	 * @return int Template post ID.
	 */
	public static function upsert_managed( string $slot, string $title, array $meta ): int {
		$existing = get_posts( [
			'post_type'      => 'worldgraph_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'worldgraph_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slot, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		] );

		$post_id = $existing ? (int) $existing[0] : 0;
		$post_id = wp_insert_post( [
			'ID'          => $post_id ?: 0,
			'post_type'   => 'worldgraph_template',
			'post_title'  => $title,
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, 'worldgraph_wizard_slot', $slot );
		update_post_meta( $post_id, 'template_name', $title );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return (int) $post_id;
	}
}
