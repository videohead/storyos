<?php
/**
 * StoryOS Export admin page.
 *
 * Provides a UI for exporting live StoryOS projects to Markdown screenplay format.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export admin page class.
 */
class Export {

	/**
	 * Initialize the export admin page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_storyos_export_markdown', [ __CLASS__, 'handle_export' ] );
	}

	/**
	 * Add the Export submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
			'Export',
			'Export',
			'manage_options',
			'storyos-export',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Export a StoryOS project as markdown.
	 */
	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to export StoryOS data.' );
		}
		check_admin_referer( 'storyos_export_markdown' );

		$project_id = isset( $_POST['storyos_project_id'] ) ? absint( $_POST['storyos_project_id'] ) : 0;
		if ( ! $project_id ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'storyos-export', 'error' => 'empty' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$exporter = new \StoryOS\Exporter\StoryOS_Exporter();
		$markdown = $exporter->export_project_markdown( $project_id );
		$filename = sanitize_file_name( get_the_title( $project_id ) ?: 'storyos-export' ) . '.md';

		nocache_headers();
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $markdown ) );
		echo $markdown;
		exit;
	}

	/**
	 * Render the export page.
	 */
	public static function render_page(): void {
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$projects = get_posts( [
			'post_type'      => 'storyos_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		?>
		<div class="wrap storyos-export-wrap">
			<h1><?php esc_html_e( 'Export StoryOS Script', 'storyos' ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e( 'Export failed:', 'storyos' ); ?></strong> <?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Export the current StoryOS project as a Markdown screenplay file based on the live project data and scene records.', 'storyos' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="storyos_export_markdown" />
				<?php wp_nonce_field( 'storyos_export_markdown' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="storyos_project_id"><?php esc_html_e( 'Project', 'storyos' ); ?></label></th>
						<td>
							<select name="storyos_project_id" id="storyos_project_id" class="regular-text">
								<option value=""><?php esc_html_e( 'Select a project', 'storyos' ); ?></option>
								<?php foreach ( $projects as $project ) : ?>
									<option value="<?php echo esc_attr( (string) $project->ID ); ?>"><?php echo esc_html( $project->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Export Markdown Script', 'storyos' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Example Output', 'storyos' ); ?></h2>
			<p>
				<?php esc_html_e( 'The example screenplay export is available at:', 'storyos' ); ?>
				<code>about/example-workflow/Little-Red-Riding-Hood-Screenplay-Example-Export.md</code>
			</p>
		</div>
		<?php
	}
}
