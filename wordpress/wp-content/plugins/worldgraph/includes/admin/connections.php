<?php
/**
 * Provider Connection Management UI.
 *
 * Admin page under World Graph Studio > Connections. Lists all provider connections
 * with status, environment, and quota configuration, and provides:
 *
 * - "Test Connection" per row (validates Comfy Cloud MCP configuration)
 * - "Sync Capabilities" (refreshes Comfy Cloud MCP provider descriptor)
 * - Environment and quota management via the worldgraph_conn CPT meta box
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

use WorldGraph\Utils\Capability_Sync;
use WorldGraph\Utils\Connection_Repository;
use WorldGraph\Utils\Connection_Tester;

/**
 * Connections admin panel.
 */
class Connections {

	/**
	 * Initialize the connections admin UI.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_worldgraph_test_connection', [ __CLASS__, 'handle_test_connection' ] );
		add_action( 'admin_post_worldgraph_sync_capabilities', [ __CLASS__, 'handle_sync_capabilities' ] );
		add_filter( 'redirect_post_location', [ __CLASS__, 'redirect_after_save' ], 10, 2 );
	}

	/**
	 * Send connection add/edit saves back to the Connections page instead of
	 * the native post list, so there is a single Connections view.
	 *
	 * @param string $location Default redirect location.
	 * @param int    $post_id  Saved post ID.
	 * @return string
	 */
	public static function redirect_after_save( string $location, int $post_id ): string {
		if ( Connection_Repository::CPT !== get_post_type( $post_id ) ) {
			return $location;
		}

		return admin_url( 'admin.php?page=worldgraph-connections&connection_id=' . $post_id . '&worldgraph_conns=saved' );
	}

