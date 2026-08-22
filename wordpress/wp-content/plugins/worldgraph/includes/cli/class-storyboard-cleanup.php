<?php
/**
 * One-time cleanup for the removed Storyboard Frame record type.
 *
 * `worldgraph_board` posts outlive their unregistered post type, so this
 * command removes them along with their relationship edges and the Asset
 * `storyboard` slot that pointed at them.
 *
 * @package WorldGraph
 */

namespace WorldGraph\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storyboard Frame cleanup command.
 */
class Storyboard_Cleanup {

	/**
	 * Post type retired in favour of Shots.
	 */
	const RETIRED_POST_TYPE = 'worldgraph_board';

	/**
	 * Register the command with WP-CLI.
	 */
	public static function init(): void {
		if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'worldgraph cleanup-storyboards', [ __CLASS__, 'run' ] );
	}

	/**
	 * Delete every orphaned Storyboard Frame record.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be deleted without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp worldgraph cleanup-storyboards --dry-run
	 *     wp worldgraph cleanup-storyboards
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$ids     = self::orphaned_ids();
		$edges   = self::dangling_edge_holders();

		if ( empty( $ids ) && empty( $edges ) ) {
			\WP_CLI::success( 'No Storyboard Frame records or edges remain.' );
			return;
		}

		if ( $dry_run ) {
			\WP_CLI::log( sprintf( 'Would delete %d Storyboard Frame record(s):', count( $ids ) ) );
			foreach ( $ids as $id ) {
				\WP_CLI::log( sprintf( '  #%d %s', $id, get_the_title( $id ) ?: '(no title)' ) );
			}
			\WP_CLI::log( sprintf( 'Would prune Storyboard Frame edges from %d related record(s).', count( $edges ) ) );
			\WP_CLI::success( 'Dry run complete. Re-run without --dry-run to delete.' );
			return;
		}

		$deleted  = 0;
		$failed   = 0;
		$unlinked = self::clear_asset_links();

		foreach ( $ids as $id ) {
			self::remove_relationships( $id );
			if ( wp_delete_post( $id, true ) ) {
				$deleted++;
			} else {
				$failed++;
				\WP_CLI::warning( sprintf( 'Could not delete Storyboard Frame #%d.', $id ) );
			}
		}

		$pruned = self::prune_dangling_edges();

		\WP_CLI::log( sprintf( 'Cleared the storyboard slot on %d Asset record(s).', $unlinked ) );
		\WP_CLI::log( sprintf( 'Pruned Storyboard Frame edges from %d related record(s).', $pruned ) );

		if ( $failed ) {
			\WP_CLI::error( sprintf( 'Deleted %d record(s); %d could not be deleted.', $deleted, $failed ) );
		}

		\WP_CLI::success( sprintf( 'Deleted %d Storyboard Frame record(s).', $deleted ) );
	}

	/**
	 * Records holding an outgoing edge to a Storyboard Frame.
	 *
	 * @return array<int, int>
	 */
	private static function dangling_edge_holders(): array {
		global $wpdb;

		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over serialized edge lists.
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				WORLDGRAPH_CPT_PREFIX . 'relationships',
				'%' . $wpdb->esc_like( self::RETIRED_POST_TYPE ) . '%'
			)
		);

		return array_map( 'absint', (array) $post_ids );
	}

	/**
	 * Drop outgoing edges that still point at a deleted Storyboard Frame.
	 *
	 * The incoming index only covers registered post types, so an edge stored
	 * on a Shot or Scene can outlive the frame it targets.
	 *
	 * @return int Number of records updated.
	 */
	private static function prune_dangling_edges(): int {
		$meta_key = WORLDGRAPH_CPT_PREFIX . 'relationships';
		$updated  = 0;

		foreach ( self::dangling_edge_holders() as $post_id ) {
			$relationships = get_post_meta( $post_id, $meta_key, true );
			if ( ! is_array( $relationships ) ) {
				continue;
			}

			$kept = array_values(
				array_filter(
					$relationships,
					static function ( $relationship ): bool {
						return ! is_array( $relationship ) || self::RETIRED_POST_TYPE !== ( $relationship['to_type'] ?? '' );
					}
				)
			);

			if ( count( $kept ) !== count( $relationships ) ) {
				update_post_meta( $post_id, $meta_key, $kept );
				$updated++;
			}
		}

		if ( $updated ) {
			unset( $GLOBALS['worldgraph_incoming_relationship_index'] );
		}

		return $updated;
	}

	/**
	 * Every remaining Storyboard Frame post ID.
	 *
	 * The post type is no longer registered, so query the table directly.
	 *
	 * @return array<int, int>
	 */
	private static function orphaned_ids(): array {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the post type is unregistered, so WP_Query cannot reach these rows.
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", self::RETIRED_POST_TYPE )
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Remove graph edges in both directions for one frame.
	 *
	 * @param int $frame_id Storyboard Frame post ID.
	 */
	private static function remove_relationships( int $frame_id ): void {
		if ( ! function_exists( '\WorldGraph\Utils\get_relationships' ) || ! function_exists( '\WorldGraph\Utils\remove_relationship' ) ) {
			return;
		}

		foreach ( [ 'outgoing', 'incoming' ] as $direction ) {
			foreach ( \WorldGraph\Utils\get_relationships( $frame_id, self::RETIRED_POST_TYPE, $direction ) as $relationship ) {
				$from_id   = absint( $relationship['from_id'] ?? 0 );
				$to_id     = absint( $relationship['to_id'] ?? 0 );
				$from_type = (string) ( $relationship['from_type'] ?? '' );
				$to_type   = (string) ( $relationship['to_type'] ?? '' );
				$type      = (string) ( $relationship['type'] ?? '' );
				if ( $from_id && $to_id && $type ) {
					\WorldGraph\Utils\remove_relationship( $from_id, $to_id, $from_type, $to_type, $type );
				}
			}
		}
	}

	/**
	 * Drop the retired `storyboard` meta slot from Asset records.
	 *
	 * @return int Number of Assets updated.
	 */
	private static function clear_asset_links(): int {
		global $wpdb;

		$asset_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over a retired meta key.
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s )",
				'storyboard',
				'_storyboard'
			)
		);

		foreach ( (array) $asset_ids as $asset_id ) {
			delete_post_meta( absint( $asset_id ), 'storyboard' );
			delete_post_meta( absint( $asset_id ), '_storyboard' );
		}

		return count( (array) $asset_ids );
	}
}
