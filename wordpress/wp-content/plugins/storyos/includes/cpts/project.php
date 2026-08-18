<?php
/**
 * Project Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

/**
 * Project Custom Post Type handler.
 */
class Project {
	/**
	 * Register the Project CPT.
	 */
	public static function init(): void {
		self::register_cpt();
		self::register_meta_boxes();
	}

	/**
	 * Register the Project CPT.
	 */
	public static function register_cpt(): void {
		$fields = [
		'project_name'        => [
			'type'        => 'text',
			'label'       => 'Project Name',
			'required'    => true,
			'description' => 'The official name of the project.',
		],
		'project_slug'        => [
			'type'        => 'text',
			'label'       => 'Project Slug',
			'required'    => true,
			'description' => 'URL-friendly identifier.',
		],
		'description'         => [
			'type'        => 'wysiwyg',
			'label'       => 'Description',
			'required'    => false,
			'description' => 'Project overview and synopsis.',
		],
		'genre'               => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'storyos_genre',
			'label'       => 'Genre',
			'required'    => false,
			'multiple'    => true,
		],
		'target_medium'       => [
			'type'        => 'select',
			'label'       => 'Target Medium',
			'required'    => false,
			'options'     => [
				'film'        => 'Feature Film',
				'short_film'  => 'Short Film',
				'tv_series'   => 'TV Series',
				'web_series'  => 'Web Series',
				'anime'       => 'Anime',
				'animation'   => 'Animation',
				'documentary' => 'Documentary',
				'game'        => 'Game',
				'other'       => 'Other',
			],
		],
		'status'              => [
			'type'        => 'taxonomy',
			'taxonomy'    => 'storyos_status',
			'label'       => 'Production Status',
			'required'    => false,
		],
		'owner'               => [
			'type'        => 'user',
			'label'       => 'Project Owner',
			'required'    => true,
		],
		'start_date'          => [
			'type'        => 'date',
			'label'       => 'Start Date',
			'required'    => false,
		],
		'end_date'            => [
			'type'        => 'date',
			'label'       => 'End Date',
			'required'    => false,
		],
		'team_members'        => [
			'type'        => 'relationship',
			'label'       => 'Team Members',
			'required'    => false,
			'related_cpt' => 'storyos_character',
			'multiple'    => true,
		],
		'production_stage'    => [
			'type'        => 'select',
			'label'       => 'Production Stage',
			'required'    => false,
			'options'     => [
				'concept'       => 'Concept',
				'development'   => 'Development',
				'pre_production'=> 'Pre-Production',
				'production'    => 'Production',
				'post_production' => 'Post-Production',
				'released'      => 'Released',
			],
		],
		'frame_width'        => [
			'type'        => 'number',
			'label'       => 'Frame Width (px)',
			'required'    => false,
			'description' => 'Pixel width used for generated images and video.',
		],
		'frame_height'       => [
			'type'        => 'number',
			'label'       => 'Frame Height (px)',
			'required'    => false,
			'description' => 'Pixel height used for generated images and video.',
		],
		'aspect_ratio'       => [
			'type'        => 'text',
			'label'       => 'Aspect Ratio',
			'required'    => false,
			'description' => 'Project frame ratio, for example 16:9 or 2.39:1.',
		],
		'frame_rate'         => [
			'type'        => 'number',
			'label'       => 'Frame Rate (fps)',
			'required'    => false,
			'description' => 'Frames per second used for generated video.',
		],
	];

	\StoryOS\Utils\register_cpt(
		'storyos_project',
		'Projects',
		[
			'menu_icon' => 'dashicons-video-alt3',
			'menu_position' => 5,
		],
		$fields
	);
	}

	/**
	 * Register meta boxes for Project.
	 */
	private static function register_meta_boxes(): void {
		add_action( 'add_meta_boxes', function(): void {
			add_meta_box(
				'storyos_project_details',
				'Project Details',
				__NAMESPACE__ . '\\project_details_meta_box',
				'storyos_project',
				'side',
				'default'
			);

			add_meta_box(
				'storyos_project_graph',
				'Story Graph Connections',
				__NAMESPACE__ . '\\project_graph_meta_box',
				'storyos_project',
				'side',
				'default'
			);
		} );
	}
}

