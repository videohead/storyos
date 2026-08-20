<?php
/**
 * Provider Connection Management UI.
 *
 * Admin page under StoryOS > Connections. Lists all provider connections
 * with status, environment, and quota configuration, and provides:
 *
 * - "Test Connection" per row (validates Comfy Cloud MCP configuration)
 * - "Sync Capabilities" (refreshes Comfy Cloud MCP provider descriptor)
 * - Environment and quota management via the storyos_connection CPT meta box
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

use StoryOS\Utils\Capability_Sync;
use StoryOS\Utils\Connection_Repository;
use StoryOS\Utils\Connection_Tester;

/**
 * Connections admin panel.
 */
class Connections {

	/**
	 * Initialize the connections admin UI.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_storyos_test_connection', [ __CLASS__, 'handle_test_connection' ] );
		add_action( 'admin_post_storyos_sync_capabilities', [ __CLASS__, 'handle_sync_capabilities' ] );
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

		return admin_url( 'admin.php?page=storyos-connections&connection_id=' . $post_id . '&storyos_connections=saved' );
	}

	/**
	 * Add the Connections submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos-administration',
			'Connections',
			'Connections',
			'manage_options',
			'storyos-connections',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Handle the "Test Connection" admin post action.
	 */
	public static function handle_test_connection(): void {
		self::verify_action( 'storyos_test_connection' );

		$connection_id = isset( $_GET['connection_id'] ) ? absint( $_GET['connection_id'] ) : 0;
		$result        = Connection_Tester::test( $connection_id );

		$redirect = add_query_arg(
			[
				'storyos_connections' => 'tested',
				'connection_id'       => $connection_id,
				'message'             => rawurlencode( $result['message'] ),
				'success'             => $result['success'] ? '1' : '0',
			],
			admin_url( 'admin.php?page=storyos-connections' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle the "Sync Capabilities" admin post action.
	 */
	public static function handle_sync_capabilities(): void {
		self::verify_action( 'storyos_sync_capabilities' );

		$result = Capability_Sync::sync();

		$redirect = add_query_arg(
			[
				'storyos_connections' => 'synced',
				'message'             => rawurlencode( $result['message'] ),
				'success'             => $result['success'] ? '1' : '0',
			],
			admin_url( 'admin.php?page=storyos-connections' )
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
			wp_die( esc_html__( 'Insufficient permissions.', 'storyos' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Invalid security nonce.', 'storyos' ) );
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
		if ( isset( $_GET['storyos_connections'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'saved' === $_GET['storyos_connections'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$notice = __( 'Connection saved.', 'storyos' );
			} else {
				$notice = isset( $_GET['message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$notice_type = ( isset( $_GET['success'] ) && '1' === $_GET['success'] ) ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		?>
		<div class="wrap storyos-connections-wrap">
			<h1><?php esc_html_e( 'Provider Connections', 'storyos' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Connections bind a provider type to a concrete endpoint, environment, credential reference, and quota configuration. Generation jobs reference connections by ID: {"provider_type": "comfyui", "connection_id": 32}. Raw credentials are never stored here.', 'storyos' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<div class="storyos-connections-toolbar" style="margin:16px 0;">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=storyos_connection' ) ); ?>">
					<?php esc_html_e( 'Add Connection', 'storyos' ); ?>
				</a>
			</div>

			<table class="widefat striped" style="max-width:1200px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Name', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Environment', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Status', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Endpoint', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Rate Limits', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Cost Controls', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'storyos' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $connections ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No connections yet. Add one to start routing generation jobs.', 'storyos' ); ?></td></tr>
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
										'action'        => 'storyos_test_connection',
										'connection_id' => $connection['id'],
									],
									admin_url( 'admin-post.php' )
								),
								'storyos_test_connection'
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
									<a href="<?php echo esc_url( $test_url ); ?>"><?php esc_html_e( 'Test', 'storyos' ); ?></a> |
									<a href="<?php echo esc_url( get_edit_post_link( $connection['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'storyos' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<div>
			<h2><?php esc_html_e( 'Synced Provider Types', 'storyos' ); ?></h2>
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
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=storyos_sync_capabilities' ), 'storyos_sync_capabilities' ) ); ?>">
					<?php esc_html_e( 'Sync Capabilities', 'storyos' ); ?>
				</a>
						</p><p>
				<span class="description" style="margin-left:8px;">
					<?php
					printf(
						/* translators: %s: timestamp */
						esc_html__( 'Capabilities last synced: %s', 'storyos' ),
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
