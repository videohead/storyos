<?php
/**
 * WordPress cron processor for Comfy Cloud generation jobs.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Batch {
	const HOOK = 'storyos_process_generation_batch';
	const LOCK = 'storyos_generation_batch_lock';

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'process' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK );
		}
	}

	public static function process(): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 55 );
		try {
			self::poll_submitted_jobs();
			self::submit_queued_jobs();
		} finally {
			delete_transient( self::LOCK );
		}

		if ( self::has_active_jobs() ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
	}

	private static function submit_queued_jobs(): void {
		$jobs = get_posts( [
			'post_type'      => 'storyos_generation',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'meta_key'       => '_storyos_generation_status',
			'meta_value'     => 'queued',
		] );

		foreach ( $jobs as $job_id ) {
			$result = Comfy_Cloud_MCP::run_template(
				(string) get_post_meta( $job_id, '_storyos_generation_workflow', true ),
				(string) get_post_meta( $job_id, '_storyos_generation_prompt', true ),
				(array) get_post_meta( $job_id, '_storyos_generation_params', true )
			);

			if ( is_wp_error( $result ) ) {
				update_post_meta( $job_id, '_storyos_generation_status', 'failed' );
				update_post_meta( $job_id, '_storyos_generation_error', $result->get_error_message() );
				continue;
			}

			$remote_job_id = sanitize_text_field( (string) ( $result['job_id'] ?? $result['id'] ?? '' ) );
			if ( '' === $remote_job_id ) {
				update_post_meta( $job_id, '_storyos_generation_status', 'failed' );
				update_post_meta( $job_id, '_storyos_generation_error', 'Comfy Cloud MCP did not return a job ID.' );
				continue;
			}

			update_post_meta( $job_id, '_storyos_generation_job_id', $remote_job_id );
			update_post_meta( $job_id, '_storyos_generation_status', 'submitted' );
		}
	}

	private static function poll_submitted_jobs(): void {
		$jobs = get_posts( [
			'post_type'      => 'storyos_generation',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'meta_key'       => '_storyos_generation_status',
			'meta_value'     => 'submitted',
		] );

		foreach ( $jobs as $job_id ) {
			$result = Comfy_Cloud_MCP::get_job_status( (string) get_post_meta( $job_id, '_storyos_generation_job_id', true ) );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$status = sanitize_key( (string) ( $result['status'] ?? 'submitted' ) );
			if ( in_array( $status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
				update_post_meta( $job_id, '_storyos_generation_status', $status );
				update_post_meta( $job_id, '_storyos_generation_result', $result );
			}
		}
	}

	private static function has_active_jobs(): bool {
		return (bool) get_posts( [
			'post_type'      => 'storyos_generation',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_storyos_generation_status', 'value' => [ 'queued', 'submitted' ], 'compare' => 'IN' ],
			],
		] );
	}
}