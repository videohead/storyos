<?php
/**
 * Plugin Name: StoryOS - Story Core
 * Plugin URI: https://github.com/videohead/storyos
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
	if ( 'AI\\Abilities\\Abilities' === $relative_class ) {
		require_once $base_dir . 'ai-editor/class-ai-abilities.php';
		return;
	}
	
	// Handle special namespace mappings (singular → plural directories).
	$special_mappings = [
		'CPT\\' => 'cpts/',
		'REST\\' => 'rest-api/',
		'Taxonomies\\' => 'taxonomies/',
		'Admin\\' => 'admin/',
		'Utils\\' => 'utils/',
		'Importer\\' => 'importer/',
		'Exporter\\' => 'exporter/',
		'AI\\' => 'ai-editor/',
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
	$original_filename = $filename;
	
	// Check if this is a REST controller (has _Controller suffix)
	if ( strpos( $relative_class, 'rest-api/' ) !== false ) {
		// REST controllers: replace underscores with hyphens and lowercase
		$filename = str_replace( '_', '-', strtolower( $filename ) ) . '.php';
	} elseif ( strpos( $relative_class, 'ai-editor/' ) !== false ) {
		$filename = 'class-' . str_replace( '_', '-', strtolower( $filename ) ) . '.php';
	} else {
		// CPT and others: convert camelCase to kebab-case
		$filename = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $filename ) ) . '.php';
	}
	
	$path_parts[] = $filename;
	$kebab_class = implode( '/', $path_parts );

	// WordPress-style filename: class-storyos-importer.php (lowercase, underscores to hyphens).
	$class_prefixed_parts = $path_parts;
	array_pop( $class_prefixed_parts );
	$class_prefixed_parts[] = 'class-' . str_replace( '_', '-', strtolower( $original_filename ) ) . '.php';
	$class_prefixed_class = implode( '/', $class_prefixed_parts );
	
	// Also try lowercase version of the full path (e.g., cpts/story-world.php).
	$lower_class = strtolower( $relative_class ) . '.php';
	$file = $base_dir . $relative_class . '.php';

	// Try kebab-case, WordPress class-prefixed, then lowercase and original case.
	if ( ! file_exists( $file ) ) {
		$file = $base_dir . $kebab_class;
	}
	if ( ! file_exists( $file ) ) {
		$file = $base_dir . $class_prefixed_class;
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
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/generation-log.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/generation-modality.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/connection-adapters.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/generation-batch.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/relationships.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/story-search.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/continuity-checker.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/utils/relationship-graph.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/dashboard.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/navigation.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/setup-wizard.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/metaboxes.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/asset-generator-metabox.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/comfy-readiness.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/plugins.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/continuity-panel.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/analytics-panel.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/import.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/editorial-cut.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/admin/generation-log-viewer.php';
	require_once STORYOS_PLUGIN_DIR . 'includes/exporter/class-storyos-exporter.php';

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
	CPT\Sound::init();
	CPT\StoryboardFrame::init();
	CPT\Asset::init();
	CPT\EditorialArtifact::init();
	CPT\Template::init();
	CPT\Connection::init();
	Utils\Connection_Adapters::load_configured();

	// Register taxonomies.
	Taxonomies\Genre::init();
	Taxonomies\AssetType::init();
	Taxonomies\ProductionStatus::init();
	Taxonomies\CharacterRelation::init();
	Taxonomies\CharacterRole::init();
	Taxonomies\SceneTag::init();
	Taxonomies\Sequence::init();
	Taxonomies\SoundType::init();
	Taxonomies\TemplateCategory::init();

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
	REST\Sounds_Controller::init();
	REST\Sequences_Controller::init();
	REST\StoryboardFrames_Controller::init();
	REST\Assets_Controller::init();
	REST\Asset_Generation_Controller::init();
	REST\EditorialArtifacts_Controller::init();
	REST\Graph_Controller::init();
	REST\Agents_Controller::init();
	REST\Generation_Controller::init();
	REST\Production_Controller::init();
	REST\Editorial_Controller::init();
	REST\Connections_Controller::init();
	REST\Import_Controller::init();

	// Register admin pages and hooks.
	Admin\Dashboard::init();
	Admin\Navigation::init();
	Admin\Setup_Wizard::init();
	Admin\MetaBoxes::init();
	Admin\Asset_Generator_MetaBox::init();
	Admin\Comfy_Readiness::init();
	Admin\Plugins::init();
	Admin\Continuity_Panel::init();
	Admin\Analytics_Panel::init();
	Admin\Connections::init();
	Admin\Import::init();
	Admin\Export::init();
	Admin\Editorial_Cut::init();
	Admin\Generation_Log_Viewer::init();
	Utils\Generation_Batch::init();

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

	// Load Celtx Sync integration.
	if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/celtx/celtx-sync.php' ) ) {
		require_once STORYOS_PLUGIN_DIR . 'plugins/celtx/celtx-sync.php';
	}

	// Load ComfyUI Generate integration.
	if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/comfy-generate/comfy-generate.php' ) ) {
		require_once STORYOS_PLUGIN_DIR . 'plugins/comfy-generate/comfy-generate.php';
	}

	// Load EDL Import/Export integration.
	if ( file_exists( STORYOS_PLUGIN_DIR . 'plugins/edl/edl-import-export.php' ) ) {
		require_once STORYOS_PLUGIN_DIR . 'plugins/edl/edl-import-export.php';
	}

	// Enqueue search widget assets on frontend.
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\Utils\\enqueue_search_assets' );

	// Enqueue continuity panel assets in admin.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_continuity_assets' );

	// Hook auto-validation on save for scenes and shots.
	add_action( 'save_post_storyos_scene', __NAMESPACE__ . '\\auto_validate_scene', 20, 3 );
	add_action( 'save_post_storyos_shot', __NAMESPACE__ . '\\auto_validate_shot', 20, 3 );

	// Auto-generate useful shot names for shots with placeholder titles.
	add_action( 'save_post_storyos_shot', __NAMESPACE__ . '\\storyos_maybe_name_shot', 5, 3 );

}

/**
 * Auto-generate a useful name for shots with default placeholder titles.
 *
 * Runs at priority 5 (before continuity validation) and guards against
 * recursion when it updates the post title.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 * @param bool     $update  Whether this is an update.
 * @return void
 */
