<?php
/**
 * Editorial Cut admin page for World Graph Studio.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Admin;

/**
 * Registers the editorial cut entry point in the World Graph Studio admin menu.
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
			'worldgraph-editorial',
			'Editorial Cut',
			'Editorial Cut',
			'edit_posts',
			'worldgraph-editorial-cut',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Render the editorial sequence overview.
	 */
	public static function render_page(): void {
		$sequences = get_terms(
			[
				'taxonomy'   => \WorldGraph\Taxonomies\Sequence::TAXONOMY,
				'hide_empty' => false,
			]
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Editorial Cut', 'worldgraph' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Organize scenes and shots into editorial sequences.', 'worldgraph' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . \WorldGraph\Taxonomies\Sequence::TAXONOMY . '&post_type=worldgraph_shot' ) ); ?>">
					<?php esc_html_e( 'Manage Sequences', 'worldgraph' ); ?>
				</a>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Sequence', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Scenes', 'worldgraph' ); ?></th>
						<th><?php esc_html_e( 'Shots', 'worldgraph' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $sequences ) || is_wp_error( $sequences ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No editorial sequences have been created yet.', 'worldgraph' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $sequences as $sequence ) : ?>
							<?php
							$scene_count = count( \WorldGraph\Utils\get_objects_in_term( $sequence->term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY, [ 'worldgraph_scene' ] ) );
							$shot_count  = count( \WorldGraph\Utils\get_objects_in_term( $sequence->term_id, \WorldGraph\Taxonomies\Sequence::TAXONOMY, [ 'worldgraph_shot' ] ) );
							?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'term.php?taxonomy=' . \WorldGraph\Taxonomies\Sequence::TAXONOMY . '&tag_ID=' . $sequence->term_id ) ); ?>"><?php echo esc_html( $sequence->name ); ?></a></td>
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
