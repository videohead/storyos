<?php
/**
 * Continuity Validation Admin Panel for StoryOS.
 *
 * Provides a dedicated admin page for viewing and managing continuity issues.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Continuity panel class.
 */
class Continuity_Panel {

	/**
	 * Initialize the continuity panel.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_storyos_run_validation', [ __CLASS__, 'ajax_run_validation' ] );
		add_action( 'wp_ajax_storyos_clear_issues', [ __CLASS__, 'ajax_clear_issues' ] );
	}

	/**
	 * Add admin menu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos-analysis',
			'Continuity Validation',
			'Continuity',
			'manage_options',
			'storyos-continuity',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'toplevel_page_storyos' !== $hook && 'storyos_page_storyos-continuity' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'storyos-continuity',
			STORYOS_PLUGIN_URL . 'assets/css/continuity-panel.css',
			[],
			STORYOS_VERSION
		);

		wp_enqueue_script(
			'storyos-continuity',
			STORYOS_PLUGIN_URL . 'assets/js/continuity-panel.js',
			[ 'jquery' ],
			STORYOS_VERSION,
			true
		);

		wp_localize_script(
			'storyos-continuity',
			'storyos_continuity',
			[
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'storyos_validation' ),
				'strings'     => [
					'running'    => 'Running continuity validation...',
					'complete'   => 'Validation complete.',
					'errors'     => 'errors',
					'warnings'   => 'warnings',
					'infos'      => 'info items',
					'clear'      => 'Clear all issues',
					'confirm'    => 'Are you sure you want to clear all continuity issues?',
					'cleared'    => 'Issues cleared.',
					'error'      => 'Error running validation.',
					'no_issues'  => 'No continuity issues found.',
					'filter_all' => 'All',
					'filter_error' => 'Errors',
					'filter_warning' => 'Warnings',
					'filter_info' => 'Info',
				],
			]
		);
	}

	/**
	 * Render the continuity validation admin page.
	 */
	public static function render_page(): void {
		// Handle AJAX clear action.
		if ( isset( $_POST['action'] ) && 'storyos_clear_issues' === $_POST['action'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'storyos_validation' );
			\StoryOS\Utils\clear_global_continuity_issues();
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'All continuity issues have been cleared.', 'storyos' ); ?></p>
			</div>
			<?php
		}

		// Get stored issues.
		$issues     = \StoryOS\Utils\get_global_continuity_issues();
		$summary    = self::compute_summary( $issues );

		// Filter by severity if requested.
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
		if ( in_array( $filter, [ 'error', 'warning', 'info' ], true ) ) {
			$filtered_issues = \StoryOS\Utils\filter_issues_by_severity( $issues, $filter );
		} else {
			$filtered_issues = $issues;
		}

		// Group by category.
		$by_category = self::group_by_category( $filtered_issues );

		?>
		<div class="wrap storyos-continuity-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Continuity Validation', 'storyos' ); ?></h1>

			<!-- Action buttons -->
			<div class="storyos-actions">
				<button type="button" id="storyos-run-validation" class="button button-primary">
					<span class="dashicons dashicons-refresh" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Run Validation', 'storyos' ); ?>
				</button>
				<?php if ( ! empty( $issues ) ) : ?>
					<button type="button" id="storyos-clear-all" class="button" style="margin-left: 10px;">
						<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Clear All Issues', 'storyos' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<!-- Summary cards -->
			<div class="storyos-summary" id="storyos-summary">
				<div class="storyos-summary-card storyos-card-errors">
					<div class="storyos-summary-number"><?php echo esc_html( $summary['errors'] ); ?></div>
					<div class="storyos-summary-label"><?php esc_html_e( 'Errors', 'storyos' ); ?></div>
				</div>
				<div class="storyos-summary-card storyos-card-warnings">
					<div class="storyos-summary-number"><?php echo esc_html( $summary['warnings'] ); ?></div>
					<div class="storyos-summary-label"><?php esc_html_e( 'Warnings', 'storyos' ); ?></div>
				</div>
				<div class="storyos-summary-card storyos-card-infos">
					<div class="storyos-summary-number"><?php echo esc_html( $summary['infos'] ); ?></div>
					<div class="storyos-summary-label"><?php esc_html_e( 'Info', 'storyos' ); ?></div>
				</div>
				<div class="storyos-summary-card storyos-card-total">
					<div class="storyos-summary-number"><?php echo esc_html( $summary['total'] ); ?></div>
					<div class="storyos-summary-label"><?php esc_html_e( 'Total Issues', 'storyos' ); ?></div>
				</div>
			</div>

