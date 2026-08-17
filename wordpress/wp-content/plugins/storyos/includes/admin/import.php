<?php
/**
 * StoryOS Import admin page.
 *
 * Provides a UI for importing StoryOS JSON documents (e.g. the example workflow).
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import admin page class.
 */
class Import {

	/**
	 * Initialize the import admin page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'admin_post_storyos_import_json', [ __CLASS__, 'handle_import' ] );
	}

	/**
	 * Add the Import submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
			'Import',
			'Import',
			'manage_options',
			'storyos-import',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin scripts on the import page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'storyos_page_storyos-import' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'storyos-import',
			STORYOS_PLUGIN_URL . 'assets/js/import.js',
			[ 'jquery' ],
			STORYOS_VERSION,
			true
		);

		wp_localize_script( 'storyos-import', 'storyosImport', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'storyos_import' ),
		] );
	}

	/**
	 * Handle the import form submission.
	 */
	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to import StoryOS data.' );
		}
		check_admin_referer( 'storyos_import' );

		$json = isset( $_POST['storyos_json'] ) ? wp_unslash( $_POST['storyos_json'] ) : '';
		$overwrite = ! empty( $_POST['storyos_overwrite'] );

		if ( empty( $json ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-import', 'error' => 'empty' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$importer = new \StoryOS\Importer\StoryOS_Importer();
		$result   = $importer->import( $json, [ 'overwrite' => $overwrite ] );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-import', 'error' => rawurlencode( $result->get_error_message() ) ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Store the report in a transient for display after redirect.
		set_transient( 'storyos_import_report', $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-import', 'imported' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the import page.
	 */
	public static function render_page(): void {
		$report = get_transient( 'storyos_import_report' );
		delete_transient( 'storyos_import_report' );

		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$imported = isset( $_GET['imported'] ) ? '1' === $_GET['imported'] : false;
		?>
		<div class="wrap storyos-import-wrap">
			<h1><?php esc_html_e( 'Import StoryOS JSON', 'storyos' ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e( 'Import failed:', 'storyos' ); ?></strong> <?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $imported && is_array( $report ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Import completed successfully.', 'storyos' ); ?></strong></p>
				</div>
				<?php self::render_report( $report ); ?>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Paste a StoryOS JSON document (e.g. the Little Red Riding Hood example) to create a complete miniature StoryOS project: Project, World, Characters, Locations, Props, Scenes, Shots, Storyboard Frames, and Sequence.', 'storyos' ); ?>
			</p>

			<form method="post" id="storyos-import-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="storyos_import_json" />
				<?php wp_nonce_field( 'storyos_import' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="storyos_json"><?php esc_html_e( 'StoryOS JSON', 'storyos' ); ?></label></th>
						<td>
							<textarea name="storyos_json" id="storyos_json" rows="20" class="large-text code" placeholder='{"storyos_version": "1.0", "project": {...}, ...}'></textarea>
							<p class="description"><?php esc_html_e( 'Paste the full StoryOS JSON document here.', 'storyos' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'storyos' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="storyos_overwrite" value="1" />
								<?php esc_html_e( 'Overwrite existing entities with the same external ID', 'storyos' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Import StoryOS JSON', 'storyos' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Example', 'storyos' ); ?></h2>
			<p>
				<?php esc_html_e( 'The example workflow JSON is available at:', 'storyos' ); ?>
				<code>about/example-workflow/little-red-riding-hood.storyos.json</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the import report.
	 *
	 * @param array $report Import report.
	 */
	private static function render_report( array $report ): void {
		$totals = $report['totals'] ?? [];
		?>
		<h2><?php esc_html_e( 'Import Report', 'storyos' ); ?></h2>

		<h3><?php esc_html_e( 'Created Entities', 'storyos' ); ?></h3>
		<table class="widefat striped" style="max-width:600px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'CPT', 'storyos' ); ?></th>
					<th><?php esc_html_e( 'Count', 'storyos' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $totals as $cpt => $count ) : ?>
					<tr>
						<td><?php echo esc_html( $cpt ); ?></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $report['created'] ) ) : ?>
			<h3><?php esc_html_e( 'Created', 'storyos' ); ?></h3>
			<ul>
				<?php foreach ( $report['created'] as $entry ) : ?>
					<li><?php echo esc_html( $entry ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $report['updated'] ) ) : ?>
			<h3><?php esc_html_e( 'Updated', 'storyos' ); ?></h3>
			<ul>
				<?php foreach ( $report['updated'] as $entry ) : ?>
					<li><?php echo esc_html( $entry ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $report['skipped'] ) ) : ?>
			<h3><?php esc_html_e( 'Skipped', 'storyos' ); ?></h3>
			<ul>
				<?php foreach ( $report['skipped'] as $entry ) : ?>
					<li><?php echo esc_html( $entry ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $report['errors'] ) ) : ?>
			<h3><?php esc_html_e( 'Errors', 'storyos' ); ?></h3>
			<ul>
				<?php foreach ( $report['errors'] as $entry ) : ?>
					<li><?php echo esc_html( $entry ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $report['sequence'] ) ) : ?>
			<h3><?php esc_html_e( 'Sequence', 'storyos' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %1$s: sequence title, %2$d: scene count */
					esc_html__( 'Sequence "%1$s" created with %2$d scenes in order.', 'storyos' ),
					esc_html( $report['sequence']['title'] ),
					absint( $report['sequence']['order'] )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}
}