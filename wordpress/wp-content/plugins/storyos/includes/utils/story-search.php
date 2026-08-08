<?php
/**
 * Story Graph Intelligence Search — WordPress search enhancement.
 *
 * Enhances default WordPress search with StoryOS entity type filters,
 * semantic search results from the orchestrator, and hybrid keyword+semantic
 * ranking. Integrates seamlessly with WP_Query and the WordPress admin bar.
 *
 * @package StoryOS\Utils
 */

namespace StoryOS\Utils;

/**
 * StoryOS Intelligence Search configuration.
 *
 * @return array
 */
function search_config(): array {
	return [
		'orchestrator_url' => defined( 'STORYOS_ORCHESTRATOR_URL' ) ? STORYOS_ORCHESTRATOR_URL : 'http://localhost:8000',
		'entity_types'     => [
			'characters' => [
				'label'       => 'Characters',
				'post_type'   => 'storyos_character',
				'icon'        => 'admin-users',
				'color'       => '#d63384',
			],
			'scenes' => [
				'label'       => 'Scenes',
				'post_type'   => 'storyos_scene',
				'icon'        => 'format-image',
				'color'       => '#0073aa',
			],
			'locations' => [
				'label'       => 'Locations',
				'post_type'   => 'storyos_location',
				'icon'        => 'admin-location',
				'color'       => '#46b450',
			],
			'shots' => [
				'label'       => 'Shots',
				'post_type'   => 'storyos_shot',
				'icon'        => 'format-video',
				'color'       => '#ffba00',
			],
			'props' => [
				'label'       => 'Props',
				'post_type'   => 'storyos_prop',
				'icon'        => 'admin-collapse',
				'color'       => '#722094',
			],
			'assets' => [
				'label'       => 'Assets',
				'post_type'   => 'storyos_asset',
				'icon'        => 'admin-appearance',
				'color'       => '#c36d17',
			],
			'storyboard_frames' => [
				'label'       => 'Storyboard Frames',
				'post_type'   => 'storyos_storyboard_frame',
				'icon'        => 'slides',
				'color'       => '#2563eb',
			],
			'editorial_artifacts' => [
				'label'       => 'Editorial',
				'post_type'   => 'storyos_editorial_artifact',
				'icon'        => 'admin-tools',
				'color'       => '#dc2626',
			],
		],
		'search_modes'     => [
			'hybrid'  => [ 'label' => 'Hybrid (Recommended)', 'semantic_weight' => 0.7, 'keyword_weight' => 0.3 ],
			'semantic' => [ 'label' => 'Semantic Only', 'semantic_weight' => 1.0, 'keyword_weight' => 0.0 ],
			'keyword'  => [ 'label' => 'Keyword Only', 'semantic_weight' => 0.0, 'keyword_weight' => 1.0 ],
		],
		'min_semantic_score' => 0.1,
		'max_results'        => 20,
	];
}

/**
 * Fetch semantic search results from the orchestrator.
 *
 * @param string $query The search query.
 * @param array  $args Optional search arguments.
 * @return array Search results from orchestrator.
 */
function fetch_semantic_search( string $query, array $args = [] ): array {
	$config = search_config();
	$url = trailingslashit( $config['orchestrator_url'] ) . 'intelligence/search';

	$entity_types = ! empty( $args['entity_types'] ) ? $args['entity_types'] : array_keys( $config['entity_types'] );
	$mode = $args['mode'] ?? 'hybrid';

	$body = [
		'query'         => $query,
		'entity_types'  => $entity_types,
		'mode'          => $mode,
		'top_k'         => $args['top_k'] ?? $config['max_results'],
		'min_score'     => $args['min_score'] ?? $config['min_semantic_score'],
	];

	if ( 'hybrid' === $mode || 'semantic' === $mode ) {
		$mode_config = $config['search_modes'][ $mode ];
		$body['semantic_weight'] = $mode_config['semantic_weight'];
		$body['keyword_weight']  = $mode_config['keyword_weight'];
	}

	$response = wp_remote_post( $url, [
		'body'        => wp_json_encode( $body ),
		'headers'     => [ 'Content-Type' => 'application/json' ],
		'timeout'     => 15,
		'sslverify'   => false,
	] );

	if ( is_wp_error( $response ) ) {
		return [ 'error' => $response->get_error_message(), 'results' => [] ];
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status_code ) {
		$body = wp_remote_retrieve_body( $response );
		return [ 'error' => "HTTP {$status_code}: {$body}", 'results' => [] ];
	}

	$data = wp_remote_retrieve_body( $response );
	$parsed = json_decode( $data, true );

	if ( ! is_array( $parsed ) ) {
		return [ 'error' => 'Invalid response from orchestrator', 'results' => [] ];
	}

	return $parsed;
}

