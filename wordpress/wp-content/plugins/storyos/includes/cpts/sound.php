<?php
/**
 * Sound Custom Post Type.
 *
 * @package StoryOS
 */

namespace StoryOS\CPT;

/**
 * Planned soundtrack cue linked to Story Graph entities.
 *
 * Sound records describe authorial and production intent. Rendered audio files
 * remain WordPress attachments represented by storyos_asset records linked
 * through the optional Asset field.
 */
class Sound {

	/**
	 * Register the CPT and its admin save handler.
	 */
	public static function init(): void {
		self::register_cpt();
		add_action( 'save_post_storyos_sound', [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'add_meta_boxes_storyos_sound', [ __CLASS__, 'remove_duplicate_taxonomy_boxes' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_validation_notice' ] );
	}

	/**
	 * Register the Sound CPT.
	 */
	private static function register_cpt(): void {
		$fields = [
			'sound_type'       => [
				'type'        => 'taxonomy',
				'taxonomy'    => 'storyos_sound_type',
				'label'       => 'Sound Type',
				'required'    => true,
				'description' => 'Narration, voice-over, music, effects, ambience, Foley, silence, or another soundtrack role.',
			],
			'production_status'=> [
				'type'        => 'taxonomy',
				'taxonomy'    => 'storyos_status',
				'label'       => 'Production Status',
				'required'    => false,
			],
			'spoken_text'      => [
				'type'        => 'textarea',
				'label'       => 'Spoken Text',
				'required'    => false,
				'description' => 'Narration, voice-over, or ADR copy. Ordinary screenplay dialogue remains on the Scene.',
			],
			'lyrics'           => [
				'type'        => 'textarea',
				'label'       => 'Lyrics',
				'required'    => false,
				'description' => 'Lyrics for a music cue, preserving line breaks.',
			],
			'start_timecode'   => [
				'type'        => 'text',
				'label'       => 'Start Timecode',
				'required'    => false,
				'description' => 'Cue position within the linked Scene or Shot, using the project timecode convention.',
			],
			'duration'         => [
				'type'        => 'text',
				'label'       => 'Duration',
				'required'    => false,
				'description' => 'ISO 8601 duration is preferred (for example, PT18S).',
			],
			'diegetic'         => [
				'type'        => 'select',
				'label'       => 'Story-world Relation',
				'required'    => false,
				'default'     => 'unspecified',
				'options'     => [
					'unspecified'  => 'Unspecified',
					'diegetic'     => 'Diegetic (heard by characters)',
					'non_diegetic' => 'Non-diegetic (audience only)',
					'internal'     => 'Internal / Subjective',
					'mixed'        => 'Mixed / Ambiguous',
				],
			],
			'production_notes' => [
				'type'        => 'textarea',
				'label'       => 'Production Notes',
				'required'    => false,
			],
			'scene'            => [
				'type'              => 'relationship',
				'label'             => 'Scene',
				'required'          => true,
				'related_cpt'       => 'storyos_scene',
				'relationship_type' => 'belongs_to',
			],
			'shot'             => [
				'type'              => 'relationship',
				'label'             => 'Shot',
				'required'          => false,
				'related_cpt'       => 'storyos_shot',
				'relationship_type' => 'belongs_to',
				'description'       => 'Optional when the cue applies to a specific shot rather than the whole scene.',
			],
			'character'        => [
				'type'              => 'relationship',
				'label'             => 'Narrator / Voice Character',
				'required'          => false,
				'related_cpt'       => 'storyos_character',
				'relationship_type' => 'linked_to',
			],
			'asset'            => [
				'type'              => 'relationship',
				'label'             => 'Rendered Audio Asset',
				'required'          => false,
				'related_cpt'       => 'storyos_asset',
				'relationship_type' => 'linked_to',
				'description'       => 'Optional audio Asset containing the recorded or generated result.',
				'query_args'        => [
					'tax_query' => [
						[
							'taxonomy' => 'storyos_asset_type',
							'field'    => 'slug',
							'terms'    => [ 'audio' ],
						],
					],
				],
			],
		];

		\StoryOS\Utils\register_cpt(
			'storyos_sound',
			'Sounds',
			[
				'menu_icon'    => 'dashicons-format-audio',
				'show_in_menu' => 'storyos-editorial',
				'supports'     => [ 'title', 'editor', 'excerpt', 'custom-fields', 'revisions', 'page-attributes' ],
			],
			$fields
		);
	}

	/**
	 * Keep the StoryOS Details selectors as the single taxonomy editing surface.
	 */
	public static function remove_duplicate_taxonomy_boxes(): void {
		remove_meta_box( 'tagsdiv-storyos_sound_type', 'storyos_sound', 'side' );
		remove_meta_box( 'tagsdiv-storyos_status', 'storyos_sound', 'side' );
	}

	/**
	 * Save Sound fields from the generic StoryOS Details meta box.
	 *
	 * @param int      $post_id Sound post ID.
	 * @param \WP_Post $post    Sound post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( 'storyos_sound' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['storyos_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storyos_details_nonce'] ) ), 'storyos_details' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$valid = self::validate_admin_request( $post );
		if ( is_wp_error( $valid ) ) {
			update_post_meta( $post_id, '_storyos_sound_validation_error', $valid->get_error_message() );
			if ( ! in_array( $post->post_status, [ 'auto-draft', 'draft', 'pending' ], true ) ) {
				remove_action( 'save_post_storyos_sound', [ __CLASS__, 'save_meta' ], 10 );
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
				add_action( 'save_post_storyos_sound', [ __CLASS__, 'save_meta' ], 10, 2 );
			}
			return;
		}

		delete_post_meta( $post_id, '_storyos_sound_validation_error' );

		$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_sound' );
		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $_POST ) ) {
				continue;
			}

			if ( 'taxonomy' === $field['type'] ) {
				$term_id = absint( $_POST[ $key ] );
				wp_set_object_terms( $post_id, $term_id ? [ $term_id ] : [], $field['taxonomy'], false );
				continue;
			}

			if ( 'relationship' === $field['type'] ) {
				\StoryOS\Utils\set_relationship(
					$post_id,
					'storyos_sound',
					absint( $_POST[ $key ] ),
					$field['related_cpt'],
					(string) ( $field['relationship_type'] ?? 'belongs_to' ),
					[ 'field' => $key ]
				);
				continue;
			}

			$value = \StoryOS\Utils\storyos_sanitize_field_value( $_POST[ $key ], $field );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Display the last server-side Sound validation failure.
	 */
	public static function render_validation_notice(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || 'storyos_sound' !== get_post_type( $post_id ) ) {
			return;
		}

		$message = (string) get_post_meta( $post_id, '_storyos_sound_validation_error', true );
		if ( '' !== $message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Validate the complete Sound selector state submitted by wp-admin.
	 *
	 * @param \WP_Post $post Sound post.
	 * @return true|\WP_Error
	 */
	private static function validate_admin_request( \WP_Post $post ) {
		if ( '' === trim( (string) $post->post_title ) ) {
			return new \WP_Error( 'storyos_sound_title_required', 'A Sound title is required. The post was kept as a draft.' );
		}

		$type_id = isset( $_POST['sound_type'] ) ? absint( $_POST['sound_type'] ) : 0;
		$type    = $type_id ? get_term( $type_id, 'storyos_sound_type' ) : null;
		if ( ! $type || is_wp_error( $type ) || \StoryOS\Utils\storyos_is_reserved_sound_type( $type ) ) {
			return new \WP_Error( 'storyos_sound_type_required', 'Select a valid non-dialogue Sound Type. The post was kept as a draft.' );
		}

		$scene_id = isset( $_POST['scene'] ) ? absint( $_POST['scene'] ) : 0;
		if ( ! $scene_id || 'storyos_scene' !== get_post_type( $scene_id ) ) {
			return new \WP_Error( 'storyos_sound_scene_required', 'Select a valid Scene. The post was kept as a draft.' );
		}

		$shot_id = isset( $_POST['shot'] ) ? absint( $_POST['shot'] ) : 0;
		if ( $shot_id && ( 'storyos_shot' !== get_post_type( $shot_id ) || ! self::shot_belongs_to_scene( $shot_id, $scene_id ) ) ) {
			return new \WP_Error( 'storyos_sound_shot_scene_mismatch', 'The selected Shot must belong to the selected Scene. The post was kept as a draft.' );
		}

		$character_id = isset( $_POST['character'] ) ? absint( $_POST['character'] ) : 0;
		if ( $character_id && 'storyos_character' !== get_post_type( $character_id ) ) {
			return new \WP_Error( 'storyos_sound_character_invalid', 'Select a valid narrator or voice Character. The post was kept as a draft.' );
		}

		$asset_id = isset( $_POST['asset'] ) ? absint( $_POST['asset'] ) : 0;
		if ( $asset_id && ! \StoryOS\Utils\storyos_is_audio_asset( $asset_id ) ) {
			return new \WP_Error( 'storyos_sound_asset_invalid', 'The rendered Asset must have the Audio asset type. The post was kept as a draft.' );
		}

		$lyrics = isset( $_POST['lyrics'] ) ? trim( (string) wp_unslash( $_POST['lyrics'] ) ) : '';
		if ( '' !== $lyrics && 'music' !== $type->slug ) {
			return new \WP_Error( 'storyos_sound_lyrics_music_only', 'Lyrics may only be stored on a Music Sound. The post was kept as a draft.' );
		}

		return true;
	}

	/**
	 * Confirm that the selected Shot belongs to the selected Scene.
	 *
	 * @param int $shot_id  Shot post ID.
	 * @param int $scene_id Scene post ID.
	 * @return bool
	 */
	private static function shot_belongs_to_scene( int $shot_id, int $scene_id ): bool {
		foreach ( \StoryOS\Utils\get_relationships( $shot_id, 'storyos_shot', 'outgoing' ) as $relationship ) {
			if ( $scene_id === (int) ( $relationship['to_id'] ?? 0 ) && 'storyos_scene' === (string) ( $relationship['to_type'] ?? '' ) ) {
				return true;
			}
		}

		foreach ( \StoryOS\Utils\get_relationships( $scene_id, 'storyos_scene', 'outgoing' ) as $relationship ) {
			if ( $shot_id === (int) ( $relationship['to_id'] ?? 0 ) && 'storyos_shot' === (string) ( $relationship['to_type'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}
}
