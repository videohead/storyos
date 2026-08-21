<?php
/**
 * Descript REST API Connection adapter.
 *
 * Implements the token-authenticated descriptapi.com surface directly in
 * WordPress: project discovery, transcript export (sync), and project media
 * import (async job). Descript does not expose an editable project schema
 * like VideoDraft, so this adapter is intentionally one-way per direction:
 * export a transcript into World Graph Studio, or import bound media into a
 * new Descript project.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Descript API client. */
class Descript_API {

	/** Default Descript API origin. */
	const ENDPOINT = 'https://descriptapi.com/v1';

	/** HTTP timeout in seconds. */
	const TIMEOUT = 60;

	/** Transcript export formats accepted by the API. */
	const TRANSCRIPT_FORMATS = [ 'txt', 'markdown', 'html', 'rtf', 'docx' ];

	/** Speaker label modes accepted by the transcript export endpoint. */
	const SPEAKER_LABEL_MODES = [ 'off', 'changes', 'every_paragraph' ];

	/** Media type accepted by the publish endpoint. */
	const PUBLISH_MEDIA_TYPES = [ 'Video', 'Audio' ];

	/**
	 * Test an unsaved REST configuration.
	 *
	 * @param string $endpoint             Descript API origin.
	 * @param string $credential_reference API token or env:// reference.
	 * @return array|WP_Error
	 */
	public static function test_configuration( string $endpoint, string $credential_reference ) {
		return self::request_to( $endpoint, $credential_reference, 'GET', '/projects', [], [ 'limit' => 1 ] );
	}

	/**
	 * List projects visible to a saved Connection's drive.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $params       Optional query params: created_by, sort, cursor, limit.
	 * @return array|WP_Error
	 */
	public static function list_projects( int $connection_id, array $params = [] ) {
		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return self::request( $connection, 'GET', '/projects', [], $params );
	}

	/**
	 * Get one project's metadata, media, and compositions.
	 *
	 * @param int    $connection_id     Connection post ID.
	 * @param string $remote_project_id Descript project ID.
	 * @return array|WP_Error
	 */
	public static function get_project( int $connection_id, string $remote_project_id ) {
		$remote_project_id = sanitize_text_field( $remote_project_id );
		if ( '' === $remote_project_id ) {
			return new WP_Error( 'descript_project_id_missing', __( 'A Descript project ID is required.', 'worldgraph' ) );
		}

		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return self::request( $connection, 'GET', '/projects/' . rawurlencode( $remote_project_id ) );
	}

	/**
	 * Export a composition's transcript as raw text.
	 *
	 * This is a synchronous endpoint; Descript returns file content directly,
	 * not a JSON envelope.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param array  $args         project_id, composition_id, format, include_speaker_labels,
	 *                              include_markers, timecodes.
	 * @return array|WP_Error {text, format}
	 */
	public static function export_transcript( int $connection_id, array $args ) {
		$remote_project_id = sanitize_text_field( (string) ( $args['project_id'] ?? '' ) );
		if ( '' === $remote_project_id ) {
			return new WP_Error( 'descript_project_id_missing', __( 'A Descript project ID is required to export a transcript.', 'worldgraph' ) );
		}

		$format = strtolower( sanitize_key( (string) ( $args['format'] ?? 'markdown' ) ) );
		if ( ! in_array( $format, self::TRANSCRIPT_FORMATS, true ) ) {
			$format = 'markdown';
		}

		$labels = sanitize_key( (string) ( $args['include_speaker_labels'] ?? 'every_paragraph' ) );
		if ( ! in_array( $labels, self::SPEAKER_LABEL_MODES, true ) ) {
			$labels = 'every_paragraph';
		}

		$body = [
			'project_id'             => $remote_project_id,
			'format'                 => $format,
			'include_speaker_labels' => $labels,
			'include_markers'        => ! empty( $args['include_markers'] ),
			'timecodes'              => [ 'on_paragraphs' => ! empty( $args['timecodes_on_paragraphs'] ) ],
		];
		$composition_id = sanitize_text_field( (string) ( $args['composition_id'] ?? '' ) );
		if ( '' !== $composition_id ) {
			$body['composition_id'] = $composition_id;
		}

		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$text = self::request_raw( $connection, 'POST', '/export/transcript', $body );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return [ 'text' => $text, 'format' => $format ];
	}