/**
 * Fetch fuzzy/keyword search results from the orchestrator.
 *
 * @param string $query The search query.
 * @param array  $args Optional search arguments.
 * @return array Search results from orchestrator.
 */
function fetch_keyword_search( string $query, array $args = [] ): array {
	$config = search_config();
	$url = trailingslashit( $config['orchestrator_url'] ) . 'intelligence/search';

	$entity_types = ! empty( $args['entity_types'] ) ? $args['entity_types'] : array_keys( $config['entity_types'] );

	$body = [
		'query'        => $query,
		'entity_types' => $entity_types,
		'mode'         => 'keyword',
		'top_k'        => $args['top_k'] ?? $config['max_results'],
	];

	$response = wp_remote_post( $url, [
		'body'        => wp_json_encode( $body ),
		'headers'     => [ 'Content-Type' => 'application/json' ],
		'timeout'     => 15,
		'sslverify'   => false,
	] );

	if ( is_wp_error( $response ) ) {
		return [ 'error' => $response->get_error_message(), 'results' => [] ];
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status_code ) {
		return [ 'error' => "HTTP {$status_code}", 'results' => [] ];
	}

	$data = wp_remote_retrieve_body( $response );
	$parsed = json_decode( $data, true );

	return is_array( $parsed ) ? $parsed : [ 'error' => 'Invalid response', 'results' => [] ];
}

/**
 * Merge semantic and keyword search results with deduplication.
 *
 * @param array $semantic_results Semantic search results.
 * @param array $keyword_results  Keyword search results.
 * @param float $semantic_weight  Weight for semantic results (0-1).
 * @param float $keyword_weight   Weight for keyword results (0-1).
 * @return array Merged and ranked results.
 */
function merge_search_results( array $semantic_results, array $keyword_results, float $semantic_weight = 0.7, float $keyword_weight = 0.3 ): array {
	$merged = [];

	// Add semantic results
	foreach ( $semantic_results as $result ) {
		$key = "{$result['entity_type']}:{$result['entity_id']}";
		$merged[ $key ] = [
			'entity_type' => $result['entity_type'],
			'entity_id'   => $result['entity_id'],
			'title'       => $result['title'],
			'score'       => $result['score'] * $semantic_weight,
			'snippet'     => $result['snippet'] ?? '',
			'source'      => 'semantic',
			'url'         => get_edit_post_link( $result['entity_id'], 'url' ),
		];
	}

	// Merge or add keyword results
	foreach ( $keyword_results as $result ) {
		$key = "{$result['entity_type']}:{$result['entity_id']}";
		if ( isset( $merged[ $key ] ) ) {
			// Normalize keyword score to 0-1 and add to existing
			$normalized_score = $result['score'] ?? 0;
			$merged[ $key ]['score'] += $keyword_weight * $normalized_score;
			$merged[ $key ]['source'] = 'hybrid';
		} else {
			$merged[ $key ] = [
				'entity_type' => $result['entity_type'],
				'entity_id'   => $result['entity_id'],
				'title'       => $result['title'],
				'score'       => $keyword_weight * ( $result['score'] ?? 0 ),
				'snippet'     => $result['snippet'] ?? '',
				'source'      => 'keyword',
				'url'         => get_edit_post_link( $result['entity_id'], 'url' ),
			];
		}
	}

	// Sort by combined score
	uasort( $merged, function( $a, $b ) {
		return $b['score'] <=> $a['score'];
	} );

	return array_values( $merged );
}

/**
 * Get entity type label from slug.
 *
 * @param string $entity_type The entity type slug.
 * @return string The human-readable label.
 */
