<?php
/**
 * The World Graph Studio Assets meta box: asset guidance plus the image/video
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
			self::asset_version( 'assets/css/asset-generator.css' )
		);

		wp_enqueue_script(
			'worldgraph-asset-generator',
			WORLDGRAPH_PLUGIN_URL . 'assets/js/asset-generator.js',
			[],
			self::asset_version( 'assets/js/asset-generator.js' ),
			true
		);

		wp_localize_script( 'worldgraph-asset-generator', 'worldgraphAssetGenerator', [
			'restUrl'        => rest_url( 'worldgraph/v1/assets/generate' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'pollIntervalMs' => 15000,
			'i18n'           => [
				'generatingImage'   => __( 'Queueing image…', 'worldgraph' ),
				'generatingVideo'   => __( 'Queueing video…', 'worldgraph' ),
				'queuedImage'       => __( 'Image generation queued. The background worker will import the completed media.', 'worldgraph' ),
				'queuedVideo'       => __( 'Video generation queued. The background worker will import the completed media.', 'worldgraph' ),
				'job'               => __( 'Job', 'worldgraph' ),
				'loading'           => __( 'Building generation context from saved Story Graph fields…', 'worldgraph' ),
				'planning'          => __( 'Planning representative media…', 'worldgraph' ),
				'starting'          => __( 'Freezing the plan and starting its background batch…', 'worldgraph' ),
				'workflow'          => __( 'Default workflow', 'worldgraph' ),
				'jobs'              => __( 'jobs', 'worldgraph' ),
				'images'            => __( 'images', 'worldgraph' ),
				'videos'            => __( 'videos', 'worldgraph' ),
				'automaticTemplate' => __( 'Automatic per intent', 'worldgraph' ),
				'confirmItem'       => __( 'Queue this item’s complete representative-media set? Provider charges may apply.', 'worldgraph' ),
				'confirmProject'    => __( 'Queue all representative frames and videos for this Project? This can incur substantial provider charges and run for hours or days.', 'worldgraph' ),
				'batchQueued'       => __( 'Representative-media batch queued.', 'worldgraph' ),
				'batchProgress'     => __( 'Batch progress', 'worldgraph' ),
				'cancelBatch'       => __( 'Stop work that has not reached a provider?', 'worldgraph' ),
				'cancelled'         => __( 'Staged and queued work was stopped. Already-submitted jobs will finish and import.', 'worldgraph' ),
				'done'              => __( 'Image generated and attached.', 'worldgraph' ),
				'generateImage'     => __( 'Generate image', 'worldgraph' ),
				'generateVideo'     => __( 'Generate video', 'worldgraph' ),
				'videoOption'       => __( 'Video (text to video)', 'worldgraph' ),
				'videoNotAvailable' => __( 'Video (not defined for this item)', 'worldgraph' ),
				'featured'          => __( 'Set as the featured asset.', 'worldgraph' ),
				'assetCreated'      => __( 'Linked Asset record created.', 'worldgraph' ),
				'reloadHint'        => __( 'Reload the editor to see completed media in the featured asset and gallery fields.', 'worldgraph' ),
				'error'             => __( 'Media generation failed.', 'worldgraph' ),
				'unconfiguredImage' => __( 'No runnable image Template is configured. Configure an active text-to-image Template and Connection first.', 'worldgraph' ),
				'unconfiguredVideo' => __( 'No runnable video Template is configured. Configure an active text-to-video Template and Connection first.', 'worldgraph' ),
				'videoUnavailable'  => __( 'Direct video is defined for Shot workflows. Project batches also use the Video Template for their Shot outputs.', 'worldgraph' ),
			],
		] );
	}

	/** Use the changed file timestamp so revised controls cannot remain cached. */
	private static function asset_version( string $relative_path ): string {
		$path = WORLDGRAPH_PLUGIN_DIR . ltrim( $relative_path, '/' );
		return is_file( $path ) ? (string) filemtime( $path ) : WORLDGRAPH_VERSION;
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
	 * Render direct image/video and representative-workflow controls.
	 *
	 * @param \WP_Post $post Current post.
	 */
	private static function render_generator( \WP_Post $post ): void {
		?>
		<div class="worldgraph-generate-asset" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-is-project="<?php echo esc_attr( 'worldgraph_project' === $post->post_type ? '1' : '0' ); ?>">
			<h4><?php esc_html_e( 'Generate representative media', 'worldgraph' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Every request automatically uses the saved title, body, relevant SCF fields, inherited Project/World context, and Generation Prompt Instructions. Save or update this post before queueing so the latest details are included.', 'worldgraph' ); ?></p>
			<div class="worldgraph-generate-asset__workflow" aria-live="polite"></div>
			<label for="worldgraph-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Additional instructions for this run (optional)', 'worldgraph' ); ?></label>
			<textarea class="widefat worldgraph-generate-asset__prompt" id="worldgraph-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>" rows="4" placeholder="<?php esc_attr_e( 'For example: no watermark; slow camera push-in; preserve the established wardrobe.', 'worldgraph' ); ?>"></textarea>
			<p class="description"><?php esc_html_e( 'Enter only one-off directions here. They are appended to the saved Story Graph context; they never replace it. Put reusable directions in the Generation Prompt Instructions SCF field.', 'worldgraph' ); ?></p>
			<details class="worldgraph-generate-asset__context">
				<summary><?php esc_html_e( 'Review the automatically generated prompt', 'worldgraph' ); ?></summary>
				<pre class="worldgraph-generate-asset__context-preview"></pre>
				<button type="button" class="button-link worldgraph-generate-asset__refresh-context"><?php esc_html_e( 'Refresh from saved fields', 'worldgraph' ); ?></button>
			</details>
			<p class="worldgraph-generate-asset__options">
				<label for="worldgraph-generate-asset-output-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Direct output', 'worldgraph' ); ?></label>
				<select class="worldgraph-generate-asset__output" id="worldgraph-generate-asset-output-<?php echo esc_attr( $post->ID ); ?>">
					<option value="image"><?php esc_html_e( 'Still image (text to image)', 'worldgraph' ); ?></option>
					<option value="video" disabled><?php esc_html_e( 'Video (checking workflow…)', 'worldgraph' ); ?></option>
				</select>
				<label for="worldgraph-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Image Template', 'worldgraph' ); ?></label>
				<select class="worldgraph-generate-asset__template" id="worldgraph-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"></select>
				<span class="worldgraph-generate-asset__video-option">
					<label for="worldgraph-generate-asset-video-template-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Video Template', 'worldgraph' ); ?></label>
					<select class="worldgraph-generate-asset__video-template" id="worldgraph-generate-asset-video-template-<?php echo esc_attr( $post->ID ); ?>"></select>
				</span>
				<span class="description"><?php esc_html_e( 'Template choices apply to the direct output and to matching jobs in a representative set.', 'worldgraph' ); ?></span>
				<label><input type="checkbox" class="worldgraph-generate-asset__featured" checked /> <?php esc_html_e( 'Set as featured asset', 'worldgraph' ); ?></label>
				<label><input type="checkbox" class="worldgraph-generate-asset__create" checked /> <?php esc_html_e( 'Create linked Asset record', 'worldgraph' ); ?></label>
			</p>
			<div class="worldgraph-generate-asset__actions">
				<div class="worldgraph-generate-asset__action-group">
					<strong><?php esc_html_e( 'Selected output', 'worldgraph' ); ?></strong>
					<button type="button" class="button button-primary worldgraph-generate-asset__run" disabled><?php esc_html_e( 'Generate image', 'worldgraph' ); ?></button>
				</div>
				<div class="worldgraph-generate-asset__action-group">
					<strong><?php esc_html_e( 'Complete workflows', 'worldgraph' ); ?></strong>
					<button type="button" class="button worldgraph-generate-asset__run-set" disabled><?php esc_html_e( 'Generate this item’s full set', 'worldgraph' ); ?></button>
					<?php if ( 'worldgraph_project' === $post->post_type ) : ?>
						<button type="button" class="button worldgraph-generate-asset__run-project" disabled><?php esc_html_e( 'Generate all Project media', 'worldgraph' ); ?></button>
					<?php endif; ?>
				</div>
				<button type="button" class="button-link-delete worldgraph-generate-asset__cancel" hidden><?php esc_html_e( 'Stop queued work', 'worldgraph' ); ?></button>
			</div>
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
