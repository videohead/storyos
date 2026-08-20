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
 * remain WordPress attachments or storyos_asset records linked through the
 * optional Asset field.
 */
class Sound {

	/**
	 * Register the CPT and its admin save handler.
	 */
	public static function init(): void {
		self::register_cpt();
		add_action( 'save_post_storyos_sound', [ __CLASS__, 'save_meta' ], 10, 2 );
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
}
