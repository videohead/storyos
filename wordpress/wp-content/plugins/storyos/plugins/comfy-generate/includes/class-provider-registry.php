<?php
/**
 * Provider registry for StoryOS Generation Engine.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a WordPress-side registry of orchestrator provider types.
 */
class Provider_Registry {

	/**
	 * Provider descriptors keyed by provider slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_providers(): array {
		$providers = apply_filters( 'storyos_generation_engine_provider_descriptors', [] );

		return is_array( $providers ) ? $providers : [];
	}

	/**
	 * Return a provider descriptor for a provider slug.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return array<string,mixed>
	 */
	public static function get_provider_descriptor( string $provider_type ): array {
		$slug = self::normalize( $provider_type );
		$providers = self::get_providers();
		return $providers[ $slug ] ?? [];
	}

	/**
	 * Get provider options for settings selects.
	 *
	 * @return array<string,string>
	 */
	public static function get_provider_options(): array {
		$options = [];
		foreach ( self::get_providers() as $slug => $descriptor ) {
			$options[ $slug ] = $descriptor['label'];
		}

		return $options;
	}

	/**
	 * Return the admin page slug for a provider.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return string
	 */
	public static function get_provider_page_slug( string $provider_type ): string {
		return 'storyos-generation-engine-provider-' . sanitize_key( self::normalize( $provider_type ) );
	}

	/**
	 * Return the settings field schema for a provider.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_settings_schema( string $provider_type ): array {
		$descriptor = self::get_provider_descriptor( $provider_type );
		return $descriptor['settings_fields'] ?? [];
	}

	/**
	 * Check if provider slug is supported.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return bool
	 */
	public static function has( string $provider_type ): bool {
		$slug = sanitize_key( $provider_type );
		return isset( self::get_providers()[ $slug ] );
	}

	/**
	 * Normalize a provider type without deciding which providers exist.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return string
	 */
	public static function normalize( string $provider_type ): string {
		return sanitize_key( $provider_type );
	}
}
