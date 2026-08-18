<?php
/**
 * Editorial Cut admin page for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Registers the editorial cut entry point in the StoryOS admin menu.
 */
class Editorial_Cut {

	/**
	 * Initialize the editorial cut admin page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
	}

	/**
	 * Add the Editorial Cut submenu page.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'storyos',
			'Editorial Cut',
			'Editorial Cut',
			'edit_posts',
			'storyos-editorial-cut',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Render the editorial sequence overview.
	 */
	public static function render_page(): void {
		$sequences = get_terms(
			[
				'taxonomy'   => \StoryOS\Taxonomies\Sequence::TAXONOMY,
				'hide_empty' => false,
			]
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Editorial Cut', 'storyos' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Organize scenes and shots into editorial sequences.', 'storyos' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . \StoryOS\Taxonomies\Sequence::TAXONOMY . '&post_type=storyos_shot' ) ); ?>">
					<?php esc_html_e( 'Manage Sequences', 'storyos' ); ?>
				</a>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Sequence', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Scenes', 'storyos' ); ?></th>
						<th><?php esc_html_e( 'Shots', 'storyos' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $sequences ) || is_wp_error( $sequences ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No editorial sequences have been created yet.', 'storyos' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $sequences as $sequence ) : ?>
							<?php
							$scene_count = count( \StoryOS\Utils\get_objects_in_term( $sequence->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY, [ 'storyos_scene' ] ) );
							$shot_count  = count( \StoryOS\Utils\get_objects_in_term( $sequence->term_id, \StoryOS\Taxonomies\Sequence::TAXONOMY, [ 'storyos_shot' ] ) );
							?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'term.php?taxonomy=' . \StoryOS\Taxonomies\Sequence::TAXONOMY . '&tag_ID=' . $sequence->term_id ) ); ?>"><?php echo esc_html( $sequence->name ); ?></a></td>
								<td><?php echo esc_html( (string) $scene_count ); ?></td>
								<td><?php echo esc_html( (string) $shot_count ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
