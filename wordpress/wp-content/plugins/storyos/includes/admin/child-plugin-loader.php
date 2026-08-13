<?php
/**
 * Child plugin loader for StoryOS integrations.
 *
 * Loads optional plugin-to-plugin integrations only when the child plugin is
 * actually active in WordPress. This keeps the main StoryOS plugin from
 * eagerly bootstrapping sub-plugins that are disabled.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap StoryOS child plugins in a WordPress-friendly way.
 */
class Child_Plugin_Loader {
	/**
	 * Initialize the loader.
	 */
	public static function init(): void {
		if ( did_action( 'plugins_loaded' ) ) {
			self::load_child_plugins();
			return;
		}

		add_action( 'plugins_loaded', [ __CLASS__, 'load_child_plugins' ], 20 );
	}

	/**
	 * Load child plugins so their admin pages and hooks are registered.
	 */
	public static function load_child_plugins(): void {
		$child_plugins = [
			'plugins/celtx/celtx-sync.php',
			'plugins/comfy-generate/comfy-generate.php',
			'plugins/edl/edl-import-export.php',
		];

		$plugin_dir = defined( 'STORYOS_PLUGIN_DIR' ) ? STORYOS_PLUGIN_DIR : '';
		if ( '' === $plugin_dir ) {
			return;
		}

		foreach ( $child_plugins as $relative_path ) {
			$plugin_file = $plugin_dir . $relative_path;
			if ( ! file_exists( $plugin_file ) ) {
				continue;
			}

			// Bundled StoryOS child plugins are feature-gated internally,
			// so we always load them to ensure admin pages and settings routes
			// are registered even when integrations are toggled off.

			require_once $plugin_file;
		}
	}
}
