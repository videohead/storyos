<?php
/**
 * Plugin Name: World Graph Studio - Headless Revalidation
 * Plugin URI: https://github.com/videohead/worldgraph
 * Description: Notifies an optional headless Next.js frontend (see /headless, based on 9d8dev/next-wp) to revalidate its cache when content changes.
 * Version: 1.0.0
 * Author: World Graph Studio Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: worldgraph-headless
 * Requires Plugins: worldgraph
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package WorldGraphHeadless
 */

namespace WorldGraphHeadless;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WORLDGRAPH_HEADLESS_VERSION', '1.0.0' );
define( 'WORLDGRAPH_HEADLESS_OPTION', 'worldgraph_headless_settings' );

/**
 * Whether headless revalidation is enabled (requires both a site URL and secret).
 *
 * @return bool
 */
function is_enabled(): bool {
	$settings = get_settings();
	return ! empty( $settings['next_url'] ) && ! empty( $settings['webhook_secret'] );
}

/**
 * Stored settings with defaults.
 *
 * @return array{next_url: string, webhook_secret: string, notifications: bool}
 */
function get_settings(): array {
	$defaults = [
		'next_url'       => '',
		'webhook_secret' => '',
		'notifications'  => false,
	];

	return wp_parse_args( get_option( WORLDGRAPH_HEADLESS_OPTION, [] ), $defaults );
}

/**
 * Initialize the module.
 */
function init(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page' );
	add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );

	if ( ! is_enabled() ) {
		return;
	}

	add_action( 'transition_post_status', __NAMESPACE__ . '\\on_post_status_transition', 10, 3 );
	add_action( 'delete_post', __NAMESPACE__ . '\\on_post_deleted' );
	add_action( 'created_category', __NAMESPACE__ . '\\on_category_changed' );
	add_action( 'edited_category', __NAMESPACE__ . '\\on_category_changed' );
	add_action( 'delete_category', __NAMESPACE__ . '\\on_category_changed' );
	add_action( 'created_post_tag', __NAMESPACE__ . '\\on_tag_changed' );
	add_action( 'edited_post_tag', __NAMESPACE__ . '\\on_tag_changed' );
	add_action( 'delete_post_tag', __NAMESPACE__ . '\\on_tag_changed' );
}

/**
 * Register the "Settings > Headless Revalidation" admin page.
 */
function add_settings_page(): void {
	add_options_page(
		__( 'Headless Revalidation', 'worldgraph-headless' ),
		__( 'Headless Revalidation', 'worldgraph-headless' ),
		'manage_options',
		'worldgraph-headless',
		__NAMESPACE__ . '\\render_settings_page'
	);
}

/**
 * Register the settings field with the Settings API.
 */
function register_settings(): void {
	register_setting(
		'worldgraph_headless',
		WORLDGRAPH_HEADLESS_OPTION,
		[ 'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings' ]
	);
}

/**
 * Sanitize submitted settings.
 *
 * @param array<string, mixed> $input Raw submitted values.
 * @return array<string, mixed>
 */
function sanitize_settings( array $input ): array {
	return [
		'next_url'       => isset( $input['next_url'] ) ? untrailingslashit( esc_url_raw( $input['next_url'] ) ) : '',
		'webhook_secret' => isset( $input['webhook_secret'] ) ? sanitize_text_field( $input['webhook_secret'] ) : '',
		'notifications'  => ! empty( $input['notifications'] ),
	];
}

/**
 * Render the settings page markup.
 */
function render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Headless Revalidation', 'worldgraph-headless' ); ?></h1>
		<p>
			<?php esc_html_e( 'Configure the optional headless Next.js frontend (see the /headless directory, based on 9d8dev/next-wp) so WordPress can tell it to refresh its cache.', 'worldgraph-headless' ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'worldgraph_headless' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="worldgraph_headless_next_url"><?php esc_html_e( 'Next.js Site URL', 'worldgraph-headless' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="worldgraph_headless_next_url"
							name="<?php echo esc_attr( WORLDGRAPH_HEADLESS_OPTION ); ?>[next_url]"
							value="<?php echo esc_attr( $settings['next_url'] ); ?>"
							class="regular-text"
							placeholder="https://your-nextjs-site.com"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="worldgraph_headless_secret"><?php esc_html_e( 'Webhook Secret', 'worldgraph-headless' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="worldgraph_headless_secret"
							name="<?php echo esc_attr( WORLDGRAPH_HEADLESS_OPTION ); ?>[webhook_secret]"
							value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>"
							class="regular-text"
						/>
						<p class="description">
							<?php esc_html_e( 'Must match WORDPRESS_WEBHOOK_SECRET in the headless app\'s .env.local.', 'worldgraph-headless' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Admin Notifications', 'worldgraph-headless' ); ?></th>
					<td>
						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( WORLDGRAPH_HEADLESS_OPTION ); ?>[notifications]"
								value="1"
								<?php checked( $settings['notifications'] ); ?>
							/>
							<?php esc_html_e( 'Show an admin notice if a revalidation request fails.', 'worldgraph-headless' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Send the revalidation webhook to the headless frontend.
 *
 * @param string          $content_type Content type slug, e.g. "posts", "pages", "categories", "tags".
 * @param int|string|null $content_id   Numeric ID or slug of the affected content.
 * @param string|null     $slug         Optional slug, sent alongside the ID.
 */
function send_webhook( string $content_type, $content_id = null, ?string $slug = null ): void {
	$settings = get_settings();

	if ( empty( $settings['next_url'] ) || empty( $settings['webhook_secret'] ) ) {
		return;
	}

	$response = wp_remote_post(
		untrailingslashit( $settings['next_url'] ) . '/api/revalidate',
		[
			'timeout' => 10,
			'headers' => [
				'Content-Type'      => 'application/json',
				'X-Webhook-Secret'  => $settings['webhook_secret'],
			],
			'body'    => wp_json_encode(
				[
					'contentType' => $content_type,
					'contentId'   => $content_id,
					'slug'        => $slug,
				]
			),
		]
	);

	if ( ! is_wp_error( $response ) && ! $settings['notifications'] ) {
		return;
	}

	if ( is_wp_error( $response ) ) {
		error_log( '[worldgraph-headless] Revalidation webhook failed: ' . $response->get_error_message() );
		return;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status >= 400 ) {
		error_log( '[worldgraph-headless] Revalidation webhook returned HTTP ' . $status );
	}
}

/**
 * Fire on post/page publish, update, or unpublish transitions.
 *
 * @param string    $new_status New post status.
 * @param string    $old_status Previous post status.
 * @param \WP_Post  $post       Post object.
 */
function on_post_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
		return;
	}

	if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
		return;
	}

	$content_type = 'page' === $post->post_type ? 'pages' : 'posts';
	send_webhook( $content_type, $post->ID, $post->post_name );
}

/**
 * Fire when a post/page is deleted outright.
 *
 * @param int $post_id Deleted post ID.
 */
function on_post_deleted( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
		return;
	}

	$content_type = 'page' === $post->post_type ? 'pages' : 'posts';
	send_webhook( $content_type, $post_id, $post->post_name );
}

/**
 * Fire on category create/update/delete.
 *
 * @param int $term_id Term ID.
 */
function on_category_changed( int $term_id ): void {
	send_webhook( 'categories', $term_id );
}

/**
 * Fire on tag create/update/delete.
 *
 * @param int $term_id Term ID.
 */
function on_tag_changed( int $term_id ): void {
	send_webhook( 'tags', $term_id );
}

if ( did_action( 'plugins_loaded' ) ) {
	init();
} else {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );
}
