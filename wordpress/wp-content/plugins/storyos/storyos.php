<?php
/**
 * Plugin Name: StoryOS - Story Core
 * Plugin URI: https://storyos.dev
 * Description: StoryOS Story Core - The canonical Story Graph for AI-powered storytelling. Manages Projects, Story Worlds, Characters, Locations, Scenes, Shots, Storyboards, Assets, and Editorial Artifacts as WordPress Custom Post Types with structured content fields and graph relationships.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos
 * Requires Plugins: secure-custom-fields/secure-custom-fields.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOS
 */

namespace StoryOS;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_VERSION', '1.0.0' );
define( 'STORYOS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'STORYOS_API_NAMESPACE', 'storyos/v1' );
define( 'STORYOS_CPT_PREFIX', 'storyos_' );

/**
 * Convert a class or namespace segment to a kebab-case filename.
 *
 * @param string $name The class or segment name.
 * @return string
 */
function to_kebab_case( string $name ): string {
	$name = str_replace( '_', '-', $name );
	return strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) );
}

/**
 * Check whether a plugin file is active in WordPress.
 *
 * @param string $plugin_file The plugin file path.
 * @return bool
 */
function storyos_is_plugin_active( string $plugin_file ): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		return false;
	}

	return is_plugin_active( plugin_basename( $plugin_file ) );
}

/**
 * Autoloader for StoryOS classes.
 *
 * @param string $class The class name.
 */
function autoloader( string $class ): void {
	$prefix = 'StoryOS\\';
	$base_dir = STORYOS_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$namespace_dir = '';
	$use_class_prefix = false;
	$special_mappings = [
		'CPT\\' => 'cpts',
		'REST\\' => 'rest-api',
		'Taxonomies\\' => 'taxonomies',
		'Admin\\' => 'admin',
		'Utils\\' => 'utils',
		'AI\\' => 'ai-editor',
	];

	foreach ( $special_mappings as $ns => $dir ) {
		if ( strpos( $relative_class, $ns ) === 0 ) {
			$namespace_dir = $dir;
			if ( 'AI\\' === $ns ) {
				$use_class_prefix = true;
			}
			break;
		}
	}

	$relative_class = str_replace( '\\', '/', $relative_class );
	$path_parts = explode( '/', $relative_class );
	$filename = array_pop( $path_parts );
	$kebab_filename = to_kebab_case( $filename );

	$candidates = [];
	if ( '' !== $namespace_dir ) {
		$candidates[] = $base_dir . $namespace_dir . '/' . $kebab_filename . '.php';
		if ( $use_class_prefix ) {
			$candidates[] = $base_dir . $namespace_dir . '/class-' . $kebab_filename . '.php';
		}
	}

	$candidates[] = $base_dir . $kebab_filename . '.php';
	$candidates[] = $base_dir . 'class-' . $kebab_filename . '.php';

	if ( '' !== $namespace_dir ) {
		$candidates[] = $base_dir . $namespace_dir . '/' . $filename . '.php';
		$candidates[] = $base_dir . $namespace_dir . '/' . strtolower( $filename ) . '.php';
	}

	foreach ( $candidates as $file ) {
		if ( file_exists( $file ) ) {
			require $file;
			return;
		}
	}
}

spl_autoload_register( __NAMESPACE__ . '\\autoloader' );

/**
 * Check if SCF (Secure Custom Fields) is active.
 *
 * @return bool
 */
