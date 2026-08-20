<?php
/**
 * Compatibility migration for legacy World Graph Studio custom post type keys.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

/**
 * Return legacy CPT keys and their WordPress-compatible replacements.
 *
 * The truncated variants account for values that may have been written to the
 * 20-character `wp_posts.post_type` column before WordPress rejected the
 * overlong registration keys.
 *
 * @return array<string, string>
 */
function worldgraph_legacy_cpt_key_map(): array {
	return [
		'worldgraph_board_frame'   => 'worldgraph_board',
		'worldgraph_board_f'       => 'worldgraph_board',
		'worldgraph_editorial_artifact' => 'worldgraph_editorial',
		'worldgraph_editorial_ar'       => 'worldgraph_editorial',
	];
}

/**
 * Migrate legacy CPT keys once without changing post content or timestamps.
 *
 * A failed database operation leaves the migration version unchanged so the
 * next request can safely retry the idempotent updates.
 */
function worldgraph_maybe_migrate_cpt_keys(): void {
	$target_version = 1;
	if ( (int) get_option( 'worldgraph_cpt_key_migration_version', 0 ) >= $target_version ) {
		return;
	}

	global $wpdb;
	if ( ! isset( $wpdb->posts, $wpdb->postmeta ) ) {
		return;
	}

	$legacy_keys       = worldgraph_legacy_cpt_key_map();
	$migrated_post_ids = [];

	foreach ( $legacy_keys as $legacy_key => $current_key ) {
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
				$legacy_key
			)
		);

		if ( empty( $post_ids ) ) {
			continue;
		}

		$updated = $wpdb->update(
			$wpdb->posts,
			[ 'post_type' => $current_key ],
			[ 'post_type' => $legacy_key ],
			[ '%s' ],
			[ '%s' ]
		);

		if ( false === $updated ) {
			return;
		}

		$migrated_post_ids = array_merge( $migrated_post_ids, array_map( 'absint', $post_ids ) );
	}

	foreach ( array_unique( $migrated_post_ids ) as $post_id ) {
		clean_post_cache( $post_id );
	}

	if ( ! worldgraph_migrate_relationship_cpt_keys( $legacy_keys ) ) {
		return;
	}

	worldgraph_migrate_legacy_field_option_keys( $legacy_keys );
	update_option( 'worldgraph_cpt_key_migration_version', $target_version, false );
}

/**
 * Update CPT keys embedded in serialized Story Graph relationships.
 *
 * @param array<string, string> $legacy_keys Legacy-to-current CPT key map.
 * @return bool Whether all relationship rows were migrated successfully.
 */
function worldgraph_migrate_relationship_cpt_keys( array $legacy_keys ): bool {
	global $wpdb;

	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			WORLDGRAPH_CPT_PREFIX . 'relationships'
		)
	);

	foreach ( $rows as $row ) {
		$relationships = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $relationships ) ) {
			continue;
		}

		$changed = false;
		foreach ( $relationships as &$relationship ) {
			if ( ! is_array( $relationship ) ) {
				continue;
			}

			foreach ( [ 'from_type', 'to_type' ] as $type_key ) {
				$legacy_key = (string) ( $relationship[ $type_key ] ?? '' );
				if ( isset( $legacy_keys[ $legacy_key ] ) ) {
					$relationship[ $type_key ] = $legacy_keys[ $legacy_key ];
					$changed                   = true;
				}
			}
		}
		unset( $relationship );

		if ( $changed && false === update_metadata_by_mid( 'post', (int) $row->meta_id, $relationships ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Remove stale CPT keys from the legacy field-definition option.
 *
 * Current code-defined fields win on duplicate names, while fields found only
 * under a legacy key are retained for compatibility.
 *
 * @param array<string, string> $legacy_keys Legacy-to-current CPT key map.
 */
function worldgraph_migrate_legacy_field_option_keys( array $legacy_keys ): void {
	$all_fields = get_option( 'worldgraph_fields', [] );
	if ( ! is_array( $all_fields ) ) {
		return;
	}

	$changed = false;
	foreach ( $legacy_keys as $legacy_key => $current_key ) {
		if ( ! isset( $all_fields[ $legacy_key ] ) || ! is_array( $all_fields[ $legacy_key ] ) ) {
			continue;
		}

		$current_fields = isset( $all_fields[ $current_key ] ) && is_array( $all_fields[ $current_key ] )
			? $all_fields[ $current_key ]
			: [];
		$all_fields[ $current_key ] = array_replace( $all_fields[ $legacy_key ], $current_fields );
		unset( $all_fields[ $legacy_key ] );
		$changed = true;
	}

	if ( $changed ) {
		update_option( 'worldgraph_fields', $all_fields, false );
	}
}