	/**
	 * Add the Connections submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'worldgraph-administration',
			'Connections',
			'Connections',
			'manage_options',
			'worldgraph-connections',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Handle the "Test Connection" admin post action.
	 */
	public static function handle_test_connection(): void {
		self::verify_action( 'worldgraph_test_connection' );

		$connection_id = isset( $_GET['connection_id'] ) ? absint( $_GET['connection_id'] ) : 0;
		$result        = Connection_Tester::test( $connection_id );

		$redirect = add_query_arg(
			[
				'worldgraph_conns' => 'tested',
				'connection_id'       => $connection_id,
				'message'             => rawurlencode( $result['message'] ),
				'success'             => $result['success'] ? '1' : '0',
			],
			admin_url( 'admin.php?page=worldgraph-connections' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle the "Sync Capabilities" admin post action.
	 */
	public static function handle_sync_capabilities(): void {
		self::verify_action( 'worldgraph_sync_capabilities' );

		$result = Capability_Sync::sync();

		$redirect = add_query_arg(
			[
				'worldgraph_conns' => 'synced',
				'message'             => rawurlencode( $result['message'] ),
				'success'             => $result['success'] ? '1' : '0',
			],
			admin_url( 'admin.php?page=worldgraph-connections' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Verify nonce and capability for an admin post action.
	 *
	 * @param string $action Action slug.
	 */
	private static function verify_action( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'worldgraph' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Invalid security nonce.', 'worldgraph' ) );
		}
	}

	/**
	 * Render the connections management page.
	 */
	public static function render_page(): void {
		$connections = Connection_Repository::get_all();
		$capabilities = Capability_Sync::get_cached();
		$provider_types = Capability_Sync::provider_types();

		$notice = '';
		$notice_type = 'success';
		if ( isset( $_GET['worldgraph_conns'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'saved' === $_GET['worldgraph_conns'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$notice = __( 'Connection saved.', 'worldgraph' );
			} else {
				$notice = isset( $_GET['message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$notice_type = ( isset( $_GET['success'] ) && '1' === $_GET['success'] ) ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		?>
		<div class="wrap worldgraph-connections-wrap">
			<h1><?php esc_html_e( 'Provider Connections', 'worldgraph' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Connections bind a provider type to a concrete endpoint, environment, credential reference, and quota configuration. Generation jobs reference connections by ID: {"provider_type": "comfyui", "connection_id": 32}. Raw credentials are never stored here.', 'worldgraph' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<div class="worldgraph-connections-toolbar" style="margin:16px 0;">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=worldgraph_conn' ) ); ?>">
					<?php esc_html_e( 'Add Connection', 'worldgraph' ); ?>
				</a>
			</div>

			<table class="widefat striped" style="max-width:1200px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Name', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Environment', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Status', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Endpoint', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Rate Limits', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Cost Controls', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'worldgraph' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $connections ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No connections yet. Add one to start routing generation jobs.', 'worldgraph' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $connections as $connection ) : ?>
							<?php
							$rate_limits   = self::summarize_json( $connection['rate_limits'] );
							$cost_controls = self::summarize_json( $connection['cost_controls'] );
							$status        = $connection['status'] ?: 'unverified';
							$color         = 'verified' === $status ? '#00a32a' : ( 'error' === $status ? '#d63638' : '#996800' );
							$test_url      = wp_nonce_url(
								add_query_arg(
									[
										'action'        => 'worldgraph_test_connection',
										'connection_id' => $connection['id'],
									],
									admin_url( 'admin-post.php' )
								),
								'worldgraph_test_connection'
							);
							?>
							<tr>
								<td><?php echo esc_html( (string) $connection['id'] ); ?></td>
								<td><a href="<?php echo esc_url( get_edit_post_link( $connection['id'] ) ); ?>"><strong><?php echo esc_html( $connection['connection_name'] ?: $connection['title'] ); ?></strong></a></td>
								<td><?php echo esc_html( $connection['provider_type'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $connection['environment'] ?: '—' ); ?></td>
								<td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;"><?php echo esc_html( $status ); ?></span></td>
								<td><?php echo esc_html( $connection['endpoint_url'] ? wp_parse_url( $connection['endpoint_url'], PHP_URL_HOST ) : '—' ); ?></td>
								<td><?php echo esc_html( $rate_limits ); ?></td>
								<td><?php echo esc_html( $cost_controls ); ?></td>
								<td>
									<a href="<?php echo esc_url( $test_url ); ?>"><?php esc_html_e( 'Test', 'worldgraph' ); ?></a> |
									<a href="<?php echo esc_url( get_edit_post_link( $connection['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'worldgraph' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<div>
			<h2><?php esc_html_e( 'Synced Provider Types', 'worldgraph' ); ?></h2>
			<p>
				<?php
				echo wp_kses_post(
					implode(
						', ',
						array_map(
							static function ( string $type ): string {
								return '<strong>' . esc_html( $type ) . '</strong>';
							},
							$provider_types
						)
					)
				);
				?>
			</p>
			<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=worldgraph_sync_capabilities' ), 'worldgraph_sync_capabilities' ) ); ?>">
					<?php esc_html_e( 'Sync Capabilities', 'worldgraph' ); ?>
				</a>
						</p><p>
				<span class="description" style="margin-left:8px;">
					<?php
					printf(
						/* translators: %s: timestamp */
						esc_html__( 'Capabilities last synced: %s', 'worldgraph' ),
						esc_html( $capabilities['synced_at'] ?: '—' )
					);
					?>
				</span>
						</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Build a short human-readable summary of a JSON quota field.
	 *
	 * @param string $raw Raw JSON meta value.
	 * @return string
	 */
	private static function summarize_json( string $raw ): string {
		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return '—';
		}

		$decoded = json_decode( $trimmed, true );
		if ( ! is_array( $decoded ) ) {
			return '—';
		}

		$parts = [];
		foreach ( $decoded as $key => $value ) {
			$parts[] = $key . '=' . ( is_scalar( $value ) ? (string) $value : '…' );
		}

		return implode( ', ', $parts );
	}
}
