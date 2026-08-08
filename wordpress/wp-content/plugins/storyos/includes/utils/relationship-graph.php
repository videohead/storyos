<?php
/**
 * Relationship Graph Analytics — network analysis over Story Graph entities.
 *
 * Provides relationship analytics including:
 * - Network density and connectivity metrics
 * - Character co-occurrence analysis
 * - Most connected entities
 * - Isolated entity detection
 * - Relationship type distribution
 * - Scene-based subgraph queries
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

/**
 * Get the orchestrator URL from options.
 *
 * @return string
 */
function orchestrator_url(): string {
	return get_option( 'storyos_orchestrator_url', 'http://localhost:8000' );
}

/**
 * Fetch relationship graph from orchestrator.
 *
 * @param array $params Query parameters (scene_ids, etc.).
 * @return array|WP_Error The relationship graph or error.
 */
function fetch_relationship_graph( array $params = [] ) {
	$url = trailingslashit( orchestrator_url() ) . 'intelligence/relationships';

	$query_args = [];
	if ( ! empty( $params['scene_ids'] ) ) {
		$query_args['scene_ids'] = implode( ',', array_map( 'absint', (array) $params['scene_ids'] ) );
	}

	$request_args = [
		'method'  => 'GET',
		'timeout' => 30,
		'headers' => [
			'Accept' => 'application/json',
		],
	];

	if ( ! empty( $query_args ) ) {
		$url = add_query_arg( $query_args, $url );
	}

	$response = wp_remote_get( $url, $request_args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new \WP_Error( 'orchestrator_error', 'Orchestrator returned error: ' . $status );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) ) {
		return new \WP_Error( 'parse_error', 'Failed to parse orchestrator response.' );
	}

	return $data;
}

/**
 * Fetch graph analytics from orchestrator.
 *
 * @return array|WP_Error The graph analytics or error.
 */
function fetch_graph_analytics() {
	$url = trailingslashit( orchestrator_url() ) . 'intelligence/graph-analytics';

	$request_args = [
		'method'  => 'GET',
		'timeout' => 30,
		'headers' => [
			'Accept' => 'application/json',
		],
	];

	$response = wp_remote_get( $url, $request_args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new \WP_Error( 'orchestrator_error', 'Orchestrator returned error: ' . $status );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) ) {
		return new \WP_Error( 'parse_error', 'Failed to parse orchestrator response.' );
	}

	return $data;
}

/**
 * Fetch character network analytics from orchestrator.
 *
 * @param array $params Query parameters.
 * @return array|WP_Error The character network or error.
 */
function fetch_character_network( array $params = [] ) {
	$url = trailingslashit( orchestrator_url() ) . 'intelligence/character-network';

	$query_args = [];
	if ( ! empty( $params['limit'] ) ) {
		$query_args['limit'] = absint( $params['limit'] );
	}

	if ( ! empty( $query_args ) ) {
		$url = add_query_arg( $query_args, $url );
	}

	$request_args = [
		'method'  => 'GET',
		'timeout' => 30,
		'headers' => [
			'Accept' => 'application/json',
		],
	];

	$response = wp_remote_get( $url, $request_args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new \WP_Error( 'orchestrator_error', 'Orchestrator returned error: ' . $status );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) ) {
		return new \WP_Error( 'parse_error', 'Failed to parse orchestrator response.' );
	}

	return $data;
}

/**
 * Fetch character analytics for specific characters.
 *
 * @param array $character_ids List of character post IDs.
 * @return array|WP_Error The character analytics or error.
 */
function fetch_character_analytics( array $character_ids = [] ) {
	$url = trailingslashit( orchestrator_url() ) . 'intelligence/character-analytics';

	$request_args = [
		'method'  => 'POST',
		'timeout' => 30,
		'headers' => [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		],
		'body'    => wp_json_encode( [
			'character_ids' => array_map( 'absint', $character_ids ),
		] ),
	];

	$response = wp_remote_post( $url, $request_args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new \WP_Error( 'orchestrator_error', 'Orchestrator returned error: ' . $status );
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) ) {
		return new \WP_Error( 'parse_error', 'Failed to parse orchestrator response.' );
	}

	return $data;
}

/**
 * Compute network density from analytics data.
 *
 * @param array $analytics The graph analytics data.
 * @return float Network density (0.0 to 1.0).
 */
function compute_network_density( array $analytics ): float {
	if ( empty( $analytics['density'] ) ) {
		return 0.0;
	}
	return (float) $analytics['density'];
}

/**
 * Get relationship type distribution.
 *
 * @param array $graph The relationship graph data.
 * @return array Distribution by relationship type.
 */
