<?php
/**
 * Film dramaturgy admin tool.
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
 * Provides evidence-based dramaturgical analysis for Story Graph content.
 */
class Dramaturgy_Tool {
	/**
	 * Initialize the tool.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_storyos_run_dramaturgy', [ __CLASS__, 'ajax_run' ] );
		add_action( 'wp_ajax_storyos_save_dramaturgy', [ __CLASS__, 'ajax_save' ] );
	}

	/**
	 * Enqueue assets only on the Dramaturgy page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'storyos-dramaturgy' !== sanitize_key( $_GET['page'] ?? '' ) ) {
			return;
		}

		wp_enqueue_style( 'storyos-dramaturgy-tool', STORYOS_PLUGIN_URL . 'assets/css/dramaturgy-tool.css', [], STORYOS_VERSION );
		wp_enqueue_script( 'storyos-dramaturgy-tool', STORYOS_PLUGIN_URL . 'assets/js/dramaturgy-tool.js', [], STORYOS_VERSION, true );
		wp_localize_script( 'storyos-dramaturgy-tool', 'storyosDramaturgyTool', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'storyos_dramaturgy_tool' ),
			'strings' => [
				'running' => __( 'Reading the story through this dramaturgical lens...', 'storyos' ),
				'ready'   => __( 'Dramaturgical reading ready to review.', 'storyos' ),
				'saving'  => __( 'Saving dramaturgical reading...', 'storyos' ),
				'saved'   => __( 'Dramaturgical reading saved.', 'storyos' ),
				'error'   => __( 'Something went wrong. Please try again.', 'storyos' ),
			],
		] );
	}

	/**
	 * Render the dramaturgy page.
	 */
	public static function render_page(): void {
		$sources = [];
		$source_types = [
			'storyos_project' => __( 'Project', 'storyos' ),
			'storyos_episode' => __( 'Episode', 'storyos' ),
			'storyos_scene'   => __( 'Scene', 'storyos' ),
		];
		foreach ( $source_types as $post_type => $label ) {
			$posts = get_posts( [
				'post_type'      => $post_type,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
			foreach ( $posts as $post ) {
				$sources[] = [
					'id'    => $post->ID,
					'label' => $label,
					'title' => $post->post_title ?: __( '(Untitled)', 'storyos' ),
				];
			}
		}
		?>
		<div class="wrap storyos-dramaturgy-page">
			<h1><?php esc_html_e( 'Film Dramaturgy', 'storyos' ); ?></h1>
			<p class="storyos-dramaturgy-intro"><?php esc_html_e( 'Use dramaturgy as a practical, iterative reading of how story, form, time, and audience experience work together on screen.', 'storyos' ); ?></p>
			<div class="storyos-dramaturgy-layout">
				<section class="storyos-dramaturgy-panel storyos-dramaturgy-controls" aria-labelledby="storyos-dramaturgy-controls-title">
					<h2 id="storyos-dramaturgy-controls-title"><?php esc_html_e( 'Dramaturgical reading', 'storyos' ); ?></h2>
					<label for="storyos-dramaturgy-source"><?php esc_html_e( 'Story source', 'storyos' ); ?></label>
					<select id="storyos-dramaturgy-source" class="widefat" <?php disabled( empty( $sources ) ); ?>>
						<?php if ( empty( $sources ) ) : ?>
							<option><?php esc_html_e( 'No story content found', 'storyos' ); ?></option>
						<?php else : ?>
							<?php foreach ( $sources as $source ) : ?>
								<option value="<?php echo esc_attr( $source['id'] ); ?>"><?php echo esc_html( $source['label'] . ': ' . $source['title'] ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<label for="storyos-dramaturgy-lens"><?php esc_html_e( 'Lens', 'storyos' ); ?></label>
					<select id="storyos-dramaturgy-lens" class="widefat">
						<option value="whole_story"><?php esc_html_e( 'Whole story: movement and dramatic question', 'storyos' ); ?></option>
						<option value="character"><?php esc_html_e( 'Character: desire, obstacles, and transformation', 'storyos' ); ?></option>
						<option value="structure"><?php esc_html_e( 'Structure: progression, rhythm, and escalation', 'storyos' ); ?></option>
						<option value="audience"><?php esc_html_e( 'Audience: information, anticipation, and feeling', 'storyos' ); ?></option>
					</select>
					<label for="storyos-dramaturgy-question"><?php esc_html_e( 'Question for the reading', 'storyos' ); ?></label>
					<textarea id="storyos-dramaturgy-question" class="widefat" rows="5" placeholder="<?php esc_attr_e( 'For example: where does the story lose momentum, and what could sharpen the turn?', 'storyos' ); ?>"></textarea>
					<button type="button" id="storyos-run-dramaturgy" class="button button-primary" <?php disabled( empty( $sources ) ); ?>><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'Run dramaturgical reading', 'storyos' ); ?></button>
					<p id="storyos-dramaturgy-status" class="storyos-dramaturgy-status" role="status" aria-live="polite"></p>
					<div class="storyos-dramaturgy-method">
						<strong><?php esc_html_e( 'Method', 'storyos' ); ?></strong>
						<p><?php esc_html_e( 'The reading separates observed evidence from interpretation and suggestions. It does not change canonical Story Graph relationships unless you choose to save the result as an editorial note.', 'storyos' ); ?></p>
					</div>
				</section>
				<section class="storyos-dramaturgy-panel storyos-dramaturgy-result" aria-labelledby="storyos-dramaturgy-result-title">
					<div class="storyos-dramaturgy-result-header">
						<h2 id="storyos-dramaturgy-result-title"><?php esc_html_e( 'Dramaturgical reading', 'storyos' ); ?></h2>
						<button type="button" id="storyos-save-dramaturgy" class="button" disabled><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Save as editorial note', 'storyos' ); ?></button>
					</div>
					<textarea id="storyos-dramaturgy-output" class="widefat" rows="22" placeholder="<?php esc_attr_e( 'Your dramaturgical reading will appear here.', 'storyos' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Review and edit the reading before saving it to the selected source.', 'storyos' ); ?></p>
				</section>
			</div>
		</div>
		<?php
	}

	/** Handle dramaturgical analysis. */
	public static function ajax_run(): void {
		check_ajax_referer( 'storyos_dramaturgy_tool', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to run dramaturgical readings.', 'storyos' ) ], 403 );
		}
		$post_id = absint( $_POST['source_id'] ?? 0 );
		$lens    = sanitize_key( $_POST['lens'] ?? 'whole_story' );
		$question = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) );
		$post    = get_post( $post_id );
		$allowed = [ 'storyos_project', 'storyos_episode', 'storyos_scene' ];
		if ( ! $post || ! in_array( $post->post_type, $allowed, true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Choose a valid story source.', 'storyos' ) ], 400 );
		}

		$lens_instructions = [
			'whole_story' => 'Trace the central dramatic question, the story\'s progression, turning points, stakes, and how each major movement changes the situation.',
			'character'   => 'Trace the protagonist and key characters through desire, opposition, choices, relationships, stakes, and meaningful change. Distinguish stated traits from demonstrated action.',
			'structure'   => 'Examine scene and sequence functions, escalation, reversals, withholding and release of information, rhythm, duration, and places where the dramatic movement accelerates or stalls.',
			'audience'   => 'Examine the audience\'s changing knowledge, expectations, anticipation, surprise, emotional alignment, and the sensory or visual opportunities that shape the experience of time-based film.',
		];
		$instruction = $lens_instructions[ $lens ] ?? $lens_instructions['whole_story'];
		$context     = ( new AI_Context_Builder() )->build_post_context( $post_id );
		$prompt      = sprintf(
			'Create a rigorous but practical film dramaturgical reading of "%s". %s Use only evidence in the Story Graph context; mark uncertainty when evidence is missing. Structure the response with exactly these headings: Dramatic situation, Evidence and movement, Tensions or questions, Practical possibilities. Under Practical possibilities, offer specific revision or research questions rather than prescriptive rewrites. Do not invent scenes, characters, relationships, or events. This is an editorial analysis, not a canonical graph update.',
			$post->post_title,
			$instruction
		);
		if ( $question ) {
			$prompt .= ' The editor\'s question is: ' . $question;
		}
		$result = ( new AI_LLM_Client() )->chat( $prompt, [
			'system_prompt' => 'You are a film dramaturg and researcher. Attend to narrative form, performance, cinematic time, audience experience, and the difference between textual evidence and interpretation. Be concrete, nuanced, and useful to a working editor.',
			'context'       => $context,
			'max_tokens'    => 1300,
			'temperature'   => 0.4,
		] );
		if ( ! empty( $result['error'] ) || empty( $result['content'] ) ) {
			wp_send_json_error( [ 'message' => __( 'The AI service could not complete the dramaturgical reading.', 'storyos' ) ], 502 );
		}
		wp_send_json_success( [ 'analysis' => trim( wp_strip_all_tags( $result['content'] ) ) ] );
	}

	/** Save a dramaturgical reading as an editorial note. */
	public static function ajax_save(): void {
		check_ajax_referer( 'storyos_dramaturgy_tool', 'nonce' );
		$post_id = absint( $_POST['source_id'] ?? 0 );
		$analysis = sanitize_textarea_field( wp_unslash( $_POST['analysis'] ?? '' ) );
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, [ 'storyos_project', 'storyos_episode', 'storyos_scene' ], true ) || ! current_user_can( 'edit_post', $post_id ) || '' === $analysis ) {
			wp_send_json_error( [ 'message' => __( 'Unable to save this dramaturgical reading.', 'storyos' ) ], 400 );
		}
		update_post_meta( $post_id, 'storyos_dramaturgy_analysis', $analysis );
		update_post_meta( $post_id, 'storyos_dramaturgy_analysis_updated', current_time( 'mysql' ) );
		wp_send_json_success( [ 'message' => __( 'Dramaturgical reading saved.', 'storyos' ) ] );
	}
}