			<!-- Filter tabs -->
			<div class="storyos-filter-tabs">
				<a href="?page=storyos-continuity&filter=all" class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'All', 'storyos' ); ?> (<?php echo esc_html( count( $filtered_issues ) ); ?>)
				</a>
				<a href="?page=storyos-continuity&filter=error" class="button <?php echo 'error' === $filter ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Errors', 'storyos' ); ?> (<?php echo esc_html( $summary['errors'] ); ?>)
				</a>
				<a href="?page=storyos-continuity&filter=warning" class="button <?php echo 'warning' === $filter ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Warnings', 'storyos' ); ?> (<?php echo esc_html( $summary['warnings'] ); ?>)
				</a>
				<a href="?page=storyos-continuity&filter=info" class="button <?php echo 'info' === $filter ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Info', 'storyos' ); ?> (<?php echo esc_html( $summary['infos'] ); ?>)
				</a>
			</div>

			<!-- Loading indicator -->
			<div class="storyos-loading" id="storyos-loading" style="display: none;">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Running continuity validation...', 'storyos' ); ?>
			</div>

			<!-- Issues list -->
			<?php if ( empty( $filtered_issues ) ) : ?>
				<div class="storyos-no-issues">
					<span class="dashicons dashicons-yes-alt"></span>
					<p><?php esc_html_e( 'No continuity issues found. Run validation to check your content.', 'storyos' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $by_category as $category => $category_issues ) : ?>
					<div class="storyos-category-section">
						<h2><?php echo esc_html( \StoryOS\Utils\category_label( $category ) ); ?></h2>
						<?php foreach ( $category_issues as $issue ) : ?>
							<div class="storyos-issue-card storyos-issue-<?php echo esc_attr( $issue['severity'] ?? 'warning' ); ?>">
								<div class="storyos-issue-header">
									<span class="storyos-issue-severity" style="background-color: <?php echo esc_attr( \StoryOS\Utils\severity_info( $issue['severity'] ?? 'warning' )['color'] ); ?>">
										<?php echo esc_html( \StoryOS\Utils\severity_info( $issue['severity'] ?? 'warning' )['label'] ); ?>
									</span>
									<span class="storyos-issue-category"><?php echo esc_html( \StoryOS\Utils\category_label( $issue['category'] ?? 'general' ) ); ?></span>
								</div>
								<div class="storyos-issue-description"><?php echo esc_html( $issue['description'] ?? '' ); ?></div>
								<?php if ( ! empty( $issue['entities'] ) && is_array( $issue['entities'] ) ) : ?>
									<div class="storyos-issue-entities">
										<?php foreach ( $issue['entities'] as $entity ) : ?>
											<span class="storyos-entity-tag">
												<?php echo esc_html( ucfirst( $entity['type'] ?? '' ) ); ?>
												<?php echo esc_html( $entity['id'] ? '#' . $entity['id'] : '' ); ?>
											</span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $issue['suggestion'] ) ) : ?>
									<div class="storyos-issue-suggestion">
										<strong><?php esc_html_e( 'Suggestion:', 'storyos' ); ?></strong>
										<?php echo esc_html( $issue['suggestion'] ); ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Compute summary counts from issues.
	 *
	 * @param array $issues The issues array.
	 * @return array
	 */
	private static function compute_summary( array $issues ): array {
		$errors   = 0;
		$warnings = 0;
		$infos    = 0;

		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ?? '' ) {
				case 'error':
					$errors++;
					break;
				case 'warning':
					$warnings++;
					break;
				case 'info':
					$infos++;
					break;
			}
		}

		return [
			'total'    => count( $issues ),
			'errors'   => $errors,
			'warnings' => $warnings,
			'infos'    => $infos,
		];
	}

	/**
	 * Group issues by category.
	 *
	 * @param array $issues The issues array.
	 * @return array
	 */
	private static function group_by_category( array $issues ): array {
		$grouped = [];
		foreach ( $issues as $issue ) {
			$category = $issue['category'] ?? 'general';
			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = [];
			}
			$grouped[ $category ][] = $issue;
		}
		return $grouped;
	}

	/**
	 * AJAX handler for running validation.
	 */
	public static function ajax_run_validation(): void {
		check_ajax_referer( 'storyos_validation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		$scene_ids  = isset( $_POST['scene_ids'] ) ? array_map( 'absint', $_POST['scene_ids'] ) : [];

		$result = \StoryOS\Utils\fetch_continuity_validation( $episode_id, $scene_ids );

		if ( ! empty( $result['error'] ) ) {
			wp_send_json_error( 'Validation error: ' . $result['error'], 500 );
		}

		// Store issues.
		\StoryOS\Utils\store_global_continuity_issues( $result['issues'] );

		wp_send_json_success( [
			'summary'        => [
				'total'    => $result['total_issues'],
				'errors'   => $result['errors'],
				'warnings' => $result['warnings'],
				'infos'    => $result['infos'],
			],
			'issues'         => $result['issues'],
			'scenes_validated' => $result['scenes_validated'],
		] );
	}

	/**
	 * AJAX handler for clearing issues.
	 */
	public static function ajax_clear_issues(): void {
		check_ajax_referer( 'storyos_validation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}

		\StoryOS\Utils\clear_global_continuity_issues();
		wp_send_json_success( 'Issues cleared.' );
	}
}
