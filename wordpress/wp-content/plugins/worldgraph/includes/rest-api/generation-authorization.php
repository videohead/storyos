<?php
/**
 * Authorization helpers for generation requests and background media access.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the requesting user's media permissions attached to queued jobs.
 */
final class Generation_Authorization {

	/** User that submitted a generation job. */
	const REQUESTER_META = '_worldgraph_gen_requested_by';

	/** Filter used by background providers before reading a local attachment. */
	const BACKGROUND_MEDIA_FILTER = 'worldgraph_generation_background_media_authorization';

	/** Whether the default background authorization filter is registered. */
	private static bool $initialized = false;

	/** Register the background attachment authorization policy. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		add_filter( self::BACKGROUND_MEDIA_FILTER, [ __CLASS__, 'filter_background_media_authorization' ], 10, 3 );
		self::$initialized = true;
	}

	/**
	 * Authorize a foreground generation submission.
	 *
	 * @param string               $type      Generation output type.
	 * @param int                  $source_id Selected asset or source post ID.
	 * @param array<string, mixed> $inputs    Sanitized generation inputs.
	 * @param int                  $user_id   Requesting user ID.
	 * @return true|WP_Error
	 */
	public static function authorize_submission( string $type, int $source_id, array $inputs, int $user_id ) {
		if ( ! $user_id || ! user_can( $user_id, 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in with edit permissions.', 'worldgraph' ),
				[ 'status' => $user_id ? 403 : 401 ]
			);
		}

		if ( self::is_media_type( $type ) && ! user_can( $user_id, 'upload_files' ) ) {
			return new WP_Error(
				'worldgraph_generation_upload_forbidden',
				__( 'You are not allowed to upload generated media to this site.', 'worldgraph' ),
				[ 'status' => 403 ]
			);
		}

		if ( $source_id && ! user_can( $user_id, 'edit_post', $source_id ) ) {
			return new WP_Error(
				'worldgraph_generation_source_forbidden',
				__( 'You are not allowed to generate media for the selected item.', 'worldgraph' ),
				[ 'status' => 403 ]
			);
		}

		return self::validate_media_inputs( $inputs, $user_id );
	}

	/**
	 * Revalidate a queued job before a provider reads one of its attachments.
	 *
	 * Providers may call this method directly. The public filter offers the same
	 * policy to integrations that cannot depend on this class directly.
	 *
	 * @param int $job_id        Generation job post ID.
	 * @param int $attachment_id Attachment about to be read or uploaded.
	 * @return true|WP_Error
	 */
	public static function authorize_background_media( int $job_id, int $attachment_id ) {
		return self::filter_background_media_authorization( true, $job_id, $attachment_id );
	}

	/**
	 * Default callback for the background media authorization filter.
	 *
	 * Callers should pass their current authorization result as the first value:
	 * `apply_filters( self::BACKGROUND_MEDIA_FILTER, true, $job_id, $attachment_id )`.
	 *
	 * @param true|false|WP_Error $authorized    Authorization from an earlier callback.
	 * @param int                 $job_id        Generation job post ID.
	 * @param int                 $attachment_id Attachment about to be read or uploaded.
	 * @return true|WP_Error
	 */
	public static function filter_background_media_authorization( $authorized, int $job_id, int $attachment_id ) {
		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}
		if ( true !== $authorized ) {
			return new WP_Error(
				'worldgraph_generation_attachment_forbidden',
				__( 'The generation attachment was not authorized for background use.', 'worldgraph' ),
				[ 'status' => 403 ]
			);
		}

		$job = get_post( $job_id );
		if ( ! $job instanceof \WP_Post || 'worldgraph_gen' !== $job->post_type ) {
			return new WP_Error(
				'worldgraph_generation_job_invalid',
				__( 'The generation job could not be authorized.', 'worldgraph' ),
				[ 'status' => 404 ]
			);
		}

