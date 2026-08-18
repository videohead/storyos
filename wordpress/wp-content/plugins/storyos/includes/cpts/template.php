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
			'workflow_json'       => [
				'type'        => 'textarea',
				'label'       => 'ComfyUI API Workflow (optional)',
				'required'    => false,
				'description' => 'Leave blank to use the built-in single-image text-to-image workflow with the checkpoint above. To use a custom graph, export it with ComfyUI\'s “Save (API Format)”, replace the positive prompt text with {{prompt}}, then paste the JSON here.',
			],
			'configuration_json'  => [
				'type'        => 'textarea',
				'label'       => 'Configuration JSON',
				'required'    => true,
				'description' => 'Provider-neutral JSON for parameters, references, and SCF field mappings.',
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