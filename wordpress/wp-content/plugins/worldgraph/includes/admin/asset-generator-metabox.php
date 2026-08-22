<?php
/**
 * The World Graph Studio Assets meta box: asset guidance plus the text-to-image
 * generation tools for a story element.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

use WorldGraph\Utils\Comfy_Bootstrap;
use WorldGraph\Utils\Connection_Adapters;
use WorldGraph\Utils\Connection_Repository;
use WorldGraph\Utils\Local_ComfyUI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate-asset meta box.
 */
class Asset_Generator_MetaBox {

	/**
	 * CPTs that can have a featured story asset generated or selected.
	 *
	 * @var array<int, string>
	 */
	private const ASSET_CPTS = [
		'worldgraph_project',
		'worldgraph_world',
		'worldgraph_character',
		'worldgraph_location',
		'worldgraph_prop',
		'worldgraph_org',
		'worldgraph_episode',
		'worldgraph_scene',
		'worldgraph_shot',
		'worldgraph_board',
		'worldgraph_asset',
		'worldgraph_editorial',
	];

	/**
	 * Register the meta box and its assets.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	/**
	 * Add the meta box to every supported CPT.
	 */
	public static function register(): void {
		foreach ( self::ASSET_CPTS as $cpt ) {
			add_meta_box(
				'worldgraph_assets',
				__( 'World Graph Studio Assets', 'worldgraph' ),
				[ __CLASS__, 'render' ],
				$cpt,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Enqueue the generator styles and script on supported edit screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::ASSET_CPTS, true ) ) {
			return;
		}

		wp_enqueue_style(
			'worldgraph-asset-generator',
			WORLDGRAPH_PLUGIN_URL . 'assets/css/asset-generator.css',
			[],
			WORLDGRAPH_VERSION
		);

		wp_enqueue_script(
			'worldgraph-asset-generator',
			WORLDGRAPH_PLUGIN_URL . 'assets/js/asset-generator.js',
			[],
			WORLDGRAPH_VERSION,
			true
		);

		wp_localize_script( 'worldgraph-asset-generator', 'worldgraphAssetGenerator', [
			'restUrl'        => rest_url( 'worldgraph/v1/assets/generate' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'pollIntervalMs' => 15000,
			'i18n'           => [
				'generating'        => __( 'Queueing image…', 'worldgraph' ),
				'queued'            => __( 'Image generation queued. The background worker will import the completed media.', 'worldgraph' ),
				'job'               => __( 'Job', 'worldgraph' ),
				'loading'           => __( 'Building a detailed prompt from saved Story Graph fields…', 'worldgraph' ),
				'planning'          => __( 'Planning representative media…', 'worldgraph' ),
				'starting'          => __( 'Freezing the plan and starting its background batch…', 'worldgraph' ),
				'workflow'          => __( 'Default workflow', 'worldgraph' ),
				'jobs'              => __( 'jobs', 'worldgraph' ),
				'images'            => __( 'images', 'worldgraph' ),
				'videos'            => __( 'videos', 'worldgraph' ),
				'automaticTemplate' => __( 'Automatic per intent', 'worldgraph' ),
				'confirmItem'       => __( 'Queue this item’s complete representative-media set? Provider charges may apply.', 'worldgraph' ),
				'confirmProject'    => __( 'Queue ALL representative frames and videos for this Project? This can incur substantial provider charges and run for hours or days.', 'worldgraph' ),
				'batchQueued'       => __( 'Representative-media batch queued.', 'worldgraph' ),
				'batchProgress'     => __( 'Batch progress', 'worldgraph' ),
				'cancelBatch'       => __( 'Stop work that has not reached a provider?', 'worldgraph' ),
				'cancelled'         => __( 'Staged and queued work was stopped. Already-submitted jobs will finish and import.', 'worldgraph' ),
				'done'              => __( 'Image generated and attached.', 'worldgraph' ),
				'featured'          => __( 'Set as the featured asset.', 'worldgraph' ),
				'assetCreated'      => __( 'Linked Asset record created.', 'worldgraph' ),
				'reloadHint'        => __( 'Reload the editor to see completed media in the featured asset and gallery fields.', 'worldgraph' ),
				'error'             => __( 'Media generation failed.', 'worldgraph' ),
				'unconfigured'      => __( 'No runnable image Template is configured. Configure an active Template and Connection first.', 'worldgraph' ),
			],
		] );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render( \WP_Post $post ): void {
		?>
	    <?php
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		self::render_readiness();
		self::render_generator( $post );
	}

	/**
	 * Warn when the configured local ComfyUI cannot run a generation yet, and
	 * point at the screen that resolves it.
	 */
	private static function render_readiness(): void {
		if ( empty( Connection_Repository::get_all( [ 'provider_type' => 'comfyui' ] ) ) || ! Connection_Adapters::load( 'comfyui' ) ) {
			return;
		}
		if ( ! Local_ComfyUI::is_configured() ) {
			return;
		}

		$status = Comfy_Bootstrap::status();
		if ( ! empty( $status['ready'] ) ) {
			return;
		}

		Comfy_Readiness::render_notice( $status );
	}

	/**
	 * Render detailed single-image and representative-workflow controls.
	 *
	 * @param \WP_Post $post Current post.
	 */
	private static function render_generator( \WP_Post $post ): void {
		?>
		<div class="worldgraph-generate-asset" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-is-project="<?php echo esc_attr( 'worldgraph_project' === $post->post_type ? '1' : '0' ); ?>">
			<h4><?php esc_html_e( 'Generate representative media', 'worldgraph' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Prompts automatically use the saved title, body, detailed SCF fields, inherited Project/World context, and Generation Prompt Instructions. Save or update this post before queueing so the latest details are included.', 'worldgraph' ); ?></p>
			<div class="worldgraph-generate-asset__workflow" aria-live="polite"></div>
			<label for="worldgraph-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Detailed prompt preview', 'worldgraph' ); ?></label>
			<textarea class="widefat worldgraph-generate-asset__prompt" id="worldgraph-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>" rows="10" placeholder="<?php esc_attr_e( 'The detailed saved-context prompt will appear here.', 'worldgraph' ); ?>"></textarea>
			<p class="description"><?php esc_html_e( 'If this preview is untouched, Generate image rebuilds it from the latest saved data at issuance time. Editing it creates a one-off image prompt; durable sets continue to use saved context and the SCF prompt-instructions field.', 'worldgraph' ); ?></p>
			<p class="worldgraph-generate-asset__options">
				<label for="worldgraph-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Image Template', 'worldgraph' ); ?></label>
				<select class="worldgraph-generate-asset__template" id="worldgraph-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"></select>
				<span class="worldgraph-generate-asset__video-option" hidden>
					<label for="worldgraph-generate-asset-video-template-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Video Template', 'worldgraph' ); ?></label>
					<select class="worldgraph-generate-asset__video-template" id="worldgraph-generate-asset-video-template-<?php echo esc_attr( $post->ID ); ?>"></select>
				</span>
				<label><input type="checkbox" class="worldgraph-generate-asset__featured" checked /> <?php esc_html_e( 'Set as featured asset', 'worldgraph' ); ?></label>
				<label><input type="checkbox" class="worldgraph-generate-asset__create" checked /> <?php esc_html_e( 'Create linked Asset record', 'worldgraph' ); ?></label>
			</p>
			<p class="worldgraph-generate-asset__actions">
				<button type="button" class="button button-primary worldgraph-generate-asset__run"><?php esc_html_e( 'Generate image', 'worldgraph' ); ?></button>
				<button type="button" class="button worldgraph-generate-asset__run-set"><?php esc_html_e( 'Generate representative set', 'worldgraph' ); ?></button>
				<?php if ( 'worldgraph_project' === $post->post_type ) : ?>
					<button type="button" class="button worldgraph-generate-asset__run-project"><?php esc_html_e( 'Generate ALL Project media', 'worldgraph' ); ?></button>
				<?php endif; ?>
				<button type="button" class="button worldgraph-generate-asset__suggest"><?php esc_html_e( 'Suggest prompt', 'worldgraph' ); ?></button>
				<button type="button" class="button-link-delete worldgraph-generate-asset__cancel" hidden><?php esc_html_e( 'Stop queued work', 'worldgraph' ); ?></button>
			</p>
			<div class="worldgraph-generate-asset__status" role="status" aria-live="polite"></div>
			<div class="worldgraph-generate-asset__progress" hidden>
				<progress max="100" value="0"></progress>
				<span></span>
			</div>
			<div class="worldgraph-generate-asset__result" hidden></div>
		</div>
		<?php
	}
}