		$user_id = absint( get_post_meta( $job_id, self::REQUESTER_META, true ) );
		if ( ! $user_id ) {
			return new WP_Error(
				'worldgraph_generation_requester_missing',
				__( 'The generation job has no requesting user to authorize.', 'worldgraph' ),
				[ 'status' => 403 ]
			);
		}

		$inputs = get_post_meta( $job_id, '_worldgraph_gen_inputs', true );
		$inputs = is_array( $inputs ) ? $inputs : [];
		$result = self::authorize_submission(
			(string) get_post_meta( $job_id, '_worldgraph_gen_type', true ),
			absint( $job->post_parent ),
			$inputs,
			$user_id
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_ids = self::attachment_ids( $inputs );
		if ( is_wp_error( $attachment_ids ) ) {
			return $attachment_ids;
		}
		if ( ! in_array( $attachment_id, $attachment_ids, true ) ) {
			return new WP_Error(
				'worldgraph_generation_attachment_not_bound',
				__( 'That attachment is not bound to this generation job.', 'worldgraph' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Validate all numeric values in known media slots for one user.
	 *
	 * @param array<string, mixed> $inputs  Generation inputs.
	 * @param int                  $user_id Requesting user ID.
	 * @return true|WP_Error
	 */
	public static function validate_media_inputs( array $inputs, int $user_id ) {
		$attachment_ids = self::attachment_ids( $inputs );
		if ( is_wp_error( $attachment_ids ) ) {
			return $attachment_ids;
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
				return new WP_Error(
					'worldgraph_generation_attachment_invalid',
					__( 'Each numeric media input must identify a WordPress attachment.', 'worldgraph' ),
					[ 'status' => 400 ]
				);
			}

			if ( ! user_can( $user_id, 'read_post', $attachment_id ) && ! user_can( $user_id, 'edit_post', $attachment_id ) ) {
				return new WP_Error(
					'worldgraph_generation_attachment_forbidden',
					__( 'You are not allowed to use one of the selected media attachments.', 'worldgraph' ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Extract positive integer attachment IDs from known media slots.
	 *
	 * Media values may only be positive attachment IDs or validated HTTPS URLs.
	 * Rejecting arbitrary strings prevents a bound meta value from becoming a
	 * local filesystem path inside a background provider adapter.
	 *
	 * @param array<string, mixed> $inputs Generation inputs.
	 * @return array<int, int>|WP_Error
	 */
	private static function attachment_ids( array $inputs ) {
		$attachment_ids = [];
		foreach ( \WorldGraph\Utils\Generation_Modality::MEDIA_SLOTS as $slot ) {
			if ( ! array_key_exists( $slot, $inputs ) ) {
				continue;
			}
			if ( ! is_scalar( $inputs[ $slot ] ) ) {
				return new WP_Error( 'worldgraph_generation_media_input_invalid', __( 'Media inputs must be WordPress attachment IDs or validated HTTPS URLs.', 'worldgraph' ), [ 'status' => 400 ] );
			}

			$value = trim( (string) $inputs[ $slot ] );
			if ( '' === $value ) {
				continue;
			}
			if ( is_numeric( $value ) ) {
				if ( ! ctype_digit( $value ) || 0 === absint( $value ) ) {
					return new WP_Error(
						'worldgraph_generation_attachment_invalid',
						__( 'Numeric media inputs must be positive WordPress attachment IDs.', 'worldgraph' ),
						[ 'status' => 400 ]
					);
				}
				$attachment_ids[] = absint( $value );
				continue;
			}

			if ( 0 !== stripos( $value, 'https://' ) || ! wp_http_validate_url( $value ) ) {
				return new WP_Error(
					'worldgraph_generation_media_input_invalid',
					__( 'Media inputs must be WordPress attachment IDs or validated HTTPS URLs.', 'worldgraph' ),
					[ 'status' => 400 ]
				);
			}
		}

		return array_values( array_unique( $attachment_ids ) );
	}

	/** Whether a generation output creates a WordPress media attachment. */
	private static function is_media_type( string $type ): bool {
		return in_array( sanitize_key( $type ), [ 'image', 'video', 'audio' ], true );
	}
}
