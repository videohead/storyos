<?php
/**
 * Scene-scoped Shot ordering service shared by wp-admin and REST.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve every non-trashed Shot belonging to a Scene, without hiding records
 * based on the current user's capabilities.
 *
 * This function is intentionally internal to the authorized reorder service.
 * Presentation code must continue to use worldgraph_get_scene_display_shots().
 *
 * @param int $scene_id Scene post ID.
 * @return array<int, \WP_Post>
 */
function worldgraph_get_scene_shots_for_reorder( int $scene_id ): array {
	if ( 'worldgraph_scene' !== get_post_type( $scene_id ) ) {
		return [];
	}

	$shot_ids = [];
	foreach ( get_relationships( $scene_id, 'worldgraph_scene', 'outgoing' ) as $relationship ) {
		if ( 'worldgraph_shot' === (string) ( $relationship['to_type'] ?? '' ) && in_array( (string) ( $relationship['type'] ?? '' ), [ 'belongs_to', 'contains' ], true ) ) {
			$shot_ids[] = absint( $relationship['to_id'] ?? 0 );
		}
	}
	foreach ( get_relationships( $scene_id, 'worldgraph_scene', 'incoming' ) as $relationship ) {
		if ( 'worldgraph_shot' === (string) ( $relationship['from_type'] ?? '' ) && in_array( (string) ( $relationship['type'] ?? '' ), [ 'belongs_to', 'contains' ], true ) ) {
			$shot_ids[] = absint( $relationship['from_id'] ?? 0 );
		}
	}

	$meta_shots = get_posts(
		[
			'post_type'      => 'worldgraph_shot',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => 'scene',
					'value'   => $scene_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		]
	);
	$shot_ids  = array_merge( $shot_ids, wp_list_pluck( $meta_shots, 'ID' ) );
	$shots     = [];
	foreach ( array_values( array_unique( array_filter( array_map( 'absint', $shot_ids ) ) ) ) as $shot_id ) {
		$shot = get_post( $shot_id );
		$canonical_scene_id = worldgraph_get_shot_canonical_scene_id( $shot_id );
		if ( $shot instanceof \WP_Post && 'worldgraph_shot' === $shot->post_type && ! in_array( $shot->post_status, [ 'trash', 'auto-draft' ], true ) && ( ! $canonical_scene_id || $scene_id === $canonical_scene_id ) ) {
			$shots[] = $shot;
		}
	}

	usort(
		$shots,
		static function( \WP_Post $left, \WP_Post $right ): int {
			$left_number  = absint( worldgraph_get_field_value( $left->ID, 'shot_number' ) );
			$right_number = absint( worldgraph_get_field_value( $right->ID, 'shot_number' ) );
			$left_order   = (int) $left->menu_order > 0 ? (int) $left->menu_order : ( $left_number ?: PHP_INT_MAX );
			$right_order  = (int) $right->menu_order > 0 ? (int) $right->menu_order : ( $right_number ?: PHP_INT_MAX );
			if ( $left_order !== $right_order ) {
				return $left_order <=> $right_order;
			}
			if ( $left_number !== $right_number ) {
				return $left_number <=> $right_number;
			}
			$title_order = strcasecmp( $left->post_title, $right->post_title );
			return 0 !== $title_order ? $title_order : $left->ID <=> $right->ID;
		}
	);

	return $shots;
}

/**
 * Calculate collision-free global editorial positions for a Scene's Shots.
 *
 * @param array<int, \WP_Post> $shots Scene Shots.
 * @return array<int, int>
 */
function worldgraph_scene_shot_order_slots( array $shots ): array {
	$order_slots = array_map(
		static function( \WP_Post $shot ): int {
			return (int) $shot->menu_order;
		},
		$shots
	);
	$valid_slots = ! empty( $order_slots ) && count( array_unique( $order_slots ) ) === count( $order_slots ) && min( $order_slots ) > 0;
	if ( $valid_slots ) {
		$scene_shot_ids = wp_list_pluck( $shots, 'ID' );
		$other_shot_ids = get_posts(
			[
				'post_type'      => 'worldgraph_shot',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => $scene_shot_ids,
			]
		);
		foreach ( $other_shot_ids as $other_shot_id ) {
			if ( in_array( (int) get_post_field( 'menu_order', $other_shot_id ), $order_slots, true ) ) {
				$valid_slots = false;
				break;
			}
		}
	}
	if ( ! $valid_slots ) {
		$last_shot_ids = get_posts(
			[
				'post_type'      => 'worldgraph_shot',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			]
		);
		$last_order  = empty( $last_shot_ids ) ? 0 : max( 0, (int) get_post_field( 'menu_order', $last_shot_ids[0] ) );
		$order_slots = range( $last_order + 1, $last_order + count( $shots ) );
	}
	sort( $order_slots, SORT_NUMERIC );

	return $order_slots;
}

