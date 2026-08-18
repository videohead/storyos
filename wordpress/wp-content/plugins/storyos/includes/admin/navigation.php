<?php
/**
 * StoryOS admin navigation.
 *
 * @package StoryOS
 */

namespace StoryOS\Admin;

/**
 * Registers the StoryOS sidebar groups and placeholder tool pages.
 */
class Navigation {

	/**
	 * Register navigation hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 5 );
	}

	/**
	 * Add the StoryOS sidebar groups.
	 */
	public static function add_menu(): void {
		$groups = [
			[ 'storyos-story-elements', 'Story Elements', 'Story Elements', 'edit_posts', 'dashicons-book-alt', 31 ],
			[ 'storyos-editorial', 'Editorial', 'Editorial', 'edit_posts', 'dashicons-edit', 32 ],
			[ 'storyos-analysis', 'Story Analysis', 'Story Analysis', 'edit_posts', 'dashicons-chart-area', 33 ],
		];

		foreach ( $groups as $group ) {
			add_menu_page(
				$group[1],
				$group[2],
				$group[3],
				$group[0],
				[ __CLASS__, 'render_group' ],
				$group[4],
				$group[5]
			);
		}

		self::add_placeholder_page( 'storyos-summaries', 'Summaries', 'storyos-analysis' );
		self::add_placeholder_page( 'storyos-dramaturgy', 'Dramaturgy', 'storyos-analysis' );
		self::add_placeholder_page( 'storyos-character-tools', 'Character Tools', 'storyos-analysis' );
	}

	/**
	 * Add a page for a tool that does not have an implementation yet.
	 *
	 * @param string $slug Menu slug.
	 * @param string $label Menu label.
	 * @param string $parent Parent menu slug.
	 */
	private static function add_placeholder_page( string $slug, string $label, string $parent ): void {
		add_submenu_page(
			$parent,
			$label,
			$label,
			'edit_posts',
			$slug,
			[ __CLASS__, 'render_placeholder' ]
		);
	}

	/**
	 * Render a group landing page.
	 */
	public static function render_group(): void {
		$screen = get_current_screen();
		$title  = $screen ? $screen->title : 'StoryOS';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php esc_html_e( 'Choose an item from this section to continue.', 'storyos' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a not-yet-available tool page.
	 */
	public static function render_placeholder(): void {
		$screen = get_current_screen();
		$title  = $screen ? $screen->title : 'StoryOS Tool';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php esc_html_e( 'This StoryOS tool is not available yet.', 'storyos' ); ?></p>
		</div>
		<?php
	}
}