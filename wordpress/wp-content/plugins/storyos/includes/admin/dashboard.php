<?php
/**
 * Admin Dashboard for StoryOS.
 *
 * Registers admin pages and renders the StoryOS dashboard.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Dashboard class.
 */
class Dashboard {

	/**
	 * Initialize the dashboard.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu(): void {
		add_menu_page(
			'StoryOS',
			'StoryOS',
			'manage_options',
			'storyos',
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-video-alt3',
			30
		);

		add_submenu_page(
			'storyos',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'storyos',
			[ __CLASS__, 'render_dashboard' ]
		);

		add_submenu_page(
			'storyos',
			'Projects',
			'Projects',
			'manage_options',
			'edit.php?post_type=storyos_project'
		);

		add_submenu_page(
			'storyos',
			'Story World',
			'Story World',
			'manage_options',
			'edit.php?post_type=storyos_story-world'
		);

		add_submenu_page(
			'storyos',
			'Characters',
			'Characters',
			'manage_options',
			'edit.php?post_type=storyos_character'
		);

		add_submenu_page(
			'storyos',
			'Scenes',
			'Scenes',
			'manage_options',
			'edit.php?post_type=storyos_scene'
		);

		add_submenu_page(
			'storyos',
			'Shots',
			'Shots',
			'manage_options',
			'edit.php?post_type=storyos_shot'
		);

		add_submenu_page(
			'storyos',
			'Assets',
			'Assets',
			'manage_options',
			'edit.php?post_type=storyos_asset'
		);

		add_submenu_page(
			'storyos',
			'Episodes',
			'Episodes',
			'manage_options',
			'edit.php?post_type=storyos_episode'
		);

		add_submenu_page(
			'storyos',
			'Editorial',
			'Editorial',
			'manage_options',
			'edit.php?post_type=storyos_editorial_artifact'
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, 'storyos' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'storyos-admin',
			STORYOS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			STORYOS_VERSION
		);

		wp_enqueue_script(
			'storyos-admin',
			STORYOS_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			STORYOS_VERSION,
			true
		);

		wp_localize_script( 'storyos-admin', 'storyosAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'storyos_admin' ),
		] );
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render_dashboard(): void {
		?>
		<div class="wrap storyos-dashboard">
			<h1>StoryOS Dashboard</h1>
			
			<div class="storyos-stats">
				<?php self::render_stat_cards(); ?>
			</div>

			<div class="storyos-quick-actions">
				<h2>Quick Actions</h2>
				<div class="storyos-actions">
					<a href="<?php echo admin_url( 'post-new.php?post_type=storyos_project' ); ?>" class="button button-primary">
						<span class="dashicons dashicons-plus"></span> New Project
					</a>
					<a href="<?php echo admin_url( 'post-new.php?post_type=storyos_story-world' ); ?>" class="button">
						<span class="dashicons dashicons-admin-site"></span> New Story World
					</a>
					<a href="<?php echo admin_url( 'post-new.php?post_type=storyos_character' ); ?>" class="button">
						<span class="dashicons dashicons-admin-users"></span> New Character
					</a>
					<a href="<?php echo admin_url( 'post-new.php?post_type=storyos_scene' ); ?>" class="button">
						<span class="dashicons dashicons-video-alt3"></span> New Scene
					</a>
				</div>
			</div>

			<div class="storyos-recent">
				<h2>Recent Activity</h2>
				<?php self::render_recent_activity(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stat cards.
	 */
	private static function render_stat_cards(): void {
		$cpts = \StoryOS\Utils\storyos_get_all_cpts();
		?>
		<div class="stat-cards">
			<?php foreach ( $cpts as $cpt ) : ?>
				<?php
				$count = wp_count_posts( $cpt );
				$total = is_object( $count ) ? array_sum( (array) $count ) : 0;
				$post_type_object = get_post_type_object( $cpt );
				$label = ( $post_type_object && isset( $post_type_object->labels->name ) ) ? $post_type_object->labels->name : $cpt;
				?>
				<div class="stat-card">
					<div class="stat-number"><?php echo esc_html( $total ); ?></div>
					<div class="stat-label">
						<a href="<?php echo admin_url( 'edit.php?post_type=storyos_' . strtolower($cpt) ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render recent activity.
	 */
	private static function render_recent_activity(): void {
		$recent = new \WP_Query( [
			'post_type'      => array_values( \StoryOS\Utils\storyos_get_all_cpts() ),
			'posts_per_page' => 10,
			'meta_key'       => 'generated_date',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		] );

		if ( ! $recent->have_posts() ) {
			echo '<p>No recent activity.</p>';
			return;
		}

		?>
		<table class="widefat">
			<thead>
				<tr>
					<th>Title</th>
					<th>Type</th>
					<th>Status</th>
					<th>Modified</th>
				</tr>
			</thead>
			<tbody>
				<?php while ( $recent->have_posts() ) : $recent->the_post(); ?>
					<tr>
						<td>
							<a href="<?php echo get_edit_post_link(); ?>">
								<?php the_title(); ?>
							</a>
						</td>
						<td><?php echo esc_html( get_post_type() ); ?></td>
						<td><?php echo esc_html( get_post_status() ); ?></td>
						<td><?php echo esc_html( get_the_modified_date() ); ?></td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		<?php
		wp_reset_postdata();
	}
}
