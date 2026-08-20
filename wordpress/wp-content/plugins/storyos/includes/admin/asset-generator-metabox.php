<?php
/**
 * The StoryOS Assets meta box: asset guidance plus the text-to-image
 * generation tools for a story element.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

use StoryOS\Utils\Comfy_Bootstrap;
use StoryOS\Utils\Connection_Adapters;
use StoryOS\Utils\Connection_Repository;
use StoryOS\Utils\Local_ComfyUI;

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
		'storyos_project',
		'storyos_story_world',
		'storyos_character',
		'storyos_location',
		'storyos_prop',
		'storyos_organization',
		'storyos_episode',
		'storyos_scene',
		'storyos_shot',
		'storyos_storyboard',
		'storyos_asset',
		'storyos_editorial',
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
				'storyos_assets',
				__( 'StoryOS Assets', 'storyos' ),
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
			'storyos-asset-generator',
			STORYOS_PLUGIN_URL . 'assets/css/asset-generator.css',
			[],
			STORYOS_VERSION
		);

		wp_enqueue_script(
			'storyos-asset-generator',
			STORYOS_PLUGIN_URL . 'assets/js/asset-generator.js',
			[],
			STORYOS_VERSION,
			true
		);

		wp_localize_script( 'storyos-asset-generator', 'storyosAssetGenerator', [
			'restUrl' => rest_url( 'storyos/v1/assets/generate' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => [
				'generating'   => __( 'Generating image…', 'storyos' ),
				'queued'       => __( 'Image generation queued. WP-Cron will import the completed provider image into this post.', 'storyos' ),
				'job'          => __( 'Job', 'storyos' ),
				'loading'      => __( 'Building a prompt from this story element…', 'storyos' ),
				'done'         => __( 'Image generated and attached.', 'storyos' ),
				'featured'     => __( 'Set as the featured asset.', 'storyos' ),
				'assetCreated' => __( 'Linked Asset record created.', 'storyos' ),
				'reloadHint'   => __( 'Reload the editor to see it in the featured asset and gallery fields.', 'storyos' ),
				'error'        => __( 'Image generation failed.', 'storyos' ),
				'unconfigured' => __( 'No text-to-image endpoint is configured. Set one in StoryOS AI Settings.', 'storyos' ),
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
	 * Render the text-to-image generation controls.
	 *
	 * @param \WP_Post $post Current post.
	 */
	private static function render_generator( \WP_Post $post ): void {
		?>
		<div class="storyos-generate-asset" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<h4><?php esc_html_e( 'Generate asset', 'storyos' ); ?></h4>
			<p class="description"><?php esc_html_e( 'The result here is uploaded to the media library and linked to this post. You can ask the agents for specific assistance.', 'storyos' ); ?></p>
			<label for="storyos-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Prompt', 'storyos' ); ?></label>
			<textarea class="widefat storyos-generate-asset__prompt" id="storyos-generate-asset-prompt-<?php echo esc_attr( $post->ID ); ?>" rows="4" placeholder="<?php esc_attr_e( 'Describe the image to generate.', 'storyos' ); ?>"></textarea>
			<p class="storyos-generate-asset__options">
				<label for="storyos-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Template', 'storyos' ); ?></label>
				<select class="storyos-generate-asset__template" id="storyos-generate-asset-template-<?php echo esc_attr( $post->ID ); ?>"></select>
				<label><input type="checkbox" class="storyos-generate-asset__featured" checked /> <?php esc_html_e( 'Set as featured asset', 'storyos' ); ?></label>
				<label><input type="checkbox" class="storyos-generate-asset__create" checked /> <?php esc_html_e( 'Create linked Asset record', 'storyos' ); ?></label>
			</p>
			<p>
				<button type="button" class="button button-primary storyos-generate-asset__run"><?php esc_html_e( 'Generate image', 'storyos' ); ?></button>
				<button type="button" class="button storyos-generate-asset__suggest"><?php esc_html_e( 'Suggest prompt', 'storyos' ); ?></button>
			</p>
			<div class="storyos-generate-asset__status" role="status" aria-live="polite"></div>
			<div class="storyos-generate-asset__result" hidden></div>
		</div>
		<?php
	}
}