function get_entity_type_label( string $entity_type ): string {
	$config = search_config();
	return $config['entity_types'][ $entity_type ]['label'] ?? $entity_type;
}

/**
 * Get entity type icon from slug.
 *
 * @param string $entity_type The entity type slug.
 * @return string The Dashicon class.
 */
function get_entity_type_icon( string $entity_type ): string {
	$config = search_config();
	return $config['entity_types'][ $entity_type ]['icon'] ?? 'admin-generic';
}

/**
 * Get entity type color from slug.
 *
 * @param string $entity_type The entity type slug.
 * @return string The hex color.
 */
function get_entity_type_color( string $entity_type ): string {
	$config = search_config();
	return $config['entity_types'][ $entity_type ]['color'] ?? '#6c757d';
}

/**
 * Get post type from entity type.
 *
 * @param string $entity_type The entity type slug.
 * @return string The WordPress post type slug.
 */
function entity_to_post_type( string $entity_type ): string {
	$config = search_config();
	return $config['entity_types'][ $entity_type ]['post_type'] ?? "storyos_{$entity_type}";
}

/**
 * Enhance WordPress search with StoryOS entity filters.
 *
 * Modifies WP_Query to filter by StoryOS entity types when search is performed.
 *
 * @param WP_Query $query The WP_Query instance.
 */
function enhance_search_query( \WP_Query $query ): void {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		// Check if entity type filters are set
		$entity_types = isset( $_GET['storyos_entity_type'] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET['storyos_entity_type'] ) ) ) ) : [];

		if ( ! empty( $entity_types ) ) {
			// Convert entity types to post types
			$post_types = array_map( 'entity_to_post_type', $entity_types );
			$query->set( 'post_type', $post_types );
		}
	}
}
add_action( 'pre_get_posts', 'StoryOS\Utils\enhance_search_query' );

/**
 * Add StoryOS entity type filters to WordPress search form.
 *
 * Hooks into the search form to add entity type checkboxes.
 *
 * @param string $form The search form HTML.
 * @return string The enhanced search form.
 */
function add_search_filters_to_form( string $form ): string {
	// Only add filters on frontend, not in admin
	if ( is_admin() ) {
		return $form;
	}

	$config = search_config();
	$entity_types = $config['entity_types'];

	// Build filter checkboxes
	$filters_html = '<div class="storyos-search-filters">';
	$filters_html .= '<fieldset>';
	$filters_html .= '<legend>Filter by type:</legend>';

	foreach ( $entity_types as $slug => $config ) {
		$checked = ''; // Checked by default
		$label = esc_html( $config['label'] );
		$icon = $config['icon'];
		$color = $config['color'];

		$filters_html .= sprintf(
			'<label class="storyos-filter-item" style="--entity-color: %s;">',
			esc_attr( $color )
		);
		$filters_html .= sprintf(
			'<input type="checkbox" name="storyos_entity_type[]" value="%s" %s />',
			esc_attr( $slug ),
			checked( $slug, '', false )
		);
		$filters_html .= sprintf(
			'<span class="dashicons dashicons-%s"></span>',
			esc_attr( $icon )
		);
		$filters_html .= esc_html( $label );
		$filters_html .= '</label>';
	}

	$filters_html .= '</fieldset>';
	$filters_html .= '</div>';

	// Insert filters before the search submit button
	$submit_pos = strpos( $form, '</form>' );
	if ( $submit_pos !== false ) {
		$form = substr( $form, 0, $submit_pos ) . $filters_html . substr( $form, $submit_pos );
	}

	return $form;
}

/**
 * Register StoryOS search REST endpoint.
 *
 * @return void
 */
