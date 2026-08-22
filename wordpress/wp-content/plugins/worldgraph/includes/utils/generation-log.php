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

	/** Per-Job event journal, so a Job record carries its own provider history. */
	const EVENTS_META = '_worldgraph_gen_events';

	/** Journal entries retained per Job. */
	const MAX_JOB_EVENTS = 100;

	/**
	 * Job record the current worker step belongs to, so provider adapters that
	 * only know a provider-side ID still log against the right Job.
	 *
	 * @var int
	 */
	private static int $current_job = 0;

	/**
	 * Attribute subsequent log entries to a Job record. Pass 0 to clear.
	 *
	 * @param int $job_id worldgraph_gen post ID.
	 */
	public static function set_current_job( int $job_id ): void {
		self::$current_job = max( 0, $job_id );
	}

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
		$generation_id = self::$current_job ?: ( ctype_digit( $job_id ) ? (int) $job_id : 0 );
		$entry = [
			'time'          => current_time( 'mysql' ),
			'level'         => $level,
			'source'        => $source,
			'job_id'        => $job_id,
			'generation_id' => $generation_id,
			'connection_id' => $connection_id,
			'message'       => $message,
			'context'       => $context,
		];

		$entries   = self::all();
		$entries[] = $entry;

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		self::write_entries( $entries );
		self::record_job_event( $generation_id, $entry );
	}

	/**
	 * Persist an event on its Job record so the Job survives the ring buffer.
	 *
	 * @param int   $generation_id worldgraph_gen post ID.
	 * @param array $entry         Log entry.
	 */
	private static function record_job_event( int $generation_id, array $entry ): void {
		if ( ! $generation_id || ! function_exists( 'get_post_type' ) || 'worldgraph_gen' !== get_post_type( $generation_id ) ) {
			return;
		}

		$events = get_post_meta( $generation_id, self::EVENTS_META, true );
		$events = is_array( $events ) ? $events : [];
		$events[] = $entry;

		if ( count( $events ) > self::MAX_JOB_EVENTS ) {
			$events = array_slice( $events, -self::MAX_JOB_EVENTS );
		}

		update_post_meta( $generation_id, self::EVENTS_META, wp_slash( $events ) );
	}

	/**
	 * The event journal stored on one Job record, oldest first.
	 *
	 * @param int $generation_id worldgraph_gen post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_job( int $generation_id ): array {
		$events = get_post_meta( $generation_id, self::EVENTS_META, true );

		return is_array( $events ) ? $events : [];
	}

	/**
	 * Recent activity for one Connection that no Job owns, such as template
	 * catalog syncs and capability probes.
	 *
	 * @param int $connection_id worldgraph_conn post ID.
	 * @param int $limit         Maximum entries to return, newest first.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_connection( int $connection_id, int $limit = 25 ): array {
		$entries = array_filter(
			self::all( $connection_id ),
			static function ( array $entry ): bool {
				return 0 === (int) ( $entry['generation_id'] ?? 0 );
			}
		);

		return array_slice( array_reverse( array_values( $entries ) ), 0, max( 1, $limit ) );
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

		$lines = is_readable( $file ) ? file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : false;
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
