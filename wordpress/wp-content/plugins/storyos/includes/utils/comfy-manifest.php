<?php
/**
 * ComfyUI requirement manifests for StoryOS generation Templates.
 *
 * Derives the node classes and model files a Template needs, checks them
 * against a live ComfyUI instance so a job never gets submitted into a graph
 * that ComfyUI cannot execute, and resolves download sources through the
 * Comfy MCP template system.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template requirement manifest builder and validator.
 */
class Comfy_Manifest {

	/**
	 * Transient prefix for the cached ComfyUI `/object_info` catalog.
	 */
	const CATALOG_TRANSIENT = 'storyos_comfy_object_info_';

	/**
	 * How long the ComfyUI node/model catalog stays cached, in seconds.
	 */
	const CATALOG_TTL = 300;

	/**
	 * ComfyUI loader input names that name a model file, mapped to the
	 * `models/` sub-directory the file belongs in. Used to discover model
	 * requirements inside a pasted custom workflow and to tell an operator
	 * where a missing file has to be installed.
	 *
	 * @var array<string, string>
	 */
	const MODEL_FIELDS = [
		'ckpt_name'          => 'checkpoints',
		'unet_name'          => 'diffusion_models',
		'vae_name'           => 'vae',
		'clip_name'          => 'text_encoders',
		'clip_name1'         => 'text_encoders',
		'clip_name2'         => 'text_encoders',
		'clip_name3'         => 'text_encoders',
		'clip_name4'         => 'text_encoders',
		'lora_name'          => 'loras',
		'control_net_name'   => 'controlnet',
		'style_model_name'   => 'style_models',
		'clip_vision_name'   => 'clip_vision',
		'gligen_name'        => 'gligen',
		'upscale_model_name' => 'upscale_models',
	];

	/**
	 * Build the requirement manifest for a Template.
	 *
	 * @param int $template_id Template post ID.
	 * @return array|WP_Error
	 */
	public static function for_template( int $template_id ) {
		$template = get_post( $template_id );
		if ( ! $template instanceof \WP_Post || 'storyos_template' !== $template->post_type ) {
			return new WP_Error( 'storyos_template_not_found', __( 'That generation Template does not exist.', 'storyos' ), [ 'status' => 404 ] );
		}

		$modality = Generation_Modality::sanitize( (string) get_post_meta( $template_id, 'modality', true ) );
		$custom   = json_decode( (string) get_post_meta( $template_id, 'workflow_json', true ), true );
		$is_custom = is_array( $custom ) && ! empty( $custom );
		$workflow = $is_custom
			? $custom
			: Generation_Modality::default_workflow( $modality, self::template_settings( $template_id, $modality ) );

		return [
			'template_id'     => $template_id,
			'name'            => (string) ( get_post_meta( $template_id, 'template_name', true ) ?: $template->post_title ),
			'slug'            => (string) $template->post_name,
			'modality'        => $modality,
			'modality_label'  => (string) Generation_Modality::get( $modality )['label'],
			'output_type'     => Generation_Modality::output_type( $modality ),
			'inputs'          => Generation_Modality::inputs( $modality ),
			'workflow_source' => $is_custom ? 'custom' : 'builtin',
			'nodes'           => self::extract_nodes( $workflow, $modality, $is_custom ),
			'models'          => self::extract_models( $workflow ),
			'downloads'       => self::declared_downloads( $template_id ),
		];
	}

	/**
	 * Check a Template's manifest against a live ComfyUI instance.
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $endpoint    Optional ComfyUI base URL; defaults to the configured one.
	 * @return array|WP_Error Report with `ok`, `missing_nodes`, and `missing_models`.
	 */
	public static function validate( int $template_id, string $endpoint = '' ) {
		$manifest = self::for_template( $template_id );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$endpoint = '' !== $endpoint ? untrailingslashit( esc_url_raw( $endpoint ) ) : Local_ComfyUI::endpoint();
		if ( '' === $endpoint ) {
			return new WP_Error( 'storyos_comfy_endpoint_missing', __( 'Set a local ComfyUI URL before checking Template requirements.', 'storyos' ), [ 'status' => 400 ] );
		}

		$catalog = self::catalog( $endpoint );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}