/**
 * Restore Shot positions and optional Sequence assignments after a failed save.
 *
 * @param array<int, int>              $original_orders Original menu_order by Shot ID.
 * @param array<int, array<int, int>>  $original_terms  Original Sequence term IDs by Shot ID.
 */
function worldgraph_rollback_scene_shot_order( array $original_orders, array $original_terms = [] ): void {
	foreach ( $original_orders as $shot_id => $menu_order ) {
		wp_update_post(
			[
				'ID'         => $shot_id,
				'menu_order' => $menu_order,
			]
		);
		if ( isset( $original_terms[ $shot_id ] ) ) {
			wp_set_object_terms( $shot_id, $original_terms[ $shot_id ], 'worldgraph_sequence', false );
		}
	}
}

/** Build a compare-and-swap token for one Scene's current Shot order. */
function worldgraph_scene_shot_order_revision( array $shots ): string {
	$state = array_map(
		static function( \WP_Post $shot ): string {
			return $shot->ID . ':' . (int) $shot->menu_order;
		},
		$shots
	);

	return hash( 'sha256', implode( ',', $state ) );
}

/** Acquire the short global lock used while allocating and swapping Shot slots. */
function worldgraph_acquire_shot_order_lock(): string|false {
	$key   = 'worldgraph_shot_order_lock';
	$now   = time();
	$token = $now . ':' . wp_generate_uuid4();
	if ( add_option( $key, $token, '', false ) ) {
		return $token;
	}

	$current_token = (string) get_option( $key, '' );
	$locked_at     = absint( strtok( $current_token, ':' ) );
	if ( $locked_at && $now - $locked_at > 300 ) {
		global $wpdb;
		$claimed = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-swap lock ownership.
			$wpdb->options,
			[ 'option_value' => $token ],
			[ 'option_name' => $key, 'option_value' => $current_token ],
			[ '%s' ],
			[ '%s', '%s' ]
		);
		if ( 1 === $claimed ) {
			wp_cache_delete( $key, 'options' );
			return $token;
		}
	}

	return false;
}

/** Release the global Shot ordering lock. */
function worldgraph_release_shot_order_lock( string $token ): void {
	$key = 'worldgraph_shot_order_lock';
	if ( '' !== $token && hash_equals( $token, (string) get_option( $key, '' ) ) ) {
		delete_option( $key );
	}
}

/**
 * Persist one complete, authorized, Scene-scoped Shot order.
 *
 * The existing positive global slots are reassigned to preserve the rest of
 * the editorial cut. Legacy zero/duplicate slots are allocated after the cut.
 *
 * @param int             $scene_id    Scene post ID.
 * @param array<int, int> $raw_ordered_ids Submitted Shot IDs.
 * @param int             $sequence_id Optional Sequence taxonomy term ID.
 * @param string          $revision    Required current-order revision token.
 * @return array<string, mixed>|\WP_Error
 */
