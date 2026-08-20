<?php
/**
 * Conditional loader and manifest for provider Connection adapters.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps provider metadata in core while loading API implementations on demand.
 */
class Connection_Adapters {

	/** @var array<string, bool> Providers loaded during this request. */
	private static $loaded = [];

	/**
	 * Provider adapter manifest.
	 *
	 * Third-party integrations may add an adapter with the
	 * `storyos_connection_adapters` filter. Files are loaded only when that
	 * provider has an enabled Connection or is explicitly requested.
	 */
	public static function all(): array {
		$adapters = [
			'comfyui' => [
				'label'         => 'ComfyUI',
				'description'   => 'Generate media through Comfy Cloud MCP or a local ComfyUI installation.',
				'icon'          => 'dashicons-format-image',
				'endpoint'      => 'https://cloud.comfy.org/mcp',
				'setup_options' => [
					'cloud'     => [
						'label'       => 'Comfy Cloud MCP',
						'environment' => 'production',
					],
					'local_mcp' => [
						'label'       => 'Local ComfyUI HTTP API + MCP',
						'environment' => 'local',
					],
				],
				'files'         => [
					'includes/utils/comfy-cloud-mcp.php',
					'includes/utils/local-comfyui.php',
					'includes/utils/comfy-manifest.php',
					'includes/utils/comfy-catalog.php',
					'includes/utils/comfy-bootstrap.php',
				],
			],
			'fal' => [
				'label'         => 'fal',
				'description'   => 'Generate media through fal MCP with automatically provisioned Templates.',
				'icon'          => 'dashicons-cloud',
				'endpoint'      => 'https://mcp.fal.ai/mcp',
				'setup_options' => [
					'fal' => [
						'label'        => 'fal MCP',
						'environment'  => 'production',
						'mcp_endpoint' => true,
					],
				],
				'files'         => [
					'includes/utils/fal-mcp.php',
					'includes/utils/fal-catalog.php',
				],
				'init'     => [ 'StoryOS\\Utils\\Fal_Catalog', 'init' ],
			],
			'elevenlabs' => [
				'label'         => 'ElevenLabs',
				'description'   => 'Generate speech, dialogue, sound effects, music, and voice previews through endpoint-specific Templates.',
				'icon'          => 'dashicons-microphone',
				'endpoint'      => 'https://api.elevenlabs.io/v1',
				'setup_options' => [
					'elevenlabs' => [
						'label'       => 'ElevenLabs Generative Audio',
						'environment' => 'production',
					],
				],
				'files'         => [
					'includes/utils/elevenlabs-api.php',
					'includes/utils/elevenlabs-catalog.php',
				],
				'init'          => [ 'StoryOS\\Utils\\ElevenLabs_Catalog', 'init' ],
			],
			'openai_compatible' => [ 'label' => 'OpenAI-compatible', 'endpoint' => '', 'files' => [] ],
			'openai'            => [ 'label' => 'OpenAI', 'endpoint' => 'https://api.openai.com/v1', 'files' => [] ],
			'anthropic'         => [ 'label' => 'Anthropic', 'endpoint' => 'https://api.anthropic.com', 'files' => [] ],
			'dual'              => [ 'label' => 'Dual LLM', 'endpoint' => '', 'files' => [] ],
			'google_gemini'     => [ 'label' => 'Google Gemini', 'endpoint' => '', 'files' => [] ],
			'veo'               => [ 'label' => 'Veo', 'endpoint' => '', 'files' => [] ],
			'nova_reel'         => [ 'label' => 'Nova Reel', 'endpoint' => '', 'files' => [] ],
		];

		return (array) apply_filters( 'storyos_connection_adapters', $adapters );
	}

	/** Known provider slugs, including metadata-only future adapters. */
	public static function provider_types(): array {
		return array_keys( self::all() );
	}

	/**
	 * Preferred generation choices exposed by the first-run Setup Wizard.
	 *
	 * Adapters own their setup labels so a third-party adapter can join the
	 * wizard without modifying the wizard itself.
	 *
	 * @return array<string, string>
	 */
	public static function setup_options(): array {
		$options = [];
		foreach ( self::setup_choices() as $value => $choice ) {
			$options[ $value ] = sanitize_text_field( (string) ( $choice['label'] ?? $value ) );
		}

		$options['none'] = 'No generation connection yet';
		return (array) apply_filters( 'storyos_setup_connection_options', $options );
	}

	/**
	 * Full Setup Wizard choice definitions keyed by submitted value.
	 *
	 * @return array<string, array>
	 */
	public static function setup_choices(): array {
		$choices = [];
		foreach ( self::all() as $provider_type => $adapter ) {
			foreach ( (array) ( $adapter['setup_options'] ?? [] ) as $value => $choice ) {
				if ( ! is_array( $choice ) ) {
					$choice = [ 'label' => (string) $choice ];
				}
				$choice['provider_type'] = $provider_type;
				$choices[ sanitize_key( (string) $value ) ] = $choice;
			}
		}

		return (array) apply_filters( 'storyos_setup_connection_choices', $choices );
	}

	/** Setup definition for one submitted wizard choice. */
	public static function setup_choice( string $value ): ?array {
		$choice = self::setup_choices()[ sanitize_key( $value ) ] ?? null;
		return is_array( $choice ) ? $choice : null;
	}

	/** Default endpoint without loading provider API code. */
	public static function endpoint( string $provider_type ): string {
		$adapter = self::all()[ sanitize_key( $provider_type ) ] ?? [];
		return esc_url_raw( (string) ( $adapter['endpoint'] ?? '' ) );
	}

	/**
	 * Load one provider implementation for this request.
	 *
	 * @return bool Whether the provider is known and its declared files loaded.
	 */
	public static function load( string $provider_type ): bool {
		$provider_type = sanitize_key( $provider_type );
		if ( isset( self::$loaded[ $provider_type ] ) ) {
			return self::$loaded[ $provider_type ];
		}

		$adapter = self::all()[ $provider_type ] ?? null;
		if ( ! is_array( $adapter ) ) {
			self::$loaded[ $provider_type ] = false;
			return false;
		}
		if ( ! empty( $adapter['loader'] ) && is_callable( $adapter['loader'] ) ) {
			call_user_func( $adapter['loader'], $provider_type, $adapter );
		}

		foreach ( (array) ( $adapter['files'] ?? [] ) as $relative_file ) {
			$relative_file = ltrim( (string) $relative_file, '/' );
			$file = realpath( STORYOS_PLUGIN_DIR . $relative_file );
			$base = trailingslashit( wp_normalize_path( (string) realpath( STORYOS_PLUGIN_DIR ) ) );
			if ( false === $file || ! str_starts_with( wp_normalize_path( $file ), $base ) || ! is_readable( $file ) ) {
				self::$loaded[ $provider_type ] = false;
				return false;
			}
			require_once $file;
		}

		if ( ! empty( $adapter['init'] ) && is_callable( $adapter['init'] ) ) {
			call_user_func( $adapter['init'] );
		}

		self::$loaded[ $provider_type ] = true;
		return true;
	}

	/** Load adapters for saved, non-disabled Connections only. */
	public static function load_configured(): void {
		foreach ( Connection_Repository::get_all() as $connection ) {
			if ( 'disabled' === ( $connection['status'] ?? '' ) ) {
				continue;
			}
			self::load( (string) ( $connection['provider_type'] ?? '' ) );
		}
	}

	/** Whether a provider implementation was loaded this request. */
	public static function is_loaded( string $provider_type ): bool {
		return ! empty( self::$loaded[ sanitize_key( $provider_type ) ] );
	}
}
