<?php
/**
 * StoryOS JSON Importer.
 *
 * Imports a StoryOS JSON document (e.g. little-red-riding-hood.storyos.json)
 * into StoryOS CPTs, SCF fields, relationships, and Story Graph entities.
 *
 * @package StoryOS
 */

namespace StoryOS\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core importer engine.
 *
 * Implements the deterministic import workflow defined in
 * about/example-workflow/JSON_import_spec.md:
 *
 *   StoryOS JSON → JSON Validation → CPT Creation → SCF Population
 *   → Relationship Creation → Story Graph Construction → Verification
 */
class StoryOS_Importer {

	/**
	 * The parsed JSON document.
	 *
	 * @var array
	 */
	private $document = [];

	/**
	 * Map of external IDs to WordPress post IDs.
	 *
	 * @var array<string, int>
	 */
	private $id_map = [];

	/**
	 * Import report.
	 *
	 * @var array
	 */
	private $report = [];

	/**
	 * Whether to overwrite existing entities with the same external ID.
	 *
	 * @var bool
	 */
	private $overwrite = false;

	/**
	 * Sound external IDs skipped because overwrite was disabled.
	 *
	 * Skipped records must not have their existing graph edges replaced.
	 *
	 * @var array<string, bool>
	 */
	private $skipped_sounds = [];

	/**
	 * Import a StoryOS JSON document.
	 *
	 * @param string $json      Raw JSON string.
	 * @param array  $options   Import options (overwrite, etc.).
	 * @return array|\WP_Error Import report or error.
	 */
	public function import( string $json, array $options = [] ) {
		$this->overwrite      = ! empty( $options['overwrite'] );
		$this->skipped_sounds = [];
		$this->report         = [
			'created' => [],
			'updated' => [],
			'skipped' => [],
			'errors'  => [],
			'totals'  => [],
		];

		// Step 1: JSON Validation.
		$validated = $this->validate_json( $json );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$this->document = $validated;

		// The validation endpoint must not create or update any WordPress data.
		if ( ! empty( $options['dry_run'] ) ) {
			$this->report['verified'] = true;
			return $this->report;
		}

		// Step 2-4: CPT Creation + SCF Population.
		$this->import_project();
		$this->import_world();
		$this->import_characters();
		$this->import_locations();
		$this->import_props();
		$this->import_scenes();
		$this->import_shots();
		$this->import_sounds();
		$this->import_storyboards();
		$this->import_sequence();

		// Step 5: Story Graph Construction (relationships).
		$this->build_story_graph();

		// Step 6: Verification.
		$this->verify_import();

		// Trigger AI analysis hooks.
		do_action( 'storyos_after_import', $this->report, $this->id_map );

		return $this->report;
	}

