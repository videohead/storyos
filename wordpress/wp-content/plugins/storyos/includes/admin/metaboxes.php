<?php
/**
 * Admin MetaBoxes for StoryOS.
 *
 * Provides generic meta box rendering for StoryOS CPTs.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * MetaBoxes class.
 */
class MetaBoxes {

	/**
	 * CPTs that can have a featured story asset selected.
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
		'storyos_storyboard_frame',
		'storyos_asset',
		'storyos_editorial_artifact',
	];

	/**
	 * Initialize the meta boxes.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media' ] );
		add_action( 'save_post', [ __CLASS__, 'save_featured_asset' ], 20, 2 );
		add_action( 'admin_footer-post.php', [ __CLASS__, 'render_media_script' ] );
		add_action( 'admin_footer-post-new.php', [ __CLASS__, 'render_media_script' ] );
	}

	/**
	 * Enqueue the native WordPress media modal on supported edit screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_media( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::ASSET_CPTS, true ) ) {
			return;
		}

		wp_enqueue_media();
	}

	/**
	 * Register meta boxes.
	 */
	public static function register_meta_boxes(): void {
		$cpts = \StoryOS\Utils\storyos_get_all_cpts();

		foreach ( $cpts as $cpt ) {
			// Add details meta box.
			add_meta_box(
				'storyos_details',
				__( 'StoryOS Details', 'storyos' ),
				[ __CLASS__, 'render_details_box' ],
				$cpt,
				'normal',
				'default'
			);

			// Add graph connections meta box.
			add_meta_box(
				'storyos_graph',
				__( 'Graph Connections', 'storyos' ),
				[ __CLASS__, 'render_graph_box' ],
				$cpt,
				'side',
				'default'
			);

			if ( in_array( $cpt, self::ASSET_CPTS, true ) ) {
				add_meta_box(
					'storyos_assets',
					__( 'StoryOS Assets', 'storyos' ),
					[ __CLASS__, 'render_assets_box' ],
					$cpt,
					'normal',
					'default'
				);
			}
		}
	}

