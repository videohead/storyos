<?php
/**
 * Story Graph Analytics Admin Panel.
 *
 * Provides a comprehensive analytics dashboard at Tools > Story Graph Analytics.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Analytics Panel class.
 */
class Analytics_Panel {

	/**
	 * Initialize the analytics panel.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_storyos_fetch_analytics', [ __CLASS__, 'ajax_fetch_analytics' ] );
		add_action( 'wp_ajax_storyos_fetch_network', [ __CLASS__, 'ajax_fetch_network' ] );
		add_action( 'wp_ajax_storyos_fetch_graph', [ __CLASS__, 'ajax_fetch_graph' ] );
		add_action( 'wp_ajax_storyos_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );
	}

	/**
	 * Add analytics menu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'tools.php',
			'Story Graph Analytics',
			'Story Graph Analytics',
			'manage_options',
			'storyos-analytics',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'tools_page_storyos-analytics' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'storyos-analytics',
			STORYOS_PLUGIN_URL . 'assets/css/analytics-panel.css',
			[],
			STORYOS_VERSION
		);

		wp_enqueue_script(
			'storyos-analytics',
			STORYOS_PLUGIN_URL . 'assets/js/analytics-panel.js',
			[ 'jquery' ],
			STORYOS_VERSION,
			true
		);

		wp_localize_script(
			'storyos-analytics',
			'storyosAnalytics',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'storyos_analytics_nonce' ),
				'strings'      => [
					'loading'       => 'Loading analytics...',
					'error'         => 'Error loading analytics.',
					'fetching'      => 'Fetching from orchestrator...',
					'noData'        => 'No analytics data available.',
					'clearCache'    => 'Clear Cache',
					'cacheCleared'  => 'Cache cleared.',
					'fetchError'    => 'Failed to fetch analytics.',
					'networkError'  => 'Failed to fetch network data.',
					'graphError'    => 'Failed to fetch graph data.',
				],
			]
		);
	}

	/**
	 * Render the analytics page.
	 */
	public static function render_page(): void {
		?>
		<div class="wrap storyos-analytics-wrap">
			<h1>Story Graph Analytics</h1>
			<p class="description">
				Comprehensive analytics for your Story Graph, powered by the StoryOS intelligence engine.
				Analyze network density, character relationships, entity connectivity, and isolated entities.
			</p>

			<div id="storyos-analytics-app">
				<!-- Summary Cards -->
				<div class="storyos-summary-cards" id="summary-cards">
					<div class="storyos-summary-card">
						<span class="storyos-card-number" id="total-entities">0</span>
						<span class="storyos-card-label">Total Entities</span>
					</div>
					<div class="storyos-summary-card">
						<span class="storyos-card-number" id="total-relationships">0</span>
						<span class="storyos-card-label">Total Relationships</span>
					</div>
					<div class="storyos-summary-card">
						<span class="storyos-card-number" id="network-density">0%</span>
						<span class="storyos-card-label">Network Density</span>
					</div>
					<div class="storyos-summary-card">
						<span class="storyos-card-number" id="isolated-count">0</span>
						<span class="storyos-card-label">Isolated Entities</span>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="storyos-actions">
					<button type="button" class="button button-primary" id="fetch-analytics-btn">
						<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
						Fetch Analytics
					</button>
					<button type="button" class="button" id="clear-cache-btn">
						<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
						Clear Cache
					</button>
				</div>

				<!-- Loading Indicator -->
				<div class="storyos-loading" id="analytics-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<span class="storyos-loading-text">Loading analytics...</span>
				</div>

				<!-- Error Notice -->
				<div class="notice notice-error" id="analytics-error" style="display: none;">
					<p id="analytics-error-message"></p>
				</div>

				<!-- Analytics Content -->
				<div id="analytics-content" style="display: none;">
					<!-- Entity Counts -->
					<div class="storyos-section">
						<h2>Entity Counts</h2>
						<div class="storyos-entity-counts" id="entity-counts"></div>
					</div>

					<!-- Most Connected Entities -->
					<div class="storyos-section">
						<h2>Most Connected Entities</h2>
						<table class="wp-list-table widefat fixed striped" id="most-connected-table">
							<thead>
								<tr>
									<th>Entity</th>
									<th>Type</th>
									<th>Connections</th>
								</tr>
							</thead>
							<tbody id="most-connected-body"></tbody>
						</table>
					</div>

					<!-- Relationship Type Distribution -->
					<div class="storyos-section">
						<h2>Relationship Type Distribution</h2>
						<div class="storyos-distribution" id="relationship-distribution"></div>
					</div>

					<!-- Isolated Entities -->
					<div class="storyos-section">
						<h2>Isolated Entities</h2>
						<p class="description">Entities with no relationships in the graph.</p>
						<table class="wp-list-table widefat fixed striped" id="isolated-table">
							<thead>
								<tr>
									<th>Entity</th>
									<th>Type</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody id="isolated-body"></tbody>
						</table>
					</div>
				</div>

				<!-- Character Network Section -->
				<div class="storyos-section" id="network-section" style="display: none;">
					<h2>Character Network</h2>
					<div class="storyos-actions">
						<button type="button" class="button" id="fetch-network-btn">
							<span class="dashicons dashicons-groups" style="margin-top: 3px;"></span>
							Fetch Character Network
						</button>
					</div>
					<div class="storyos-loading" id="network-loading" style="display: none;">
						<span class="spinner is-active"></span>
						<span class="storyos-loading-text">Loading network data...</span>
					</div>

					<div id="network-content" style="display: none;">
						<!-- Strongest Relationships -->
						<div class="storyos-subsection">
							<h3>Strongest Character Relationships</h3>
							<table class="wp-list-table widefat fixed striped" id="strongest-table">
								<thead>
									<tr>
										<th>Character A</th>
										<th>Character B</th>
										<th>Relationship</th>
										<th>Co-occurrences</th>
									</tr>
								</thead>
								<tbody id="strongest-body"></tbody>
							</table>
						</div>

						<!-- Character Scene Presence -->
						<div class="storyos-subsection">
							<h3>Character Scene Presence</h3>
							<table class="wp-list-table widefat fixed striped" id="scene-presence-table">
								<thead>
									<tr>
										<th>Character</th>
										<th>Scenes</th>
										<th>Shots</th>
									</tr>
								</thead>
								<tbody id="scene-presence-body"></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- No Data State -->
				<div class="storyos-no-data" id="no-data-state">
					<p>No analytics data available. Click "Fetch Analytics" to load data from the orchestrator.</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Fetch analytics from orchestrator.
	 */
	public static function ajax_fetch_analytics(): void {
		check_ajax_referer( 'storyos_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		// Check cache first.
		$cached = \StoryOS\Utils\get_cached_graph_analytics();
		if ( is_array( $cached ) ) {
			wp_send_json_success( [
				'data'   => $cached,
				'cached' => true,
			] );
		}

		// Fetch from orchestrator.
		$analytics = \StoryOS\Utils\fetch_graph_analytics();

		if ( is_wp_error( $analytics ) ) {
			wp_send_json_error( [
				'message' => $analytics->get_error_message(),
			] );
		}

		// Cache the result.
		\StoryOS\Utils\cache_graph_analytics( $analytics );

		wp_send_json_success( [
			'data'   => $analytics,
			'cached' => false,
		] );
	}

	/**
	 * AJAX handler: Fetch character network from orchestrator.
	 */
	public static function ajax_fetch_network(): void {
		check_ajax_referer( 'storyos_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		// Check cache first.
		$cached = \StoryOS\Utils\get_cached_character_network();
		if ( is_array( $cached ) ) {
			wp_send_json_success( [
				'data'   => $cached,
				'cached' => true,
			] );
		}

		// Fetch from orchestrator.
		$network = \StoryOS\Utils\fetch_character_network();

		if ( is_wp_error( $network ) ) {
			wp_send_json_error( [
				'message' => $network->get_error_message(),
			] );
		}

		// Cache the result.
		\StoryOS\Utils\cache_character_network( $network );

		wp_send_json_success( [
			'data'   => $network,
			'cached' => false,
		] );
	}

	/**
	 * AJAX handler: Fetch full relationship graph.
	 */
	public static function ajax_fetch_graph(): void {
		check_ajax_referer( 'storyos_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		$scene_ids = isset( $_REQUEST['scene_ids'] ) ? array_map( 'absint', explode( ',', $_REQUEST['scene_ids'] ) ) : [];

		$graph = \StoryOS\Utils\fetch_relationship_graph( [
			'scene_ids' => $scene_ids,
		] );

		if ( is_wp_error( $graph ) ) {
			wp_send_json_error( [
				'message' => $graph->get_error_message(),
			] );
		}

		wp_send_json_success( [
			'data' => $graph,
		] );
	}

	/**
	 * AJAX handler: Clear cache.
	 */
	public static function ajax_clear_cache(): void {
		check_ajax_referer( 'storyos_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		\StoryOS\Utils\clear_cached_graph_analytics();
		\StoryOS\Utils\clear_cached_character_network();

		wp_send_json_success( [
			'message' => 'Cache cleared.',
		] );
	}
}