function get_relationship_type_distribution( array $graph ): array {
	$distribution = [];

	if ( empty( $graph['edges'] ) || ! is_array( $graph['edges'] ) ) {
		return $distribution;
	}

	foreach ( $graph['edges'] as $edge ) {
		$type = $edge['type'] ?? 'unknown';
		if ( ! isset( $distribution[ $type ] ) ) {
			$distribution[ $type ] = 0;
		}
		$distribution[ $type ]++;
	}

	return $distribution;
}

/**
 * Get most connected entities from analytics.
 *
 * @param array $analytics The graph analytics data.
 * @param int   $limit Maximum number of entities to return.
 * @return array List of most connected entities.
 */
function get_most_connected_entities( array $analytics, int $limit = 10 ): array {
	if ( empty( $analytics['most_connected'] ) || ! is_array( $analytics['most_connected'] ) ) {
		return [];
	}

	return array_slice( $analytics['most_connected'], 0, $limit );
}

/**
 * Get isolated entities from analytics.
 *
 * @param array $analytics The graph analytics data.
 * @return array List of isolated entities.
 */
function get_isolated_entities( array $analytics ): array {
	return $analytics['isolated_entities'] ?? [];
}

/**
 * Get entity counts by type.
 *
 * @param array $analytics The graph analytics data.
 * @return array Entity counts.
 */
function get_entity_counts( array $analytics ): array {
	return $analytics['entity_counts'] ?? [];
}

/**
 * Get total entity count.
 *
 * @param array $analytics The graph analytics data.
 * @return int Total entities.
 */
function get_total_entities( array $analytics ): int {
	return (int) ( $analytics['total_entities'] ?? 0 );
}

/**
 * Get total relationship count.
 *
 * @param array $analytics The graph analytics data.
 * @return int Total relationships.
 */
function get_total_relationships( array $analytics ): int {
	return (int) ( $analytics['total_relationships'] ?? 0 );
}

/**
 * Get strongest character relationships.
 *
 * @param array $network The character network data.
 * @param int   $limit Maximum number of relationships to return.
 * @return array Strongest relationships.
 */
function get_strongest_relationships( array $network, int $limit = 10 ): array {
	return $network['strongest_relationships'] ?? [];
}

/**
 * Get character scene presence.
 *
 * @param array $network The character network data.
 * @return array Character scene presence data.
 */
function get_character_scene_presence( array $network ): array {
	return $network['character_scene_presence'] ?? [];
}

/**
 * Get character co-occurrence data.
 *
 * @param array $network The character network data.
 * @return array Co-occurrence data.
 */
function get_character_cooccurrence( array $network ): array {
	return $network['cooccurrence_data'] ?? [];
}

/**
 * Get entity display name.
 *
 * @param int    $post_id The post ID.
 * @param string $post_type The post type slug.
 * @return string The entity display name.
 */
function entity_display_name( int $post_id, string $post_type ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return sprintf( '%s #%d (deleted)', $post_type, $post_id );
	}

	$title = get_the_title( $post_id );
	if ( empty( $title ) ) {
		return sprintf( '%s #%d', $post_type, $post_id );
	}

	return $title;
}

/**
 * Get entity permalink.
 *
 * @param int    $post_id The post ID.
 * @param string $post_type The post type slug.
 * @return string The entity permalink.
 */
function entity_permalink( int $post_id, string $post_type ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '#';
	}

	return get_permalink( $post_id );
}

/**
 * Get relationship type label.
 *
 * @param string $type The relationship type slug.
 * @return string The human-readable label.
 */
function relationship_type_label( string $type ): string {
	$types = relationship_types();
	return $types[ $type ] ?? $type;
}

/**
 * Cache graph analytics locally.
 *
 * @param array $analytics The analytics data.
 * @param int   $ttl Cache TTL in seconds (default: 3600).
 * @return bool
 */
function cache_graph_analytics( array $analytics, int $ttl = 3600 ): bool {
	return set_transient( 'storyos_graph_analytics', $analytics, $ttl );
}

/**
 * Get cached graph analytics.
 *
 * @return array|false The cached analytics or false.
 */
function get_cached_graph_analytics() {
	return get_transient( 'storyos_graph_analytics' );
}

/**
 * Clear cached graph analytics.
 *
 * @return bool
 */
function clear_cached_graph_analytics(): bool {
	return delete_transient( 'storyos_graph_analytics' );
}

/**
 * Cache character network locally.
 *
 * @param array $network The network data.
 * @param int   $ttl Cache TTL in seconds (default: 3600).
 * @return bool
 */
function cache_character_network( array $network, int $ttl = 3600 ): bool {
	return set_transient( 'storyos_character_network', $network, $ttl );
}

/**
 * Get cached character network.
 *
 * @return array|false The cached network or false.
 */
function get_cached_character_network() {
	return get_transient( 'storyos_character_network' );
}

/**
 * Clear cached character network.
 *
 * @return bool
 */
function clear_cached_character_network(): bool {
	return delete_transient( 'storyos_character_network' );
}