	/**
	 * Submit an asynchronous project media import job.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $payload      project_name|project_id, folder_name, team_access, add_media, add_compositions.
	 * @return array|WP_Error {job_id}
	 */
	public static function import_project_media( int $connection_id, array $payload ) {
		if ( empty( $payload['add_media'] ) || ! is_array( $payload['add_media'] ) ) {
			return new WP_Error( 'descript_import_media_missing', __( 'At least one media URL is required to import into Descript.', 'worldgraph' ) );
		}
		if ( empty( $payload['project_name'] ) && empty( $payload['project_id'] ) ) {
			return new WP_Error( 'descript_import_target_missing', __( 'Provide a new project name or an existing Descript project ID.', 'worldgraph' ) );
		}

		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}
		$payload['callback_url'] = self::callback_url( $connection_id );

		return self::request( $connection, 'POST', '/jobs/import/project_media', $payload );
	}

	/**
	 * List recent jobs.
	 *
	 * @param int   $connection_id Connection post ID.
	 * @param array $params       project_id, type, limit, created_after, created_before, cursor.
	 * @return array|WP_Error
	 */
	public static function list_jobs( int $connection_id, array $params = [] ) {
		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return self::request( $connection, 'GET', '/jobs', [], $params );
	}

	/**
	 * Poll one job's status.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $job_id       Descript job ID.
	 * @return array|WP_Error
	 */
	public static function get_job_status( int $connection_id, string $job_id ) {
		$job_id = sanitize_text_field( $job_id );
		if ( '' === $job_id ) {
			return new WP_Error( 'descript_job_id_missing', __( 'The Descript job ID is missing.', 'worldgraph' ) );
		}

		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return self::request( $connection, 'GET', '/jobs/' . rawurlencode( $job_id ) );
	}

	/**
	 * Cancel a queued or running job. Irreversible.
	 *
	 * @param int    $connection_id Connection post ID.
	 * @param string $job_id       Descript job ID.
	 * @return true|WP_Error
	 */
	public static function cancel_job( int $connection_id, string $job_id ) {
		$job_id = sanitize_text_field( $job_id );
		if ( '' === $job_id ) {
			return new WP_Error( 'descript_job_id_missing', __( 'The Descript job ID is missing.', 'worldgraph' ) );
		}

		$connection = self::connection( $connection_id );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$result = self::request( $connection, 'DELETE', '/jobs/' . rawurlencode( $job_id ) );
		return is_wp_error( $result ) ? $result : true;
	}

	/** Build the mandatory public callback URL for a saved Connection. */
	public static function callback_url( int $connection_id ): string {
		$connection_id = absint( $connection_id );
		return add_query_arg(
			[
				'connection_id' => $connection_id,
				'token'         => self::callback_token( $connection_id ),
			],
			rest_url( 'worldgraph/v1/descript/callback' )
		);
	}

	/** Stable WP-salt HMAC used to authenticate a Descript job callback. */
	public static function callback_token( int $connection_id ): string {
		return hash_hmac( 'sha256', 'worldgraph:descript:' . absint( $connection_id ), wp_salt( 'auth' ) );
	}

	/** Verify a token supplied to the public Descript callback route. */
	public static function verify_callback_token( int $connection_id, string $token ): bool {
		$token = trim( $token );
		return '' !== $token && hash_equals( self::callback_token( $connection_id ), $token );
	}

	/** Map Descript job states onto World Graph Studio's terminal vocabulary. */
	public static function normalize_status( string $status ): string {
		$status = strtolower( str_replace( [ ' ', '-' ], '_', trim( $status ) ) );
		if ( in_array( $status, [ 'completed', 'complete', 'succeeded', 'success', 'done' ], true ) ) {
			return 'completed';
		}
		if ( in_array( $status, [ 'failed', 'failure', 'error' ], true ) ) {
			return 'failed';
		}
		if ( in_array( $status, [ 'cancelled', 'canceled' ], true ) ) {
			return 'cancelled';
		}
		if ( in_array( $status, [ 'queued', 'pending', 'processing', 'in_progress', 'running', 'submitted' ], true ) ) {
			return 'submitted';
		}

		return '';
	}

	/** Resolve and validate one saved Descript Connection. */
	private static function connection( int $connection_id ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'descript' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'descript_connection_invalid', __( 'The selected Connection is not a Descript Connection.', 'worldgraph' ) );
		}

		return $connection;
	}

	/** Perform one request for a saved Connection. */
	private static function request( array $connection, string $method, string $path, array $body = [], array $query = [] ) {
		return self::request_to(
			(string) ( $connection['endpoint_url'] ?? self::ENDPOINT ),
			(string) ( $connection['credential_reference'] ?? '' ),
			$method,
			$path,
			$body,
			$query
		);
	}

	/** Perform one authenticated JSON request. */
	private static function request_to( string $endpoint, string $credential_reference, string $method, string $path, array $body = [], array $query = [] ) {
		$headers = self::headers( $credential_reference );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$url = self::api_root( $endpoint ) . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		$args = [ 'timeout' => self::TIMEOUT, 'headers' => $headers ];
		if ( in_array( $method, [ 'POST', 'PUT', 'DELETE' ], true ) ) {
			$args['method'] = $method;
			$args['body']   = wp_json_encode( $body );
			$response       = wp_remote_request( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'descript_api_unreachable', $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw       = (string) wp_remote_retrieve_body( $response );
		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error( 'descript_api_request_failed', self::error_message( $raw, $http_code ), [ 'status' => $http_code ] );
		}
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'descript_api_invalid_response', __( 'Descript returned invalid JSON.', 'worldgraph' ) );
		}

		return $decoded;
	}

	/** Perform an authenticated request expecting a raw (non-JSON) body. */
	private static function request_raw( array $connection, string $method, string $path, array $body = [] ) {
		$headers = self::headers( (string) ( $connection['credential_reference'] ?? '' ) );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$url  = self::api_root( (string) ( $connection['endpoint_url'] ?? self::ENDPOINT ) ) . $path;
		$args = [
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
			'method'  => $method,
			'body'    => wp_json_encode( $body ),
		];

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'descript_api_unreachable', $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw       = (string) wp_remote_retrieve_body( $response );
		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error( 'descript_api_request_failed', self::error_message( $raw, $http_code ), [ 'status' => $http_code ] );
		}

		return $raw;
	}

	/** Build authenticated Descript REST headers. */
	private static function headers( string $credential_reference ) {
		$token = self::resolve_credential( $credential_reference );
		if ( '' === $token ) {
			return new WP_Error( 'descript_credential_missing', __( 'Set a Descript API token or env://DESCRIPT_API_TOKEN reference on this Connection.', 'worldgraph' ) );
		}

		return [
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		];
	}

	/** Normalize the configured origin to its API root, without a trailing slash. */
	private static function api_root( string $endpoint ): string {
		$endpoint = untrailingslashit( trim( $endpoint ) ?: self::ENDPOINT );
		return $endpoint;
	}

	/** Extract a human-readable message from a Descript error body. */
	private static function error_message( string $raw, int $http_code ): string {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$message = (string) ( $decoded['message'] ?? $decoded['error'] ?? '' );
			if ( '' !== $message ) {
				return sprintf( 'Descript API error (HTTP %d): %s', $http_code, $message );
			}
		}

		return sprintf( 'Descript API request failed with HTTP %d.', $http_code );
	}

	/** Resolve a literal token or an env:// environment-variable reference. */
	private static function resolve_credential( string $reference ): string {
		$reference = trim( $reference );
		if ( 0 === strpos( $reference, 'env://' ) ) {
			$name = substr( $reference, 6 );
			if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
				return '';
			}
			$value = getenv( $name );
			return false === $value ? '' : trim( (string) $value );
		}

		return $reference;
	}
}
