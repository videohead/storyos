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
	const LOG_SUBDIR = 'worldgraph/logs';
	const LOG_FILENAME = 'generation.log';

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

		self::write_entries( $entries );
	}

	/**
	 * All log entries, oldest first.
	 *
	 * @param int $connection_id Optional: only entries for this Connection post ID.
	 * @return array
	 */
	public static function all( int $connection_id = 0 ): array {
		$entries = self::read_entries();

		if ( empty( $entries ) ) {
			$legacy = get_option( self::OPTION, [] );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$entries = $legacy;
				self::write_entries( $entries );
			}
		}

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
		$file = self::log_file_path();
		if ( '' !== $file && file_exists( $file ) ) {
			wp_delete_file( $file );
		}
		delete_option( self::OPTION );
	}

	/**
	 * Resolve the filesystem path for the generation log file.
	 *
	 * @return string
	 */
	private static function log_file_path(): string {
		$uploads = wp_upload_dir();
		$basedir = is_array( $uploads ) ? (string) ( $uploads['basedir'] ?? '' ) : '';
		if ( '' === $basedir ) {
			return '';
		}

		$dir = trailingslashit( $basedir ) . self::LOG_SUBDIR;
		wp_mkdir_p( $dir );

		return trailingslashit( $dir ) . self::LOG_FILENAME;
	}

	/**
	 * Read log entries from the JSONL generation log file.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function read_entries(): array {
		$file = self::log_file_path();
		if ( '' === $file || ! is_readable( $file ) ) {
			return [];
		}

		$lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return [];
		}

		$entries = [];
		foreach ( $lines as $line ) {
			$decoded = json_decode( (string) $line, true );
			if ( is_array( $decoded ) ) {
				$entries[] = $decoded;
			}
		}

		return $entries;
	}

	/**
	 * Persist log entries to disk as JSON Lines.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries ordered oldest-first.
	 */
	private static function write_entries( array $entries ): void {
		$file = self::log_file_path();
		if ( '' === $file ) {
			return;
		}

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		$lines = [];
		foreach ( $entries as $entry ) {
			$lines[] = wp_json_encode( $entry );
		}

		$payload = implode( "\n", array_filter( $lines, 'is_string' ) );
		if ( '' !== $payload ) {
			$payload .= "\n";
		}

		file_put_contents( $file, $payload, LOCK_EX );
	}
}
