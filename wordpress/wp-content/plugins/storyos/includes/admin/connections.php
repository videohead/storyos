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
		add_filter( 'manage_storyos_connection_posts_columns', [ __CLASS__, 'list_columns' ] );
		add_action( 'manage_storyos_connection_posts_custom_column', [ __CLASS__, 'render_list_column' ], 10, 2 );
		add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
	}

	/**
	 * Add the Connections submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
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
	 * Custom columns for the connections list table.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function list_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['provider_type']   = __( 'Provider', 'storyos' );
				$new['environment']     = __( 'Environment', 'storyos' );
				$new['connection_status' ] = __( 'Status', 'storyos' );
				$new['endpoint_url']    = __( 'Endpoint', 'storyos' );
				$new['last_validated']  = __( 'Last Validated', 'storyos' );
			}
		}

		return $new;
	}

	/**
	 * Render a custom column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_list_column( string $column, int $post_id ): void {
		$value = get_post_meta( $post_id, $column, true );

		switch ( $column ) {
			case 'connection_status':
				$status = $value ?: 'unverified';
				$color  = 'verified' === $status ? '#00a32a' : ( 'error' === $status ? '#d63638' : '#996800' );
				printf( '<span style="color:%s;font-weight:600;">%s</span>', esc_attr( $color ), esc_html( $status ) );
				break;

			case 'endpoint_url':
				echo esc_html( $value ? wp_parse_url( $value, PHP_URL_HOST ) : '—' );
				break;

			case 'last_validated':
				echo esc_html( $value ? $value : '—' );
				break;

			default:
				echo esc_html( $value ? $value : '—' );
		}
	}

	/**
	 * Add a "Test" row action on the connections list table.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( 'storyos_connection' !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$test_url = wp_nonce_url(
			add_query_arg(
				[
					'action'        => 'storyos_test_connection',
					'connection_id' => $post->ID,
				],
				admin_url( 'admin-post.php' )
			),
			'storyos_test_connection'
		);

		$actions['storyos_test_connection'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $test_url ),
			esc_html__( 'Test Connection', 'storyos' )
		);

		return $actions;
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
			$notice = isset( $_GET['message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_type = ( isset( $_GET['success'] ) && '1' === $_GET['success'] ) ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=storyos_sync_capabilities' ), 'storyos_sync_capabilities' ) ); ?>">
					<?php esc_html_e( 'Sync Capabilities', 'storyos' ); ?>
				</a>
				<span class="description" style="margin-left:8px;">
					<?php
					printf(
						/* translators: %s: timestamp */
						esc_html__( 'Capabilities last synced: %s', 'storyos' ),
						esc_html( $capabilities['synced_at'] ?: '—' )
					);
					?>
				</span>
			</div>

			<table class="widefat striped" style="max-width:1200px;">
				<thead>
					<tr>
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
								<td><a href="<?php echo esc_url( get_edit_post_link( $connection['id'] ) ); ?>"><strong><?php echo esc_html( $connection['connection_name'] ?: $connection['title'] ); ?></strong></a><br><span class="description">#<?php echo esc_html( (string) $connection['id'] ); ?></span></td>
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

			<h2><?php esc_html_e( 'Known Provider Types', 'storyos' ); ?></h2>
			<p>
				<?php
				echo wp_kses_post(
					implode(
						', ',
						array_map(
							static function ( string $type ): string {
								return '<code>' . esc_html( $type ) . '</code>';
							},
							$provider_types
						)
					)
				);
				?>
			</p>
			<p class="description">
					<?php esc_html_e( 'Provider capabilities are cached from Comfy Cloud MCP. Use "Sync Capabilities" to refresh provider descriptors.', 'storyos' ); ?>
			</p>
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