	/**
	 * Render the quick asset uploader.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render_assets_box( \WP_Post $post ): void {
		wp_nonce_field( 'storyos_featured_asset', 'storyos_featured_asset_nonce' );
		wp_nonce_field( 'storyos_asset_gallery', 'storyos_asset_gallery_nonce' );
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		$gallery_ids  = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_storyos_asset_gallery_ids', true ) ) ) );
		?>
		<p><?php esc_html_e( 'Choose the primary image or asset for this story item. This uses the WordPress featured image and is available through the StoryOS API.', 'storyos' ); ?></p>
		<div class="storyos-featured-asset" data-attachment-id="<?php echo esc_attr( $thumbnail_id ); ?>">
			<input type="hidden" name="storyos_featured_asset_id" value="<?php echo esc_attr( $thumbnail_id ); ?>" />
			<?php if ( $thumbnail_id ) : ?>
				<?php echo wp_get_attachment_image( $thumbnail_id, [ 120, 120 ], true ); ?>
				<span><?php echo esc_html( get_the_title( $thumbnail_id ) ?: wp_basename( get_attached_file( $thumbnail_id ) ) ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'No featured asset selected.', 'storyos' ); ?></span>
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button storyos-select-featured-asset"><?php esc_html_e( 'Upload or select featured asset', 'storyos' ); ?></button>
			<button type="button" class="button-link-delete storyos-remove-featured-asset" <?php disabled( ! $thumbnail_id ); ?>><?php esc_html_e( 'Remove', 'storyos' ); ?></button>
		</p>
		<hr />
		<p><?php esc_html_e( 'Add supporting references, alternates, and generated outputs to this media manager gallery.', 'storyos' ); ?></p>
		<div class="storyos-asset-gallery">
			<?php foreach ( $gallery_ids as $gallery_id ) : ?>
				<?php if ( 'attachment' !== get_post_type( $gallery_id ) ) : continue; endif; ?>
				<div class="storyos-gallery-item" data-attachment-id="<?php echo esc_attr( $gallery_id ); ?>">
					<input type="hidden" name="storyos_asset_gallery_ids[]" value="<?php echo esc_attr( $gallery_id ); ?>" />
					<?php echo wp_get_attachment_image( $gallery_id, [ 80, 80 ], true ); ?>
					<span><?php echo esc_html( get_the_title( $gallery_id ) ?: wp_basename( get_attached_file( $gallery_id ) ) ); ?></span>
					<button type="button" class="button-link-delete storyos-remove-gallery-item"><?php esc_html_e( 'Remove', 'storyos' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button storyos-select-gallery"><?php esc_html_e( 'Upload or select gallery assets', 'storyos' ); ?></button></p>
		<?php
	}

	/**
	 * Save the selected WordPress featured image for supported StoryOS posts.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_featured_asset( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::ASSET_CPTS, true ) || ! isset( $_POST['storyos_featured_asset_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storyos_featured_asset_nonce'] ) ), 'storyos_featured_asset' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$thumbnail_id = isset( $_POST['storyos_featured_asset_id'] ) ? absint( $_POST['storyos_featured_asset_id'] ) : 0;
		if ( ! $thumbnail_id || 'attachment' !== get_post_type( $thumbnail_id ) ) {
			delete_post_thumbnail( $post_id );
			self::save_asset_gallery( $post_id );
			return;
		}

		set_post_thumbnail( $post_id, $thumbnail_id );
		self::save_asset_gallery( $post_id );
	}

	/**
	 * Save the selected supporting media IDs.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function save_asset_gallery( int $post_id ): void {
		if ( ! isset( $_POST['storyos_asset_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storyos_asset_gallery_nonce'] ) ), 'storyos_asset_gallery' ) ) {
			return;
		}

		$gallery_ids = isset( $_POST['storyos_asset_gallery_ids'] ) && is_array( $_POST['storyos_asset_gallery_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['storyos_asset_gallery_ids'] ) ) : [];
		$gallery_ids = array_values( array_filter( array_unique( $gallery_ids ), static function ( int $attachment_id ): bool {
			return 'attachment' === get_post_type( $attachment_id );
		} ) );

		if ( empty( $gallery_ids ) ) {
			delete_post_meta( $post_id, '_storyos_asset_gallery_ids' );
			return;
		}

		update_post_meta( $post_id, '_storyos_asset_gallery_ids', $gallery_ids );
	}

	/**
	 * Add the small media-modal controller used by the uploader box.
	 */
	public static function render_media_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::ASSET_CPTS, true ) ) {
			return;
		}
		?>
		<script>
		jQuery(function($) {
			var frame;
			$('.storyos-select-featured-asset').on('click', function(event) {
				event.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({ title: 'Select or upload featured asset', button: { text: 'Use as featured asset' }, multiple: false });
				frame.on('select', function() {
					var item = frame.state().get('selection').first().toJSON();
					var thumbnail = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.icon;
					$('.storyos-featured-asset').attr('data-attachment-id', item.id).html('<input type="hidden" name="storyos_featured_asset_id" value="' + item.id + '" /><img src="' + thumbnail + '" width="120" height="120" alt="" /><span>' + $('<div>').text(item.filename || item.title).html() + '</span>');
					$('.storyos-remove-featured-asset').prop('disabled', false);
				});
				frame.open();
			});
			$('.storyos-remove-featured-asset').on('click', function(event) { event.preventDefault(); $('.storyos-featured-asset').attr('data-attachment-id', 0).html('<input type="hidden" name="storyos_featured_asset_id" value="0" /><span>No featured asset selected.</span>'); $(this).prop('disabled', true); });
			var galleryFrame;
			$('.storyos-select-gallery').on('click', function(event) {
				event.preventDefault();
				if (galleryFrame) { galleryFrame.open(); return; }
				galleryFrame = wp.media({ title: 'Select or upload gallery assets', button: { text: 'Add to gallery' }, multiple: true });
				galleryFrame.on('select', function() {
					galleryFrame.state().get('selection').each(function(attachment) {
						var item = attachment.toJSON();
						if ($('.storyos-gallery-item[data-attachment-id="' + item.id + '"]').length) { return; }
						var thumbnail = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.icon;
						$('.storyos-asset-gallery').append('<div class="storyos-gallery-item" data-attachment-id="' + item.id + '"><input type="hidden" name="storyos_asset_gallery_ids[]" value="' + item.id + '" /><img src="' + thumbnail + '" width="80" height="80" alt="" /><span>' + $('<div>').text(item.filename || item.title).html() + '</span><button type="button" class="button-link-delete storyos-remove-gallery-item">Remove</button></div>');
					});
				});
				galleryFrame.open();
			});
			$('.storyos-asset-gallery').on('click', '.storyos-remove-gallery-item', function() { $(this).closest('.storyos-gallery-item').remove(); });
		});
		</script>
		<style>
		.storyos-featured-asset { align-items: center; display: flex; gap: 12px; min-height: 80px; }
		.storyos-featured-asset img { height: 120px; object-fit: cover; width: 120px; }
		.storyos-gallery-item { align-items: center; display: flex; gap: 12px; margin: 8px 0; }
		.storyos-gallery-item img { height: 80px; object-fit: cover; width: 80px; }
		.storyos-gallery-item .storyos-remove-gallery-item { margin-left: auto; }
		</style>
		<?php
	}

	/**
	 * Render the details meta box.
	 *
	 * @param \WP_Post $post
	 */
	public static function render_details_box( \WP_Post $post ): void {
		wp_nonce_field( 'storyos_details', 'storyos_details_nonce' );

		// Get all fields for this CPT.
		$fields = \StoryOS\Utils\storyos_get_fields( $post->post_type );

		if ( empty( $fields ) ) {
			echo '<p>No fields defined for this post type.</p>';
			return;
		}

		$cpt_fields = $fields;

		?>
		<table class="form-table">
			<?php foreach ( $cpt_fields as $field ) : ?>
				<?php
				$value = get_post_meta( $post->ID, $field['name'], true );
				if ( empty( $value ) && isset( $field['default'] ) ) {
					$value = $field['default'];
				}
				?>
				<tr>
					<th><label for="<?php echo esc_attr( $field['name'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<?php
						switch ( $field['type'] ) {
							case 'textarea':
								?>
								<textarea name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['name'] ); ?>" class="large-text" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
								<?php
								if ( ! empty( $field['description'] ) ) {
									echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
								}
								break;

							case 'wysiwyg':
								wp_editor(
									$value,
									$field['name'],
									[
										'tinymce'  => true,
										'quicktags' => true,
										'editor_height' => 150,
									]
								);
								break;

							case 'select':
								?>
								<select name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['name'] ); ?>">
									<option value=""><?php echo esc_html( 'Select...' ); ?></option>
									<?php foreach ( (array) $field['options'] as $option_value => $option_label ) : ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
											<?php echo esc_html( $option_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php
								if ( ! empty( $field['description'] ) ) {
									echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
								}
								break;

							case 'number':
								?>
								<input type="number" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
								<?php
								if ( ! empty( $field['description'] ) ) {
									echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
								}
								break;

							case 'date':
								?>
								<input type="date" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
								<?php
								if ( ! empty( $field['description'] ) ) {
									echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
								}
								break;

							default:
								?>
								<input type="text" name="<?php echo esc_attr( $field['name'] ); ?>" id="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
								<?php
								if ( ! empty( $field['description'] ) ) {
									echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
								}
								break;
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Render the graph connections meta box.
	 *
	 * @param \WP_Post $post
	 */
	public static function render_graph_box( \WP_Post $post ): void {
		$relationships = \StoryOS\Utils\get_relationships( $post->ID );

		if ( empty( $relationships ) ) {
			echo '<p>' . esc_html( 'No connections yet.' ) . '</p>';
			return;
		}

		?>
		<ul class="list-inside">
			<?php foreach ( $relationships as $rel ) : ?>
				<li>
					<?php
					$target = get_post( $rel['to_id'] );
					if ( $target ) :
						?>
						<a href="<?php echo get_edit_post_link( $rel['to_id'] ); ?>">
							<?php echo esc_html( $target->post_title ); ?>
						</a>
						<span class="dashicons dashicons-arrow-right-alt"></span>
						<small><?php echo esc_html( $rel['type'] ); ?></small>
					<?php else : ?>
						<small><?php echo esc_html( 'Deleted' ); ?></small>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
