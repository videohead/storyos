<?php
/**
 * First-run provisioning and readiness probing for a local ComfyUI Connection.
 *
 * ComfyUI normally loads its own default text-to-image workflow the first time
 * it starts, but only if the matching nodes and checkpoint are actually
 * installed. StoryOS cannot assume any of that, so this class probes the
 * instance, provisions the single text-to-image Template that the asset
 * generator falls back to, and reports the remaining work as ordered steps an
 * operator can be walked through.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local ComfyUI provisioning and readiness reporting.
 */
class Comfy_Bootstrap {

	/**
	 * Wizard slot marker for the provisioned text-to-image Template.
	 */
	const TEMPLATE_SLOT = 'local_comfyui_text_to_image';

	/**
	 * Transient holding the last readiness report.
	 */
	const STATUS_TRANSIENT = 'storyos_comfy_readiness';

	/**
	 * How long a readiness report stays cached, in seconds.
	 */
	const STATUS_TTL = 300;

	/**
	 * The checkpoint ComfyUI's own default text-to-image workflow loads.
	 */
	const DEFAULT_CHECKPOINT = 'v1-5-pruned-emaonly-fp16.safetensors';

	/**
	 * Where that checkpoint is published, for the one-click model install.
	 */
	const DEFAULT_CHECKPOINT_URL = 'https://huggingface.co/Comfy-Org/stable-diffusion-v1-5-archive/resolve/main/v1-5-pruned-emaonly-fp16.safetensors';

	/**
	 * The node class ComfyUI's default text-to-image graph loads its model with.
	 */
	const CHECKPOINT_NODE = 'CheckpointLoaderSimple';

