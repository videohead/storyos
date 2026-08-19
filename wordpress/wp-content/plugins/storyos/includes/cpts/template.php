<?php
/**
 * Generation Template Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

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
		add_action( 'save_post_storyos_template', [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'wp_ajax_storyos_check_template_requirements', [ __CLASS__, 'ajax_check_requirements' ] );
		add_action( 'wp_ajax_storyos_install_template_models', [ __CLASS__, 'ajax_install_models' ] );
		add_action( 'wp_ajax_storyos_discover_comfy_templates', [ __CLASS__, 'ajax_discover_comfy_templates' ] );
		add_action( 'wp_ajax_storyos_download_comfy_template_requirements', [ __CLASS__, 'ajax_download_comfy_template_requirements' ] );
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
				'options'     => \StoryOS\Utils\Generation_Modality::labels(),
				'description' => 'What this Template generates and which inputs it consumes. Determines the built-in workflow and the ComfyUI nodes and models StoryOS checks for.',
			],
			'connection_id'       => [
				'type'        => 'text',
				'label'       => 'Connection ID',
				'required'    => false,
				'description' => 'The storyos_connection post ID this template runs against (a Connection can back many Templates/checkpoints).',
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
				'options'     => \StoryOS\Utils\Model_Family::labels(),
				'description' => 'The generative model this Template runs, e.g. LTX 2.3, MiniMax, SCAIL, or Wan 2.1. Used to group Templates and cross-check against the Connection\'s allowed models.',
			],
			'workflow_json'       => [
				'type'        => 'textarea',
				'label'       => 'ComfyUI API Workflow (optional)',
				'required'    => false,
				'description' => 'Leave blank to use the built-in workflow for the selected modality with the checkpoint above. To use a custom graph, export it with ComfyUI\'s “Save (API Format)”, then replace each input value with its slot placeholder: {{prompt}}, {{negative_prompt}}, {{image}}, {{start_frame}}, {{end_frame}}, {{video}}, {{audio}}.',
			],
			'provider_template_id' => [
				'type'        => 'text',
				'label'       => 'Provider MCP Template ID',
				'required'    => false,
				'description' => 'Template ID discovered from the selected provider Connection. The provider and Template must belong to the same Connection pair.',
			],
			'configuration_json'  => [
				'type'        => 'textarea',
				'label'       => 'Configuration JSON',
				'required'    => true,
				'description' => 'Provider-neutral JSON for parameters, references, and SCF field mappings. Recognized parameters include width, height, length, frame_rate, steps, cfg, denoise, sampler, and scheduler.',
			],
			'input_bindings'      => [
				'type'        => 'textarea',
				'label'       => 'Input Bindings JSON',
				'required'    => false,
				'description' => 'Optional JSON mapping this modality\'s input slots (image, start_frame, end_frame, video, audio) to a Story Graph source, e.g. {"image":{"source":"featured_image"}}.',
			],
			'model_requirements'  => [
				'type'        => 'textarea',
				'label'       => 'Model Requirements JSON',
				'required'    => false,
				'description' => 'Optional JSON array of download sources for the models this Template loads: [{"filename":"ltx-2.3.safetensors","folder":"checkpoints","url":"https://…"}]. Used by the requirement check to offer a one-click install.',
			],
			'default_values'     => [
				'type'        => 'textarea',
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

		\StoryOS\Utils\register_cpt(
			'storyos_template',
			'Templates',
			[
				'menu_icon' => 'dashicons-media-document',
				'show_in_menu' => 'storyos-administration',
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
				'storyos_template_details',
				'Template Details',
				[ self::class, 'render_template_meta_box' ],
				'storyos_template',
				'normal',
				'default'
			);
			add_meta_box(
				'storyos_template_requirements',
				'ComfyUI Requirements',
				[ self::class, 'render_requirements_meta_box' ],
				'storyos_template',
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
		wp_nonce_field( 'storyos_template_details', 'storyos_template_nonce' );
		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_template' );
		?>
		<p><em><?php echo esc_html__( 'Use SCF-backed field names in the configuration JSON when a template should preload from Story Graph content.', 'storyos' ); ?></em></p>
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
	 * Render the ComfyUI requirements panel: what this Template will ask
	 * ComfyUI for, and whether the connected instance can supply it.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_requirements_meta_box( \WP_Post $post ): void {
		$connection_id = absint( get_post_meta( $post->ID, 'connection_id', true ) );
		$connection = \StoryOS\Utils\Connection_Repository::get( $connection_id );
		if ( ! $connection || 'comfyui' !== $connection['provider_type'] ) {
			echo '<p>' . esc_html__( 'This Template is paired with a non-ComfyUI provider. Use that provider connection\'s adapter to discover and download its requirements.', 'storyos' ) . '</p>';
			return;
		}
		$manifest = \StoryOS\Utils\Comfy_Manifest::for_template( $post->ID );
		if ( is_wp_error( $manifest ) ) {
			echo '<p>' . esc_html__( 'Save this Template to see its ComfyUI requirements.', 'storyos' ) . '</p>';
			return;
		}
		?>
		<p>
			<strong><?php echo esc_html( $manifest['modality_label'] ); ?></strong><br />
			<span class="description">
				<?php
				printf(
					/* translators: 1: output media kind, 2: workflow source. */
					esc_html__( 'Outputs %1$s using the %2$s workflow.', 'storyos' ),
					esc_html( $manifest['output_type'] ),
					esc_html( 'custom' === $manifest['workflow_source'] ? __( 'pasted custom', 'storyos' ) : __( 'built-in', 'storyos' ) )
				);
				?>
			</span>
		</p>
		<p><strong><?php echo esc_html__( 'Inputs', 'storyos' ); ?></strong><br />
		<?php foreach ( $manifest['inputs'] as $slot => $input ) : ?>
			<code>{{<?php echo esc_html( $slot ); ?>}}</code>
			<?php echo esc_html( empty( $input['required'] ) ? __( '(optional)', 'storyos' ) : __( '(required)', 'storyos' ) ); ?><br />
		<?php endforeach; ?>
		</p>
		<p><strong><?php echo esc_html__( 'Models', 'storyos' ); ?></strong><br />
		<?php if ( empty( $manifest['models'] ) ) : ?>
			<span class="description"><?php echo esc_html__( 'None detected. Set a checkpoint above.', 'storyos' ); ?></span>
		<?php else : ?>
			<?php foreach ( $manifest['models'] as $model ) : ?>
				<code><?php echo esc_html( $model['filename'] ); ?></code> &rarr; <code>models/<?php echo esc_html( $model['folder'] ); ?></code><br />
			<?php endforeach; ?>
		<?php endif; ?>
		</p>
		<p>
			<button type="button" class="button" id="storyos-check-requirements"><?php echo esc_html__( 'Check ComfyUI', 'storyos' ); ?></button>
			<button type="button" class="button" id="storyos-install-models"><?php echo esc_html__( 'Install missing models', 'storyos' ); ?></button>
		</p>
		<p><strong><?php echo esc_html__( 'ComfyUI MCP Template', 'storyos' ); ?></strong></p>
		<p><input type="search" class="regular-text" id="storyos-comfy-template-search" placeholder="<?php echo esc_attr__( 'Search provider templates', 'storyos' ); ?>" />
		<button type="button" class="button" id="storyos-discover-comfy-templates"><?php echo esc_html__( 'Discover', 'storyos' ); ?></button></p>
		<div id="storyos-comfy-template-results"></div>
		<p><button type="button" class="button" id="storyos-download-comfy-requirements"><?php echo esc_html__( 'Download selected requirements', 'storyos' ); ?></button></p>
		<div id="storyos-requirements-result" aria-live="polite"></div>
		<script>
			(function () {
				var result = document.getElementById('storyos-requirements-result');
				var nonce = '<?php echo esc_js( wp_create_nonce( 'storyos_template_requirements' ) ); ?>';
				var postId = '<?php echo esc_js( (string) $post->ID ); ?>';
				var providerTemplateId = document.getElementById('provider_template_id') || document.getElementById('comfy_template_id');

				function call(action, button) {
					button.disabled = true;
					result.textContent = '<?php echo esc_js( __( 'Checking…', 'storyos' ) ); ?>';
					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams({ action: action, nonce: nonce, post_id: postId, provider_template_id: providerTemplateId ? providerTemplateId.value : '' })
					})
						.then(function (response) { return response.json(); })
						.then(function (response) {
							result.textContent = (response.data && response.data.message) || '<?php echo esc_js( __( 'The requirement check could not be completed.', 'storyos' ) ); ?>';
							result.style.color = response.success ? '#008a20' : '#b32d2e';
						})
						.catch(function () {
							result.textContent = '<?php echo esc_js( __( 'The requirement check could not be completed.', 'storyos' ) ); ?>';
							result.style.color = '#b32d2e';
						})
						.finally(function () { button.disabled = false; });
				}

				document.getElementById('storyos-check-requirements').addEventListener('click', function () {
					call('storyos_check_template_requirements', this);
				});
				document.getElementById('storyos-install-models').addEventListener('click', function () {
					call('storyos_install_template_models', this);
				});
				 document.getElementById('storyos-discover-comfy-templates').addEventListener('click', function () {
					var button = this;
					var results = document.getElementById('storyos-comfy-template-results');
					button.disabled = true;
					results.textContent = '<?php echo esc_js( __( 'Searching Comfy MCP...', 'storyos' ) ); ?>';
					fetch(ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'storyos_discover_comfy_templates', nonce: nonce, post_id: postId, search: document.getElementById('storyos-comfy-template-search').value }) })
						.then(function (response) { return response.json(); })
						.then(function (response) {
							results.replaceChildren();
							if (!response.success) { results.textContent = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Template discovery failed.', 'storyos' ) ); ?>'; return; }
							(response.data.templates || []).forEach(function (template) {
								var row = document.createElement('p');
								var select = document.createElement('button');
								select.type = 'button'; select.className = 'button-link'; select.textContent = template.name || template.id;
								select.addEventListener('click', function () { providerTemplateId.value = template.id; results.querySelectorAll('.button-link').forEach(function (item) { item.classList.remove('current'); }); select.classList.add('current'); });
								row.append(select, document.createTextNode(' (' + template.id + ')')); results.append(row);
							});
						})
						.catch(function () { results.textContent = '<?php echo esc_js( __( 'Template discovery failed.', 'storyos' ) ); ?>'; })
						.finally(function () { button.disabled = false; });
				});
				document.getElementById('storyos-download-comfy-requirements').addEventListener('click', function () {
					if (!providerTemplateId.value) { result.textContent = '<?php echo esc_js( __( 'Select a ComfyUI MCP Template first.', 'storyos' ) ); ?>'; return; }
					call('storyos_download_comfy_template_requirements', this);
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
		\StoryOS\Utils\Comfy_Manifest::flush_catalog();
		$report = \StoryOS\Utils\Comfy_Manifest::validate( $post_id );
		if ( is_wp_error( $report ) ) {
			wp_send_json_error( [ 'message' => $report->get_error_message() ] );
		}

		if ( ! empty( $report['ok'] ) ) {
			wp_send_json_success( [
				'message' => sprintf(
					/* translators: %s: ComfyUI base URL. */
					__( 'ComfyUI at %s has every node and model this Template needs.', 'storyos' ),
					$report['endpoint']
				),
				'report'  => $report,
			] );
		}

		$problems = [];
		if ( ! empty( $report['missing_nodes'] ) ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated node class names. */
				__( 'Missing nodes: %s.', 'storyos' ),
				implode( ', ', $report['missing_nodes'] )
			);
		}
		foreach ( $report['missing_models'] as $model ) {
			$problems[] = sprintf(
				/* translators: 1: model filename, 2: ComfyUI models sub-directory. */
				__( 'Missing model %1$s in models/%2$s.', 'storyos' ),
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
		$result  = \StoryOS\Utils\Comfy_Manifest::request_downloads( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		\StoryOS\Utils\Comfy_Manifest::flush_catalog();
		wp_send_json_success( [
			'message' => empty( $result['requested'] )
				? (string) $result['message']
				: sprintf(
					/* translators: %d: number of model downloads requested. */
					_n( 'Requested %d model download.', 'Requested %d model downloads.', count( $result['requested'] ), 'storyos' ),
					count( $result['requested'] )
				),
			'result'  => $result,
		] );
	}

	/** Search the connected Comfy MCP template catalog. */
	public static function ajax_discover_comfy_templates(): void {
		$post_id = self::authorize_requirements_request();
		$connection_id = absint( get_post_meta( $post_id, 'connection_id', true ) );
		$result = \StoryOS\Utils\Comfy_Manifest::discover_provider_templates( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ), $connection_id );
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
			wp_send_json_error( [ 'message' => __( 'Save a ComfyUI MCP Template ID first.', 'storyos' ) ] );
		}

		$connection_id = absint( get_post_meta( $post_id, 'connection_id', true ) );
		$result = \StoryOS\Utils\Comfy_Manifest::request_provider_template_downloads( $provider_template_id, $connection_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => sprintf( __( 'Requested %d provider Template requirement downloads.', 'storyos' ), count( $result['requested'] ?? [] ) ), 'result' => $result ] );
	}

	/**
	 * Shared permission and nonce gate for the requirements panel actions.
	 *
	 * @return int Template post ID.
	 */
	private static function authorize_requirements_request(): int {
		check_ajax_referer( 'storyos_template_requirements', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to inspect this Template.', 'storyos' ) ], 403 );
		}

		return $post_id;
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

		if ( ! isset( $_POST['storyos_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storyos_template_nonce'] ) ), 'storyos_template_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_template' );
		foreach ( $fields as $field_name => $field ) {
			if ( ! array_key_exists( $field_name, $_POST ) ) {
				continue;
			}

			$value = sanitize_textarea_field( wp_unslash( $_POST[ $field_name ] ) );
			if ( 'status' === $field_name || 'provider_type' === $field_name || 'version' === $field_name || 'generation_structure' === $field_name || 'checkpoint' === $field_name ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
			}
			if ( 'modality' === $field_name ) {
				$value = \StoryOS\Utils\Generation_Modality::sanitize( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) );
			}
			if ( 'model_family' === $field_name ) {
				$value = \StoryOS\Utils\Model_Family::sanitize( sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) );
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
			'post_type'      => 'storyos_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'storyos_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $slot, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		] );

		$post_id = $existing ? (int) $existing[0] : 0;
		$post_id = wp_insert_post( [
			'ID'          => $post_id ?: 0,
			'post_type'   => 'storyos_template',
			'post_title'  => $title,
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( $post_id, 'storyos_wizard_slot', $slot );
		update_post_meta( $post_id, 'template_name', $title );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return (int) $post_id;
	}
}