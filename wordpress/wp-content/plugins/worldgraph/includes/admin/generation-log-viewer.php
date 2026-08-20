<?php
/**
 * ComfyUI MCP / local ComfyUI generation log viewer.
 *
 * Admin page under World Graph Studio > Generation Log for troubleshooting generation
 * jobs before the WP-Cron batch returns a result.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

use WorldGraph\Utils\Generation_Batch;
use WorldGraph\Utils\Generation_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Log_Viewer {

	/**
	 * Initialize the log viewer admin UI.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_worldgraph_clear_generation_log', [ __CLASS__, 'handle_clear_log' ] );
		add_action( 'admin_post_worldgraph_run_generation_batch', [ __CLASS__, 'handle_run_batch' ] );
	}

	/**
	 * Add the Generation Log submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'worldgraph-administration',
			'Generation Log',
			'Generation Log',
			'manage_options',
			'worldgraph-generation-log',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Handle the "Clear Log" admin post action.
	 */
	public static function handle_clear_log(): void {
		self::verify_action( 'worldgraph_clear_generation_log' );
		Generation_Log::clear();
		wp_safe_redirect( admin_url( 'admin.php?page=worldgraph-generation-log&worldgraph_log=cleared' ) );
		exit;
	}

	/**
	 * Handle the "Run Batch Now" admin post action, so a stuck job doesn't
	 * require waiting for the next WP-Cron tick to see fresh log output.
	 */
	public static function handle_run_batch(): void {
		self::verify_action( 'worldgraph_run_generation_batch' );
		Generation_Batch::process();
		wp_safe_redirect( admin_url( 'admin.php?page=worldgraph-generation-log&worldgraph_log=ran' ) );
		exit;
	}

	/**
	 * Verify a nonce for an admin-post action.
	 *
	 * @param string $action Nonce action name.
	 */
	private static function verify_action( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $action ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'worldgraph' ) );
		}
	}

	/**
	 * Render the generation log page.
	 */
	public static function render_page(): void {
		$entries = array_reverse( Generation_Log::all() );

		$notice = '';
		if ( isset( $_GET['worldgraph_log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = 'cleared' === $_GET['worldgraph_log'] ? __( 'Log cleared.', 'worldgraph' ) : __( 'Batch run triggered.', 'worldgraph' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		?>
		<div class="wrap worldgraph-generation-log-wrap">
			<h1><?php esc_html_e( 'ComfyUI MCP Generation Log', 'worldgraph' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Recent requests and responses to and from the ComfyUI / Comfy Cloud MCP provider and the WP-Cron generation batch, newest first. Use this to troubleshoot a job before WP-Cron returns a result.', 'worldgraph' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<div class="worldgraph-generation-log-toolbar" style="margin:16px 0;">
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=worldgraph_run_generation_batch' ), 'worldgraph_run_generation_batch' ) ); ?>">
					<?php esc_html_e( 'Run Batch Now', 'worldgraph' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=worldgraph_clear_generation_log' ), 'worldgraph_clear_generation_log' ) ); ?>">
					<?php esc_html_e( 'Clear Log', 'worldgraph' ); ?>
				</a>
			</div>

			<table class="widefat striped" style="max-width:1400px;">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e( 'Time', 'worldgraph' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'Level', 'worldgraph' ); ?></th>
						<th style="width:140px;"><?php esc_html_e( 'Source', 'worldgraph' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Job ID', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Message', 'worldgraph' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No log entries yet.', 'worldgraph' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['level'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['source'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['job_id'] ?? '' ) ); ?></td>
								<td>
									<?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?>
									<?php if ( ! empty( $entry['context'] ) ) : ?>
										<details>
											<summary><?php esc_html_e( 'Context', 'worldgraph' ); ?></summary>
											<pre style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html( wp_json_encode( $entry['context'], JSON_PRETTY_PRINT ) ); ?></pre>
										</details>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
