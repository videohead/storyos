<?php
/**
 * Ring-buffer log for ComfyUI MCP / local ComfyUI generation requests, so
 * failures can be diagnosed before the WP-cron generation batch returns a
 * result.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Log {
	const OPTION = 'worldgraph_gen_log';
	const MAX_ENTRIES = 200;

	/**
	 * Append a log entry.
	 *
	 * @param string $level         'info', 'error', or 'debug'.
	 * @param string $source        Short origin tag, e.g. 'local_comfyui', 'comfy_cloud_mcp', 'generation_batch'.
	 * @param string $message       Human-readable message.
	 * @param array  $context       Optional structured detail (request/response payloads, etc).
	 * @param string $job_id        Optional World Graph Studio or provider job ID.
	 * @param int    $connection_id Optional parent worldgraph_conn post ID.
	 */
	public static function add( string $level, string $source, string $message, array $context = [], string $job_id = '', int $connection_id = 0 ): void {
		$entries = self::all();

		$entries[] = [
			'time'          => current_time( 'mysql' ),
			'level'         => $level,
			'source'        => $source,
			'job_id'        => $job_id,
			'connection_id' => $connection_id,
			'message'       => $message,
			'context'       => $context,
		];

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * All log entries, oldest first.
	 *
	 * @param int $connection_id Optional: only entries for this Connection post ID.
	 * @return array
	 */
	public static function all( int $connection_id = 0 ): array {
		$entries = get_option( self::OPTION, [] );
		$entries = is_array( $entries ) ? $entries : [];

		if ( $connection_id > 0 ) {
			$entries = array_values( array_filter( $entries, static function ( $entry ) use ( $connection_id ) {
				return (int) ( $entry['connection_id'] ?? 0 ) === $connection_id;
			} ) );
		}

		return $entries;
	}

	/**
	 * Clear the log.
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
