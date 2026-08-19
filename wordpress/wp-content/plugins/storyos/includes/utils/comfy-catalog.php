<?php
/**
 * Per-Connection catalog of the ComfyUI templates a provider advertises.
 *
 * Discovery answers "what could this Connection run", which is a different and
 * much larger question than "what has an operator chosen to offer". Entries are
 * cached on the Connection and stay inert until explicitly enabled, so a
 * provider advertising seventy example workflows never becomes seventy Template
 * posts.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template catalog discovery, caching, and curation.
 */
class Comfy_Catalog {

	/**
	 * Connection post meta holding the discovered catalog snapshot.
	 */
	const CATALOG_META = 'comfy_template_catalog';

	/**
	 * Connection post meta holding the operator's enabled allow-list. Kept
	 * separate from the snapshot so a re-sync never discards curation.
	 */
	const ENABLED_META = 'enabled_templates';

	/**
	 * Discover everything a Connection's provider advertises and cache it.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array|WP_Error Catalog snapshot.
	 */
	public static function sync( int $connection_id ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) ) {
			return new WP_Error( 'storyos_connection_not_found', __( 'That Connection does not exist.', 'storyos' ), [ 'status' => 404 ] );
		}
		if ( 'comfyui' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'storyos_connection_not_comfy', __( 'Template discovery only applies to ComfyUI Connections.', 'storyos' ), [ 'status' => 400 ] );
		}

		$capability = Comfy_Cloud_MCP::capability_tier( $connection_id );
		$use_mcp    = in_array( $capability['tier'], [ 'a', 'b' ], true );
		$entries    = $use_mcp
			? self::discover_via_mcp( $connection_id, $capability )
			: self::synthesize_local( $connection );

		if ( is_wp_error( $entries ) ) {
			Generation_Log::add( 'error', 'comfy_catalog', $entries->get_error_message(), [ 'tier' => $capability['tier'] ], '', $connection_id );

			return $entries;
		}

		// A configured-but-unresponsive MCP endpoint still yields a local
		// catalog. Record the tier that actually produced these entries, and
		// keep the probe failure as the message so the cause stays visible.
		$message = $capability['message'];
		if ( 'unreachable' === $capability['tier'] ) {
			$message = sprintf(
				/* translators: %s: MCP probe error message. */
				__( 'Built from the local ComfyUI because the configured MCP endpoint did not respond: %s', 'storyos' ),
				$capability['message']
			);
		}

		$snapshot = [
			'synced_at' => gmdate( 'Y-m-d H:i:s' ),
			'tier'      => $use_mcp ? $capability['tier'] : 'c',
			'probed'    => $capability['tier'],
			'message'   => $message,
			'entries'   => $entries,
		];

		update_post_meta( $connection_id, self::CATALOG_META, wp_slash( (string) wp_json_encode( $snapshot ) ) );
		Generation_Log::add( 'info', 'comfy_catalog', sprintf( 'Synced %d template(s).', count( $entries ) ), [ 'tier' => $capability['tier'] ], '', $connection_id );

		return $snapshot;
	}

	/**
	 * The cached catalog for a Connection, with enable state and requirement
	 * status merged onto each entry.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array Snapshot, empty-but-shaped when nothing has been synced.
	 */
	public static function get( int $connection_id ): array {
		$decoded = json_decode( (string) get_post_meta( $connection_id, self::CATALOG_META, true ), true );
		$snapshot = is_array( $decoded ) ? $decoded : [];

		$snapshot += [ 'synced_at' => '', 'tier' => '', 'probed' => '', 'message' => '', 'entries' => [] ];
		$snapshot['entries'] = self::decorate( (array) $snapshot['entries'], $connection_id );
		$snapshot['enabled'] = self::enabled( $connection_id );

		return $snapshot;
	}

	/**
	 * The operator's enabled allow-list for a Connection.
	 *
	 * @param int $connection_id Connection post ID.
	 * @return array<int, array>
	 */
	public static function enabled( int $connection_id ): array {
		$decoded = json_decode( (string) get_post_meta( $connection_id, self::ENABLED_META, true ), true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		return array_values( array_filter( $decoded, static function ( $entry ): bool {
			return is_array( $entry ) && ! empty( $entry['id'] );
		} ) );
	}

	/**
	 * Whether an entry is enabled on a Connection.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return bool
	 */
	public static function is_enabled( int $connection_id, string $entry_id ): bool {
		foreach ( self::enabled( $connection_id ) as $entry ) {
			if ( (string) $entry['id'] === $entry_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add an entry to the allow-list. Enabling downloads nothing; it only marks
	 * the entry as one this Connection should offer.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return array|WP_Error The stored allow-list entry.
	 */
	public static function enable( int $connection_id, string $entry_id ) {
		$entry = self::find( $connection_id, $entry_id );
		if ( null === $entry ) {
			return new WP_Error( 'storyos_catalog_entry_missing', __( 'That template is not in this Connection\'s catalog. Sync the catalog and try again.', 'storyos' ), [ 'status' => 404 ] );
		}
		if ( empty( $entry['modality'] ) ) {
			return new WP_Error( 'storyos_catalog_entry_unmappable', __( 'This template\'s task type does not map to a StoryOS modality, so it cannot be enabled automatically.', 'storyos' ), [ 'status' => 400 ] );
		}

		$enabled = self::enabled( $connection_id );
		foreach ( $enabled as $existing ) {
			if ( (string) $existing['id'] === $entry_id ) {
				return $existing;
			}
		}

		$record = [
			'id'          => (string) $entry['id'],
			'modality'    => (string) $entry['modality'],
			'enabled_at'  => gmdate( 'Y-m-d H:i:s' ),
			'template_id' => 0,
		];

		$enabled[] = $record;
		self::store_enabled( $connection_id, $enabled );
		Generation_Log::add( 'info', 'comfy_catalog', sprintf( 'Enabled template "%s".', $record['id'] ), $record, '', $connection_id );

		return $record;
	}

	/**
	 * Remove an entry from the allow-list.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return array<int, array> The remaining allow-list.
	 */
	public static function disable( int $connection_id, string $entry_id ): array {
		$remaining = array_values( array_filter( self::enabled( $connection_id ), static function ( array $entry ) use ( $entry_id ): bool {
			return (string) $entry['id'] !== $entry_id;
		} ) );

		self::store_enabled( $connection_id, $remaining );
		Generation_Log::add( 'info', 'comfy_catalog', sprintf( 'Disabled template "%s".', $entry_id ), [], '', $connection_id );

		return $remaining;
	}

	/**
	 * Record the StoryOS Template a catalog entry was materialized into.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @param int    $template_id   Template post ID.
	 */
	public static function link_template( int $connection_id, string $entry_id, int $template_id ): void {
		$enabled = self::enabled( $connection_id );
		foreach ( $enabled as $index => $entry ) {
			if ( (string) $entry['id'] === $entry_id ) {
				$enabled[ $index ]['template_id'] = $template_id;
			}
		}

		self::store_enabled( $connection_id, $enabled );
	}

	/**
	 * Look up one entry in a Connection's cached catalog.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $entry_id      Catalog entry ID.
	 * @return array|null
	 */
	public static function find( int $connection_id, string $entry_id ): ?array {
		foreach ( self::get( $connection_id )['entries'] as $entry ) {
			if ( (string) $entry['id'] === $entry_id ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Discover through the Comfy MCP template system, one call per modality
	 * task type plus an unfiltered sweep, merged by entry ID. Filtering by task
	 * type is what lets a provider template be mapped onto a StoryOS modality
	 * without guessing.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $capability    Capability tier report.
	 * @return array<int, array>|WP_Error
	 */
	private static function discover_via_mcp( int $connection_id, array $capability ) {
		if ( ! in_array( 'list_templates', $capability['tools'], true ) ) {
			return new WP_Error( 'storyos_catalog_no_discovery', __( 'This MCP server does not expose list_templates, so its catalog cannot be discovered.', 'storyos' ), [ 'status' => 501 ] );
		}

		$task_types = [];
		foreach ( Generation_Modality::all() as $modality ) {
			$task_types[ (string) $modality['task_type'] ] = true;
		}

		$entries = [];
		$errors  = [];
		foreach ( array_merge( [ '' ], array_keys( $task_types ) ) as $task_type ) {
			$result = Comfy_Cloud_MCP::list_templates( '' !== $task_type ? [ 'task_type' => $task_type ] : [], $connection_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result;
				continue;
			}

			foreach ( (array) ( $result['templates'] ?? $result ) as $template ) {
				if ( ! is_array( $template ) ) {
					continue;
				}
				$entry = Comfy_Manifest::normalize_entry( $template );
				if ( null === $entry ) {
					continue;
				}
				// A task-type-filtered hit is more trustworthy about modality
				// than the unfiltered sweep, so let it win the merge.
				if ( ! isset( $entries[ $entry['id'] ] ) || ( empty( $entries[ $entry['id'] ]['modality'] ) && ! empty( $entry['modality'] ) ) ) {
					$entries[ $entry['id'] ] = $entry;
				}
			}
		}

		if ( empty( $entries ) && ! empty( $errors ) ) {
			return $errors[0];
		}

		return array_values( $entries );
	}

	/**
	 * Build a catalog for a ComfyUI with no MCP template system, from the
	 * built-in modalities plus what the instance actually reports installed.
	 * This is the honest local answer: these are the shapes StoryOS can run,
	 * and here is which ones your ComfyUI is ready for.
	 *
	 * @param array $connection Resolved Connection record.
	 * @return array<int, array>|WP_Error
	 */
	private static function synthesize_local( array $connection ) {
		$endpoint = untrailingslashit( esc_url_raw( (string) ( $connection['endpoint_url'] ?? '' ) ) );
		$catalog  = '' !== $endpoint ? Comfy_Manifest::object_info( $endpoint ) : [];
		$installed = is_wp_error( $catalog ) ? [] : array_keys( (array) $catalog );

		$entries = [];
		foreach ( Generation_Modality::all() as $slug => $modality ) {
			$nodes = Generation_Modality::required_nodes( $slug );
			$missing = $installed ? array_values( array_diff( $nodes, $installed ) ) : [];

			$entries[] = [
				'id'             => 'builtin:' . $slug,
				'name'           => (string) $modality['label'],
				'source'         => 'builtin',
				'model_type'     => '',
				'task_type'      => (string) $modality['task_type'],
				'modality'       => $slug,
				'model_family'   => Model_Family::for_nodes( $nodes ),
				'required_nodes' => $nodes,
				'models'         => [],
				'model_urls'     => [],
				'parameters'     => Generation_Modality::default_settings( $slug ),
				'workflow_hash'  => '',
				'missing_nodes'  => $missing,
				'installable'    => $installed ? empty( $missing ) : null,
			];
		}

		return $entries;
	}

	/**
	 * Merge enable state and a coarse readiness status onto catalog entries.
	 *
	 * @param array $entries       Stored catalog entries.
	 * @param int   $connection_id Connection post ID.
	 * @return array<int, array>
	 */
	private static function decorate( array $entries, int $connection_id ): array {
		$enabled = [];
		foreach ( self::enabled( $connection_id ) as $entry ) {
			$enabled[ (string) $entry['id'] ] = $entry;
		}

		$decorated = [];
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
				continue;
			}

			$id = (string) $entry['id'];
			$entry['enabled']     = isset( $enabled[ $id ] );
			$entry['template_id'] = (int) ( $enabled[ $id ]['template_id'] ?? 0 );
			$entry['status']      = self::entry_status( $entry );
			unset( $enabled[ $id ] );
			$decorated[] = $entry;
		}

		// Anything still enabled but no longer advertised has been withdrawn by
		// the provider. Surface it rather than letting a Template silently rot.
		foreach ( $enabled as $id => $entry ) {
			$decorated[] = [
				'id'          => (string) $id,
				'name'        => (string) $id,
				'source'      => 'withdrawn',
				'modality'    => (string) ( $entry['modality'] ?? '' ),
				'enabled'     => true,
				'template_id' => (int) ( $entry['template_id'] ?? 0 ),
				'status'      => 'withdrawn',
			];
		}

		return $decorated;
	}

	/**
	 * Coarse readiness for catalog rendering. Authoritative validation happens
	 * against a materialized Template via {@see Comfy_Manifest::validate()}.
	 *
	 * @param array $entry Catalog entry.
	 * @return string
	 */
	private static function entry_status( array $entry ): string {
		if ( empty( $entry['modality'] ) ) {
			return 'unmappable';
		}
		if ( ! empty( $entry['missing_nodes'] ) ) {
			return 'needs_nodes';
		}
		if ( ! empty( $entry['models'] ) && empty( $entry['model_urls'] ) ) {
			return 'needs_models';
		}

		return 'ready';
	}

	/**
	 * Persist the allow-list.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $enabled       Allow-list entries.
	 */
	private static function store_enabled( int $connection_id, array $enabled ): void {
		update_post_meta( $connection_id, self::ENABLED_META, wp_slash( (string) wp_json_encode( array_values( $enabled ) ) ) );
	}
}
