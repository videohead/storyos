<?php
/**
 * Provider Connection Repository.
 *
 * Data-access layer for the worldgraph_conn CPT. Generation jobs reference
 * connections by ID: { "provider_type": "comfyui", "connection_id": 32 }.
 *
 * The repository only ever exposes non-secret configuration. Raw credentials
 * are never read from or written to WordPress; the credential_reference field
 * is a pointer (e.g. env://COMFYUI_API_KEY) that the environment or configuration provides.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

/**
 * Connection repository.
 */
class Connection_Repository {

	/**
	 * Connection CPT slug.
	 *
	 * @var string
	 */
	const CPT = 'worldgraph_conn';

	/**
	 * Non-secret fields exposed to the generation engine and UI.
	 *
	 * @var array<int, string>
	 */
	const PUBLIC_FIELDS = [
		'connection_name',
		'provider_type',
		'environment',
		'status',
		'is_default',
		'endpoint_url',
		'mcp_endpoint_url',
		'credential_reference',
		'mcp_credential_reference',
		'model',
		'max_tokens',
		'temperature',
		'model_access',
		'enabled_structures',
		'enabled_templates',
		'rate_limits',
		'cost_controls',
	];

	/**
	 * Get a single connection record.
	 *
	 * @param int $id Connection post ID.
	 * @return array|null Record with id, title, and meta fields, or null.
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}

		return self::to_array( $post );
	}

	/**
	 * List connections, optionally filtered.
	 *
	 * @param array $filters Optional filters: provider_type, environment, status.
	 * @return array<int, array>
	 */
	public static function get_all( array $filters = [] ): array {
		$query_args = [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$meta_query = [];
		foreach ( [ 'provider_type', 'environment', 'status' ] as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$meta_query[] = [
					'key'   => $key,
					'value' => $filters[ $key ],
				];
			}
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query = new \WP_Query( $query_args );
		$items = [];
		foreach ( $query->posts as $post ) {
			$items[] = self::to_array( $post );
		}

		return $items;
	}

	/**
	 * Resolve the non-secret configuration for a connection.
	 *
	 * This is the shape the generation engine consumes when a generation job
	 * carries { "provider_type": "...", "connection_id": N }.
	 *
	 * @param int $id Connection post ID.
	 * @return array|null Resolved configuration, or null when unavailable.
	 */
	public static function resolve( int $id ): ?array {
		$record = self::get( $id );
		if ( null === $record ) {
			return null;
		}

		$config = [
			'connection_id'           => $record['id'],
			'connection_name'         => $record['connection_name'],
			'provider_type'           => $record['provider_type'],
			'environment'             => $record['environment'],
			'status'                  => $record['status'],
			'endpoint_url'            => $record['endpoint_url'],
			'mcp_endpoint_url'        => $record['mcp_endpoint_url'],
			'credential_reference'    => $record['credential_reference'],
			'mcp_credential_reference' => $record['mcp_credential_reference'],
			'model'                   => $record['model'],
			'max_tokens'              => $record['max_tokens'],
			'temperature'             => $record['temperature'],
			'model_access'            => self::decode_json_field( $record['model_access'] ),
			'enabled_structures'      => self::decode_json_field( $record['enabled_structures'] ),
			'enabled_templates'       => self::decode_json_field( $record['enabled_templates'] ),
			'rate_limits'             => self::decode_json_field( $record['rate_limits'] ),
			'cost_controls'           => self::decode_json_field( $record['cost_controls'] ),
		];

		return apply_filters( 'worldgraph_conn_resolved', $config, $id );
	}

	/**
	 * Whether a connection is usable for new generation jobs.
	 *
	 * @param int $id Connection post ID.
	 * @return bool
	 */
	public static function is_available( int $id ): bool {
		$record = self::get( $id );
		if ( null === $record ) {
			return false;
		}

		if ( 'disabled' === $record['status'] ) {
			return false;
		}

		return '' !== $record['provider_type'] && '' !== $record['endpoint_url'];
	}

	/**
	 * Find the default connection for a provider type.
	 *
	 * The default is the Connection an operator explicitly marked active for
	 * this provider type (and environment, when given), so Generate has an
	 * unambiguous choice once multiple Connections exist for one provider.
	 * Falls back to the first verified connection, then the first available
	 * one, for accounts that have not set an active Connection yet.
	 *
	 * @param string $provider_type Provider type slug.
	 * @param string $environment   Optional environment to scope the lookup to.
	 * @return int|null Connection post ID, or null.
	 */
	public static function get_default( string $provider_type, string $environment = '' ): ?int {
		$filters = [ 'provider_type' => $provider_type ];
		if ( '' !== $environment ) {
			$filters['environment'] = $environment;
		}
		$items = self::get_all( $filters );
		if ( empty( $items ) ) {
			return null;
		}

		foreach ( $items as $item ) {
			if ( 'yes' === ( $item['is_default'] ?? '' ) && self::is_available( $item['id'] ) ) {
				return $item['id'];
			}
		}

		foreach ( $items as $item ) {
			if ( 'verified' === $item['status'] ) {
				return $item['id'];
			}
		}

		foreach ( $items as $item ) {
			if ( self::is_available( $item['id'] ) ) {
				return $item['id'];
			}
		}

		return null;
	}

	/**
	 * Convert a connection post to a plain array.
	 *
	 * @param \WP_Post $post Connection post.
	 * @return array
	 */
	public static function to_array( \WP_Post $post ): array {
		$record = [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'slug'        => $post->post_name,
			'status_wp'   => $post->post_status,
		];

		foreach ( self::PUBLIC_FIELDS as $field ) {
			$record[ $field ] = get_post_meta( $post->ID, $field, true );
		}

		$record['last_validated_at'] = get_post_meta( $post->ID, 'last_validated_at', true );

		return $record;
	}

	/**
	 * Decode a JSON meta field into a PHP value.
	 *
	 * @param string $raw Raw meta value.
	 * @return mixed Decoded value, or null when empty/invalid.
	 */
	private static function decode_json_field( string $raw ) {
		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return null;
		}

		$decoded = json_decode( $trimmed, true );
		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
	}
}