	/**
	 * The readiness report for the configured local ComfyUI instance.
	 *
	 * @param bool $refresh Re-probe instead of reading the cached report.
	 * @return array{ready: bool, endpoint: string, template_id: int, steps: array<int, array>}
	 */
	public static function status( bool $refresh = false ): array {
		if ( ! $refresh ) {
			$cached = get_transient( self::STATUS_TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		if ( $refresh ) {
			Comfy_Manifest::flush_catalog();
		}

		$status = self::probe();
		set_transient( self::STATUS_TRANSIENT, $status, self::STATUS_TTL );

		return $status;
	}

	/**
	 * Drop the cached readiness report.
	 */
	public static function flush(): void {
		delete_transient( self::STATUS_TRANSIENT );
	}

	/**
	 * The Template the local text-to-image fallback runs: the provisioned one,
	 * or any other ComfyUI text-to-image Template already on the site.
	 *
	 * @return int Template post ID, or 0 when none exists.
	 */
	public static function template_id(): int {
		$provisioned = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'storyos_wizard_slot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => self::TEMPLATE_SLOT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		] );
		if ( $provisioned ) {
			return (int) $provisioned[0];
		}

		$existing = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'modality',
					'value' => Generation_Modality::TEXT_TO_IMAGE,
				],
				[
					'key'   => 'provider_type',
					'value' => 'comfyui',
				],
			],
		] );

		return $existing ? (int) $existing[0] : 0;
	}

	/**
	 * Provision the text-to-image Template a local ComfyUI Connection needs,
	 * pointing it at a checkpoint the instance actually has where possible.
	 *
	 * @param int $connection_id Parent storyos_connection post ID.
	 * @return int Template post ID, or 0 when the Template could not be created.
	 */
	public static function ensure_template( int $connection_id = 0 ): int {
		self::flush();

		$existing = self::template_id();
		if ( $existing ) {
			update_post_meta( $existing, 'storyos_wizard_slot', self::TEMPLATE_SLOT );
			update_post_meta( $existing, 'modality', Generation_Modality::TEXT_TO_IMAGE );
			update_post_meta( $existing, 'generation_structure', Generation_Modality::output_type( Generation_Modality::TEXT_TO_IMAGE ) );
			update_post_meta( $existing, 'provider_type', 'comfyui' );
			update_post_meta( $existing, 'status', 'active' );
			self::remove_legacy_parameter_defaults( $existing );
			$checkpoint = self::detect_checkpoint();
			update_post_meta( $existing, 'checkpoint', $checkpoint );
			update_post_meta( $existing, 'model_requirements', (string) wp_json_encode( [ self::download_entry( $checkpoint ) ] ) );
			if ( $connection_id ) {
				update_post_meta( $existing, 'connection_id', (string) $connection_id );
			}

			self::retire_legacy_templates( $existing, $connection_id );

			return $existing;
		}

		$checkpoint = self::detect_checkpoint();

		$template_id = \StoryOS\CPT\Template::upsert_managed(
			self::TEMPLATE_SLOT,
			__( 'Local ComfyUI Text to Image', 'storyos' ),
			[
				'connection_id'        => (string) $connection_id,
				'generation_structure' => Generation_Modality::output_type( Generation_Modality::TEXT_TO_IMAGE ),
				'modality'             => Generation_Modality::TEXT_TO_IMAGE,
				'provider_type'        => 'comfyui',
				'status'               => 'active',
				'checkpoint'           => $checkpoint,
				'model_requirements'   => (string) wp_json_encode( [ self::download_entry( $checkpoint ) ] ),
			]
		);

		if ( $template_id ) {
			self::retire_legacy_templates( $template_id, $connection_id );
		}

		return $template_id;
	}

	/**
	 * Remove bootstrap-era hardcoded template parameter JSON so runtime values
	 * can inherit from the source project's media profile.
	 *
	 * @param int $template_id Template post ID.
	 * @return void
	 */
	private static function remove_legacy_parameter_defaults( int $template_id ): void {
		$decoded = json_decode( (string) get_post_meta( $template_id, 'configuration_json', true ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['parameters'] ) || ! is_array( $decoded['parameters'] ) ) {
			return;
		}

		$legacy_defaults = Generation_Modality::default_settings( Generation_Modality::TEXT_TO_IMAGE );
		$parameters      = $decoded['parameters'];
		$keys            = array_keys( $legacy_defaults );

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $parameters ) || (string) $parameters[ $key ] !== (string) $legacy_defaults[ $key ] ) {
				return;
			}
		}

		if ( count( array_intersect( array_keys( $parameters ), $keys ) ) === count( $parameters ) ) {
			delete_post_meta( $template_id, 'configuration_json' );
		}
	}

	/**
	 * Keep one active local ComfyUI text-to-image Template and retire legacy
	 * managed Templates oriented around text-to-video and frame-guided flows.
	 *
	 * @param int $keep_id       Template post ID to keep active.
	 * @param int $connection_id Optional parent storyos_connection post ID.
	 * @return void
	 */
	private static function retire_legacy_templates( int $keep_id, int $connection_id = 0 ): void {
		$templates = get_posts( [
			'post_type'      => 'storyos_template',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'provider_type',
					'value' => 'comfyui',
				],
			],
		] );

		$legacy_modalities = [
			Generation_Modality::TEXT_TO_VIDEO,
			Generation_Modality::TEXT_IMAGE_TO_VIDEO,
			Generation_Modality::VIDEO_TO_VIDEO,
			Generation_Modality::VIDEO_WITH_AUDIO,
		];

		foreach ( $templates as $template_id ) {
			$template_id = (int) $template_id;
			if ( $template_id === $keep_id ) {
				continue;
			}

			$slot          = (string) get_post_meta( $template_id, 'storyos_wizard_slot', true );
			$modality      = (string) get_post_meta( $template_id, 'modality', true );
			$template_conn = (int) get_post_meta( $template_id, 'connection_id', true );

			$is_legacy_managed = 'local_comfyui_default' === $slot;
			$is_video_template = in_array( $modality, $legacy_modalities, true );

			if ( ! $is_legacy_managed && ! $is_video_template ) {
				continue;
			}

			if ( $connection_id && ! $is_legacy_managed && $template_conn && $template_conn !== $connection_id ) {
				continue;
			}

			wp_trash_post( $template_id );
		}
	}

	/**
	 * A checkpoint the connected ComfyUI can load, preferring the one its own
	 * default text-to-image workflow uses.
	 *
	 * @return string
	 */
	public static function detect_checkpoint(): string {
		$installed = Comfy_Manifest::installed_files( self::CHECKPOINT_NODE, 'ckpt_name' );
		if ( is_wp_error( $installed ) || empty( $installed ) ) {
			return self::DEFAULT_CHECKPOINT;
		}

		foreach ( $installed as $filename ) {
			if ( false !== stripos( (string) $filename, 'v1-5-pruned-emaonly' ) ) {
				return (string) $filename;
			}
		}

		return self::DEFAULT_CHECKPOINT;
	}

	/**
	 * Probe the instance and build the ordered setup steps.
	 *
	 * @return array
	 */
	private static function probe(): array {
		$endpoint = Local_ComfyUI::endpoint();
		$steps    = [];

		if ( '' === $endpoint ) {
			$steps[] = self::step(
				'endpoint',
				__( 'ComfyUI address', 'storyos' ),
				'todo',
				__( 'No local ComfyUI URL is set. Enter one in the Generation Connection section, e.g. http://host.lando.internal:8188 when ComfyUI runs on the Lando host.', 'storyos' )
			);

			return self::report( $endpoint, 0, $steps );
		}

		$steps[] = self::step(
			'endpoint',
			__( 'ComfyUI address', 'storyos' ),
			'ok',
			/* translators: %s: ComfyUI base URL. */
			sprintf( __( 'StoryOS calls ComfyUI at %s.', 'storyos' ), $endpoint )
		);

		$stats = wp_remote_get( $endpoint . '/system_stats', [ 'timeout' => 10 ] );
		if ( is_wp_error( $stats ) ) {
			$steps[] = self::step(
				'server',
				__( 'ComfyUI is running', 'storyos' ),
				'error',
				sprintf(
					/* translators: %s: HTTP error message. */
					__( 'ComfyUI did not answer: %s. Start ComfyUI, then re-check. From a container, localhost refers to the container, not the host.', 'storyos' ),
					$stats->get_error_message()
				)
			);

			return self::report( $endpoint, 0, $steps );
		}
		$code = wp_remote_retrieve_response_code( $stats );
		if ( $code < 200 || $code >= 300 ) {
			$steps[] = self::step(
				'server',
				__( 'ComfyUI is running', 'storyos' ),
				'error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'ComfyUI returned HTTP %d from /system_stats. Confirm the URL points at the ComfyUI API server.', 'storyos' ),
					(int) $code
				)
			);

			return self::report( $endpoint, 0, $steps );
		}

		$steps[] = self::step( 'server', __( 'ComfyUI is running', 'storyos' ), 'ok', __( 'The API server answered its status request.', 'storyos' ) );

		$checkpoints = Comfy_Manifest::installed_files( self::CHECKPOINT_NODE, 'ckpt_name', $endpoint );
		if ( is_wp_error( $checkpoints ) ) {
			$steps[] = self::step(
				'workflow',
				__( 'Default text-to-image workflow', 'storyos' ),
				'error',
				sprintf(
					/* translators: %s: error message from the node catalog read. */
					__( 'StoryOS could not read the ComfyUI node catalog: %s. Without it, ComfyUI has not loaded the nodes its default text-to-image workflow needs.', 'storyos' ),
					$checkpoints->get_error_message()
				)
			);

			return self::report( $endpoint, 0, $steps );
		}

		$steps[] = self::step(
			'workflow',
			__( 'Default text-to-image workflow', 'storyos' ),
			'ok',
			__( 'ComfyUI has loaded the checkpoint, prompt, sampler, and save nodes the built-in text-to-image graph uses.', 'storyos' )
		);

		if ( empty( $checkpoints ) ) {
			$steps[] = self::step(
				'models',
				__( 'Checkpoint installed', 'storyos' ),
				'todo',
				sprintf(
					/* translators: 1: checkpoint filename, 2: download URL. */
					__( 'ComfyUI has no checkpoint installed, so its default text-to-image workflow cannot run. Install one into models/checkpoints — ComfyUI\'s own default is %1$s (%2$s) — or use the Template\'s "Install missing models" button, then re-check.', 'storyos' ),
					self::DEFAULT_CHECKPOINT,
					self::DEFAULT_CHECKPOINT_URL
				)
			);
		} else {
			$sample = array_slice( array_map( 'strval', $checkpoints ), 0, 3 );
			$steps[] = self::step(
				'models',
				__( 'Checkpoint installed', 'storyos' ),
				'ok',
				sprintf(
					/* translators: 1: number of checkpoints, 2: comma-separated filenames. */
					_n( 'ComfyUI reports %1$d checkpoint: %2$s.', 'ComfyUI reports %1$d checkpoints, including %2$s.', count( $checkpoints ), 'storyos' ),
					count( $checkpoints ),
					implode( ', ', $sample )
				)
			);
		}

		$template_id = self::template_id();
		if ( ! $template_id ) {
			$steps[] = self::step(
				'template',
				__( 'Text-to-image Template', 'storyos' ),
				'todo',
				__( 'No text-to-image Template exists yet. Create one so story elements have a workflow to generate against.', 'storyos' ),
				'provision'
			);

			return self::report( $endpoint, 0, $steps );
		}

		$steps[] = self::step(
			'template',
			__( 'Text-to-image Template', 'storyos' ),
			'ok',
			sprintf(
				/* translators: %s: Template title. */
				__( 'Generating against the "%s" Template.', 'storyos' ),
				get_the_title( $template_id )
			),
			'',
			(string) get_edit_post_link( $template_id, '' )
		);

		$steps[] = self::requirements_step( $template_id, $endpoint );

		return self::report( $endpoint, $template_id, $steps );
	}

	/**
	 * Check the provisioned Template against the live instance.
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $endpoint    ComfyUI base URL.
	 * @return array
	 */
	private static function requirements_step( int $template_id, string $endpoint ): array {
		$report = Comfy_Manifest::validate( $template_id, $endpoint );
		if ( is_wp_error( $report ) ) {
			return self::step( 'requirements', __( 'Template requirements', 'storyos' ), 'error', $report->get_error_message() );
		}
		if ( ! empty( $report['ok'] ) ) {
			return self::step( 'requirements', __( 'Template requirements', 'storyos' ), 'ok', __( 'Every node and model this Template needs is installed.', 'storyos' ) );
		}

		$problems = [];
		if ( ! empty( $report['missing_nodes'] ) ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated ComfyUI node class names. */
				__( 'Install the custom nodes providing: %s.', 'storyos' ),
				implode( ', ', $report['missing_nodes'] )
			);
		}
		foreach ( $report['missing_models'] as $model ) {
			$problems[] = sprintf(
				/* translators: 1: model filename, 2: ComfyUI models sub-directory. */
				__( 'Install %1$s into models/%2$s.', 'storyos' ),
				(string) $model['filename'],
				(string) $model['folder']
			);
		}

		return self::step(
			'requirements',
			__( 'Template requirements', 'storyos' ),
			'todo',
			implode( ' ', $problems ),
			'',
			(string) get_edit_post_link( $template_id, '' )
		);
	}

	/**
	 * Build one setup step.
	 *
	 * @param string $id      Step identifier.
	 * @param string $label   Human-readable step name.
	 * @param string $state   One of ok, todo, error.
	 * @param string $message Guidance for the operator.
	 * @param string $action  Optional action the readiness UI can offer.
	 * @param string $url     Optional link to the screen that resolves the step.
	 * @return array
	 */
	private static function step( string $id, string $label, string $state, string $message, string $action = '', string $url = '' ): array {
		return [
			'id'      => $id,
			'label'   => $label,
			'state'   => $state,
			'message' => $message,
			'action'  => $action,
			'url'     => $url,
		];
	}

	/**
	 * Assemble the report from its steps.
	 *
	 * @param string $endpoint    ComfyUI base URL.
	 * @param int    $template_id Template post ID, or 0.
	 * @param array  $steps       Ordered steps.
	 * @return array
	 */
	private static function report( string $endpoint, int $template_id, array $steps ): array {
		$ready = true;
		foreach ( $steps as $step ) {
			if ( 'ok' !== $step['state'] ) {
				$ready = false;
				break;
			}
		}

		return [
			'ready'       => $ready,
			'endpoint'    => $endpoint,
			'template_id' => $template_id,
			'checked_at'  => gmdate( 'Y-m-d H:i:s' ),
			'steps'       => $steps,
		];
	}

	/**
	 * The Model Requirements entry seeded on the provisioned Template, so the
	 * "Install missing models" action has a source to fetch from.
	 *
	 * @param string $checkpoint Checkpoint filename.
	 * @return array<string, string>
	 */
	private static function download_entry( string $checkpoint ): array {
		return [
			'filename' => $checkpoint,
			'folder'   => 'checkpoints',
			'url'      => self::DEFAULT_CHECKPOINT === $checkpoint ? self::DEFAULT_CHECKPOINT_URL : '',
		];
	}
}
