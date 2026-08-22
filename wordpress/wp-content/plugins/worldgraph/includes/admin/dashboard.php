<?php
/**
 * Admin Dashboard for World Graph Studio.
 *
 * Registers admin pages and renders the World Graph Studio dashboard.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

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
			'World Graph Studio',
			'World Graph Studio',
			'manage_options',
			'worldgraph',
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-video-alt3',
			30
		);

		add_submenu_page(
			'worldgraph',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'worldgraph',
			[ __CLASS__, 'render_dashboard' ]
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( strpos( $hook, 'worldgraph' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'worldgraph-admin',
			WORLDGRAPH_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WORLDGRAPH_VERSION
		);

		wp_enqueue_script(
			'worldgraph-admin',
			WORLDGRAPH_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WORLDGRAPH_VERSION,
			true
		);

		wp_localize_script( 'worldgraph-admin', 'worldgraphAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'worldgraph_admin' ),
		] );
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render_dashboard(): void {
		$areas = [
			[
				'title'       => 'Generate',
				'description' => 'Set up Connections, Templates, and adapters, and review generation Jobs.',
				'icon'        => 'dashicons-images-alt2',
				'url'         => admin_url( 'admin.php?page=worldgraph-generate' ),
			],
			[
				'title'       => 'Story Elements',
				'description' => 'Build the people, places, worlds, props, and projects that make up your story.',
				'icon'        => 'dashicons-book-alt',
				'url'         => admin_url( 'admin.php?page=worldgraph-story-elements' ),
			],
			[
				'title'       => 'Editorial',
				'description' => 'Shape episodes, scenes, shots, assets, and your editorial cut.',
				'icon'        => 'dashicons-edit',
				'url'         => admin_url( 'admin.php?page=worldgraph-editorial' ),
			],
			[
				'title'       => 'Story Analysis',
				'description' => 'Explore analysis, summaries, continuity, dramaturgy.',
				'icon'        => 'dashicons-chart-area',
				'url'         => admin_url( 'admin.php?page=worldgraph-analysis' ),
			],
			[
				'title'       => 'Plugins',
				'description' => 'Import, export, and connect World Graph Studio with Celtx, EDL, and Google Story.',
				'icon'        => 'dashicons-admin-plugins',
				'url'         => admin_url( 'admin.php?page=worldgraph-plugins' ),
			],
		];
		?>
		<div class="wrap worldgraph-dashboard">
			<h1>World Graph Studio</h1>
			<p class="worldgraph-dashboard-intro">Set up your workspace, connections, and templates. These steps are required before you can begin generating AI content.</p>
			<section aria-labelledby="worldgraph-areas-title">
				<h2 id="worldgraph-areas-title">Workspaces</h2>
				<div class="worldgraph-area-cards">
					<?php foreach ( $areas as $area ) : ?>
						<a class="worldgraph-area-card" href="<?php echo esc_url( $area['url'] ); ?>">
							<span class="worldgraph-area-icon dashicons <?php echo esc_attr( $area['icon'] ); ?>" aria-hidden="true"></span>
							<span class="worldgraph-area-card-content">
								<strong><?php echo esc_html( $area['title'] ); ?></strong>
								<span><?php echo esc_html( $area['description'] ); ?></span>
							</span>
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
			<br />
			<section class="worldgraph-setup-panel" aria-labelledby="worldgraph-setup-title">
				<div class="worldgraph-setup-icon"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></div>
				<div class="worldgraph-setup-content">
					<h2 id="worldgraph-setup-title">Setup Wizard</h2>
					<p>A helpful wizard for configuring a first Connection and Templates with simplified user interaction.</p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=worldgraph-setup' ) ); ?>">Open Setup</a>
			</section>
		</div>
		<?php
	}
}
