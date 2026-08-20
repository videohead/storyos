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

		// Keep direct dashboard destinations valid without showing these groups in the sidebar.
		foreach ( [
			[ 'storyos-administration', 'Administration' ],
			[ 'storyos-plugins', 'Plugins' ],
		] as $hidden_page ) {
			add_menu_page(
				$hidden_page[1],
				$hidden_page[1],
				'manage_options',
				$hidden_page[0],
				[ __CLASS__, 'render_group' ],
				'dashicons-admin-generic',
				99
			);
		}

		add_action( 'admin_menu', [ __CLASS__, 'hide_legacy_groups' ], 99 );

		self::add_placeholder_page( 'storyos-summaries', 'Summaries', 'storyos-analysis' );
		self::add_placeholder_page( 'storyos-dramaturgy', 'Dramaturgy', 'storyos-analysis' );
		self::add_placeholder_page( 'storyos-character-tools', 'Character Tools', 'storyos-analysis' );
	}

	/**
	 * Remove dashboard-only groups from the visible sidebar after child pages register.
	 */
	public static function hide_legacy_groups(): void {
		remove_menu_page( 'storyos-administration' );
		remove_menu_page( 'storyos-plugins' );
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
		$title = get_admin_page_title();
		$title = $title ?: __( 'StoryOS', 'storyos' );
		$page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$cards  = self::get_group_cards( $page );
		?>
		<div class="wrap storyos-group-page">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p class="storyos-group-intro"><?php esc_html_e( 'Choose an area to continue.', 'storyos' ); ?></p>
			<?php if ( ! empty( $cards ) ) : ?>
				<div class="storyos-group-cards">
					<?php foreach ( $cards as $card ) : ?>
						<a class="storyos-group-card" href="<?php echo esc_url( $card['url'] ); ?>">
							<span class="storyos-group-card-icon dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
							<span class="storyos-group-card-content">
								<strong><?php echo esc_html( $card['title'] ); ?></strong>
								<span><?php echo esc_html( $card['description'] ); ?></span>
							</span>
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the navigation cards for a main StoryOS page.
	 *
	 * @param string $page Main page slug.
	 * @return array
	 */
	private static function get_group_cards( string $page ): array {
		$cards = [
			'storyos-story-elements' => [
				[ 'title' => 'Projects', 'description' => 'Manage the stories you are building.', 'icon' => 'dashicons-portfolio', 'url' => admin_url( 'edit.php?post_type=storyos_project' ) ],
				[ 'title' => 'Story Worlds', 'description' => 'Define the worlds and settings behind your stories.', 'icon' => 'dashicons-admin-site', 'url' => admin_url( 'edit.php?post_type=storyos_story_world' ) ],
				[ 'title' => 'Characters', 'description' => 'Build the people who drive the story.', 'icon' => 'dashicons-admin-users', 'url' => admin_url( 'edit.php?post_type=storyos_character' ) ],
				[ 'title' => 'Locations', 'description' => 'Organize the places where stories unfold.', 'icon' => 'dashicons-location', 'url' => admin_url( 'edit.php?post_type=storyos_location' ) ],
				[ 'title' => 'Props', 'description' => 'Track meaningful objects and story details.', 'icon' => 'dashicons-archive', 'url' => admin_url( 'edit.php?post_type=storyos_prop' ) ],
			],
			'storyos-editorial' => [
				[ 'title' => 'Episodes', 'description' => 'Organize the larger structure of your story.', 'icon' => 'dashicons-list-view', 'url' => admin_url( 'edit.php?post_type=storyos_episode' ) ],
				[ 'title' => 'Scenes', 'description' => 'Develop the story beat by beat.', 'icon' => 'dashicons-format-video', 'url' => admin_url( 'edit.php?post_type=storyos_scene' ) ],
				[ 'title' => 'Shots', 'description' => 'Plan the visual coverage of each scene.', 'icon' => 'dashicons-camera-alt', 'url' => admin_url( 'edit.php?post_type=storyos_shot' ) ],
				[ 'title' => 'Sounds', 'description' => 'Plan narration, music, effects, ambience, Foley, and silence.', 'icon' => 'dashicons-format-audio', 'url' => admin_url( 'edit.php?post_type=storyos_sound' ) ],
				[ 'title' => 'Assets', 'description' => 'Manage the media and files used by your story.', 'icon' => 'dashicons-media-default', 'url' => admin_url( 'edit.php?post_type=storyos_asset' ) ],
				[ 'title' => 'Editorial Cut', 'description' => 'Review and shape the assembled cut.', 'icon' => 'dashicons-editor-video', 'url' => admin_url( 'admin.php?page=storyos-editorial-cut' ) ],
			],
			'storyos-analysis' => [
				[ 'title' => 'Analysis', 'description' => 'Explore relationships and story graph intelligence.', 'icon' => 'dashicons-chart-area', 'url' => admin_url( 'admin.php?page=storyos-analytics' ) ],
				[ 'title' => 'Summaries', 'description' => 'Generate and review story summaries.', 'icon' => 'dashicons-media-document', 'url' => admin_url( 'admin.php?page=storyos-summaries' ) ],
				[ 'title' => 'Continuity', 'description' => 'Check your story for continuity issues.', 'icon' => 'dashicons-yes-alt', 'url' => admin_url( 'admin.php?page=storyos-continuity' ) ],
				[ 'title' => 'Dramaturgy', 'description' => 'Examine structure, tension, and narrative movement.', 'icon' => 'dashicons-lightbulb', 'url' => admin_url( 'admin.php?page=storyos-dramaturgy' ) ],
				[ 'title' => 'Character Tools', 'description' => 'Work with character arcs and relationships.', 'icon' => 'dashicons-id-alt', 'url' => admin_url( 'admin.php?page=storyos-character-tools' ) ],
			],
			'storyos-administration' => [
				[ 'title' => 'Setup Wizard', 'description' => 'Configure StoryOS connections and workspace settings.', 'icon' => 'dashicons-admin-tools', 'url' => admin_url( 'admin.php?page=storyos-setup' ) ],
				[ 'title' => 'Connections', 'description' => 'Manage external services and integrations.', 'icon' => 'dashicons-admin-links', 'url' => admin_url( 'admin.php?page=storyos-connections' ) ],
				[ 'title' => 'Templates', 'description' => 'Manage reusable story and editorial templates.', 'icon' => 'dashicons-layout', 'url' => admin_url( 'edit.php?post_type=storyos_template' ) ],
				[ 'title' => 'Logs', 'description' => 'Review generation and system activity logs.', 'icon' => 'dashicons-list-view', 'url' => admin_url( 'admin.php?page=storyos-generation-log' ) ],
			],
		];

		return $cards[ $page ] ?? [];
	}

	/**
	 * Render a not-yet-available tool page.
	 */
	public static function render_placeholder(): void {
		$title = get_admin_page_title();
		$title = $title ?: __( 'StoryOS Tool', 'storyos' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php esc_html_e( 'This StoryOS tool is not available yet.', 'storyos' ); ?></p>
		</div>
		<?php
	}
}
