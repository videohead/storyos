<?php
/**
 * Quick story summary admin tool.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

use StoryOS\AI\AI_Context_Builder;
use StoryOS\AI\AI_LLM_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides quick AI-generated summaries for Story Graph content.
 */
class Summary_Tool {
	/**
	 * Initialize the tool.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_storyos_generate_summary', [ __CLASS__, 'ajax_generate' ] );
		add_action( 'wp_ajax_storyos_save_summary', [ __CLASS__, 'ajax_save' ] );
	}

	/**
	 * Enqueue assets only on the Summaries page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'storyos-summaries' !== sanitize_key( $_GET['page'] ?? '' ) ) {
			return;
		}

		wp_enqueue_style( 'storyos-summary-tool', STORYOS_PLUGIN_URL . 'assets/css/summary-tool.css', [], STORYOS_VERSION );
		wp_enqueue_script( 'storyos-summary-tool', STORYOS_PLUGIN_URL . 'assets/js/summary-tool.js', [], STORYOS_VERSION, true );
		wp_localize_script( 'storyos-summary-tool', 'storyosSummaryTool', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'storyos_summary_tool' ),
			'strings' => [
				'generating' => __( 'Generating summary...', 'storyos' ),
				'generated'  => __( 'Summary ready to review.', 'storyos' ),
				'saving'    => __( 'Saving summary...', 'storyos' ),
				'saved'     => __( 'Summary saved.', 'storyos' ),
				'error'     => __( 'Something went wrong. Please try again.', 'storyos' ),
			],
		] );
	}

	/**
	 * Render the summaries page.
	 */
	public static function render_page(): void {
		$sources = [];
		$source_types = [
			'storyos_project' => [ 'label' => __( 'Projects', 'storyos' ), 'field' => 'description' ],
			'storyos_episode' => [ 'label' => __( 'Episodes', 'storyos' ), 'field' => 'synopsis' ],
			'storyos_scene'   => [ 'label' => __( 'Scenes', 'storyos' ), 'field' => 'summary' ],
		];

		foreach ( $source_types as $post_type => $config ) {
			$posts = get_posts( [
				'post_type'      => $post_type,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
			foreach ( $posts as $post ) {
				$sources[] = [
					'id'      => $post->ID,
					'type'    => $post_type,
					'label'   => $config['label'],
					'title'   => $post->post_title ?: __( '(Untitled)', 'storyos' ),
					'field'   => $config['field'],
					'summary' => get_post_meta( $post->ID, $config['field'], true ),
				];
			}
		}
		?>
		<div class="wrap storyos-summary-page">
			<h1><?php esc_html_e( 'Quick Story Summary', 'storyos' ); ?></h1>
			<p class="storyos-summary-intro"><?php esc_html_e( 'Turn your Story Graph content into a concise summary you can review and save.', 'storyos' ); ?></p>
			<div class="storyos-summary-layout">
				<section class="storyos-summary-panel storyos-summary-controls" aria-labelledby="storyos-summary-controls-title">
					<h2 id="storyos-summary-controls-title"><?php esc_html_e( 'Summary source', 'storyos' ); ?></h2>
					<label for="storyos-summary-source"><?php esc_html_e( 'Choose a project, episode, or scene', 'storyos' ); ?></label>
					<select id="storyos-summary-source" class="widefat" <?php disabled( empty( $sources ) ); ?>>
						<?php if ( empty( $sources ) ) : ?>
							<option><?php esc_html_e( 'No story content found', 'storyos' ); ?></option>
						<?php else : ?>
							<?php foreach ( $sources as $source ) : ?>
								<option value="<?php echo esc_attr( $source['id'] ); ?>" data-type="<?php echo esc_attr( $source['type'] ); ?>" data-summary="<?php echo esc_attr( $source['summary'] ); ?>">
									<?php echo esc_html( $source['label'] . ': ' . $source['title'] ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<label for="storyos-summary-length"><?php esc_html_e( 'Length', 'storyos' ); ?></label>
					<select id="storyos-summary-length" class="widefat">
						<option value="short"><?php esc_html_e( 'Short: 1-2 sentences', 'storyos' ); ?></option>
						<option value="standard" selected><?php esc_html_e( 'Standard: one paragraph', 'storyos' ); ?></option>
						<option value="detailed"><?php esc_html_e( 'Detailed: 2-3 paragraphs', 'storyos' ); ?></option>
					</select>
					<label for="storyos-summary-focus"><?php esc_html_e( 'Optional focus', 'storyos' ); ?></label>
					<textarea id="storyos-summary-focus" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'For example: emphasize the protagonist\'s central conflict.', 'storyos' ); ?>"></textarea>
					<button type="button" id="storyos-generate-summary" class="button button-primary" <?php disabled( empty( $sources ) ); ?>>
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Generate summary', 'storyos' ); ?>
					</button>
					<p id="storyos-summary-status" class="storyos-summary-status" role="status" aria-live="polite"></p>
				</section>
				<section class="storyos-summary-panel storyos-summary-result" aria-labelledby="storyos-summary-result-title">
					<div class="storyos-summary-result-header">
						<h2 id="storyos-summary-result-title"><?php esc_html_e( 'Summary', 'storyos' ); ?></h2>
						<button type="button" id="storyos-save-summary" class="button" disabled><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Save to source', 'storyos' ); ?></button>
					</div>
					<textarea id="storyos-summary-output" class="widefat" rows="15" placeholder="<?php esc_attr_e( 'Your generated summary will appear here.', 'storyos' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Review the text before saving. Saving replaces the selected source summary field.', 'storyos' ); ?></p>
				</section>
			</div>
		</div>
		<?php
	}

	/** Handle summary generation. */
	public static function ajax_generate(): void {
		check_ajax_referer( 'storyos_summary_tool', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to generate summaries.', 'storyos' ) ], 403 );
		}

		$post_id = absint( $_POST['source_id'] ?? 0 );
		$length  = sanitize_key( $_POST['length'] ?? 'standard' );
		$focus   = sanitize_textarea_field( wp_unslash( $_POST['focus'] ?? '' ) );
		$post    = get_post( $post_id );
		$allowed = [ 'storyos_project', 'storyos_episode', 'storyos_scene' ];
		if ( ! $post || ! in_array( $post->post_type, $allowed, true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Choose a valid story source.', 'storyos' ) ], 400 );
		}

		$length_instruction = [
			'short'    => 'in 1 or 2 sentences',
			'standard' => 'as one concise paragraph of 3 to 5 sentences',
			'detailed' => 'in 2 or 3 concise paragraphs',
		][ $length ] ?? 'as one concise paragraph of 3 to 5 sentences';
		$context_builder = new AI_Context_Builder();
		$context         = $context_builder->build_post_context( $post_id );
		$prompt          = sprintf( 'Write a faithful story summary %s. Use only details supported by the provided Story Graph context. Do not add headings, bullet points, commentary, or invented details. Source title: %s.', $length_instruction, $post->post_title );
		if ( $focus ) {
			$prompt .= ' Focus on: ' . $focus;
		}
		$result = ( new AI_LLM_Client() )->chat( $prompt, [
			'system_prompt' => 'You are a precise story editor. Write clear, engaging summaries that preserve the source material.',
			'context'       => $context,
			'max_tokens'    => 700,
			'temperature'   => 0.45,
		] );
		if ( ! empty( $result['error'] ) || empty( $result['content'] ) ) {
			wp_send_json_error( [ 'message' => __( 'The AI service could not generate a summary.', 'storyos' ) ], 502 );
		}
		wp_send_json_success( [ 'summary' => trim( wp_strip_all_tags( $result['content'] ) ) ] );
	}

	/** Handle summary persistence. */
	public static function ajax_save(): void {
		check_ajax_referer( 'storyos_summary_tool', 'nonce' );
		$post_id = absint( $_POST['source_id'] ?? 0 );
		$summary = sanitize_textarea_field( wp_unslash( $_POST['summary'] ?? '' ) );
		$post    = get_post( $post_id );
		$fields  = [ 'storyos_project' => 'description', 'storyos_episode' => 'synopsis', 'storyos_scene' => 'summary' ];
		if ( ! $post || ! isset( $fields[ $post->post_type ] ) || ! current_user_can( 'edit_post', $post_id ) || '' === $summary ) {
			wp_send_json_error( [ 'message' => __( 'Unable to save this summary.', 'storyos' ) ], 400 );
		}
		update_post_meta( $post_id, $fields[ $post->post_type ], $summary );
		wp_send_json_success( [ 'message' => __( 'Summary saved.', 'storyos' ) ] );
	}
}
