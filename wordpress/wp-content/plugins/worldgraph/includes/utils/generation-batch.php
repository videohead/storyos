<?php
/**
 * WordPress cron processor for Comfy Cloud generation jobs.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generation_Batch {
	const HOOK = 'worldgraph_process_generation_batch';
	const LOCK = 'worldgraph_gen_batch_lock';

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
			'post_type'      => 'worldgraph_gen',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'meta_key'       => '_worldgraph_gen_status',
			'meta_value'     => 'queued',
		] );

		foreach ( $jobs as $job_id ) {
			$connection_id = absint( get_post_meta( $job_id, '_worldgraph_gen_connection_id', true ) );
			$connection = Connection_Repository::get( $connection_id );
			if ( ! $connection || 'disabled' === $connection['status'] ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', 'The generation Template has no available Connection.' );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d has no available Connection.', $job_id ), [], (string) $job_id );
				continue;
			}
			$provider_type = $connection['provider_type'];
			Connection_Adapters::load( (string) $provider_type );
			if ( ! in_array( $provider_type, [ 'comfyui', 'fal', 'elevenlabs', 'suno' ], true ) ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', sprintf( 'No generation adapter is registered for provider: %s.', $provider_type ) );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d has no adapter for provider %s.', $job_id, $provider_type ), [], (string) $job_id );
				continue;
			}
			$client = self::client_for_job( $job_id, $connection );
			$params = (array) get_post_meta( $job_id, '_worldgraph_gen_params', true );
			$template_id = absint( get_post_meta( $job_id, '_worldgraph_gen_template_id', true ) );
			if ( in_array( $provider_type, [ 'fal', 'elevenlabs', 'suno' ], true ) && $template_id ) {
				$params = array_merge( self::template_input( $template_id ), $params );
			}
			$inputs = get_post_meta( $job_id, '_worldgraph_gen_inputs', true );
			if ( 'fal' === $provider_type && is_array( $inputs ) && ! empty( $inputs ) ) {
				$params = array_merge( $params, $inputs );
			} elseif ( is_array( $inputs ) && ! empty( $inputs ) ) {
				$params['inputs'] = $inputs;
			}

			Generation_Log::add( 'info', 'generation_batch', sprintf( 'Submitting job %d via %s.', $job_id, $provider_type ), [], (string) $job_id );
			$result = $client::run_template(
				(string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true ),
				(string) get_post_meta( $job_id, '_worldgraph_gen_prompt', true ),
				$params,
				$connection_id
			);

			if ( is_wp_error( $result ) && Comfy_Cloud_MCP::class === $client && 'local' === ( $connection['environment'] ?? '' ) ) {
				$template_ref = (string) absint( get_post_meta( $job_id, '_worldgraph_gen_template_id', true ) );
				if ( '' === $template_ref ) {
					$template_ref = (string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true );
				}

				Generation_Log::add(
					'warning',
					'generation_batch',
					sprintf( 'Job %d MCP submission failed (%s). Retrying via local ComfyUI API.', $job_id, $result->get_error_message() ),
					[],
					(string) $job_id
				);

				$fallback = Local_ComfyUI::run_template(
					$template_ref,
					(string) get_post_meta( $job_id, '_worldgraph_gen_prompt', true ),
					$params,
					$connection_id
				);

				if ( ! is_wp_error( $fallback ) ) {
					$result = $fallback;
					$client = Local_ComfyUI::class;
					update_post_meta( $job_id, '_worldgraph_gen_adapter', 'local_comfyui' );
				}
			}

			if ( is_wp_error( $result ) ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', $result->get_error_message() );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d failed to submit: %s', $job_id, $result->get_error_message() ), [], (string) $job_id );
				continue;
			}
			if ( 'completed' === sanitize_key( (string) ( $result['status'] ?? '' ) ) ) {
				self::complete_job( $job_id, $result );
				continue;
			}

			$remote_job_id = sanitize_text_field( (string) ( $result['job_id'] ?? $result['id'] ?? $result['prompt_id'] ?? '' ) );
			if ( '' === $remote_job_id ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', 'The generation provider did not return a job ID.' );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d: provider did not return a job ID.', $job_id ), $result, (string) $job_id );
				continue;
			}

			update_post_meta( $job_id, '_worldgraph_gen_job_id', $remote_job_id );
			update_post_meta( $job_id, '_worldgraph_gen_status', 'submitted' );
			Generation_Log::add( 'info', 'generation_batch', sprintf( 'Job %d submitted as remote job %s.', $job_id, $remote_job_id ), [], (string) $job_id );
		}
	}

	private static function poll_submitted_jobs(): void {
		$jobs = get_posts( [
			'post_type'      => 'worldgraph_gen',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'meta_key'       => '_worldgraph_gen_status',
			'meta_value'     => 'submitted',
		] );

		foreach ( $jobs as $job_id ) {
			$connection_id = absint( get_post_meta( $job_id, '_worldgraph_gen_connection_id', true ) );
			$connection = Connection_Repository::get( $connection_id );
			if ( ! $connection || ! in_array( $connection['provider_type'], [ 'comfyui', 'fal', 'suno' ], true ) ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', 'No generation adapter is registered for this Connection provider.' );
				continue;
			}
			Connection_Adapters::load( (string) $connection['provider_type'] );
			$client = self::client_for_job( $job_id, $connection );
			if ( in_array( $client, [ Fal_MCP::class, Suno_API::class, Suno_MCP::class ], true ) ) {
				$result = $client::get_job_status(
					(string) get_post_meta( $job_id, '_worldgraph_gen_job_id', true ),
					$connection_id,
					(string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true )
				);
			} else {
				$result = $client::get_job_status(
					(string) get_post_meta( $job_id, '_worldgraph_gen_job_id', true ),
					$connection_id
				);
			}
			if ( is_wp_error( $result ) ) {
				Generation_Log::add(
					'error',
					'generation_batch',
					sprintf( 'Job %d status poll failed: %s', $job_id, $result->get_error_message() ),
					[],
					(string) $job_id,
					$connection_id
				);
				continue;
			}

			$status = sanitize_key( (string) ( $result['status'] ?? 'submitted' ) );
			if ( in_array( $status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
				self::complete_job( $job_id, $result );
			}
		}
	}

	private static function has_active_jobs(): bool {
		return (bool) get_posts( [
			'post_type'      => 'worldgraph_gen',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_worldgraph_gen_status', 'value' => [ 'queued', 'submitted' ], 'compare' => 'IN' ],
			],
		] );
	}

	/**
	 * Resolve the generation adapter a queued job should run on.
	 *
	 * @param int   $job_id      Generation job post ID.
	 * @param array $connection  Resolved Connection record.
	 * @return string
	 */
	private static function client_for_job( int $job_id, array $connection ): string {
		if ( 'elevenlabs' === ( $connection['provider_type'] ?? '' ) ) {
			return ElevenLabs_API::class;
		}
		if ( 'fal' === ( $connection['provider_type'] ?? '' ) ) {
			return Fal_MCP::class;
		}
		if ( 'suno' === ( $connection['provider_type'] ?? '' ) ) {
			$template = trim( (string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true ) );
			return str_starts_with( $template, 'mcp:' ) ? Suno_MCP::class : Suno_API::class;
		}

		$adapter = sanitize_key( (string) get_post_meta( $job_id, '_worldgraph_gen_adapter', true ) );
		if ( 'local_comfyui' === $adapter ) {
			return Local_ComfyUI::class;
		}

		// Backward compatibility for older jobs that predate adapter metadata.
		if ( 'local' === ( $connection['environment'] ?? '' ) ) {
			$template = trim( (string) get_post_meta( $job_id, '_worldgraph_gen_workflow', true ) );
			if ( '' !== $template && ctype_digit( $template ) ) {
				return Local_ComfyUI::class;
			}
		}

		return Comfy_Cloud_MCP::class;
	}

	/** Complete a terminal provider result, importing all media before success. */
	private static function complete_job( int $job_id, array $result ): void {
		$status = sanitize_key( (string) ( $result['status'] ?? 'completed' ) );
		if ( 'completed' === $status && in_array( get_post_meta( $job_id, '_worldgraph_gen_type', true ), [ 'image', 'video', 'audio' ], true ) ) {
			$asset = Asset_Generator::import_completed_job( $job_id, $result );
			if ( is_wp_error( $asset ) ) {
				update_post_meta( $job_id, '_worldgraph_gen_status', 'failed' );
				update_post_meta( $job_id, '_worldgraph_gen_error', $asset->get_error_message() );
				Generation_Log::add( 'error', 'generation_batch', sprintf( 'Job %d asset import failed: %s', $job_id, $asset->get_error_message() ), [], (string) $job_id );
				return;
			}
			update_post_meta( $job_id, '_worldgraph_gen_attachment_id', $asset['attachment_id'] );
			update_post_meta( $job_id, '_worldgraph_gen_attachment_ids', $asset['attachment_ids'] ?? [ $asset['attachment_id'] ] );
			update_post_meta( $job_id, '_worldgraph_gen_asset_id', $asset['asset_id'] );
		}

		// Never persist raw synchronous provider bytes in post meta.
		unset( $result['audio_data'] );
		unset( $result['audio_items'] );
		update_post_meta( $job_id, '_worldgraph_gen_status', $status );
		update_post_meta( $job_id, '_worldgraph_gen_result', $result );
		Generation_Log::add( 'info', 'generation_batch', sprintf( 'Job %d reached status: %s.', $job_id, $status ), [], (string) $job_id );
	}

	/** Read provider defaults provisioned onto a Template. */
	private static function template_input( int $template_id ): array {
		$configuration = json_decode( (string) get_post_meta( $template_id, 'configuration_json', true ), true );
		return is_array( $configuration ) && is_array( $configuration['input'] ?? null ) ? $configuration['input'] : [];
	}
}
