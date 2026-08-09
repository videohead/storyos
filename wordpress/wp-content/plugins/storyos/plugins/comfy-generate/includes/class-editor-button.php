<?php
/**
 * Editor button handler for ComfyUI Generate plugin.
 *
 * @package StoryOSComfyGenerate
 */

namespace StoryOSComfyGenerate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the "Send to ComfyUI" button to the post editor.
 */
class Editor_Button {

	/**
	 * Initialize the editor button.
	 */
	public static function init(): void {
		add_action( 'post_submitbox_misc_actions', [ __CLASS__, 'add_button' ] );
	}

	/**
	 * Add the Send to ComfyUI button to the post editor.
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

		$nonce  = wp_create_nonce( 'comfy_generate_send_nonce' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<div id="comfy-generate-box" style="padding:10px; border-top:1px solid #ddd;">
			<button id="comfy-generate-btn"
					class="button button-primary"
					style="width:100%; margin-top:10px;">
				<?php esc_html_e( 'Send to ComfyUI', 'storyos-comfy-generate' ); ?>
			</button>

			<div id="comfy-generate-status"
				 style="margin-top:10px; font-weight:bold;"></div>

			<script>
				document.addEventListener("DOMContentLoaded", function() {
					const btn = document.getElementById("comfy-generate-btn");
					const status = document.getElementById("comfy-generate-status");

					btn.addEventListener("click", function() {
						status.textContent = "<?php esc_js( __( 'Sending request to ComfyUI...', 'storyos-comfy-generate' ) ); ?>";
						btn.disabled = true;

						fetch("<?php echo esc_url( $ajax_url ); ?>", {
							method: "POST",
							headers: {
								"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
							},
							body: new URLSearchParams({
								action: "comfy_generate_send_to_comfyui",
								nonce: "<?php echo esc_js( $nonce ); ?>",
								post_id: "<?php echo (int) $post->ID; ?>"
							}).toString()
						})
						.then(response => response.json())
						.then(data => {
							btn.disabled = false;

							if (!data || typeof data !== "object") {
								status.textContent = "<?php esc_js( __( 'Error: Invalid response from server.', 'storyos-comfy-generate' ) ); ?>";
								return;
							}

							if (data.success && data.data && data.data.response) {
								const payload = data.data.response;
								const jobId = payload.job_id || payload.id || payload.queue_id;

								if (jobId) {
									status.textContent = "<?php esc_js( __( 'Job queued:', 'storyos-comfy-generate' ) ); ?> " + jobId;
								} else {
									status.textContent = "<?php esc_js( __( 'Request sent successfully.', 'storyos-comfy-generate' ) ); ?>";
								}
							} else {
								const err = data.data && (data.data.message || data.data.details)
									? (data.data.message + (data.data.details ? " " + data.data.details : ""))
									: "<?php esc_js( __( 'Unknown error.', 'storyos-comfy-generate' ) ); ?>";
								status.textContent = "<?php esc_js( __( 'Error:', 'storyos-comfy-generate' ) ); ?> " + err;
							}
						})
						.catch(err => {
							btn.disabled = false;
							status.textContent = "<?php esc_js( __( 'Request failed:', 'storyos-comfy-generate' ) ); ?> " + err;
						});
					});
				});
			</script>
		</div>
		<?php
	}
}
