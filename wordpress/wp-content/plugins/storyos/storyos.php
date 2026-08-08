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
	
	// Handle special namespace mappings (singular → plural directories).
	$special_mappings = [
		'CPT\\' => 'cpts/',
		'REST\\' => 'rest-api/',
		'Taxonomies\\' => 'taxonomies/',
		'Admin\\' => 'admin/',
		'Utils\\' => 'utils/',
	];
	foreach ( $special_mappings as $ns => $dir ) {
		if ( strpos( $relative_class, $ns ) === 0 ) {
			$relative_class = $dir . substr( $relative_class, strlen( $ns ) );
			break;
		}
	}
	
	// Convert class names to filenames based on namespace.
	// CPT files: StoryWorld -> story-world.php (camelCase to kebab-case)
	// REST files: Projects_Controller -> projects-controller.php (underscore to hyphen)
	$path_parts = explode( '/', $relative_class );
	$filename = array_pop( $path_parts );
	
	// Check if this is a REST controller (has _Controller suffix)
	if ( strpos( $relative_class, 'rest-api/' ) !== false ) {
		// REST controllers: replace underscores with hyphens and lowercase
		$filename = str_replace( '_', '-', strtolower( $filename ) ) . '.php';
	} else {
		// CPT and others: convert camelCase to kebab-case
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $filename ) ) . '.php';
	}
	
	$path_parts[] = $filename;
	$kebab_class = implode( '/', $path_parts );
	
	// Also try lowercase version of the full path (e.g., cpts/story-world.php).
	$lower_class = strtolower( $relative_class ) . '.php';
	$file = $base_dir . $relative_class . '.php';

	// Try kebab-case, then lowercase, then original case.
	if ( ! file_exists( $file ) ) {
		$file = $base_dir . $kebab_class;
	}
	if ( ! file_exists( $file ) ) {
		$file = $base_dir . $lower_class;
	}

	if ( file_exists( $file ) ) {
		require $file;
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

	// Activation/deactivation hooks.
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

	// Load Celtx Sync integration.
	if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/celtx/celtx-sync.php' ) ) {
		require_once STORYOS_PLUGIN_DIR . 'plugins/celtx/celtx-sync.php';
	}
}
add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Flush rewrite rules on activation.
 */
function activate(): void {
	init();
	flush_rewrite_rules();

	// Set default StoryOS options.
	add_option( 'storyos_version', STORYOS_VERSION );
	add_option( 'storyos_enabled', true );
}

/**
 * Flush rewrite rules on deactivation.
 */
function deactivate(): void {
	flush_rewrite_rules();
}
