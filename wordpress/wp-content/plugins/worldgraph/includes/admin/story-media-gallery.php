<?php
/**
 * Media-library gallery editor for Story Graph posts.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Manage the ordered supporting-media gallery used by story displays. */
class Story_Media_Gallery {

	/** Stable gallery metadata key shared with the generation system. */
	private const META_KEY = '_worldgraph_asset_gallery_ids';

	/** Post types that may own supporting display media. */
	private const POST_TYPES = [
		'worldgraph_project',
		'worldgraph_world',
		'worldgraph_character',
		'worldgraph_location',
		'worldgraph_prop',
		'worldgraph_org',
		'worldgraph_episode',
		'worldgraph_scene',
		'worldgraph_shot',
		'worldgraph_sound',
		'worldgraph_board',
		'worldgraph_asset',
		'worldgraph_editorial',
	];

	/** Register editor hooks. */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'save_post', [ __CLASS__, 'save' ], 20, 2 );
	}

	/** Add the same media-curation panel to supported story entities. */
	public static function register_meta_boxes(): void {
		foreach ( self::POST_TYPES as $post_type ) {
			add_meta_box(
				'worldgraph_story_media_gallery',
				__( 'Story Media Gallery', 'worldgraph' ),
				[ __CLASS__, 'render' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Load WordPress's media frame and the gallery controller on edit screens.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::POST_TYPES, true ) ) {
			return;
		}

		wp_enqueue_media();
		$script_path = WORLDGRAPH_PLUGIN_DIR . 'assets/js/story-media-gallery.js';
		$style_path  = WORLDGRAPH_PLUGIN_DIR . 'assets/css/story-media-gallery.css';
		wp_enqueue_style(
			'worldgraph-story-media-gallery',
			WORLDGRAPH_PLUGIN_URL . 'assets/css/story-media-gallery.css',
			[],
			is_file( $style_path ) ? (string) filemtime( $style_path ) : WORLDGRAPH_VERSION
		);
		wp_enqueue_script(
			'worldgraph-story-media-gallery',
			WORLDGRAPH_PLUGIN_URL . 'assets/js/story-media-gallery.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			is_file( $script_path ) ? (string) filemtime( $script_path ) : WORLDGRAPH_VERSION,
			true
		);
		wp_localize_script(
			'worldgraph-story-media-gallery',
			'worldgraphStoryGallery',
			[
				'title'  => __( 'Choose story media', 'worldgraph' ),
				'button' => __( 'Add to story gallery', 'worldgraph' ),
				'remove' => __( 'Remove from gallery', 'worldgraph' ),
				'moveUp' => __( 'Move media up', 'worldgraph' ),
				'moveDown' => __( 'Move media down', 'worldgraph' ),
				'audio'  => __( 'Audio', 'worldgraph' ),
				'video'  => __( 'Video', 'worldgraph' ),
				'file'   => __( 'Media', 'worldgraph' ),
			]
		);
	}

	/**
	 * Render the ordered gallery editor.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render( \WP_Post $post ): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to manage story media.', 'worldgraph' ) . '</p>';
			return;
		}

		wp_nonce_field( 'worldgraph_story_media_gallery', 'worldgraph_story_media_gallery_nonce' );
		$attachment_ids = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, self::META_KEY, true ) ) ) );
		?>
		<div class="worldgraph-story-gallery" data-worldgraph-story-gallery>
			<p class="description">
				<?php esc_html_e( 'Choose and order supporting images, audio, or video for the WordPress and headless story displays. Featured media remains the primary image. Generated view frames with a named intent display in their canonical view order.', 'worldgraph' ); ?>
			</p>
			<input type="hidden" name="worldgraph_story_media_gallery_ids" value="<?php echo esc_attr( implode( ',', $attachment_ids ) ); ?>" data-gallery-input />
			<ul class="worldgraph-story-gallery__items" data-gallery-items>
				<?php foreach ( $attachment_ids as $attachment_id ) : ?>
					<?php
					$attachment = get_post( $attachment_id );
					if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
						continue;
					}
					$mime_type = (string) get_post_mime_type( $attachment_id );
					?>
					<li class="worldgraph-story-gallery__item" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
						<span class="worldgraph-story-gallery__handle dashicons dashicons-move" aria-hidden="true"></span>
						<span class="worldgraph-story-gallery__preview">
							<?php if ( 0 === strpos( $mime_type, 'image/' ) ) : ?>
								<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', true, [ 'alt' => '' ] ); ?>
							<?php else : ?>
								<span class="dashicons <?php echo esc_attr( 0 === strpos( $mime_type, 'audio/' ) ? 'dashicons-format-audio' : 'dashicons-format-video' ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
						</span>
						<span class="worldgraph-story-gallery__details">
							<strong><?php echo esc_html( get_the_title( $attachment_id ) ); ?></strong>
							<small><?php echo esc_html( $mime_type ); ?></small>
						</span>
						<span class="worldgraph-story-gallery__actions">
							<button type="button" class="button-link" data-gallery-move="up">
								<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Move media up', 'worldgraph' ); ?></span>
							</button>
							<button type="button" class="button-link" data-gallery-move="down">
								<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Move media down', 'worldgraph' ); ?></span>
							</button>
							<button type="button" class="button-link-delete" data-gallery-remove>
								<?php esc_html_e( 'Remove', 'worldgraph' ); ?>
								<span class="screen-reader-text"> <?php echo esc_html( get_the_title( $attachment_id ) ); ?></span>
							</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="worldgraph-story-gallery__empty" data-gallery-empty <?php echo empty( $attachment_ids ) ? '' : 'hidden'; ?>>
				<?php esc_html_e( 'No supporting media selected.', 'worldgraph' ); ?>
			</p>
			<button type="button" class="button" data-gallery-add><?php esc_html_e( 'Add or choose media', 'worldgraph' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Persist the ordered attachment IDs with the post edit.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Saved post.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		if ( ! isset( $_POST['worldgraph_story_media_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['worldgraph_story_media_gallery_nonce'] ) ), 'worldgraph_story_media_gallery' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$raw_ids = isset( $_POST['worldgraph_story_media_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['worldgraph_story_media_gallery_ids'] ) ) : '';
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) ) );
		$ids     = array_values(
			array_filter(
				$ids,
				static function( int $attachment_id ): bool {
					if ( 'attachment' !== get_post_type( $attachment_id ) ) {
						return false;
					}
					$mime_type = (string) get_post_mime_type( $attachment_id );
					return 0 === strpos( $mime_type, 'image/' ) || 0 === strpos( $mime_type, 'audio/' ) || 0 === strpos( $mime_type, 'video/' );
				}
			)
		);

		if ( empty( $ids ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $ids );
	}
}