function storyos_maybe_name_shot( int $post_id, \WP_Post $post, bool $update ): void {
	// Only runs on our own plugin saves; never during autosave or batch operations.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Never rename shots that already have an intentional title.
	$title = trim( (string) $post->post_title );
	if ( '' !== $title && ! preg_match( '/^shot \d+$/i', $title ) ) {
		return;
	}

	$name = \StoryOS\Utils\storyos_get_shot_display_name( $post_id );
	if ( '' === $name || $name === $title ) {
		return;
	}

	// Remove this hook before the nested update to avoid infinite recursion.
	remove_action( 'save_post_storyos_shot', __NAMESPACE__ . '\\storyos_maybe_name_shot', 5 );

	wp_update_post( [
		'ID'         => $post_id,
		'post_title' => $name,
	] );

	add_action( 'save_post_storyos_shot', __NAMESPACE__ . '\\storyos_maybe_name_shot', 5, 3 );
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
	Utils\Generation_Batch::schedule();

	// Send the admin to the connection setup wizard on first activation.
	if ( ! get_option( 'storyos_setup_complete', false ) ) {
		set_transient( 'storyos_activation_redirect', true, MINUTE_IN_SECONDS );
	}
}

/**
 * Flush rewrite rules on deactivation.
 */
function deactivate(): void {
	wp_clear_scheduled_hook( Utils\Generation_Batch::HOOK );
	flush_rewrite_rules();
}
