<?php
/**
 * Editor button handler for StoryOS Generation Engine plugin.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
	 * Adds the generation job button to the post editor.
 */
class Editor_Button {

	/**
	 * Initialize the editor button.
	 */
	public static function init(): void {
		add_action( 'post_submitbox_misc_actions', [ __CLASS__, 'add_button' ] );
	}

	/**
	 * Add the generation submission button to the post editor.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function add_button( $post ): void {
		if ( ! $post ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$nonce     = wp_create_nonce( 'storyos_generation_engine_nonce' );
		$ajax_url  = admin_url( 'admin-ajax.php' );
		$settings  = Settings::get_settings();
		$provider_type = Settings::get_default_provider();
		$structures = class_exists( __NAMESPACE__ . '\\Structure_Registry' ) ? Structure_Registry::get_structures() : [];
		foreach ( $structures as $structure_id => $structure ) {
			foreach ( $structure['fields'] ?? [] as $field_index => $field ) {
				$scf_field = $field['scf_field'] ?? '';
				$structures[ $structure_id ]['fields'][ $field_index ]['initial_value'] = $scf_field
					? get_post_meta( $post->ID, sanitize_key( $scf_field ), true )
					: '';
			}
		}
		?>
		<div id="storyos-generation-engine-box" style="padding:10px; border-top:1px solid #ddd;">
			<label for="storyos-generation-structure"><strong><?php esc_html_e( 'Generation Structure', 'storyos-generation-engine' ); ?></strong></label>
			<select id="storyos-generation-structure" style="width:100%; margin-top:5px;">
				<?php foreach ( $structures as $structure_id => $structure ) : ?>
					<option value="<?php echo esc_attr( $structure_id ); ?>"><?php echo esc_html( $structure['label'] ?? $structure_id ); ?></option>
				<?php endforeach; ?>
			</select>
			<div id="storyos-generation-structure-description" style="margin-top:5px;"></div>
			<div id="storyos-generation-structure-fields" style="margin-top:8px;"></div>
			<button id="storyos-generation-engine-btn"
					class="button button-primary"
					style="width:100%; margin-top:10px;">
				<?php esc_html_e( 'Queue Generation Job', 'storyos-generation-engine' ); ?>
			</button>

			<div id="storyos-generation-engine-status"
				 style="margin-top:10px; font-weight:bold;"></div>

			<script>
				document.addEventListener("DOMContentLoaded", function() {
					const btn = document.getElementById("storyos-generation-engine-btn");
					const status = document.getElementById("storyos-generation-engine-status");
					const structureSelect = document.getElementById("storyos-generation-structure");
					const structureDescription = document.getElementById("storyos-generation-structure-description");
					const structureFields = document.getElementById("storyos-generation-structure-fields");
					const structures = <?php echo wp_json_encode( $structures ); ?>;

					function renderStructureFields() {
						const structure = structures[structureSelect.value] || {};
						structureDescription.textContent = structure.description || "";
						structureFields.replaceChildren();
						(structure.fields || []).forEach(function(field) {
							const label = document.createElement("label");
							label.textContent = field.label || field.name;
							label.style.display = "block";
							label.style.marginTop = "6px";
							const input = document.createElement(field.type === "textarea" ? "textarea" : "input");
							input.name = field.name;
							input.dataset.structureField = field.name;
							input.style.width = "100%";
							if (field.type !== "textarea") input.type = field.type || "text";
							if (field.placeholder) input.placeholder = field.placeholder;
							if (field.min) input.min = field.min;
							if (field.required) input.required = true;
							if (field.initial_value) input.value = field.initial_value;
							label.appendChild(input);
							structureFields.appendChild(label);
						});
					}

					structureSelect.addEventListener("change", renderStructureFields);
					renderStructureFields();

					btn.addEventListener("click", function() {
							status.textContent = "<?php esc_js( __( 'Submitting generation job...', 'storyos-generation-engine' ) ); ?>";
						btn.disabled = true;

						fetch("<?php echo esc_url( $ajax_url ); ?>", {
							method: "POST",
							headers: {
								"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
							},
							body: new URLSearchParams({
								action: "storyos_generation_engine_submit",
								nonce: "<?php echo esc_js( $nonce ); ?>",
								post_id: "<?php echo (int) $post->ID; ?>",
								workflow: "<?php echo esc_js( $settings['workflow'] ?? 'character-sheet' ); ?>",
								custom_params: JSON.stringify({
									provider_type: "<?php echo esc_js( $provider_type ); ?>",
									connection_id: <?php echo (int) ( $settings['connection_id'] ?? 1 ); ?>,
									structure_id: structureSelect.value,
									structure_fields: Object.fromEntries(Array.from(structureFields.querySelectorAll("[data-structure-field]")).map(function(input) { return [input.dataset.structureField, input.value]; }))
								})
							}).toString()
						})
						.then(response => response.json())
						.then(data => {
							btn.disabled = false;

							if (!data || typeof data !== "object") {
								status.textContent = "<?php esc_js( __( 'Error: Invalid response from server.', 'storyos-generation-engine' ) ); ?>";
								return;
							}

							if (data.success && data.data && data.data.response) {
								const payload = data.data.response;
								const jobId = payload.job_id || payload.id || payload.queue_id;

								if (jobId) {
									status.textContent = "<?php esc_js( __( 'Job queued:', 'storyos-generation-engine' ) ); ?> " + jobId;
								} else {
									status.textContent = "<?php esc_js( __( 'Request sent successfully.', 'storyos-generation-engine' ) ); ?>";
								}
							} else {
								const err = data.data && (data.data.message || data.data.details)
									? (data.data.message + (data.data.details ? " " + data.data.details : ""))
									: "<?php esc_js( __( 'Unknown error.', 'storyos-generation-engine' ) ); ?>";
								status.textContent = "<?php esc_js( __( 'Error:', 'storyos-generation-engine' ) ); ?> " + err;
							}
						})
						.catch(err => {
							btn.disabled = false;
							status.textContent = "<?php esc_js( __( 'Request failed:', 'storyos-generation-engine' ) ); ?> " + err;
						});
					});
				});
			</script>
		</div>
		<?php
	}
}
