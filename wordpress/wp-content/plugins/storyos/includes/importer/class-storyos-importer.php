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
	 * Import a StoryOS JSON document.
	 *
	 * @param string $json      Raw JSON string.
	 * @param array  $options   Import options (overwrite, etc.).
	 * @return array|\WP_Error Import report or error.
	 */
	public function import( string $json, array $options = [] ) {
		$this->overwrite = ! empty( $options['overwrite'] );
		$this->report    = [
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

		// Step 2-4: CPT Creation + SCF Population.
		$this->import_project();
		$this->import_world();
		$this->import_characters();
		$this->import_locations();
		$this->import_props();
		$this->import_scenes();
		$this->import_shots();
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
		foreach ( [ 'characters', 'locations', 'props', 'scenes', 'shots', 'storyboards' ] as $section ) {
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

		return $data;
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

			$post_data = [
				'post_type'    => 'storyos_scene',
				'post_title'   => sanitize_text_field( $scene['title'] ),
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
			update_post_meta( $post_id, 'title', sanitize_text_field( $scene['title'] ) );
			if ( isset( $scene['summary'] ) ) {
				update_post_meta( $post_id, 'summary', wp_kses_post( $scene['summary'] ) );
			}

			// Store dialogue as structured metadata.
			if ( ! empty( $scene['dialogue'] ) && is_array( $scene['dialogue'] ) ) {
				$dialogue = [];
				$sequence = 1;
				foreach ( $scene['dialogue'] as $line ) {
					$dialogue[] = [
						'speaker'  => sanitize_text_field( $line['speaker'] ?? '' ),
						'line'     => sanitize_text_field( $line['text'] ?? '' ),
						'sequence' => $sequence++,
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
		foreach ( $this->document['shots'] as $shot ) {
			$external_id = sanitize_text_field( $shot['id'] );

			$post_id = $this->find_existing( 'storyos_shot', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Shot {$external_id} already exists.";
				$this->id_map[ $external_id ] = $post_id;
				$shot_index++;
				continue;
			}

			$post_data = [
				'post_type'    => 'storyos_shot',
				'post_title'   => sprintf( 'Shot %d', $shot_index ),
				'post_status'  => 'publish',
				'post_content' => isset( $shot['description'] ) ? wp_kses_post( $shot['description'] ) : '',
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
			if ( isset( $shot['type'] ) ) {
				update_post_meta( $post_id, 'shot_type', sanitize_text_field( $shot['type'] ) );
			}
			if ( isset( $shot['description'] ) ) {
				update_post_meta( $post_id, 'shot_description', wp_kses_post( $shot['description'] ) );
			}

			$shot_index++;
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