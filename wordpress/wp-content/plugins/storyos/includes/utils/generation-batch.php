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
			Generation_Log::add( 'debug', 'generation_batch', 'Batch already running; skipped.' );
			return;
		}

		set_transient( self::LOCK, 1, 55 );
		Generation_Log::add( 'debug', 'generation_batch', 'Batch run starting.' );
		try {
			self::poll_submitted_jobs();
			self::submit_queued_jobs();
		} finally {
			delete_transient( self::LOCK );
		}

		if ( self::has_active_jobs() ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
			Generation_Log::add( 'debug', 'generation_batch', 'Active jobs remain; rescheduled in 60s.' );
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
			$provider_type = 'local_comfyui' === get_post_meta( $job_id, '_storyos_generation_provider_type', true ) ? 'local_comfyui' : 'comfy_cloud_mcp';
			$client = 'local_comfyui' === $provider_type ? Local_ComfyUI::class : Comfy_Cloud_MCP::class;
			$connection_id = absint( get_post_meta( $job_id, '_storyos_generation_connection_id', true ) );
			$params = (array) get_post_meta( $job_id, '_storyos_generation_params', true );
			$inputs = get_post_meta( $job_id, '_storyos_generation_inputs', true );
			if ( is_array( $inputs ) && ! empty( $inputs ) ) {
				$params['inputs'] = $inputs;
			}

			Generation_Log::add( 'info', 'generation_batch', sprintf( 'Submitting job %d via %s.', $job_id, $provider_type ), [], (string) $job_id );
			$result = $client::run_template(
				(string) get_post_meta( $job_id, '_storyos_generation_workflow', true ),
				(string) get_post_meta( $job_id, '_storyos_generation_prompt', true ),
				$params,
				$connection_id
			);

			if ( is_wp_error( $result ) ) {
				update_post_meta( $job_id, '_storyos_generation_status', 'failed' );
				update_post_meta( $job_id, '_storyos_generation_error', $result->get_error_message() );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d failed to submit: %s', $job_id, $result->get_error_message() ), [], (string) $job_id );
				continue;
			}

			$remote_job_id = sanitize_text_field( (string) ( $result['job_id'] ?? $result['id'] ?? $result['prompt_id'] ?? '' ) );
			if ( '' === $remote_job_id ) {
				update_post_meta( $job_id, '_storyos_generation_status', 'failed' );
				update_post_meta( $job_id, '_storyos_generation_error', 'The generation provider did not return a job ID.' );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d: provider did not return a job ID.', $job_id ), $result, (string) $job_id );
				continue;
			}

			update_post_meta( $job_id, '_storyos_generation_job_id', $remote_job_id );
			update_post_meta( $job_id, '_storyos_generation_status', 'submitted' );
			Generation_Log::add( 'info', 'generation_batch', sprintf( 'Job %d submitted as remote job %s.', $job_id, $remote_job_id ), [], (string) $job_id );
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
			$client = 'local_comfyui' === get_post_meta( $job_id, '_storyos_generation_provider_type', true ) ? Local_ComfyUI::class : Comfy_Cloud_MCP::class;
			$result = $client::get_job_status(
				(string) get_post_meta( $job_id, '_storyos_generation_job_id', true ),
				absint( get_post_meta( $job_id, '_storyos_generation_connection_id', true ) )
			);
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$status = sanitize_key( (string) ( $result['status'] ?? 'submitted' ) );
			if ( in_array( $status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
				update_post_meta( $job_id, '_storyos_generation_status', $status );
				update_post_meta( $job_id, '_storyos_generation_result', $result );
				Generation_Log::add( 'info', 'generation_batch', sprintf( 'Job %d reached status: %s.', $job_id, $status ), [], (string) $job_id );

				if ( 'completed' === $status && in_array( get_post_meta( $job_id, '_storyos_generation_type', true ), [ 'image', 'video' ], true ) ) {
					$asset = Asset_Generator::import_completed_job( $job_id, $result );
					if ( is_wp_error( $asset ) ) {
						update_post_meta( $job_id, '_storyos_generation_status', 'failed' );
						update_post_meta( $job_id, '_storyos_generation_error', $asset->get_error_message() );
						Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d asset import failed: %s', $job_id, $asset->get_error_message() ), [], (string) $job_id );
					} else {
						update_post_meta( $job_id, '_storyos_generation_attachment_id', $asset['attachment_id'] );
						update_post_meta( $job_id, '_storyos_generation_asset_id', $asset['asset_id'] );
					}
				}
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