/**
 * Render the project details meta box.
 *
 * @param \WP_Post $post
 */
function project_details_meta_box( \WP_Post $post ): void {
	wp_nonce_field( 'storyos_project_details', 'storyos_project_nonce' );

	$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_project' );

	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );

		echo '<p>';
		echo '<label for="storyos_' . esc_attr( $key ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label><br>';

		switch ( $field['type'] ) {
			case 'text':
			case 'date':
				echo '<input type="' . esc_attr( $field['type'] ) . '" id="storyos_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="widefat" />';
				break;

			case 'number':
				echo '<input type="number" id="storyos_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" min="0" step="any" class="widefat" />';
				break;

			case 'select':
				echo '<select id="storyos_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
				echo '<option value="">-- Select --</option>';
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					echo '<option value="' . esc_attr( $opt_value ) . '" ' . selected( $value, $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
				break;

			case 'user':
				echo '<input type="number" id="storyos_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" min="1" class="small-text" />';
				echo '<span class="description">User ID</span>';
				break;

			default:
				echo '<input type="text" id="storyos_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="widefat" />';
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<br><span class="description">' . esc_html( $field['description'] ) . '</span>';
		}

		echo '</p>';
	}
}

/**
 * Render the story graph connections meta box.
 *
 * @param \WP_Post $post
 */
function project_graph_meta_box( \WP_Post $post ): void {
	$rels = \StoryOS\Utils\get_relationships( $post->ID, 'storyos_project', 'outgoing' );

	if ( empty( $rels ) ) {
		echo '<p>No connections yet.</p>';
		return;
	}

	echo '<ul class="storyos-graph-list">';
	foreach ( $rels as $rel ) {
		$target = get_post( $rel['to_id'] );
		if ( $target ) {
			echo '<li>';
			echo '<strong>' . esc_html( $target->post_title ) . '</strong> ';
			echo '<span class="storyos-rel-type">(' . esc_html( $rel['type'] ) . ')</span>';
			echo ' <small>(' . esc_html( $rel['to_type'] ) . ')</small>';
			echo '</li>';
		}
	}
	echo '</ul>';
}

/**
 * Save project meta.
 *
 * @param int $post_id
 * @param \WP_Post $post
 */
function save_project_meta( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['storyos_project_nonce'] ) || ! wp_verify_nonce( $_POST['storyos_project_nonce'], 'storyos_project_details' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_project' );

	foreach ( $fields as $key => $field ) {
		if ( isset( $_POST[ $key ] ) ) {
			switch ( $field['type'] ) {
				case 'taxonomy':
					if ( ! empty( $_POST[ $key ] ) ) {
						if ( ! empty( $field['multiple'] ) ) {
							wp_set_object_terms( $post_id, array_map( 'absint', $_POST[ $key ] ), $field['taxonomy'] );
						} else {
							wp_set_object_terms( $post_id, absint( $_POST[ $key ] ), $field['taxonomy'] );
						}
					}
					break;

				case 'relationship':
					if ( ! empty( $_POST[ $key ] ) ) {
						$rel_ids = is_array( $_POST[ $key ] ) ? $_POST[ $key ] : [ $_POST[ $key ] ];
						foreach ( $rel_ids as $rel_id ) {
							\StoryOS\Utils\add_relationship(
								$post_id,
								'storyos_project',
								absint( $rel_id ),
								$field['related_cpt'],
								'contains'
							);
						}
					}
					break;

				default:
					update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
					break;
			}
		}
	}
}
add_action( 'save_post_storyos_project', __NAMESPACE__ . '\save_project_meta', 10, 2 );