function worldgraph_reorder_scene_shots( int $scene_id, array $raw_ordered_ids, int $sequence_id = 0, string $revision = '' ) {
	if ( ! $scene_id || 'worldgraph_scene' !== get_post_type( $scene_id ) ) {
		return new \WP_Error( 'worldgraph_scene_not_found', __( 'Scene not found.', 'worldgraph' ), [ 'status' => 404 ] );
	}
	if ( ! current_user_can( 'edit_post', $scene_id ) ) {
		return new \WP_Error( 'worldgraph_scene_forbidden', __( 'You cannot edit this Scene.', 'worldgraph' ), [ 'status' => 403 ] );
	}
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $revision ) ) {
		return new \WP_Error( 'worldgraph_scene_shot_revision_required', __( 'Reload the Scene before changing Shot order.', 'worldgraph' ), [ 'status' => 400 ] );
	}
	$lock_token = worldgraph_acquire_shot_order_lock();
	if ( false === $lock_token ) {
		return new \WP_Error( 'worldgraph_shot_order_locked', __( 'Another Shot order is being saved. Try again.', 'worldgraph' ), [ 'status' => 409 ] );
	}

	try {
		$ordered_ids  = array_values( array_map( 'absint', $raw_ordered_ids ) );
		$shots        = worldgraph_get_scene_shots_for_reorder( $scene_id );
		$expected     = wp_list_pluck( $shots, 'ID' );
		$submitted_set = $ordered_ids;
		$expected_set  = $expected;
		sort( $submitted_set, SORT_NUMERIC );
		sort( $expected_set, SORT_NUMERIC );
		if ( empty( $ordered_ids ) || in_array( 0, $ordered_ids, true ) || count( array_unique( $ordered_ids ) ) !== count( $ordered_ids ) || $submitted_set !== $expected_set ) {
			return new \WP_Error( 'worldgraph_scene_shot_membership', __( 'Submit every Shot that belongs to this Scene exactly once.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		if ( ! hash_equals( worldgraph_scene_shot_order_revision( $shots ), $revision ) ) {
			return new \WP_Error( 'worldgraph_scene_shot_conflict', __( 'Shot order changed after this screen loaded. Reload the Scene and try again.', 'worldgraph' ), [ 'status' => 409 ] );
		}

		foreach ( $ordered_ids as $shot_id ) {
			if ( ! current_user_can( 'edit_post', $shot_id ) ) {
				return new \WP_Error( 'worldgraph_shot_forbidden', __( 'You cannot reorder one or more of these Shots.', 'worldgraph' ), [ 'status' => 403 ] );
			}
		}

		$sequence = null;
		if ( $sequence_id ) {
			$sequence = get_term( $sequence_id, 'worldgraph_sequence' );
			$taxonomy = get_taxonomy( 'worldgraph_sequence' );
			if ( ! $sequence || is_wp_error( $sequence ) ) {
				return new \WP_Error( 'worldgraph_sequence_not_found', __( 'Sequence not found.', 'worldgraph' ), [ 'status' => 404 ] );
			}
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
				return new \WP_Error( 'worldgraph_sequence_forbidden', __( 'You cannot assign this Sequence.', 'worldgraph' ), [ 'status' => 403 ] );
			}
		}

		$order_slots      = worldgraph_scene_shot_order_slots( $shots );
		$original_orders = [];
		$original_terms  = [];
		foreach ( $shots as $shot ) {
			$original_orders[ $shot->ID ] = (int) $shot->menu_order;
			if ( $sequence ) {
				$term_ids = wp_get_object_terms( $shot->ID, 'worldgraph_sequence', [ 'fields' => 'ids' ] );
				if ( is_wp_error( $term_ids ) ) {
					return $term_ids;
				}
				$original_terms[ $shot->ID ] = array_map( 'absint', $term_ids );
			}
		}

		foreach ( $ordered_ids as $index => $shot_id ) {
			$result = wp_update_post(
				[
					'ID'         => $shot_id,
					'menu_order' => $order_slots[ $index ],
				],
				true
			);
			if ( is_wp_error( $result ) || $order_slots[ $index ] !== (int) get_post_field( 'menu_order', $shot_id ) ) {
				worldgraph_rollback_scene_shot_order( $original_orders );
				return is_wp_error( $result ) ? $result : new \WP_Error( 'worldgraph_shot_order_write_failed', __( 'A Shot order could not be saved.', 'worldgraph' ), [ 'status' => 500 ] );
			}
		}

		if ( $sequence ) {
			foreach ( $ordered_ids as $shot_id ) {
				$result = wp_set_object_terms( $shot_id, [ (int) $sequence->term_id ], 'worldgraph_sequence', false );
				if ( is_wp_error( $result ) ) {
					worldgraph_rollback_scene_shot_order( $original_orders, $original_terms );
					return $result;
				}
			}
		}

		$updated_shots = worldgraph_get_scene_shots_for_reorder( $scene_id );
		return [
			'ordered_ids' => $ordered_ids,
			'updated'     => $ordered_ids,
			'order_slots' => $order_slots,
			'revision'    => worldgraph_scene_shot_order_revision( $updated_shots ),
		];
	} finally {
		worldgraph_release_shot_order_lock( $lock_token );
	}
}