		$missing_nodes = array_values( array_diff( $manifest['nodes'], array_keys( $catalog ) ) );

		$missing_models = [];
		$unverified     = [];
		foreach ( $manifest['models'] as $model ) {
			$installed = self::installed_options( $catalog, (string) $model['node_class'], (string) $model['field'] );
			if ( null === $installed ) {
				$unverified[] = $model;
				continue;
			}
			if ( ! in_array( (string) $model['filename'], $installed, true ) ) {
				$model['available'] = $installed;
				$model['source_url'] = self::download_url_for( $manifest['downloads'], (string) $model['filename'] );
				$missing_models[]   = $model;
			}
		}

		return [
			'ok'             => empty( $missing_nodes ) && empty( $missing_models ),
			'template_id'    => $template_id,
			'modality'       => $manifest['modality'],
			'endpoint'       => $endpoint,
			'checked_at'     => gmdate( 'Y-m-d H:i:s' ),
			'missing_nodes'  => $missing_nodes,
			'missing_models' => $missing_models,
			'unverified'     => $unverified,
		];
	}

	/**
	 * Sampling and model settings a Template overrides on the built-in graph.
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $modality    Modality slug.
	 * @return array<string, mixed>
	 */
	public static function template_settings( int $template_id, string $modality ): array {
		$settings = [ 'checkpoint' => trim( (string) get_post_meta( $template_id, 'checkpoint', true ) ) ];

		foreach ( [ 'configuration_json', 'default_values' ] as $meta_key ) {
			$decoded = json_decode( (string) get_post_meta( $template_id, $meta_key, true ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$parameters = isset( $decoded['parameters'] ) && is_array( $decoded['parameters'] ) ? $decoded['parameters'] : $decoded;
			foreach ( array_keys( Generation_Modality::default_settings( $modality ) ) as $key ) {
				if ( isset( $parameters[ $key ] ) && is_scalar( $parameters[ $key ] ) ) {
					$settings[ $key ] = $parameters[ $key ];
				}
			}
		}

		return $settings;
	}

	/**
	 * Ask the Comfy MCP template system for templates that match a modality,
	 * so an operator can adopt a known-good graph and its model list instead
	 * of assembling one by hand. This is the reciprocal of the
	 * `storyos/templates-manifest` resource StoryOS exposes to MCP clients.
	 *
	 * @param string $modality Modality slug.
	 * @return array|WP_Error
	 */
	public static function discover( string $modality ) {
		$modality = Generation_Modality::sanitize( $modality );
		$result   = Comfy_Cloud_MCP::list_templates( [
			'task_type' => (string) Generation_Modality::get( $modality )['task_type'],
		] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$templates = $result['templates'] ?? $result;
		if ( ! is_array( $templates ) ) {
			return new WP_Error( 'storyos_comfy_discovery_invalid', __( 'Comfy MCP returned no usable template list.', 'storyos' ) );
		}

		$discovered = [];
		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}

			$workflow = is_array( $template['workflow'] ?? null ) ? $template['workflow'] : [];
			$discovered[] = [
				'id'             => (string) ( $template['id'] ?? $template['template_id'] ?? $template['name'] ?? '' ),
				'name'           => (string) ( $template['name'] ?? $template['template_name'] ?? '' ),
				'model_type'     => (string) ( $template['model_type'] ?? '' ),
				'task_type'      => (string) ( $template['task_type'] ?? '' ),
				'required_nodes' => array_values( array_unique( array_merge(
					array_map( 'strval', (array) ( $template['required_nodes'] ?? [] ) ),
					$workflow ? self::extract_nodes( $workflow, $modality, true ) : []
				) ) ),
				'models'         => $workflow ? self::extract_models( $workflow ) : [],
				'model_urls'     => self::extract_model_urls( $template ),
				'parameters'     => is_array( $template['parameters'] ?? null ) ? $template['parameters'] : [],
			];
		}

		return [ 'modality' => $modality, 'templates' => $discovered ];
	}

	/**
	 * Ask Comfy MCP to fetch the models a Template is missing.
	 *
	 * @param int $template_id Template post ID.
	 * @return array|WP_Error Result payload, or an error describing the manual install plan.
	 */
	public static function request_downloads( int $template_id ) {
		$report = self::validate( $template_id );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		if ( empty( $report['missing_models'] ) ) {
			return [ 'requested' => [], 'message' => __( 'Every model this Template needs is already installed.', 'storyos' ) ];
		}

		$urls    = [];
		$manual  = [];
		foreach ( $report['missing_models'] as $model ) {
			if ( ! empty( $model['source_url'] ) ) {
				$urls[] = (string) $model['source_url'];
				continue;
			}
			$manual[] = sprintf( '%s (models/%s)', (string) $model['filename'], (string) $model['folder'] );
		}

		if ( empty( $urls ) ) {
			return new WP_Error(
				'storyos_comfy_no_download_urls',
				sprintf(
					/* translators: %s: comma-separated list of model files. */
					__( 'No download URL is recorded for: %s. Add each file to the Template\'s Model Requirements JSON as {"filename":"…","folder":"…","url":"…"}, or install it into ComfyUI manually.', 'storyos' ),
					implode( ', ', $manual )
				)
			);
		}

		$result = Comfy_Cloud_MCP::download_models( $urls );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'requested' => $urls, 'manual' => $manual, 'result' => $result ];
	}

	/**
	 * Node class types a workflow executes.
	 *
	 * @param array  $workflow  ComfyUI API-format graph.
	 * @param string $modality  Modality slug.
	 * @param bool   $is_custom Whether the graph was supplied by the operator.
	 * @return array<int, string>
	 */
	private static function extract_nodes( array $workflow, string $modality, bool $is_custom ): array {
		$nodes = [];
		foreach ( $workflow as $node ) {
			if ( is_array( $node ) && ! empty( $node['class_type'] ) && is_string( $node['class_type'] ) ) {
				$nodes[] = $node['class_type'];
			}
		}

		// A built-in graph is generated from the modality definition, so fall
		// back to it when a graph carries no recognizable nodes.
		if ( ! $is_custom || empty( $nodes ) ) {
			$nodes = array_merge( $nodes, Generation_Modality::required_nodes( $modality ) );
		}

		sort( $nodes );

		return array_values( array_unique( $nodes ) );
	}

	/**
	 * Model files a workflow loads.
	 *
	 * @param array $workflow ComfyUI API-format graph.
	 * @return array<int, array>
	 */
	private static function extract_models( array $workflow ): array {
		$models = [];
		foreach ( $workflow as $node ) {
			if ( ! is_array( $node ) || empty( $node['class_type'] ) || ! is_array( $node['inputs'] ?? null ) ) {
				continue;
			}

			foreach ( $node['inputs'] as $field => $value ) {
				if ( ! isset( self::MODEL_FIELDS[ $field ] ) || ! is_string( $value ) || '' === trim( $value ) ) {
					continue;
				}

				$models[ $node['class_type'] . '|' . $field . '|' . $value ] = [
					'node_class' => (string) $node['class_type'],
					'field'      => (string) $field,
					'filename'   => trim( $value ),
					'folder'     => self::MODEL_FIELDS[ $field ],
				];
			}
		}

		return array_values( $models );
	}

	/**
	 * Download sources declared on a Template's Model Requirements field.
	 *
	 * @param int $template_id Template post ID.
	 * @return array<int, array>
	 */
	private static function declared_downloads( int $template_id ): array {
		$decoded = json_decode( (string) get_post_meta( $template_id, 'model_requirements', true ), true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$entries = isset( $decoded['models'] ) && is_array( $decoded['models'] ) ? $decoded['models'] : $decoded;
		$downloads = [];
		foreach ( (array) $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['filename'] ) ) {
				continue;
			}

			$downloads[] = [
				'filename' => sanitize_text_field( (string) $entry['filename'] ),
				'folder'   => sanitize_text_field( (string) ( $entry['folder'] ?? '' ) ),
				'url'      => esc_url_raw( (string) ( $entry['url'] ?? '' ) ),
			];
		}

		return $downloads;
	}

	/**
	 * Find a declared download URL for a model filename.
	 *
	 * @param array  $downloads Declared downloads.
	 * @param string $filename  Model filename.
	 * @return string
	 */
	private static function download_url_for( array $downloads, string $filename ): string {
		foreach ( $downloads as $download ) {
			if ( ( $download['filename'] ?? '' ) === $filename ) {
				return (string) ( $download['url'] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Model URLs advertised by a Comfy MCP template descriptor.
	 *
	 * @param array $template MCP template descriptor.
	 * @return array<int, string>
	 */
	private static function extract_model_urls( array $template ): array {
		$urls = [];
		array_walk_recursive( $template, static function ( $value, $key ) use ( &$urls ): void {
			if ( is_string( $value ) && preg_match( '#^https?://#', $value ) && in_array( $key, [ 'url', 'models', 'model_url', 'download_url' ], true ) ) {
				$urls[] = $value;
			}
		} );

		return array_values( array_unique( $urls ) );
	}

	/**
	 * The filenames ComfyUI currently offers for a loader input, or null when
	 * the input is not an enumerated file list (so it cannot be validated).
	 *
	 * @param array  $catalog    Decoded `/object_info` payload.
	 * @param string $node_class Node class type.
	 * @param string $field      Input name.
	 * @return array<int, string>|null
	 */
	private static function installed_options( array $catalog, string $node_class, string $field ): ?array {
		foreach ( [ 'required', 'optional' ] as $group ) {
			$spec = $catalog[ $node_class ]['input'][ $group ][ $field ][0] ?? null;
			if ( ! is_array( $spec ) ) {
				continue;
			}

			$options = array_values( array_filter( $spec, 'is_string' ) );

			return $options === $spec ? $options : null;
		}

		return null;
	}

	/**
	 * Fetch and cache ComfyUI's node/model catalog.
	 *
	 * @param string $endpoint ComfyUI base URL.
	 * @return array|WP_Error
	 */
	private static function catalog( string $endpoint ) {
		$key    = self::CATALOG_TRANSIENT . md5( $endpoint );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( $endpoint . '/object_info', [ 'timeout' => 30 ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'storyos_comfy_unreachable', sprintf( __( 'Unable to read the ComfyUI node catalog: %s', 'storyos' ), $response->get_error_message() ) );
		}
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
			return new WP_Error( 'storyos_comfy_catalog_failed', sprintf( __( 'ComfyUI returned HTTP %d from /object_info.', 'storyos' ), wp_remote_retrieve_response_code( $response ) ) );
		}

		$catalog = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $catalog ) ) {
			return new WP_Error( 'storyos_comfy_catalog_invalid', __( 'ComfyUI returned an unreadable node catalog.', 'storyos' ) );
		}

		set_transient( $key, $catalog, self::CATALOG_TTL );

		return $catalog;
	}

	/**
	 * Drop the cached ComfyUI catalog so the next check re-reads it.
	 *
	 * @param string $endpoint ComfyUI base URL.
	 */
	public static function flush_catalog( string $endpoint = '' ): void {
		$endpoint = '' !== $endpoint ? untrailingslashit( esc_url_raw( $endpoint ) ) : Local_ComfyUI::endpoint();
		if ( '' !== $endpoint ) {
			delete_transient( self::CATALOG_TRANSIENT . md5( $endpoint ) );
		}
	}
}
