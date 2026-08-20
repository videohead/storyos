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
		$areas = [
			[
				'title'       => 'Story Elements',
				'description' => 'Build the people, places, worlds, props, and projects that make up your story.',
				'icon'        => 'dashicons-book-alt',
				'url'         => admin_url( 'admin.php?page=storyos-story-elements' ),
			],
			[
				'title'       => 'Editorial',
				'description' => 'Shape episodes, scenes, shots, assets, and your editorial cut.',
				'icon'        => 'dashicons-edit',
				'url'         => admin_url( 'admin.php?page=storyos-editorial' ),
			],
			[
				'title'       => 'Story Analysis',
				'description' => 'Explore analysis, summaries, continuity, dramaturgy.',
				'icon'        => 'dashicons-chart-area',
				'url'         => admin_url( 'admin.php?page=storyos-analysis' ),
			],
			[
				'title'       => 'Administration',
				'description' => 'Manage setup, connections, templates, and StoryOS logs.',
				'icon'        => 'dashicons-admin-generic',
				'url'         => admin_url( 'admin.php?page=storyos-administration' ),
			],
			[
				'title'       => 'Plugins',
				'description' => 'Import, export, and connect StoryOS with Celtx, EDL, and Google Story.',
				'icon'        => 'dashicons-admin-plugins',
				'url'         => admin_url( 'admin.php?page=storyos-plugins' ),
				'actions'     => [
					[ 'label' => 'Import JSON', 'url' => admin_url( 'admin.php?page=storyos-import' ) ],
					[ 'label' => 'Export Markdown', 'url' => admin_url( 'admin.php?page=storyos-export' ) ],
				],
			],
		];
		?>
		<div class="wrap storyos-dashboard">
			<h1>StoryOS</h1>
			<p class="storyos-dashboard-intro">Choose where you want to work.</p>

			<section class="storyos-setup-panel" aria-labelledby="storyos-setup-title">
				<div class="storyos-setup-icon"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></div>
				<div class="storyos-setup-content">
					<h2 id="storyos-setup-title">Set up StoryOS</h2>
					<p>Connect your services and configure the workspace before you begin building.</p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=storyos-setup' ) ); ?>">Open Setup</a>
			</section>

			<section aria-labelledby="storyos-areas-title">
				<h2 id="storyos-areas-title">Workspaces</h2>
				<div class="storyos-area-cards">
					<?php foreach ( $areas as $area ) : ?>
						<div class="storyos-area-card">
							<span class="storyos-area-icon dashicons <?php echo esc_attr( $area['icon'] ); ?>" aria-hidden="true"></span>
							<span class="storyos-area-card-content">
								<strong><a href="<?php echo esc_url( $area['url'] ); ?>"><?php echo esc_html( $area['title'] ); ?></a></strong>
								<span><?php echo esc_html( $area['description'] ); ?></span>
								<?php if ( ! empty( $area['actions'] ) ) : ?>
									<span class="storyos-area-actions">
										<?php foreach ( $area['actions'] as $action ) : ?>
											<a href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
							</span>
							<a class="storyos-area-card-open" href="<?php echo esc_url( $area['url'] ); ?>" aria-label="Open <?php echo esc_attr( $area['title'] ); ?>">
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}
}