	/**
	 * Validate and parse the JSON document.
	 *
	 * @param string $json Raw JSON string.
	 * @return array|\WP_Error Parsed document or error.
	 */
	private function validate_json( string $json ) {
		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'storyos_invalid_json',
				'Invalid JSON: ' . json_last_error_msg()
			);
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'storyos_invalid_json', 'JSON must be an object.' );
		}

		// Sounds were added in StoryOS JSON 1.1. Treat the section as optional
		// so existing 1.0 documents remain importable.
		if ( ! isset( $data['sounds'] ) ) {
			$data['sounds'] = [];
		}

		// Validate required top-level sections.
		$required = [ 'project', 'world', 'characters', 'locations', 'props', 'scenes', 'shots', 'storyboards', 'sequence' ];
		foreach ( $required as $section ) {
			if ( ! isset( $data[ $section ] ) ) {
				return new \WP_Error(
					'storyos_missing_section',
					sprintf( 'Missing required section: %s', $section )
				);
			}
		}

		// Validate project.
		if ( empty( $data['project']['id'] ) || empty( $data['project']['title'] ) ) {
			return new \WP_Error( 'storyos_invalid_project', 'Project must have id and title.' );
		}

		// Validate world.
		if ( empty( $data['world']['id'] ) || empty( $data['world']['name'] ) ) {
			return new \WP_Error( 'storyos_invalid_world', 'World must have id and name.' );
		}

		// Validate arrays.
		foreach ( [ 'characters', 'locations', 'props', 'scenes', 'shots', 'sounds', 'storyboards' ] as $section ) {
			if ( ! is_array( $data[ $section ] ) ) {
				return new \WP_Error(
					'storyos_invalid_section',
					sprintf( 'Section %s must be an array.', $section )
				);
			}
		}

		// Validate sequence.
		if ( empty( $data['sequence']['id'] ) || empty( $data['sequence']['order'] ) ) {
			return new \WP_Error( 'storyos_invalid_sequence', 'Sequence must have id and order.' );
		}

		$references_valid = $this->validate_references( $data );
		if ( is_wp_error( $references_valid ) ) {
			return $references_valid;
		}

		return $data;
	}

	/**
	 * Validate all external-ID references before creating any posts.
	 *
	 * @param array $data Parsed StoryOS document.
	 * @return true|\WP_Error True when every reference resolves, otherwise an error.
	 */
	private function validate_references( array $data ) {
		$id_sets = [];
		$all_ids = [];
		$errors  = [];

		foreach ( [ 'project', 'world' ] as $section ) {
			if ( ! is_scalar( $data[ $section ]['id'] ) ) {
				$errors[] = sprintf( '%s id must be a scalar value.', $section );
				continue;
			}
			$external_id = sanitize_text_field( (string) $data[ $section ]['id'] );
			if ( $external_id !== (string) $data[ $section ]['id'] ) {
				$errors[] = sprintf( '%s id "%s" contains unsupported characters.', $section, $data[ $section ]['id'] );
			}
			if ( isset( $all_ids[ $external_id ] ) ) {
				$errors[] = sprintf( 'External id "%s" is reused by %s and %s.', $external_id, $all_ids[ $external_id ], $section );
			}
			$all_ids[ $external_id ] = $section;
		}

		foreach ( [ 'characters', 'locations', 'props', 'scenes', 'shots', 'sounds', 'storyboards' ] as $section ) {
			$id_sets[ $section ] = [];
			foreach ( $data[ $section ] as $index => $entity ) {
				if ( ! is_array( $entity ) || empty( $entity['id'] ) ) {
					$errors[] = sprintf( '%s[%d] must have an id.', $section, $index );
					continue;
				}
				if ( ! is_scalar( $entity['id'] ) ) {
					$errors[] = sprintf( '%s[%d] id must be a scalar value.', $section, $index );
					continue;
				}

				$external_id = sanitize_text_field( (string) $entity['id'] );
				if ( $external_id !== (string) $entity['id'] ) {
					$errors[] = sprintf( '%s id "%s" contains unsupported characters.', $section, $entity['id'] );
				}
				if ( isset( $id_sets[ $section ][ $external_id ] ) ) {
					$errors[] = sprintf( '%s contains duplicate id "%s".', $section, $external_id );
				}
				if ( isset( $all_ids[ $external_id ] ) ) {
					$errors[] = sprintf( 'External id "%s" is reused by %s and %s.', $external_id, $all_ids[ $external_id ], $section );
				}
				$id_sets[ $section ][ $external_id ] = true;
				$all_ids[ $external_id ]             = $section;
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'storyos_invalid_reference', implode( ' ', $errors ) );
		}

		foreach ( $data['props'] as $prop ) {
			if ( ! empty( $prop['owner_character'] ) ) {
				$this->validate_reference( $prop['owner_character'], 'characters', 'Prop ' . ( $prop['id'] ?? '(unknown)' ) . ' owner_character', $id_sets, $errors );
			}
		}

		foreach ( $data['scenes'] as $scene ) {
			$context = 'Scene ' . ( $scene['id'] ?? '(unknown)' );
			if ( ! empty( $scene['location'] ) ) {
				$this->validate_reference( $scene['location'], 'locations', $context . ' location', $id_sets, $errors );
			}
			foreach ( (array) ( $scene['characters'] ?? [] ) as $character_id ) {
				$this->validate_reference( $character_id, 'characters', $context . ' character', $id_sets, $errors );
			}
			foreach ( (array) ( $scene['props'] ?? [] ) as $prop_id ) {
				$this->validate_reference( $prop_id, 'props', $context . ' prop', $id_sets, $errors );
			}
		}

		foreach ( $data['shots'] as $shot ) {
			$this->validate_reference( $shot['scene'] ?? '', 'scenes', 'Shot ' . ( $shot['id'] ?? '(unknown)' ) . ' scene', $id_sets, $errors );
		}

		$shot_scenes = [];
		foreach ( $data['shots'] as $shot ) {
			if ( ! empty( $shot['id'] ) ) {
				$shot_scenes[ (string) $shot['id'] ] = (string) ( $shot['scene'] ?? '' );
			}
		}

		foreach ( $data['sounds'] as $sound ) {
			$context = 'Sound ' . ( $sound['id'] ?? '(unknown)' );
			$invalid_shape = false;
			foreach ( [ 'title', 'type', 'production_status', 'description', 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ] as $field ) {
				if ( array_key_exists( $field, $sound ) && null !== $sound[ $field ] && ! is_scalar( $sound[ $field ] ) ) {
					$errors[]     = sprintf( '%s %s must be a scalar value.', $context, $field );
					$invalid_shape = true;
				}
			}
			if ( $invalid_shape ) {
				continue;
			}

			if ( empty( $sound['title'] ) || empty( $sound['type'] ) ) {
				$errors[] = $context . ' must have a title and type.';
			}

			$sound_type = sanitize_title( (string) ( $sound['type'] ?? '' ) );
			if ( \StoryOS\Utils\storyos_is_reserved_sound_type( $sound_type ) ) {
				$errors[] = $context . ' cannot use the reserved dialogue type; ordinary dialogue belongs in scenes[].dialogue.';
			}

			if ( ! empty( $sound['lyrics'] ) && 'music' !== $sound_type ) {
				$errors[] = $context . ' may only include lyrics when type is music.';
			}

			if ( isset( $sound['diegetic'] ) && ! in_array( (string) $sound['diegetic'], [ 'unspecified', 'diegetic', 'non_diegetic', 'internal', 'mixed' ], true ) ) {
				$errors[] = $context . ' has an invalid diegetic value.';
			}

			if ( ! empty( $sound['production_status'] ) && ! get_term_by( 'slug', sanitize_title( (string) $sound['production_status'] ), 'storyos_status' ) ) {
				$errors[] = $context . ' production_status must match an existing Status term.';
			}

			$this->validate_reference( $sound['scene'] ?? '', 'scenes', $context . ' scene', $id_sets, $errors );

			if ( ! empty( $sound['shot'] ) ) {
				$this->validate_reference( $sound['shot'], 'shots', $context . ' shot', $id_sets, $errors );
				if ( isset( $shot_scenes[ (string) $sound['shot'] ] ) && (string) ( $sound['scene'] ?? '' ) !== $shot_scenes[ (string) $sound['shot'] ] ) {
					$errors[] = sprintf( '%s shot "%s" does not belong to scene "%s".', $context, $sound['shot'], $sound['scene'] ?? '' );
				}
			}

			if ( ! empty( $sound['character'] ) ) {
				$this->validate_reference( $sound['character'], 'characters', $context . ' character', $id_sets, $errors );
			}

			if ( ! empty( $sound['asset'] ) ) {
				$asset_external_id = sanitize_text_field( (string) $sound['asset'] );
				$asset_id          = $this->find_existing( 'storyos_asset', $asset_external_id );
				if ( $asset_external_id !== (string) $sound['asset'] || ! $asset_id ) {
					$errors[] = sprintf( '%s references unknown existing asset id "%s".', $context, $asset_external_id );
				} elseif ( ! \StoryOS\Utils\storyos_is_audio_asset( $asset_id ) ) {
					$errors[] = sprintf( '%s asset "%s" is not classified as Audio.', $context, $asset_external_id );
				}
			}
		}

		foreach ( $data['storyboards'] as $frame ) {
			$this->validate_reference( $frame['shot'] ?? '', 'shots', 'Storyboard ' . ( $frame['id'] ?? '(unknown)' ) . ' shot', $id_sets, $errors );
		}

		foreach ( (array) $data['sequence']['order'] as $scene_id ) {
			$this->validate_reference( $scene_id, 'scenes', 'Sequence scene', $id_sets, $errors );
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'storyos_invalid_reference', implode( ' ', $errors ) );
		}

		return true;
	}

	/**
	 * Append an error when an external-ID reference cannot be resolved.
	 *
	 * @param mixed  $reference Referenced external ID.
	 * @param string $section   Target document section.
	 * @param string $context   Human-readable reference context.
	 * @param array  $id_sets   External IDs keyed by document section.
	 * @param array  $errors    Validation errors, passed by reference.
	 */
	private function validate_reference( $reference, string $section, string $context, array $id_sets, array &$errors ): void {
		if ( ! is_scalar( $reference ) ) {
			$errors[] = sprintf( '%s must be a scalar external id.', $context );
			return;
		}

		$external_id = sanitize_text_field( (string) $reference );
		if ( $external_id !== (string) $reference ) {
			$errors[] = sprintf( '%s id "%s" contains unsupported characters.', $context, $reference );
			return;
		}
		if ( '' === $external_id || ! isset( $id_sets[ $section ][ $external_id ] ) ) {
			$errors[] = sprintf( '%s references unknown %s id "%s".', $context, $section, $external_id );
		}
	}

	/**
	 * Import the project entity.
	 */
	private function import_project(): void {
		$project = $this->document['project'];
		$external_id = sanitize_text_field( $project['id'] );

		$post_id = $this->find_existing( 'storyos_project', $external_id );

		if ( $post_id && ! $this->overwrite ) {
			$this->report['skipped'][] = "Project {$external_id} already exists.";
			$this->id_map[ $external_id ] = $post_id;
			return;
		}

		$post_data = [
			'post_type'    => 'storyos_project',
			'post_title'   => sanitize_text_field( $project['title'] ),
			'post_status'  => 'publish',
			'post_content' => isset( $project['description'] ) ? wp_kses_post( $project['description'] ) : '',
		];

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$post_id = wp_update_post( $post_data, true );
			$this->report['updated'][] = "Project {$external_id}";
		} else {
			$post_id = wp_insert_post( $post_data, true );
			$this->report['created'][] = "Project {$external_id}";
		}

		if ( is_wp_error( $post_id ) ) {
			$this->report['errors'][] = 'Project: ' . $post_id->get_error_message();
			return;
		}

		$this->id_map[ $external_id ] = $post_id;

		// SCF fields.
		update_post_meta( $post_id, 'external_id', $external_id );
		update_post_meta( $post_id, 'project_name', sanitize_text_field( $project['title'] ) );
		update_post_meta( $post_id, 'project_slug', \StoryOS\Utils\sanitize_story_id( $external_id ) );
		if ( isset( $project['description'] ) ) {
			update_post_meta( $post_id, 'description', wp_kses_post( $project['description'] ) );
		}
	}

	/**
	 * Import the world entity.
	 */
	private function import_world(): void {
		$world = $this->document['world'];
		$external_id = sanitize_text_field( $world['id'] );

		$post_id = $this->find_existing( 'storyos_story_world', $external_id );

		if ( $post_id && ! $this->overwrite ) {
			$this->report['skipped'][] = "World {$external_id} already exists.";
			$this->id_map[ $external_id ] = $post_id;
			return;
		}

		$post_data = [
			'post_type'    => 'storyos_story_world',
			'post_title'   => sanitize_text_field( $world['name'] ),
			'post_status'  => 'publish',
			'post_content' => isset( $world['description'] ) ? wp_kses_post( $world['description'] ) : '',
		];

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$post_id = wp_update_post( $post_data, true );
			$this->report['updated'][] = "World {$external_id}";
		} else {
			$post_id = wp_insert_post( $post_data, true );
			$this->report['created'][] = "World {$external_id}";
		}

		if ( is_wp_error( $post_id ) ) {
			$this->report['errors'][] = 'World: ' . $post_id->get_error_message();
			return;
		}

		$this->id_map[ $external_id ] = $post_id;

		// SCF fields.
		update_post_meta( $post_id, 'external_id', $external_id );
		update_post_meta( $post_id, 'world_name', sanitize_text_field( $world['name'] ) );
		if ( isset( $world['description'] ) ) {
			update_post_meta( $post_id, 'synopsis', wp_kses_post( $world['description'] ) );
		}
	}

	/**
	 * Import all characters.
	 */
	private function import_characters(): void {
		foreach ( $this->document['characters'] as $character ) {
			$external_id = sanitize_text_field( $character['id'] );

			$post_id = $this->find_existing( 'storyos_character', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Character {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_character',
				'post_title'   => sanitize_text_field( $character['name'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $character['description'] ) ? wp_kses_post( $character['description'] ) : '',
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Character {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Character {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Character {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'display_name', sanitize_text_field( $character['name'] ) );
			if ( isset( $character['archetype'] ) ) {
				update_post_meta( $post_id, 'archetype', sanitize_text_field( $character['archetype'] ) );
			}
			if ( isset( $character['description'] ) ) {
				update_post_meta( $post_id, 'biography', wp_kses_post( $character['description'] ) );
			}
		}
	}

	/**
	 * Import all locations.
	 */
	private function import_locations(): void {
		foreach ( $this->document['locations'] as $location ) {
			$external_id = sanitize_text_field( $location['id'] );

			$post_id = $this->find_existing( 'storyos_location', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Location {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_location',
				'post_title'   => sanitize_text_field( $location['name'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $location['description'] ) ? wp_kses_post( $location['description'] ) : '',
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Location {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Location {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Location {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'location_name', sanitize_text_field( $location['name'] ) );
			if ( isset( $location['description'] ) ) {
				update_post_meta( $post_id, 'description', wp_kses_post( $location['description'] ) );
			}
		}
	}

	/**
	 * Import all props.
	 */
	private function import_props(): void {
		foreach ( $this->document['props'] as $prop ) {
			$external_id = sanitize_text_field( $prop['id'] );

			$post_id = $this->find_existing( 'storyos_prop', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Prop {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_prop',
				'post_title'   => sanitize_text_field( $prop['name'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $prop['description'] ) ? wp_kses_post( $prop['description'] ) : '',
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Prop {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Prop {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Prop {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'prop_name', sanitize_text_field( $prop['name'] ) );
			if ( isset( $prop['description'] ) ) {
				update_post_meta( $post_id, 'description', wp_kses_post( $prop['description'] ) );
			}
		}
	}

	/**
	 * Import all scenes.
	 */
	private function import_scenes(): void {
		$scene_index = 1;
		foreach ( $this->document['scenes'] as $scene ) {
			$external_id = sanitize_text_field( $scene['id'] );

			$post_id = $this->find_existing( 'storyos_scene', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Scene {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				$scene_index++;
				continue;
			}

			$scene_label = sanitize_text_field( $scene['title'] ?? ( $scene['label'] ?? '' ) );
			$post_data = [
				'post_type'    => 'storyos_scene',
				'post_title'   => $scene_label ?: sprintf( 'Scene %d', $scene_index ),
				'post_status'  => 'publish',
				'post_content' => isset( $scene['summary'] ) ? wp_kses_post( $scene['summary'] ) : '',
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Scene {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Scene {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Scene {$external_id}: " . $post_id->get_error_message();
				$scene_index++;
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'scene_number', $scene_index );
			update_post_meta( $post_id, 'title', $scene_label );
			if ( isset( $scene['summary'] ) ) {
				update_post_meta( $post_id, 'summary', wp_kses_post( $scene['summary'] ) );
			}

			// Store dialogue as structured metadata.
			if ( ! empty( $scene['dialogue'] ) && is_array( $scene['dialogue'] ) ) {
				$dialogue = [];
				$sequence = 1;
				foreach ( $scene['dialogue'] as $line ) {
					$dialogue[] = [
						'speaker'     => sanitize_text_field( $line['speaker'] ?? '' ),
						'line'        => sanitize_text_field( $line['text'] ?? '' ),
						'description' => sanitize_text_field( $line['description'] ?? '' ),
						'sequence'    => $sequence++,
					];
				}
				update_post_meta( $post_id, 'dialogue', $dialogue );
			}

			$scene_index++;
		}
	}

	/**
	 * Import all shots.
	 */
	private function import_shots(): void {
		$shot_index = 1;

		// Build a scene external ID → title lookup for useful shot names.
		$scene_titles = [];
		foreach ( $this->document['scenes'] as $scene ) {
			$scene_label = sanitize_text_field( $scene['title'] ?? ( $scene['label'] ?? '' ) );
			$scene_titles[ sanitize_text_field( $scene['id'] ) ] = $scene_label;
		}

		foreach ( $this->document['shots'] as $shot ) {
			$external_id = sanitize_text_field( $shot['id'] );

			$post_id = $this->find_existing( 'storyos_shot', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Shot {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				$shot_index++;
				continue;
			}

			// Normalize the shot type so it matches the canonical options.
			$shot_type = isset( $shot['type'] ) ? \StoryOS\Utils\storyos_normalize_shot_type( (string) $shot['type'] ) : '';

			$explicit_shot_title = sanitize_text_field( $shot['title'] ?? ( $shot['label'] ?? '' ) );
			$shot_name = \StoryOS\Utils\storyos_generate_shot_name( [
				'title'            => $explicit_shot_title,
				'shot_number'      => $shot_index,
				'shot_type'        => $shot_type,
				'shot_description' => $shot['description'] ?? '',
				'scene_title'      => $scene_titles[ sanitize_text_field( $shot['scene'] ?? '' ) ] ?? '',
			] );

			$post_data = [
				'post_type'    => 'storyos_shot',
				'post_title'   => $shot_name,
				'post_status'  => 'publish',
				'post_content' => isset( $shot['description'] ) ? wp_kses_post( $shot['description'] ) : '',
				'menu_order'   => $shot_index,
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Shot {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Shot {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Shot {$external_id}: " . $post_id->get_error_message();
				$shot_index++;
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'shot_number', $shot_index );
			update_post_meta( $post_id, 'shot_name', $shot_name );
			if ( '' !== $shot_type ) {
				update_post_meta( $post_id, 'shot_type', $shot_type );
			}
			if ( isset( $shot['description'] ) ) {
				update_post_meta( $post_id, 'shot_description', wp_kses_post( $shot['description'] ) );
			}

			$shot_index++;
		}
	}

	/**
	 * Import planned soundtrack cues without duplicating Scene dialogue.
	 */
	private function import_sounds(): void {
		$sound_index = 1;
		foreach ( $this->document['sounds'] as $sound ) {
			$external_id = sanitize_text_field( (string) $sound['id'] );
			$post_id     = $this->find_existing( 'storyos_sound', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Sound {$external_id} already exists.";
				$this->id_map[ $external_id ]       = $post_id;
				$this->skipped_sounds[ $external_id ] = true;
				$sound_index++;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_sound',
				'post_title'   => sanitize_text_field( (string) $sound['title'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $sound['description'] ) ? wp_kses_post( (string) $sound['description'] ) : '',
				'menu_order'   => $sound_index,
			];

			$operation = $post_id ? 'updated' : 'created';
			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Sound {$external_id}: " . $post_id->get_error_message();
				$sound_index++;
				continue;
			}
			$this->report[ $operation ][] = "Sound {$external_id}";

			$this->id_map[ $external_id ] = $post_id;
			update_post_meta( $post_id, 'external_id', $external_id );

			$fields = \StoryOS\Utils\storyos_get_fields( 'storyos_sound' );
			foreach ( [ 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes' ] as $meta_field ) {
				if ( array_key_exists( $meta_field, $sound ) ) {
					$value = \StoryOS\Utils\storyos_sanitize_field_value( $sound[ $meta_field ], $fields[ $meta_field ] ?? [] );
					if ( '' === $value ) {
						delete_post_meta( $post_id, $meta_field );
					} else {
						update_post_meta( $post_id, $meta_field, $value );
					}
				} elseif ( $this->overwrite ) {
					delete_post_meta( $post_id, $meta_field );
				}
			}

			$sound_type = sanitize_title( (string) $sound['type'] );
			$term       = get_term_by( 'slug', $sound_type, 'storyos_sound_type' );
			if ( ! $term ) {
				$seed_types = \StoryOS\Utils\storyos_sound_types();
				$term       = wp_insert_term(
					$seed_types[ $sound_type ] ?? sanitize_text_field( (string) $sound['type'] ),
					'storyos_sound_type',
					[ 'slug' => $sound_type ]
				);
			}

			if ( is_wp_error( $term ) ) {
				$this->report['errors'][] = "Sound {$external_id} type: " . $term->get_error_message();
			} else {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term->term_id;
				wp_set_object_terms( $post_id, [ $term_id ], 'storyos_sound_type', false );
			}

			if ( array_key_exists( 'production_status', $sound ) ) {
				$status_slug = sanitize_title( (string) $sound['production_status'] );
				$status_term = '' !== $status_slug ? get_term_by( 'slug', $status_slug, 'storyos_status' ) : null;
				wp_set_object_terms( $post_id, $status_term ? [ (int) $status_term->term_id ] : [], 'storyos_status', false );
			} elseif ( $this->overwrite ) {
				wp_set_object_terms( $post_id, [], 'storyos_status', false );
			}

			$sound_index++;
		}
	}

	/**
	 * Import all storyboard frames.
	 */
	private function import_storyboards(): void {
		$frame_index = 1;
		foreach ( $this->document['storyboards'] as $frame ) {
			$external_id = sanitize_text_field( $frame['id'] );

			$post_id = $this->find_existing( 'storyos_storyboard_frame', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Storyboard frame {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				$frame_index++;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_storyboard_frame',
				'post_title'   => sprintf( 'Storyboard Frame %d', $frame_index ),
				'post_status'  => 'publish',
				'post_content' => isset( $frame['description'] ) ? wp_kses_post( $frame['description'] ) : '',
			];

			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id = wp_update_post( $post_data, true );
				$this->report['updated'][] = "Storyboard frame {$external_id}";
			} else {
				$post_id = wp_insert_post( $post_data, true );
				$this->report['created'][] = "Storyboard frame {$external_id}";
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Storyboard frame {$external_id}: " . $post_id->get_error_message();
				$frame_index++;
				continue;
			}

			$this->id_map[ $external_id ] = $post_id;

			// SCF fields.
			update_post_meta( $post_id, 'external_id', $external_id );
			update_post_meta( $post_id, 'frame_number', $frame_index );
			if ( isset( $frame['description'] ) ) {
				update_post_meta( $post_id, 'frame_description', wp_kses_post( $frame['description'] ) );
			}

			$frame_index++;
		}
	}

	/**
	 * Import the sequence taxonomy and assign scenes in order.
	 */
	private function import_sequence(): void {
		$sequence = $this->document['sequence'];
		$sequence_title = sanitize_text_field( $sequence['title'] ?? 'Sequence' );

		// Create or find the sequence term.
		$term = term_exists( $sequence_title, 'storyos_sequence' );
		if ( ! $term ) {
			$term = wp_insert_term( $sequence_title, 'storyos_sequence' );
		}

		if ( is_wp_error( $term ) ) {
			$this->report['errors'][] = 'Sequence: ' . $term->get_error_message();
			return;
		}

		$term_id = is_array( $term ) ? $term['term_id'] : (int) $term;

		// Assign scenes to the sequence in order.
		$order = 1;
		foreach ( $sequence['order'] as $scene_external_id ) {
			$scene_external_id = sanitize_text_field( $scene_external_id );
			$scene_post_id = $this->id_map[ $scene_external_id ] ?? 0;

			if ( ! $scene_post_id ) {
				$this->report['errors'][] = "Sequence: scene {$scene_external_id} not found.";
				continue;
			}

			wp_set_object_terms( $scene_post_id, $term_id, 'storyos_sequence' );
			update_post_meta( $scene_post_id, 'sequence_order', $order );
			$order++;
		}

		// Assign every shot that belongs to a scene in the sequence.
		foreach ( $this->document['shots'] as $shot ) {
			$scene_external_id = sanitize_text_field( $shot['scene'] ?? '' );
			$shot_post_id      = $this->id_map[ sanitize_text_field( $shot['id'] ) ] ?? 0;

			if ( ! $shot_post_id || ! in_array( $scene_external_id, (array) $sequence['order'], true ) ) {
				continue;
			}

			wp_set_object_terms( $shot_post_id, $term_id, 'storyos_sequence', false );
		}

		// Record the editorial order of the sequence term itself.
		\StoryOS\Utils\storyos_set_sequence_order( (int) $term_id, 1 );

		$this->report['sequence'] = [
			'term_id' => $term_id,
			'title'   => $sequence_title,
			'order'   => $order - 1,
		];
	}

	/**
	 * Build Story Graph relationships between all imported entities.
	 */
	private function build_story_graph(): void {
		$project_id = $this->id_map[ $this->document['project']['id'] ] ?? 0;
		$world_id   = $this->id_map[ $this->document['world']['id'] ] ?? 0;

		// Project → World.
		if ( $project_id && $world_id ) {
			\StoryOS\Utils\add_relationship( $project_id, 'storyos_project', $world_id, 'storyos_story_world', 'contains' );
		}

		// World → Characters, Locations, Props.
		if ( $world_id ) {
			foreach ( $this->document['characters'] as $character ) {
				$char_id = $this->id_map[ $character['id'] ] ?? 0;
				if ( $char_id ) {
					\StoryOS\Utils\add_relationship( $world_id, 'storyos_story_world', $char_id, 'storyos_character', 'contains' );
				}
			}

			foreach ( $this->document['locations'] as $location ) {
				$loc_id = $this->id_map[ $location['id'] ] ?? 0;
				if ( $loc_id ) {
					\StoryOS\Utils\add_relationship( $world_id, 'storyos_story_world', $loc_id, 'storyos_location', 'contains' );
				}
			}

			foreach ( $this->document['props'] as $prop ) {
				$prop_id = $this->id_map[ $prop['id'] ] ?? 0;
				if ( $prop_id ) {
					\StoryOS\Utils\add_relationship( $world_id, 'storyos_story_world', $prop_id, 'storyos_prop', 'contains' );
				}
			}
		}

		// Prop → Owner Character.
		foreach ( $this->document['props'] as $prop ) {
			$prop_id = $this->id_map[ $prop['id'] ] ?? 0;
			$char_id = $this->id_map[ $prop['owner_character'] ?? '' ] ?? 0;
			if ( $prop_id && $char_id ) {
				\StoryOS\Utils\add_relationship( $prop_id, 'storyos_prop', $char_id, 'storyos_character', 'linked_to' );
			}
		}

		// Scene relationships.
		foreach ( $this->document['scenes'] as $scene ) {
			$scene_id = $this->id_map[ $scene['id'] ] ?? 0;
			if ( ! $scene_id ) {
				continue;
			}

			// Scene → Location.
			if ( ! empty( $scene['location'] ) ) {
				$loc_id = $this->id_map[ $scene['location'] ] ?? 0;
				if ( $loc_id ) {
					\StoryOS\Utils\add_relationship( $scene_id, 'storyos_scene', $loc_id, 'storyos_location', 'located_in' );
				}
			}

			// Scene → Characters.
			if ( ! empty( $scene['characters'] ) && is_array( $scene['characters'] ) ) {
				foreach ( $scene['characters'] as $char_external_id ) {
					$char_id = $this->id_map[ $char_external_id ] ?? 0;
					if ( $char_id ) {
						\StoryOS\Utils\add_relationship( $scene_id, 'storyos_scene', $char_id, 'storyos_character', 'appears_in' );
					}
				}
			}

			// Scene → Props.
			if ( ! empty( $scene['props'] ) && is_array( $scene['props'] ) ) {
				foreach ( $scene['props'] as $prop_external_id ) {
					$prop_id = $this->id_map[ $prop_external_id ] ?? 0;
					if ( $prop_id ) {
						\StoryOS\Utils\add_relationship( $scene_id, 'storyos_scene', $prop_id, 'storyos_prop', 'used_in' );
					}
				}
			}
		}

		// Shot → Scene.
		foreach ( $this->document['shots'] as $shot ) {
			$shot_id = $this->id_map[ $shot['id'] ] ?? 0;
			if ( ! $shot_id ) {
				continue;
			}

			$scene_id = $this->id_map[ $shot['scene'] ] ?? 0;
			if ( $scene_id ) {
				\StoryOS\Utils\add_relationship( $scene_id, 'storyos_scene', $shot_id, 'storyos_shot', 'contains' );
			}
		}

		// Sound cues keep their own placement edges. Ordinary dialogue remains
		// structured Scene metadata and is intentionally not converted to Sounds.
		foreach ( $this->document['sounds'] as $sound ) {
			if ( isset( $this->skipped_sounds[ (string) $sound['id'] ] ) ) {
				continue;
			}

			$sound_id = $this->id_map[ $sound['id'] ] ?? 0;
			if ( ! $sound_id ) {
				continue;
			}

			$scene_id = $this->id_map[ $sound['scene'] ?? '' ] ?? 0;
			\StoryOS\Utils\set_relationship(
				$sound_id,
				'storyos_sound',
				$scene_id,
				'storyos_scene',
				'belongs_to',
				[ 'field' => 'scene' ]
			);

			$shot_id = $this->id_map[ $sound['shot'] ?? '' ] ?? 0;
			\StoryOS\Utils\set_relationship(
				$sound_id,
				'storyos_sound',
				$shot_id,
				'storyos_shot',
				'belongs_to',
				[ 'field' => 'shot' ]
			);

			$character_id = $this->id_map[ $sound['character'] ?? '' ] ?? 0;
			\StoryOS\Utils\set_relationship(
				$sound_id,
				'storyos_sound',
				$character_id,
				'storyos_character',
				'linked_to',
				[ 'field' => 'character' ]
			);

			$asset_id = 0;
			if ( ! empty( $sound['asset'] ) ) {
				$asset_id = $this->find_existing( 'storyos_asset', sanitize_text_field( (string) $sound['asset'] ) );
			}
			\StoryOS\Utils\set_relationship(
				$sound_id,
				'storyos_sound',
				$asset_id,
				'storyos_asset',
				'linked_to',
				[ 'field' => 'asset' ]
			);
		}

		// Storyboard Frame → Shot.
		foreach ( $this->document['storyboards'] as $frame ) {
			$frame_id = $this->id_map[ $frame['id'] ] ?? 0;
			if ( ! $frame_id ) {
				continue;
			}

			$shot_id = $this->id_map[ $frame['shot'] ] ?? 0;
			if ( $shot_id ) {
				\StoryOS\Utils\add_relationship( $shot_id, 'storyos_shot', $frame_id, 'storyos_storyboard_frame', 'contains' );
			}
		}
	}

	/**
	 * Verify the import against expected totals.
	 */
	private function verify_import(): void {
		// Map CPT slugs to the label prefix used in report['created'] entries.
		$label_prefixes = [
			'storyos_project'          => 'Project ',
			'storyos_story_world'      => 'World ',
			'storyos_character'        => 'Character ',
			'storyos_location'         => 'Location ',
			'storyos_prop'             => 'Prop ',
			'storyos_scene'            => 'Scene ',
			'storyos_shot'             => 'Shot ',
			'storyos_sound'            => 'Sound ',
			'storyos_storyboard_frame' => 'Storyboard frame ',
		];

		$totals = [];
		foreach ( $label_prefixes as $cpt => $prefix ) {
			$created = 0;
			foreach ( $this->report['created'] as $entry ) {
				if ( strpos( $entry, $prefix ) === 0 ) {
					$created++;
				}
			}
			$totals[ $cpt ] = $created;
		}

		$this->report['totals'] = $totals;
		$this->report['verified'] = empty( $this->report['errors'] );
	}

	/**
	 * Find an existing post by external ID.
	 *
	 * @param string $cpt         CPT slug.
	 * @param string $external_id External ID.
	 * @return int Post ID or 0.
	 */
	private function find_existing( string $cpt, string $external_id ): int {
		$posts = get_posts( [
			'post_type'      => $cpt,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'external_id',
			'meta_value'     => $external_id,
			'fields'         => 'ids',
		] );

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
}
