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
	 * Initialize the meta boxes.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_boxes' ] );
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
		}
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