function register_search_endpoint(): void {
	register_rest_route( 'storyos/v1', '/search', [
		'methods'             => 'POST',
		'callback'            => __NAMESPACE__ . '\\handle_search_request',
		'permission_callback' => '__return_true', // Public endpoint
		'args'                => [
			'query'     => [
				'required'    => true,
				'type'        => 'string',
				'description' => 'Search query.',
			],
			'entity_types' => [
				'required'    => false,
				'type'        => 'array',
				'description' => 'Entity types to search.',
			],
			'mode'      => [
				'required'    => false,
				'type'        => 'string',
				'default'     => 'hybrid',
				'enum'        => [ 'hybrid', 'semantic', 'keyword' ],
				'description' => 'Search mode.',
			],
			'top_k'     => [
				'required'    => false,
				'type'        => 'integer',
				'default'     => 20,
				'description' => 'Maximum results.',
			],
		],
	] );

	register_rest_route( 'storyos/v1', '/search/suggest', [
		'methods'             => 'GET',
		'callback'            => __NAMESPACE__ . '\\handle_search_suggestions',
		'permission_callback' => '__return_true',
		'args'                => [
			'q' => [
				'required'    => true,
				'type'        => 'string',
				'description' => 'Partial search query.',
			],
			'limit' => [
				'required'    => false,
				'type'        => 'integer',
				'default'     => 5,
			],
		],
	] );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_search_endpoint' );

/**
 * Handle search REST API request.
 *
 * @param \WP_REST_Request $request The REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function handle_search_request( \WP_REST_Request $request ) {
	$query     = sanitize_text_field( $request->get_param( 'query' ) );
	$entity_types = $request->get_param( 'entity_types' ) ?: [];
	$mode      = $request->get_param( 'mode' ) ?: 'hybrid';
	$top_k     = (int) $request->get_param( 'top_k' );

	if ( empty( $query ) ) {
		return new \WP_Error( 'empty_query', 'Search query is required.', [ 'status' => 400 ] );
	}

	// Fetch semantic results
	$semantic_results = fetch_semantic_search( $query, [
		'entity_types' => $entity_types,
		'mode'         => $mode,
		'top_k'        => $top_k,
	] );

	// Fetch keyword results if hybrid mode
	$keyword_results = [];
	if ( 'hybrid' === $mode ) {
		$keyword_results = fetch_keyword_search( $query, [
			'entity_types' => $entity_types,
			'top_k'        => $top_k,
		] );
	}

	// Merge results
	$config = search_config();
	$mode_config = $config['search_modes'][ $mode ];

	$merged = merge_search_results(
		$semantic_results['results'] ?? [],
		$keyword_results['results'] ?? [],
		$mode_config['semantic_weight'],
		$mode_config['keyword_weight']
	);

	return new \WP_REST_Response( [
		'success'   => true,
		'query'     => $query,
		'mode'      => $mode,
		'total'     => count( $merged ),
		'results'   => $merged,
		'entity_types' => array_keys( $config['entity_types'] ),
	], 200 );
}

/**
 * Handle search suggestions REST API request.
 *
 * @param \WP_REST_Request $request The REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function handle_search_suggestions( \WP_REST_Request $request ) {
	$query = sanitize_text_field( $request->get_param( 'q' ) );
	$limit = (int) $request->get_param( 'limit' );

	if ( empty( $query ) || strlen( $query ) < 2 ) {
		return new \WP_REST_Response( [ 'suggestions' => [] ], 200 );
	}

	$config = search_config();
	$suggestions = [];

	// Get suggestions from each entity type
	foreach ( $config['entity_types'] as $slug => $entity_config ) {
		$posts = get_posts( [
			'post_type'      => $entity_config['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $query,
			'fields'         => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			$title = get_the_title( $post_id );
			$suggestions[] = [
				'id'          => $post_id,
				'title'       => $title,
				'entity_type' => $slug,
				'label'       => sprintf( '%s — %s', $entity_config['label'], $title ),
				'url'         => get_edit_post_link( $post_id, 'url' ),
			];
		}
	}

	// Sort by title and limit
	usort( $suggestions, function( $a, $b ) {
		return strcmp( $a['title'], $b['title'] );
	} );

	$suggestions = array_slice( $suggestions, 0, $limit * count( $config['entity_types'] ) );

	return new \WP_REST_Response( [
		'success'     => true,
		'query'       => $query,
		'suggestions' => $suggestions,
	], 200 );
}

/**
 * Enqueue search widget styles and scripts.
 *
 * @return void
 */
function enqueue_search_assets(): void {
	wp_enqueue_style(
		'storyos-search',
		STORYOS_PLUGIN_URL . 'assets/css/search-widget.css',
		[],
		STORYOS_VERSION
	);

	wp_enqueue_script(
		'storyos-search',
		STORYOS_PLUGIN_URL . 'assets/js/search-widget.js',
		[ 'jquery' ],
		STORYOS_VERSION,
		true
	);

	wp_localize_script( 'storyos-search', 'storyosSearch', [
		'ajax_url'  => admin_url( 'admin-ajax.php' ),
		'search_url' => rest_url( 'storyos/v1/search' ),
		'suggest_url' => rest_url( 'storyos/v1/search/suggest' ),
		'nonce'     => wp_create_nonce( 'storyos_search' ),
	] );
}

/**
 * Create a shortcode for embedding StoryOS search anywhere.
 *
 * [storyos_search mode="hybrid" show_filters="true" max_results="20"]
 *
 * @param array $atts Shortcode attributes.
 * @return string The search widget HTML.
 */
function storyos_search_shortcode( array $atts ): string {
	$atts = shortcode_atts( [
		'mode'        => 'hybrid',
		'show_filters' => 'true',
		'max_results' => '20',
		'placeholder' => 'Search stories, characters, scenes...',
	], $atts, 'storyos_search' );

	ob_start();
	?>
	<div class="storyos-search-widget" data-mode="<?php echo esc_attr( $atts['mode'] ); ?>" data-max-results="<?php echo esc_attr( $atts['max_results'] ); ?>">
		<form class="storyos-search-form" role="search">
			<div class="storyos-search-input-wrapper">
				<input type="search"
					class="storyos-search-input"
					placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
					autocomplete="off"
				/>
				<button type="submit" class="storyos-search-button" aria-label="Search">
					<span class="dashicons dashicons-search"></span>
				</button>
			</div>
			<?php if ( 'true' === $atts['show_filters'] ) : ?>
				<div class="storyos-search-filters">
					<?php
					$config = search_config();
					foreach ( $config['entity_types'] as $slug => $entity_config ) : ?>
						<label class="storyos-filter-item" style="--entity-color: <?php echo esc_attr( $entity_config['color'] ); ?>">
							<input type="checkbox" name="storyos_entity_type[]" value="<?php echo esc_attr( $slug ); ?>" checked />
							<span class="dashicons dashicons-<?php echo esc_attr( $entity_config['icon'] ); ?>"></span>
							<?php echo esc_html( $entity_config['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="storyos-search-results" style="display: none;"></div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'storyos_search', 'StoryOS\Utils\storyos_search_shortcode' );

/**
 * Add StoryOS search to WordPress widget areas.
 *
 * @return void
 */
function register_storyos_search_widget(): void {
	register_widget( 'StoryOS\\Utils\\Search_Widget' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_storyos_search_widget' );

/**
 * StoryOS Search Widget class.
 *
 * @package StoryOS\Utils
 */
class Search_Widget extends \WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'storyos_search',
			__( 'StoryOS Search', 'storyos' ),
			[ 'description' => __( 'Enhanced search with StoryOS entity type filters.', 'storyos' ) ]
		);
	}

	/**
	 * Front-end widget display.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ): void {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$title    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Story Search', 'storyos' );
		$mode     = ! empty( $instance['mode'] ) ? $instance['mode'] : 'hybrid';
		$show_filters = ! empty( $instance['show_filters'] );

		echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="storyos-search-widget" data-mode="<?php echo esc_attr( $mode ); ?>">
			<form class="storyos-search-form" role="search">
				<div class="storyos-search-input-wrapper">
					<input type="search"
						class="storyos-search-input"
						placeholder="<?php esc_attr_e( 'Search stories, characters, scenes...', 'storyos' ); ?>"
						autocomplete="off"
					/>
					<button type="submit" class="storyos-search-button" aria-label="Search">
						<span class="dashicons dashicons-search"></span>
					</button>
				</div>
				<?php if ( $show_filters ) : ?>
					<div class="storyos-search-filters">
						<?php
						$config = search_config();
						foreach ( $config['entity_types'] as $slug => $entity_config ) : ?>
							<label class="storyos-filter-item" style="--entity-color: <?php echo esc_attr( $entity_config['color'] ); ?>">
								<input type="checkbox" name="storyos_entity_type[]" value="<?php echo esc_attr( $slug ); ?>" checked />
								<span class="dashicons dashicons-<?php echo esc_attr( $entity_config['icon'] ); ?>"></span>
								<?php echo esc_html( $entity_config['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="storyos-search-results" style="display: none;"></div>
			</form>
		</div>
		<?php
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previous widget settings.
	 */
	public function form( $instance ): void {
		$title      = isset( $instance['title'] ) ? $instance['title'] : __( 'Story Search', 'storyos' );
		$mode       = isset( $instance['mode'] ) ? $instance['mode'] : 'hybrid';
		$show_filters = isset( $instance['show_filters'] ) ? $instance['show_filters'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'storyos' ); ?>
				<input class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					type="text"
					value="<?php echo esc_attr( $title ); ?>"
				/>
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>">
				<?php esc_html_e( 'Search Mode:', 'storyos' ); ?>
				<select class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>"
				>
					<?php
					$config = search_config();
					foreach ( $config['search_modes'] as $slug => $mode_config ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $mode, $slug ); ?>>
							<?php echo esc_html( $mode_config['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_filters' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_filters' ) ); ?>"
				value="1"
				<?php checked( $show_filters, true ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_filters' ) ); ?>">
				<?php esc_html_e( 'Show entity type filters', 'storyos' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values.
	 *
	 * @param array $new_instance New widget settings.
	 * @param array $old_instance Old widget settings.
	 * @return array Sanitized settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];
		$instance['title']      = sanitize_text_field( $new_instance['title'] );
		$instance['mode']       = in_array( $new_instance['mode'], [ 'hybrid', 'semantic', 'keyword' ], true ) ? $new_instance['mode'] : 'hybrid';
		$instance['show_filters'] = ! empty( $new_instance['show_filters'] );
		return $instance;
	}
}

/**
 * Add StoryOS search to admin bar.
 *
 * @param \WP_Admin_Bar $admin_bar The admin bar object.
 * @return void
 */
function add_admin_bar_search( \WP_Admin_Bar $admin_bar ): void {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'read' ) ) {
		return;
	}

	$config = search_config();
	$entity_types = array_keys( $config['entity_types'] );

	$admin_bar->add_node( [
		'id'    => 'storyos-search',
		'title' => '<span class="ab-icon dashicons dashicons-search"></span><span class="screen-reader-text">StoryOS Search</span>',
		'href'  => '#',
		'meta'  => [
			'tabindex' => 0,
			'class'    => 'ab-item storyos-admin-search-trigger',
		],
	] );

	// Add submenu with entity type filters
	foreach ( $config['entity_types'] as $slug => $entity_config ) {
		$admin_bar->add_node( [
			'id'     => "storyos-search-{$slug}",
			'parent' => 'storyos-search',
			'title'  => sprintf(
				'<span style="color:%s;" class="dashicons dashicons-%s"></span> %s',
				esc_attr( $entity_config['color'] ),
				esc_attr( $entity_config['icon'] ),
				esc_html( $entity_config['label'] )
			),
			'href'   => '#',
			'meta'   => [
				'class' => 'storyos-search-entity-filter',
				'data-entity' => $slug,
			],
		] );
	}
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\\add_admin_bar_search', 999 );

/**
 * Get all searchable StoryOS entities for autocomplete.
 *
 * @param string $search The search string.
 * @param int    $limit  Maximum results.
 * @return array Searchable entities.
 */
function get_searchable_entities( string $search = '', int $limit = 20 ): array {
	$config = search_config();
	$results = [];

	foreach ( $config['entity_types'] as $slug => $entity_config ) {
		$args = [
			'post_type'      => $entity_config['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		];

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$posts = get_posts( $args );

		foreach ( $posts as $post_id ) {
			$title = get_the_title( $post_id );
			$results[] = [
				'id'          => $post_id,
				'title'       => $title,
				'entity_type' => $slug,
				'label'       => sprintf( '%s — %s', $entity_config['label'], $title ),
				'url'         => get_edit_post_link( $post_id, 'url' ),
				'color'       => $entity_config['color'],
			];
		}
	}

	return $results;
}