function scf_is_active(): bool {
	return in_array( 'secure-custom-fields/secure-custom-fields.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Handle missing SCF dependency.
 */
function storyos_missing_scf_dependency(): void {
	wp_die(
		'<h1>StoryOS requires Secure Custom Fields (SCF)</h1>
		<p>StoryOS depends on the <strong>Secure Custom Fields (SCF)</strong> plugin for field management. Please install and activate it before enabling StoryOS.</p>
		<p><a href="' . esc_url( admin_url( 'plugin-install.php?s=secure+custom+fields&tab=search&type=tabs' ) ) . '" class="button button-primary">Install SCF</a>
		<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '" class="button">Go to Plugins</a></p>
		<p><small>Secure Custom Fields: <a href="https://wordpress.org/plugins/secure-custom-fields/">WordPress.org</a></small></p>',
		'StoryOS Missing Dependency',
		[ 'response' => 500 ]
	);
}

/**
 * Initialize the plugin.
 */
function init(): void {
	// Check SCF dependency.
	if ( ! scf_is_active() ) {
		add_action( 'admin_notices', function(): void {
			?>
			<div class="notice notice-error">
				<p><strong>StoryOS</strong> requires the <strong>Secure Custom Fields (SCF)</strong> plugin to be installed and activated. <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Go to Plugins</a></p>
			</div>
			<?php
		} );
		return;
	}

	// Load dependencies.
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/helpers.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/relationships.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/story-search.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/continuity-checker.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/relationship-graph.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/dashboard.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/metaboxes.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/plugins.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/continuity-panel.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/analytics-panel.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/child-plugin-loader.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/base-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/projects-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/storyworlds-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/characters-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/locations-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/props-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/organizations-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/episodes-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/scenes-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/shots-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/storyboardframes-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/assets-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/editorialartifacts-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/graph-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/agents-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/generation-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/production-controller.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/rest-api/editorial-controller.php';

	// Register CPTs.
	CPT\Project::init();
	CPT\StoryWorld::init();
	CPT\Character::init();
	CPT\Location::init();
	CPT\Prop::init();
	CPT\Organization::init();
	CPT\Episode::init();
	CPT\Scene::init();
	CPT\Shot::init();
	CPT\StoryboardFrame::init();
	CPT\Asset::init();
	CPT\EditorialArtifact::init();

	// Register taxonomies.
	Taxonomies\Genre::init();
	Taxonomies\AssetType::init();
	Taxonomies\ProductionStatus::init();
	Taxonomies\CharacterRelation::init();
	Taxonomies\CharacterRole::init();
	Taxonomies\SceneTag::init();
	Taxonomies\Sequence::init();

	// Register REST API routes.
	REST\Projects_Controller::init();
	REST\StoryWorlds_Controller::init();
	REST\Characters_Controller::init();
	REST\Locations_Controller::init();
	REST\Props_Controller::init();
	REST\Organizations_Controller::init();
	REST\Episodes_Controller::init();
	REST\Scenes_Controller::init();
	REST\Shots_Controller::init();
	REST\StoryboardFrames_Controller::init();
	REST\Assets_Controller::init();
	REST\EditorialArtifacts_Controller::init();
	REST\Graph_Controller::init();
	REST\Agents_Controller::init();
	REST\Generation_Controller::init();
	REST\Production_Controller::init();
	REST\Editorial_Controller::init();

	// Register admin pages and hooks.
	Admin\Dashboard::init();
	Admin\MetaBoxes::init();
	Admin\Plugins::init();
	Admin\Continuity_Panel::init();
	Admin\Analytics_Panel::init();
	Admin\Child_Plugin_Loader::init();

	// Initialize AI Editor module (LLM, MAF bridge, Gutenberg panel, REST endpoints).
	if ( class_exists( '\StoryOS\AI\AI_Editor' ) ) {
		\StoryOS\AI\AI_Editor::init();

		// Initialize StoryOS Abilities for MCP exposure (requires WP 6.9+).
		if ( function_exists( 'wp_register_ability' ) ) {
			\StoryOS\AI\Abilities\Abilities::instance()->init();
		}
	}

	// Activation/deactivation hooks.
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );


	// Enqueue search widget assets on frontend.
	add_action( 'wp_enqueue_scripts', '\\StoryOS\\Utils\\enqueue_search_assets' );

	// Enqueue continuity panel assets in admin.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_continuity_assets' );

	// Hook auto-validation on save for scenes and shots.
	add_action( 'save_post_storyos_scene', __NAMESPACE__ . '\\auto_validate_scene', 20, 3 );
	add_action( 'save_post_storyos_shot', __NAMESPACE__ . '\\auto_validate_shot', 20, 3 );

	// Add orchestrator URL constant if not defined.
	if ( ! defined( 'STORYOS_ORCHESTRATOR_URL' ) ) {
		define( 'STORYOS_ORCHESTRATOR_URL', 'http://localhost:8000' );
	}
}
add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Enqueue continuity panel admin assets.
 */
function enqueue_continuity_assets(): void {
	// Assets are enqueued by Admin\Continuity_Panel::enqueue_scripts().
	// This hook ensures the CSS/JS files are registered.
	wp_enqueue_style(
		'storyos-continuity',
		STORYOS_PLUGIN_URL . 'assets/css/continuity-panel.css',
		[],
		STORYOS_VERSION
	);
	wp_enqueue_script(
		'storyos-continuity',
		STORYOS_PLUGIN_URL . 'assets/js/continuity-panel.js',
		[ 'jquery' ],
		STORYOS_VERSION,
		true
	);
}

/**
 * Auto-validate a scene on save.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post Post object.
 * @param bool     $update Whether this is an update.
 */
function auto_validate_scene( int $post_id, \WP_Post $post, bool $update ): void {
	\StoryOS\Utils\auto_check_continuity_on_save( $post_id, $post, $update );
}

/**
 * Auto-validate a shot on save.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post Post object.
 * @param bool     $update Whether this is an update.
 */
function auto_validate_shot( int $post_id, \WP_Post $post, bool $update ): void {
	\StoryOS\Utils\auto_check_continuity_on_save( $post_id, $post, $update );
}

/**
 * Flush rewrite rules on activation.
 */
function activate(): void {
	init();
	flush_rewrite_rules();

	// Set default StoryOS options.
	add_option( 'storyos_version', STORYOS_VERSION );
	add_option( 'storyos_enabled', true );
	add_option( 'storyos_orchestrator_url', 'http://localhost:8000' );
}

/**
 * Flush rewrite rules on deactivation.
 */
function deactivate(): void {
	flush_rewrite_rules();
}
