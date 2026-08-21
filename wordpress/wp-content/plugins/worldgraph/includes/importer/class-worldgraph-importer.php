<?php
/**
 * World Graph Studio JSON Importer.
 *
 * Imports a World Graph Studio JSON document (e.g. little-red-riding-hood.worldgraph.json)
 * into World Graph Studio CPTs, SCF fields, relationships, and Story Graph entities.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core importer engine.
 *
 * Implements the deterministic import workflow defined in
 * about/example-workflow/JSON_import_spec.md:
 *
 *   World Graph Studio JSON → JSON Validation → CPT Creation → SCF Population
 *   → Relationship Creation → Story Graph Construction → Verification
 */
class WorldGraph_Importer {

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
	 * External IDs skipped because overwrite was disabled.
	 *
	 * Skipped records must not have their existing graph edges replaced.
	 *
	 * @var array<string, bool>
	 */
	private $skipped_entities = [];

	/**
	 * Import a World Graph Studio JSON document.
	 *
	 * @param string $json      Raw JSON string.
	 * @param array  $options   Import options (overwrite, etc.).
	 * @return array|\WP_Error Import report or error.
	 */
	public function import( string $json, array $options = [] ) {
		$this->overwrite        = ! empty( $options['overwrite'] );
		$this->skipped_entities = [];
		$this->id_map           = [];
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
		$this->import_organizations();
		$this->import_episodes();
		$this->import_scenes();
		$this->import_shots();
		$this->import_sounds();
		$this->import_storyboards();
		$this->import_assets();
		$this->import_editorial_artifacts();
		$this->import_sequence();

		// Step 5: Story Graph Construction (relationships).
		$this->build_story_graph();

		// Step 6: Verification.
		$this->verify_import();
		$this->report['id_map'] = $this->id_map;

		// Trigger AI analysis hooks.
		do_action( 'worldgraph_after_import', $this->report, $this->id_map );

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
				'worldgraph_invalid_json',
				'Invalid JSON: ' . json_last_error_msg()
			);
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'worldgraph_invalid_json', 'JSON must be an object.' );
		}

		if ( isset( $data['worldgraph_version'] ) && ! is_scalar( $data['worldgraph_version'] ) ) {
			return new \WP_Error( 'worldgraph_unsupported_version', 'World Graph Studio JSON version must be a string.' );
		}
		$version = isset( $data['worldgraph_version'] ) ? (string) $data['worldgraph_version'] : '1.0';
		if ( ! preg_match( '/^1\.(?:0|1|2)$/', $version ) ) {
			return new \WP_Error(
				'worldgraph_unsupported_version',
				sprintf( 'Unsupported World Graph Studio JSON version: %s', $version )
			);
		}
		$data['worldgraph_version'] = $version;

		// Sounds were added in 1.1. The expanded content sections were added in
		// 1.2. They remain optional so legacy and integration-produced structural
		// subsets continue to import.
		foreach ( [ 'sounds', 'organizations', 'episodes', 'assets', 'editorial_artifacts' ] as $optional_section ) {
			if ( ! isset( $data[ $optional_section ] ) ) {
				$data[ $optional_section ] = [];
			}
		}

		// Validate required top-level sections.
		$required = [ 'project', 'world', 'characters', 'locations', 'props', 'scenes', 'shots', 'storyboards', 'sequence' ];
		foreach ( $required as $section ) {
			if ( ! isset( $data[ $section ] ) ) {
				return new \WP_Error(
					'worldgraph_missing_section',
					sprintf( 'Missing required section: %s', $section )
				);
			}
		}

		// Validate project.
		if ( ! is_array( $data['project'] ) || empty( $data['project']['id'] ) || empty( $data['project']['title'] ) ) {
			return new \WP_Error( 'worldgraph_invalid_project', 'Project must have id and title.' );
		}

		// Validate world.
		if ( ! is_array( $data['world'] ) || empty( $data['world']['id'] ) || empty( $data['world']['name'] ) ) {
			return new \WP_Error( 'worldgraph_invalid_world', 'World must have id and name.' );
		}

		// Validate arrays.
		foreach ( [ 'characters', 'locations', 'props', 'organizations', 'episodes', 'scenes', 'shots', 'sounds', 'storyboards', 'assets', 'editorial_artifacts' ] as $section ) {
			if ( ! is_array( $data[ $section ] ) ) {
				return new \WP_Error(
					'worldgraph_invalid_section',
					sprintf( 'Section %s must be an array.', $section )
				);
			}
		}

		// Validate sequence.
		if ( ! is_array( $data['sequence'] ) || empty( $data['sequence']['id'] ) || ! array_key_exists( 'order', $data['sequence'] ) ) {
			return new \WP_Error( 'worldgraph_invalid_sequence', 'Sequence must have id and order.' );
		}

		$fields_valid = $this->validate_field_values( $data );
		if ( is_wp_error( $fields_valid ) ) {
			return $fields_valid;
		}

		$references_valid = $this->validate_references( $data );
		if ( is_wp_error( $references_valid ) ) {
			return $references_valid;
		}

		return $data;
	}

	/**
	 * Validate portable field shapes and canonical select values before writes.
	 *
	 * @param array $data Parsed World Graph Studio document.
	 * @return true|\WP_Error True when field values are valid.
	 */
	private function validate_field_values( array $data ) {
		$errors = [];
		foreach ( [ 'id', 'title', 'name', 'sequence_order' ] as $field ) {
			if ( array_key_exists( $field, $data['sequence'] ) && null !== $data['sequence'][ $field ] && ! is_scalar( $data['sequence'][ $field ] ) ) {
				$errors[] = sprintf( 'Sequence %s must be a scalar value.', $field );
			}
		}
		if ( ! isset( $data['sequence']['order'] ) || ! is_array( $data['sequence']['order'] ) ) {
			$errors[] = 'Sequence order must be an array.';
		}
		$records = [
			'Project' => [ $data['project'] ],
			'World'   => [ $data['world'] ],
		];
		foreach ( [ 'characters' => 'Character', 'locations' => 'Location', 'props' => 'Prop', 'organizations' => 'Organization', 'episodes' => 'Episode', 'scenes' => 'Scene', 'shots' => 'Shot', 'sounds' => 'Sound', 'storyboards' => 'Storyboard', 'assets' => 'Asset', 'editorial_artifacts' => 'Editorial Artifact' ] as $section => $label ) {
			$records[ $label ] = $data[ $section ];
		}

		$scalar_fields = [
			'Project'    => [ 'id', 'title', 'project_slug', 'description', 'target_medium', 'production_status', 'start_date', 'end_date', 'production_stage', 'frame_width', 'frame_height', 'aspect_ratio', 'frame_rate' ],
			'World'      => [ 'id', 'name', 'description', 'timeline', 'rules', 'themes', 'geography', 'references', 'project' ],
			'Character'  => [ 'id', 'name', 'description', 'archetype', 'age', 'appearance', 'personality', 'motivation', 'backstory', 'voice_profile', 'avatar_asset', 'story_world' ],
			'Location'   => [ 'id', 'name', 'description', 'environment_type', 'geography', 'mood', 'visual_reference', 'story_world' ],
			'Prop'       => [ 'id', 'name', 'description', 'purpose', 'owner_character', 'notes' ],
			'Organization' => [ 'id', 'name', 'organization_name', 'organization_type', 'description', 'leadership', 'goals', 'story_world' ],
			'Episode'    => [ 'id', 'episode_number', 'title', 'synopsis', 'production_status', 'project' ],
			'Scene'      => [ 'id', 'title', 'label', 'scene_number', 'summary', 'script_content', 'location', 'time_of_day', 'emotional_tone', 'production_notes', 'sequence', 'episode' ],
			'Shot'       => [ 'id', 'scene', 'title', 'label', 'shot_number', 'type', 'camera_angle', 'lens', 'duration', 'take_number', 'slate_id', 'description', 'editorial_notes', 'sequence' ],
			'Sound'      => [ 'id', 'title', 'type', 'production_status', 'description', 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ],
			'Storyboard' => [ 'id', 'title', 'frame_number', 'description', 'prompt_text', 'camera_notes', 'scene', 'shot', 'image_asset' ],
			'Asset'      => [ 'id', 'title', 'asset_title', 'type', 'asset_type', 'workflow_name', 'prompt', 'model_name', 'seed', 'version', 'status', 'storage_uri', 'project', 'character', 'location', 'scene', 'storyboard' ],
			'Editorial Artifact' => [ 'id', 'title', 'artifact_type', 'export_format', 'generated_date', 'source_scene', 'source_shot', 'notes', 'project' ],
		];

		foreach ( $records as $label => $items ) {
			foreach ( $items as $index => $item ) {
				if ( ! is_array( $item ) ) {
					$errors[] = sprintf( '%s[%d] must be an object.', $label, $index );
					continue;
				}

				$context = sprintf( '%s %s', $label, $item['id'] ?? '#' . ( $index + 1 ) );
				foreach ( $scalar_fields[ $label ] as $field ) {
					if ( array_key_exists( $field, $item ) && null !== $item[ $field ] && ! is_scalar( $item[ $field ] ) ) {
						$errors[] = sprintf( '%s %s must be a scalar value.', $context, $field );
					}
				}
			}
		}
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'worldgraph_invalid_field', implode( ' ', array_unique( $errors ) ) );
		}

		foreach ( [ 'characters' => 'name', 'locations' => 'name', 'props' => 'name' ] as $section => $field ) {
			foreach ( $data[ $section ] as $index => $item ) {
				if ( empty( $item[ $field ] ) || ! is_scalar( $item[ $field ] ) ) {
					$errors[] = sprintf( '%s[%d] must have a %s.', $section, $index, $field );
				}
			}
		}

		foreach ( $data['organizations'] as $index => $organization ) {
			if ( empty( $organization['name'] ) && empty( $organization['organization_name'] ) ) {
				$errors[] = sprintf( 'organizations[%d] must have a name.', $index );
			}
		}
		foreach ( $data['episodes'] as $index => $episode ) {
			if ( empty( $episode['title'] ) || ! isset( $episode['episode_number'] ) ) {
				$errors[] = sprintf( 'episodes[%d] must have a title and episode_number.', $index );
			}
		}
		foreach ( $data['editorial_artifacts'] as $index => $artifact ) {
			if ( empty( $artifact['title'] ) || empty( $artifact['artifact_type'] ) ) {
				$errors[] = sprintf( 'editorial_artifacts[%d] must have a title and artifact_type.', $index );
			}
		}

		foreach ( $data['scenes'] as $index => $scene ) {
			if ( empty( $scene['title'] ) && empty( $scene['label'] ) ) {
				$errors[] = sprintf( 'scenes[%d] must have a title.', $index );
			}
			if ( isset( $scene['dialogue'] ) && ! is_array( $scene['dialogue'] ) ) {
				$errors[] = sprintf( 'Scene %s dialogue must be an array.', $scene['id'] ?? '#' . ( $index + 1 ) );
				continue;
			}
			foreach ( (array) ( $scene['dialogue'] ?? [] ) as $line_index => $line ) {
				if ( ! is_array( $line ) || empty( $line['speaker'] ) || ( ! array_key_exists( 'text', $line ) && ! array_key_exists( 'line', $line ) ) ) {
					$errors[] = sprintf( 'Scene %s dialogue[%d] must have speaker and text.', $scene['id'] ?? '#' . ( $index + 1 ), $line_index );
					continue;
				}
				foreach ( [ 'speaker', 'text', 'line', 'description', 'sequence' ] as $field ) {
					if ( array_key_exists( $field, $line ) && null !== $line[ $field ] && ! is_scalar( $line[ $field ] ) ) {
						$errors[] = sprintf( 'Scene %s dialogue[%d] %s must be scalar.', $scene['id'] ?? '#' . ( $index + 1 ), $line_index, $field );
					}
				}
				if ( isset( $line['sequence'] ) && ( ! is_numeric( $line['sequence'] ) || (int) $line['sequence'] < 1 ) ) {
					$errors[] = sprintf( 'Scene %s dialogue[%d] sequence must be a positive number.', $scene['id'] ?? '#' . ( $index + 1 ), $line_index );
				}
			}
		}

		foreach ( $data['assets'] as $index => $asset ) {
			if ( empty( $asset['title'] ) && empty( $asset['asset_title'] ) ) {
				$errors[] = sprintf( 'assets[%d] must have a title.', $index );
			}
			if ( empty( $asset['type'] ) && empty( $asset['asset_type'] ) ) {
				$errors[] = sprintf( 'assets[%d] must have a type.', $index );
			}
			if ( array_key_exists( 'generation_parameters', $asset ) && ! is_array( $asset['generation_parameters'] ) && ! is_scalar( $asset['generation_parameters'] ) ) {
				$errors[] = sprintf( 'Asset %s generation_parameters must be an object or JSON string.', $asset['id'] ?? '#' . ( $index + 1 ) );
			}
		}

		$array_fields = [
			[ $data['project'], 'genres', 'Project' ],
			[ $data['project'], 'team_members', 'Project' ],
		];
		foreach ( $data['characters'] as $character ) {
			$array_fields[] = [ $character, 'roles', 'Character ' . ( $character['id'] ?? '(unknown)' ) ];
			$array_fields[] = [ $character, 'relations', 'Character ' . ( $character['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['organizations'] as $organization ) {
			$array_fields[] = [ $organization, 'members', 'Organization ' . ( $organization['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['episodes'] as $episode ) {
			$array_fields[] = [ $episode, 'scenes', 'Episode ' . ( $episode['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['scenes'] as $scene ) {
			foreach ( [ 'characters', 'props', 'tags' ] as $field ) {
				$array_fields[] = [ $scene, $field, 'Scene ' . ( $scene['id'] ?? '(unknown)' ) ];
			}
		}
		foreach ( $array_fields as [ $item, $field, $context ] ) {
			if ( ! array_key_exists( $field, $item ) ) {
				continue;
			}
			if ( ! is_array( $item[ $field ] ) ) {
				$errors[] = sprintf( '%s %s must be an array.', $context, $field );
				continue;
			}
			foreach ( $item[ $field ] as $value ) {
				if ( ! is_scalar( $value ) ) {
					$errors[] = sprintf( '%s %s values must be scalar.', $context, $field );
				}
			}
		}

		$choice_values = [
			[ $data['project'], 'target_medium', [ 'film', 'short_film', 'tv_series', 'web_series', 'anime', 'animation', 'documentary', 'game', 'other' ], 'Project' ],
			[ $data['project'], 'production_stage', [ 'concept', 'development', 'pre_production', 'production', 'post_production', 'released' ], 'Project' ],
		];
		foreach ( $data['locations'] as $location ) {
			$choice_values[] = [ $location, 'environment_type', [ 'indoor', 'outdoor', 'urban', 'rural', 'fantasy', 'sci_fi', 'abstract' ], 'Location ' . ( $location['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['scenes'] as $scene ) {
			$choice_values[] = [ $scene, 'time_of_day', [ 'dawn', 'morning', 'midday', 'afternoon', 'dusk', 'evening', 'night' ], 'Scene ' . ( $scene['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['shots'] as $shot ) {
			$choice_values[] = [ $shot, 'camera_angle', [ 'eye_level', 'low_angle', 'high_angle', 'birdseye', 'wormseye', 'dutch' ], 'Shot ' . ( $shot['id'] ?? '(unknown)' ) ];
			if ( isset( $shot['type'] ) && is_scalar( $shot['type'] ) && '' !== trim( (string) $shot['type'] ) ) {
				$shot_type = \WorldGraph\Utils\worldgraph_normalize_shot_type( (string) $shot['type'] );
				if ( ! isset( \WorldGraph\Utils\worldgraph_shot_types()[ $shot_type ] ) ) {
					$errors[] = sprintf( 'Shot %s type has an invalid value.', $shot['id'] ?? '(unknown)' );
				}
			}
		}
		foreach ( $data['assets'] as $asset ) {
			$choice_values[] = [ $asset, 'status', [ 'pending', 'processing', 'done', 'error' ], 'Asset ' . ( $asset['id'] ?? '(unknown)' ) ];
		}
		foreach ( $data['editorial_artifacts'] as $artifact ) {
			$choice_values[] = [ $artifact, 'artifact_type', [ 'edl', 'timeline_metadata', 'xml', 'aaf', 'shot_list', 'production_report' ], 'Editorial Artifact ' . ( $artifact['id'] ?? '(unknown)' ) ];
		}

		foreach ( $choice_values as [ $item, $field, $allowed, $context ] ) {
			if ( ! array_key_exists( $field, $item ) || '' === (string) $item[ $field ] ) {
				continue;
			}
			if ( ! is_scalar( $item[ $field ] ) || ! in_array( sanitize_key( (string) $item[ $field ] ), $allowed, true ) ) {
				$errors[] = sprintf( '%s %s has an invalid value.', $context, $field );
			}
		}

		foreach ( [ 'frame_width', 'frame_height', 'frame_rate' ] as $field ) {
			if ( isset( $data['project'][ $field ] ) && ( ! is_numeric( $data['project'][ $field ] ) || (float) $data['project'][ $field ] <= 0 ) ) {
				$errors[] = sprintf( 'Project %s must be a positive number.', $field );
			}
		}
		foreach ( [ 'episodes' => 'episode_number', 'scenes' => 'scene_number', 'shots' => 'shot_number', 'storyboards' => 'frame_number', 'assets' => 'seed' ] as $section => $field ) {
			foreach ( $data[ $section ] as $item ) {
				$minimum = 'seed' === $field ? 0 : 1;
				if ( isset( $item[ $field ] ) && ( ! is_numeric( $item[ $field ] ) || (float) $item[ $field ] < $minimum ) ) {
					$errors[] = sprintf( '%s %s must be at least %d.', ucfirst( rtrim( $section, 's' ) ), $field, $minimum );
				}
			}
		}
		if ( isset( $data['sequence']['sequence_order'] ) && ( ! is_numeric( $data['sequence']['sequence_order'] ) || (int) $data['sequence']['sequence_order'] < 1 ) ) {
			$errors[] = 'Sequence sequence_order must be a positive number.';
		}

		$status_records = [ [ $data['project'], 'Project' ] ];
		foreach ( $data['episodes'] as $episode ) {
			$status_records[] = [ $episode, 'Episode ' . ( $episode['id'] ?? '(unknown)' ) ];
		}
		foreach ( $status_records as [ $item, $context ] ) {
			if ( ! empty( $item['production_status'] ) && ! get_term_by( 'slug', sanitize_title( (string) $item['production_status'] ), 'worldgraph_status' ) ) {
				$errors[] = $context . ' production_status must match an existing Status term.';
			}
		}

		$date_fields = [
			[ $data['project'], 'start_date', 'Project' ],
			[ $data['project'], 'end_date', 'Project' ],
		];
		foreach ( $data['editorial_artifacts'] as $artifact ) {
			$date_fields[] = [ $artifact, 'generated_date', 'Editorial Artifact ' . ( $artifact['id'] ?? '(unknown)' ) ];
		}
		foreach ( $date_fields as [ $item, $field, $context ] ) {
			if ( empty( $item[ $field ] ) ) {
				continue;
			}
			$value = (string) $item[ $field ];
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) || ! checkdate( (int) ( $parts[2] ?? 0 ), (int) ( $parts[3] ?? 0 ), (int) ( $parts[1] ?? 0 ) ) ) {
				$errors[] = sprintf( '%s %s must use a valid YYYY-MM-DD date.', $context, $field );
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'worldgraph_invalid_field', implode( ' ', array_unique( $errors ) ) );
		}

		return true;
	}

	/**
	 * Validate all external-ID references before creating any posts.
	 *
	 * @param array $data Parsed World Graph Studio document.
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

		foreach ( [ 'characters', 'locations', 'props', 'organizations', 'episodes', 'scenes', 'shots', 'sounds', 'storyboards', 'assets', 'editorial_artifacts' ] as $section ) {
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

		$id_sets['sequences'] = [];
		$sequence_external_id = sanitize_text_field( (string) $data['sequence']['id'] );
		if ( $sequence_external_id !== (string) $data['sequence']['id'] ) {
			$errors[] = sprintf( 'Sequence id "%s" contains unsupported characters.', $data['sequence']['id'] );
		}
		if ( isset( $all_ids[ $sequence_external_id ] ) ) {
			$errors[] = sprintf( 'External id "%s" is reused by %s and sequence.', $sequence_external_id, $all_ids[ $sequence_external_id ] );
		}
		$id_sets['sequences'][ $sequence_external_id ] = true;
		$all_ids[ $sequence_external_id ]              = 'sequence';

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'worldgraph_invalid_reference', implode( ' ', $errors ) );
		}

		$sequence_matches = get_terms(
			[
				'taxonomy'   => 'worldgraph_sequence',
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => 'external_id',
				'meta_value' => $sequence_external_id,
			]
		);
		if ( ! is_wp_error( $sequence_matches ) && empty( $sequence_matches ) ) {
			$sequence_title = sanitize_text_field( (string) ( $data['sequence']['title'] ?? $data['sequence']['name'] ?? 'Sequence' ) );
			$title_term     = get_term_by( 'name', $sequence_title, 'worldgraph_sequence' );
			if ( $title_term ) {
				$title_external_id = (string) get_term_meta( $title_term->term_id, 'external_id', true );
				if ( '' !== $title_external_id && $sequence_external_id !== $title_external_id ) {
					$errors[] = sprintf( 'Sequence title "%s" is already assigned to external id "%s".', $sequence_title, $title_external_id );
				}
			}
		}

		if ( ! empty( $data['world']['project'] ) && (string) $data['world']['project'] !== (string) $data['project']['id'] ) {
			$errors[] = sprintf( 'Story World references unknown project id "%s".', $data['world']['project'] );
		}

		foreach ( (array) ( $data['project']['team_members'] ?? [] ) as $character_id ) {
			$this->validate_reference( $character_id, 'characters', 'Project team member', $id_sets, $errors );
		}

		foreach ( $data['characters'] as $character ) {
			if ( ! empty( $character['story_world'] ) && (string) $character['story_world'] !== (string) $data['world']['id'] ) {
				$errors[] = sprintf( 'Character %s references unknown story world id "%s".', $character['id'] ?? '(unknown)', $character['story_world'] );
			}
			if ( ! empty( $character['avatar_asset'] ) ) {
				$this->validate_asset_reference( $character['avatar_asset'], 'Character ' . ( $character['id'] ?? '(unknown)' ) . ' avatar_asset', $id_sets, $errors );
			}
		}

		foreach ( $data['locations'] as $location ) {
			if ( ! empty( $location['story_world'] ) && (string) $location['story_world'] !== (string) $data['world']['id'] ) {
				$errors[] = sprintf( 'Location %s references unknown story world id "%s".', $location['id'] ?? '(unknown)', $location['story_world'] );
			}
			if ( ! empty( $location['visual_reference'] ) ) {
				$this->validate_asset_reference( $location['visual_reference'], 'Location ' . ( $location['id'] ?? '(unknown)' ) . ' visual_reference', $id_sets, $errors );
			}
		}

		foreach ( $data['props'] as $prop ) {
			if ( ! empty( $prop['owner_character'] ) ) {
				$this->validate_reference( $prop['owner_character'], 'characters', 'Prop ' . ( $prop['id'] ?? '(unknown)' ) . ' owner_character', $id_sets, $errors );
			}
		}

		foreach ( $data['organizations'] as $organization ) {
			$context = 'Organization ' . ( $organization['id'] ?? '(unknown)' );
			if ( ! empty( $organization['leadership'] ) ) {
				$this->validate_reference( $organization['leadership'], 'characters', $context . ' leadership', $id_sets, $errors );
			}
			foreach ( (array) ( $organization['members'] ?? [] ) as $character_id ) {
				$this->validate_reference( $character_id, 'characters', $context . ' member', $id_sets, $errors );
			}
			if ( ! empty( $organization['story_world'] ) ) {
				if ( (string) $organization['story_world'] !== (string) $data['world']['id'] ) {
					$errors[] = sprintf( '%s references unknown story world id "%s".', $context, $organization['story_world'] );
				}
			}
		}

		$declared_scene_episodes = [];
		foreach ( $data['scenes'] as $scene ) {
			if ( ! empty( $scene['id'] ) && ! empty( $scene['episode'] ) ) {
				$declared_scene_episodes[ (string) $scene['id'] ] = (string) $scene['episode'];
			}
		}

		$episode_scenes          = [];
		$episodes_with_scene_list = [];
		foreach ( $data['episodes'] as $episode ) {
			$context = 'Episode ' . ( $episode['id'] ?? '(unknown)' );
			if ( array_key_exists( 'scenes', $episode ) ) {
				$episodes_with_scene_list[ (string) ( $episode['id'] ?? '' ) ] = true;
			}
			if ( empty( $episode['project'] ) ) {
				$errors[] = $context . ' must reference its Project.';
			} elseif ( (string) $episode['project'] !== (string) $data['project']['id'] ) {
				$errors[] = sprintf( 'Episode %s references unknown project id "%s".', $episode['id'] ?? '(unknown)', $episode['project'] );
			}
			foreach ( (array) ( $episode['scenes'] ?? [] ) as $scene_id ) {
				$this->validate_reference( $scene_id, 'scenes', $context . ' scene', $id_sets, $errors );
				if ( is_scalar( $scene_id ) ) {
					$scene_id = (string) $scene_id;
					$episode_scenes[ $scene_id ][] = (string) ( $episode['id'] ?? '' );
					if ( ! isset( $declared_scene_episodes[ $scene_id ] ) || (string) ( $episode['id'] ?? '' ) !== $declared_scene_episodes[ $scene_id ] ) {
						$errors[] = sprintf( '%s scene "%s" disagrees with that Scene\'s episode relationship.', $context, $scene_id );
					}
				}
			}
		}
		foreach ( $episode_scenes as $scene_id => $episode_ids ) {
			if ( count( array_unique( $episode_ids ) ) > 1 ) {
				$errors[] = sprintf( 'Scene "%s" is listed by more than one Episode.', $scene_id );
			}
		}

		foreach ( $data['scenes'] as $scene ) {
			$context = 'Scene ' . ( $scene['id'] ?? '(unknown)' );
			if ( ! empty( $scene['location'] ) ) {
				$this->validate_reference( $scene['location'], 'locations', $context . ' location', $id_sets, $errors );
			}
			if ( ! empty( $scene['episode'] ) ) {
				$this->validate_reference( $scene['episode'], 'episodes', $context . ' episode', $id_sets, $errors );
				$listed_episodes = $episode_scenes[ (string) ( $scene['id'] ?? '' ) ] ?? [];
				if (
					isset( $episodes_with_scene_list[ (string) $scene['episode'] ] ) &&
					! in_array( (string) $scene['episode'], $listed_episodes, true )
				) {
					$errors[] = sprintf( '%s episode "%s" disagrees with its Episode scenes list.', $context, $scene['episode'] );
				}
			}
			if ( ! empty( $scene['sequence'] ) ) {
				$this->validate_reference( $scene['sequence'], 'sequences', $context . ' sequence', $id_sets, $errors );
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
			if ( ! empty( $shot['sequence'] ) ) {
				$this->validate_reference( $shot['sequence'], 'sequences', 'Shot ' . ( $shot['id'] ?? '(unknown)' ) . ' sequence', $id_sets, $errors );
				if ( ! in_array( (string) ( $shot['scene'] ?? '' ), array_map( 'strval', (array) $data['sequence']['order'] ), true ) ) {
					$errors[] = sprintf( 'Shot %s names Sequence "%s" but its Scene is absent from sequence.order.', $shot['id'] ?? '(unknown)', $shot['sequence'] );
				}
			}
		}

		$shot_scenes = [];
		foreach ( $data['shots'] as $shot ) {
			if ( ! empty( $shot['id'] ) ) {
				$shot_scenes[ (string) $shot['id'] ] = (string) ( $shot['scene'] ?? '' );
			}
		}

		$document_assets = [];
		foreach ( $data['assets'] as $asset ) {
			if ( ! empty( $asset['id'] ) ) {
				$document_assets[ (string) $asset['id'] ] = $asset;
			}
		}

		foreach ( $data['editorial_artifacts'] as $artifact ) {
			$context = 'Editorial Artifact ' . ( $artifact['id'] ?? '(unknown)' );
			if ( ! empty( $artifact['source_scene'] ) ) {
				$this->validate_reference( $artifact['source_scene'], 'scenes', $context . ' source_scene', $id_sets, $errors );
			}
			if ( ! empty( $artifact['source_shot'] ) ) {
				$this->validate_reference( $artifact['source_shot'], 'shots', $context . ' source_shot', $id_sets, $errors );
				if (
					! empty( $artifact['source_scene'] ) &&
					isset( $shot_scenes[ (string) $artifact['source_shot'] ] ) &&
					(string) $artifact['source_scene'] !== $shot_scenes[ (string) $artifact['source_shot'] ]
				) {
					$errors[] = sprintf( '%s source_shot "%s" does not belong to source_scene "%s".', $context, $artifact['source_shot'], $artifact['source_scene'] );
				}
			}
			if ( ! empty( $artifact['project'] ) && (string) $artifact['project'] !== (string) $data['project']['id'] ) {
				$errors[] = sprintf( '%s references unknown project id "%s".', $context, $artifact['project'] );
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
			if ( \WorldGraph\Utils\worldgraph_is_reserved_sound_type( $sound_type ) ) {
				$errors[] = $context . ' cannot use the reserved dialogue type; ordinary dialogue belongs in scenes[].dialogue.';
			}

			if ( ! empty( $sound['lyrics'] ) && 'music' !== $sound_type ) {
				$errors[] = $context . ' may only include lyrics when type is music.';
			}

			if ( isset( $sound['diegetic'] ) && ! in_array( (string) $sound['diegetic'], [ 'unspecified', 'diegetic', 'non_diegetic', 'internal', 'mixed' ], true ) ) {
				$errors[] = $context . ' has an invalid diegetic value.';
			}

			if ( ! empty( $sound['production_status'] ) && ! get_term_by( 'slug', sanitize_title( (string) $sound['production_status'] ), 'worldgraph_status' ) ) {
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
				if ( isset( $document_assets[ $asset_external_id ] ) ) {
					$asset_type = sanitize_title( (string) ( $document_assets[ $asset_external_id ]['type'] ?? $document_assets[ $asset_external_id ]['asset_type'] ?? '' ) );
					if ( 'audio' !== $asset_type ) {
						$errors[] = sprintf( '%s asset "%s" is not classified as Audio.', $context, $asset_external_id );
					}
				} else {
					$asset_id = $this->find_existing( 'worldgraph_asset', $asset_external_id );
					if ( $asset_external_id !== (string) $sound['asset'] || ! $asset_id ) {
						$errors[] = sprintf( '%s references unknown asset id "%s".', $context, $asset_external_id );
					} elseif ( ! \WorldGraph\Utils\worldgraph_is_audio_asset( $asset_id ) ) {
						$errors[] = sprintf( '%s asset "%s" is not classified as Audio.', $context, $asset_external_id );
					}
				}
			}
		}

		foreach ( $data['storyboards'] as $frame ) {
			$context = 'Storyboard ' . ( $frame['id'] ?? '(unknown)' );
			$this->validate_reference( $frame['shot'] ?? '', 'shots', $context . ' shot', $id_sets, $errors );
			if ( ! empty( $frame['scene'] ) ) {
				$this->validate_reference( $frame['scene'], 'scenes', $context . ' scene', $id_sets, $errors );
				$shot_scene = $shot_scenes[ (string) ( $frame['shot'] ?? '' ) ] ?? '';
				if ( $shot_scene && (string) $frame['scene'] !== $shot_scene ) {
					$errors[] = sprintf( '%s shot "%s" does not belong to scene "%s".', $context, $frame['shot'], $frame['scene'] );
				}
			}
			if ( ! empty( $frame['image_asset'] ) ) {
				$this->validate_asset_reference( $frame['image_asset'], $context . ' image_asset', $id_sets, $errors );
			}
		}

		foreach ( $data['assets'] as $asset ) {
			$context = 'Asset ' . ( $asset['id'] ?? '(unknown)' );
			if ( ! empty( $asset['project'] ) && (string) $asset['project'] !== (string) $data['project']['id'] ) {
				$errors[] = sprintf( '%s references unknown project id "%s".', $context, $asset['project'] );
			}
			foreach ( [ 'character' => 'characters', 'location' => 'locations', 'scene' => 'scenes', 'storyboard' => 'storyboards' ] as $field => $section ) {
				if ( ! empty( $asset[ $field ] ) ) {
					$this->validate_reference( $asset[ $field ], $section, $context . ' ' . $field, $id_sets, $errors );
				}
			}
		}

		$sequence_scene_ids = [];
		foreach ( (array) $data['sequence']['order'] as $scene_id ) {
			$this->validate_reference( $scene_id, 'scenes', 'Sequence scene', $id_sets, $errors );
			if ( is_scalar( $scene_id ) ) {
				$scene_id = (string) $scene_id;
				if ( isset( $sequence_scene_ids[ $scene_id ] ) ) {
					$errors[] = sprintf( 'Sequence order contains Scene "%s" more than once.', $scene_id );
				}
				$sequence_scene_ids[ $scene_id ] = true;
			}
		}
		foreach ( $data['scenes'] as $scene ) {
			if ( ! empty( $scene['sequence'] ) && ! isset( $sequence_scene_ids[ (string) $scene['id'] ] ) ) {
				$errors[] = sprintf( 'Scene %s names Sequence "%s" but is absent from sequence.order.', $scene['id'], $scene['sequence'] );
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'worldgraph_invalid_reference', implode( ' ', $errors ) );
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
	 * Validate an Asset external ID from this document or the existing library.
	 *
	 * @param mixed  $reference Referenced external ID.
	 * @param string $context   Human-readable reference context.
	 * @param array  $id_sets   External IDs keyed by document section.
	 * @param array  $errors    Validation errors, passed by reference.
	 */
	private function validate_asset_reference( $reference, string $context, array $id_sets, array &$errors ): void {
		if ( ! is_scalar( $reference ) ) {
			$errors[] = sprintf( '%s must be a scalar external id.', $context );
			return;
		}

		$external_id = sanitize_text_field( (string) $reference );
		if ( $external_id !== (string) $reference ) {
			$errors[] = sprintf( '%s id "%s" contains unsupported characters.', $context, $reference );
			return;
		}

		if ( isset( $id_sets['assets'][ $external_id ] ) ) {
			return;
		}

		if ( '' === $external_id || ! $this->find_existing( 'worldgraph_asset', $external_id ) ) {
			$errors[] = sprintf( '%s references unknown asset id "%s".', $context, $external_id );
		}
	}

	/**
	 * Import the project entity.
	 */
	private function import_project(): void {
		$project = $this->document['project'];
		$external_id = sanitize_text_field( $project['id'] );

		$post_id = $this->find_existing( 'worldgraph_project', $external_id );

		if ( $post_id && ! $this->overwrite ) {
			$this->report['skipped'][] = "Project {$external_id} already exists.";
			$this->id_map[ $external_id ]            = $post_id;
			$this->skipped_entities[ $external_id ] = true;
			return;
		}

		$post_data = [
			'post_type'    => 'worldgraph_project',
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
		\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'project_name', sanitize_text_field( $project['title'] ) );
		$project_slug = isset( $project['project_slug'] )
			? \WorldGraph\Utils\sanitize_story_id( (string) $project['project_slug'] )
			: \WorldGraph\Utils\sanitize_story_id( $external_id );
		\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'project_slug', $project_slug );
		if ( isset( $project['description'] ) ) {
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'description', wp_kses_post( $project['description'] ) );
		}
		$this->update_scalar_fields(
			$post_id,
			'worldgraph_project',
			$project,
			[
				'target_medium'    => 'target_medium',
				'start_date'       => 'start_date',
				'end_date'         => 'end_date',
				'production_stage' => 'production_stage',
				'frame_width'      => 'frame_width',
				'frame_height'     => 'frame_height',
				'aspect_ratio'     => 'aspect_ratio',
				'frame_rate'       => 'frame_rate',
			]
		);

		$owner_id = get_current_user_id();
		if ( ! $owner_id ) {
			$owner_id = (int) get_post_field( 'post_author', $post_id );
		}
		if ( $owner_id ) {
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'owner', $owner_id );
		}

		if ( array_key_exists( 'genres', $project ) || array_key_exists( 'genre', $project ) ) {
			$this->assign_taxonomy_terms( $post_id, 'worldgraph_genre', $project['genres'] ?? $project['genre'], true, false, 'Project genre' );
		}
		if ( array_key_exists( 'production_status', $project ) ) {
			$this->assign_taxonomy_terms( $post_id, 'worldgraph_status', $project['production_status'], false, true, 'Project production status' );
		}
	}

	/**
	 * Import the world entity.
	 */
	private function import_world(): void {
		$world = $this->document['world'];
		$external_id = sanitize_text_field( $world['id'] );

		$post_id = $this->find_existing( 'worldgraph_world', $external_id );

		if ( $post_id && ! $this->overwrite ) {
			$this->report['skipped'][] = "World {$external_id} already exists.";
			$this->id_map[ $external_id ]            = $post_id;
			$this->skipped_entities[ $external_id ] = true;
			return;
		}

		$post_data = [
			'post_type'    => 'worldgraph_world',
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
		\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'world_name', sanitize_text_field( $world['name'] ) );
		if ( isset( $world['description'] ) ) {
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'synopsis', wp_kses_post( $world['description'] ) );
		}
		$this->update_scalar_fields(
			$post_id,
			'worldgraph_world',
			$world,
			[
				'timeline'   => 'timeline',
				'rules'      => 'rules',
				'themes'     => 'themes',
				'geography'  => 'geography',
				'references' => 'references',
			]
		);
	}

	/**
	 * Import all characters.
	 */
	private function import_characters(): void {
		foreach ( $this->document['characters'] as $character ) {
			$external_id = sanitize_text_field( $character['id'] );

			$post_id = $this->find_existing( 'worldgraph_character', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Character {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_character',
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'display_name', sanitize_text_field( $character['name'] ) );
			if ( isset( $character['description'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'biography', wp_kses_post( $character['description'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_character',
				$character,
				[
					'age'           => 'age',
					'appearance'    => 'appearance',
					'personality'   => 'personality',
					'motivation'    => 'motivation',
					'backstory'     => 'backstory',
					'voice_profile' => 'voice_profile',
				]
			);

			$roles = $character['roles'] ?? ( isset( $character['archetype'] ) ? [ $character['archetype'] ] : null );
			if ( null !== $roles ) {
				$this->assign_taxonomy_terms( $post_id, 'worldgraph_character_role', $roles, true, false, "Character {$external_id} roles" );
			}
			if ( array_key_exists( 'relations', $character ) ) {
				$this->assign_taxonomy_terms( $post_id, 'worldgraph_character_relation', $character['relations'], true, false, "Character {$external_id} relations" );
			}
		}
	}

	/**
	 * Import all locations.
	 */
	private function import_locations(): void {
		foreach ( $this->document['locations'] as $location ) {
			$external_id = sanitize_text_field( $location['id'] );

			$post_id = $this->find_existing( 'worldgraph_location', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Location {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_location',
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'location_name', sanitize_text_field( $location['name'] ) );
			if ( isset( $location['description'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'description', wp_kses_post( $location['description'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_location',
				$location,
				[
					'environment_type' => 'environment_type',
					'geography'        => 'geography',
					'mood'             => 'mood',
				]
			);
		}
	}

	/**
	 * Import all props.
	 */
	private function import_props(): void {
		foreach ( $this->document['props'] as $prop ) {
			$external_id = sanitize_text_field( $prop['id'] );

			$post_id = $this->find_existing( 'worldgraph_prop', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Prop {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_prop',
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'prop_name', sanitize_text_field( $prop['name'] ) );
			if ( isset( $prop['description'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'description', wp_kses_post( $prop['description'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_prop',
				$prop,
				[
					'purpose' => 'purpose',
					'notes'   => 'notes',
				]
			);
		}
	}

	/**
	 * Import all story-world organizations.
	 */
	private function import_organizations(): void {
		foreach ( $this->document['organizations'] as $organization ) {
			$external_id = sanitize_text_field( (string) $organization['id'] );
			$post_id     = $this->find_existing( 'worldgraph_org', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][]  = "Organization {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$name = sanitize_text_field( (string) ( $organization['name'] ?? $organization['organization_name'] ) );
			$post_data = [
				'post_type'    => 'worldgraph_org',
				'post_title'   => $name,
				'post_status'  => 'publish',
				'post_content' => isset( $organization['description'] ) ? wp_kses_post( (string) $organization['description'] ) : '',
			];

			$operation = $post_id ? 'updated' : 'created';
			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Organization {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->report[ $operation ][] = "Organization {$external_id}";
			$this->id_map[ $external_id ] = $post_id;
			update_post_meta( $post_id, 'external_id', $external_id );
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'organization_name', $name );
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_org',
				$organization,
				[
					'organization_type' => 'organization_type',
					'description'       => 'description',
					'goals'             => 'goals',
				]
			);
		}
	}

	/**
	 * Import all episodes.
	 */
	private function import_episodes(): void {
		foreach ( $this->document['episodes'] as $episode ) {
			$external_id = sanitize_text_field( (string) $episode['id'] );
			$post_id     = $this->find_existing( 'worldgraph_episode', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][]  = "Episode {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_episode',
				'post_title'   => sanitize_text_field( (string) $episode['title'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $episode['synopsis'] ) ? wp_kses_post( (string) $episode['synopsis'] ) : '',
				'menu_order'   => (int) $episode['episode_number'],
			];

			$operation = $post_id ? 'updated' : 'created';
			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Episode {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->report[ $operation ][] = "Episode {$external_id}";
			$this->id_map[ $external_id ] = $post_id;
			update_post_meta( $post_id, 'external_id', $external_id );
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_episode',
				$episode,
				[
					'episode_number' => 'episode_number',
					'title'          => 'title',
					'synopsis'       => 'synopsis',
				]
			);
			if ( array_key_exists( 'production_status', $episode ) ) {
				$this->assign_taxonomy_terms( $post_id, 'worldgraph_status', $episode['production_status'], false, true, "Episode {$external_id} production status" );
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
			$scene_number = isset( $scene['scene_number'] ) ? (int) $scene['scene_number'] : $scene_index;

			$post_id = $this->find_existing( 'worldgraph_scene', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Scene {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				$scene_index++;
				continue;
			}

			$scene_label = sanitize_text_field( $scene['title'] ?? ( $scene['label'] ?? '' ) );
			$post_data = [
				'post_type'    => 'worldgraph_scene',
				'post_title'   => $scene_label ?: sprintf( 'Scene %d', $scene_index ),
				'post_status'  => 'publish',
				'post_content' => isset( $scene['summary'] ) ? wp_kses_post( $scene['summary'] ) : '',
				'menu_order'   => $scene_number,
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'scene_number', $scene_number );
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'title', $scene_label );
			if ( isset( $scene['summary'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'summary', wp_kses_post( $scene['summary'] ) );
			}
			if ( isset( $scene['script_content'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'script_content', wp_kses_post( $scene['script_content'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_scene',
				$scene,
				[
					'time_of_day'     => 'time_of_day',
					'emotional_tone'  => 'emotional_tone',
					'production_notes'=> 'production_notes',
				]
			);

			// Store dialogue as structured metadata, including an explicit clear.
			if ( array_key_exists( 'dialogue', $scene ) && is_array( $scene['dialogue'] ) ) {
				$dialogue = [];
				$sequence = 1;
				foreach ( $scene['dialogue'] as $line ) {
					$dialogue[] = [
						'speaker'     => sanitize_text_field( $line['speaker'] ?? '' ),
						'line'        => sanitize_textarea_field( (string) ( $line['line'] ?? $line['text'] ?? '' ) ),
						'description' => sanitize_text_field( $line['description'] ?? '' ),
						'sequence'    => isset( $line['sequence'] ) ? max( 1, (int) $line['sequence'] ) : $sequence,
					];
					$sequence++;
				}
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'dialogue', $dialogue );
			}

			if ( array_key_exists( 'tags', $scene ) ) {
				$this->assign_taxonomy_terms( $post_id, 'worldgraph_scene_tag', $scene['tags'], true, false, "Scene {$external_id} tags" );
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
			$shot_number = isset( $shot['shot_number'] ) ? (int) $shot['shot_number'] : $shot_index;

			$post_id = $this->find_existing( 'worldgraph_shot', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Shot {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				$shot_index++;
				continue;
			}

			// Normalize the shot type so it matches the canonical options.
			$shot_type = isset( $shot['type'] ) ? \WorldGraph\Utils\worldgraph_normalize_shot_type( (string) $shot['type'] ) : '';

			$explicit_shot_title = sanitize_text_field( $shot['title'] ?? ( $shot['label'] ?? '' ) );
			$shot_name = \WorldGraph\Utils\worldgraph_generate_shot_name( [
				'title'            => $explicit_shot_title,
				'shot_number'      => $shot_number,
				'shot_type'        => $shot_type,
				'shot_description' => $shot['description'] ?? '',
				'scene_title'      => $scene_titles[ sanitize_text_field( $shot['scene'] ?? '' ) ] ?? '',
			] );

			$post_data = [
				'post_type'    => 'worldgraph_shot',
				'post_title'   => $shot_name,
				'post_status'  => 'publish',
				'post_content' => isset( $shot['description'] ) ? wp_kses_post( $shot['description'] ) : '',
				'menu_order'   => $shot_number,
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'shot_number', $shot_number );
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'shot_name', $shot_name );
			if ( array_key_exists( 'type', $shot ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'shot_type', $shot_type );
			}
			if ( isset( $shot['description'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'shot_description', wp_kses_post( $shot['description'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_shot',
				$shot,
				[
					'camera_angle'    => 'camera_angle',
					'lens'            => 'lens',
					'duration'        => 'duration',
					'take_number'     => 'take_number',
					'slate_id'        => 'slate_id',
					'editorial_notes' => 'editorial_notes',
				]
			);

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
			$post_id     = $this->find_existing( 'worldgraph_sound', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Sound {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				$sound_index++;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_sound',
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

			$fields = \WorldGraph\Utils\worldgraph_get_fields( 'worldgraph_sound' );
			foreach ( [ 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes' ] as $meta_field ) {
				if ( array_key_exists( $meta_field, $sound ) ) {
					$value = \WorldGraph\Utils\worldgraph_sanitize_field_value( $sound[ $meta_field ], $fields[ $meta_field ] ?? [] );
					if ( '' === $value ) {
						\WorldGraph\Utils\worldgraph_delete_field_value( $post_id, $meta_field );
					} else {
						\WorldGraph\Utils\worldgraph_update_field_value( $post_id, $meta_field, $value );
					}
				} elseif ( $this->overwrite ) {
					\WorldGraph\Utils\worldgraph_delete_field_value( $post_id, $meta_field );
				}
			}

			$sound_type = sanitize_title( (string) $sound['type'] );
			$term       = get_term_by( 'slug', $sound_type, 'worldgraph_sound_type' );
			if ( ! $term ) {
				$seed_types = \WorldGraph\Utils\worldgraph_sound_types();
				$term       = wp_insert_term(
					$seed_types[ $sound_type ] ?? sanitize_text_field( (string) $sound['type'] ),
					'worldgraph_sound_type',
					[ 'slug' => $sound_type ]
				);
			}

			if ( is_wp_error( $term ) ) {
				$this->report['errors'][] = "Sound {$external_id} type: " . $term->get_error_message();
			} else {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term->term_id;
				wp_set_object_terms( $post_id, [ $term_id ], 'worldgraph_sound_type', false );
			}

			if ( array_key_exists( 'production_status', $sound ) ) {
				$status_slug = sanitize_title( (string) $sound['production_status'] );
				$status_term = '' !== $status_slug ? get_term_by( 'slug', $status_slug, 'worldgraph_status' ) : null;
				wp_set_object_terms( $post_id, $status_term ? [ (int) $status_term->term_id ] : [], 'worldgraph_status', false );
			} elseif ( $this->overwrite ) {
				wp_set_object_terms( $post_id, [], 'worldgraph_status', false );
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
			$frame_number = isset( $frame['frame_number'] ) ? (int) $frame['frame_number'] : $frame_index;

			$post_id = $this->find_existing( 'worldgraph_board', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][] = "Storyboard frame {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				$frame_index++;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_board',
				'post_title'   => sanitize_text_field( (string) ( $frame['title'] ?? sprintf( 'Storyboard Frame %d', $frame_number ) ) ),
				'post_status'  => 'publish',
				'post_content' => isset( $frame['description'] ) ? wp_kses_post( $frame['description'] ) : '',
				'menu_order'   => $frame_number,
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
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'frame_number', $frame_number );
			if ( isset( $frame['description'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'frame_description', wp_kses_post( $frame['description'] ) );
			}
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_board',
				$frame,
				[
					'prompt_text'  => 'prompt_text',
					'camera_notes' => 'camera_notes',
				]
			);

			$frame_index++;
		}
	}

	/**
	 * Import portable Asset records and their generation provenance.
	 */
	private function import_assets(): void {
		foreach ( $this->document['assets'] as $asset ) {
			$external_id = sanitize_text_field( (string) $asset['id'] );
			$post_id     = $this->find_existing( 'worldgraph_asset', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][]  = "Asset {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$title = sanitize_text_field( (string) ( $asset['title'] ?? $asset['asset_title'] ) );
			$post_data = [
				'post_type'    => 'worldgraph_asset',
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => isset( $asset['prompt'] ) ? wp_kses_post( (string) $asset['prompt'] ) : '',
			];

			$operation = $post_id ? 'updated' : 'created';
			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Asset {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->report[ $operation ][] = "Asset {$external_id}";
			$this->id_map[ $external_id ] = $post_id;
			update_post_meta( $post_id, 'external_id', $external_id );
			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, 'asset_title', $title );
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_asset',
				$asset,
				[
					'workflow_name'         => 'workflow_name',
					'prompt'                => 'prompt',
					'model_name'            => 'model_name',
					'seed'                  => 'seed',
					'generation_parameters' => 'generation_parameters',
					'version'               => 'version',
					'status'                => 'status',
					'storage_uri'           => 'storage_uri',
				]
			);

			$asset_type = $asset['asset_type'] ?? $asset['type'];
			$this->assign_taxonomy_terms( $post_id, 'worldgraph_asset_type', $asset_type, true, true, "Asset {$external_id} type" );
		}
	}

	/**
	 * Import portable editorial deliverable records.
	 */
	private function import_editorial_artifacts(): void {
		foreach ( $this->document['editorial_artifacts'] as $artifact ) {
			$external_id = sanitize_text_field( (string) $artifact['id'] );
			$post_id     = $this->find_existing( 'worldgraph_editorial', $external_id );

			if ( $post_id && ! $this->overwrite ) {
				$this->report['skipped'][]  = "Editorial Artifact {$external_id} already exists.";
				$this->id_map[ $external_id ]            = $post_id;
				$this->skipped_entities[ $external_id ] = true;
				continue;
			}

			$post_data = [
				'post_type'    => 'worldgraph_editorial',
				'post_title'   => sanitize_text_field( (string) $artifact['title'] ),
				'post_status'  => 'publish',
				'post_content' => isset( $artifact['notes'] ) ? wp_kses_post( (string) $artifact['notes'] ) : '',
			];

			$operation = $post_id ? 'updated' : 'created';
			if ( $post_id ) {
				$post_data['ID'] = $post_id;
				$post_id         = wp_update_post( $post_data, true );
			} else {
				$post_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $post_id ) ) {
				$this->report['errors'][] = "Editorial Artifact {$external_id}: " . $post_id->get_error_message();
				continue;
			}

			$this->report[ $operation ][] = "Editorial Artifact {$external_id}";
			$this->id_map[ $external_id ] = $post_id;
			update_post_meta( $post_id, 'external_id', $external_id );
			$this->update_scalar_fields(
				$post_id,
				'worldgraph_editorial',
				$artifact,
				[
					'artifact_type'  => 'artifact_type',
					'export_format'  => 'export_format',
					'generated_date' => 'generated_date',
					'notes'          => 'notes',
				]
			);
		}
	}

	/**
	 * Import the sequence taxonomy and assign scenes in order.
	 */
	private function import_sequence(): void {
		$sequence = $this->document['sequence'];
		$sequence_external_id = sanitize_text_field( (string) $sequence['id'] );
		$sequence_title = sanitize_text_field( $sequence['title'] ?? $sequence['name'] ?? 'Sequence' );

		// Resolve the portable identity before falling back to the display title.
		$matching_terms = get_terms(
			[
				'taxonomy'   => 'worldgraph_sequence',
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => 'external_id',
				'meta_value' => $sequence_external_id,
			]
		);
		$term = ( ! is_wp_error( $matching_terms ) && ! empty( $matching_terms ) )
			? [ 'term_id' => (int) $matching_terms[0]->term_id ]
			: term_exists( $sequence_title, 'worldgraph_sequence' );
		$sequence_term_was_existing = (bool) $term;
		if ( $term ) {
			$term_id          = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			$term_external_id = (string) get_term_meta( $term_id, 'external_id', true );
			if ( '' !== $term_external_id && $sequence_external_id !== $term_external_id ) {
				$this->report['errors'][] = sprintf( 'Sequence title "%s" belongs to external id "%s".', $sequence_title, $term_external_id );
				return;
			}
		}
		if ( ! $term ) {
			$term = wp_insert_term( $sequence_title, 'worldgraph_sequence', [ 'slug' => sanitize_title( $sequence_external_id ) ] );
		}

		if ( is_wp_error( $term ) ) {
			$this->report['errors'][] = 'Sequence: ' . $term->get_error_message();
			return;
		}

		$term_id = is_array( $term ) ? $term['term_id'] : (int) $term;
		if ( ! $sequence_term_was_existing || $this->overwrite || '' === (string) get_term_meta( $term_id, 'external_id', true ) ) {
			update_term_meta( $term_id, 'external_id', $sequence_external_id );
		}
		if ( $this->overwrite ) {
			wp_update_term( $term_id, 'worldgraph_sequence', [ 'name' => $sequence_title ] );
		}

		// Assign scenes to the sequence in order.
		$order = 1;
		foreach ( $sequence['order'] as $scene_external_id ) {
			$scene_external_id = sanitize_text_field( $scene_external_id );
			$scene_post_id = $this->id_map[ $scene_external_id ] ?? 0;

			if ( ! $scene_post_id ) {
				$this->report['errors'][] = "Sequence: scene {$scene_external_id} not found.";
				continue;
			}
			if ( $this->entity_was_skipped( $scene_external_id ) ) {
				$order++;
				continue;
			}

			wp_set_object_terms( $scene_post_id, $term_id, 'worldgraph_sequence' );
			update_post_meta( $scene_post_id, 'sequence_order', $order );
			$order++;
		}

		// Assign every shot that belongs to a scene in the sequence.
		foreach ( $this->document['shots'] as $shot ) {
			$scene_external_id = sanitize_text_field( $shot['scene'] ?? '' );
			$shot_external_id  = sanitize_text_field( $shot['id'] );
			$shot_post_id      = $this->id_map[ $shot_external_id ] ?? 0;

			if ( ! $shot_post_id || $this->entity_was_skipped( $shot_external_id ) || ! in_array( $scene_external_id, (array) $sequence['order'], true ) ) {
				continue;
			}

			wp_set_object_terms( $shot_post_id, $term_id, 'worldgraph_sequence', false );
		}

		// Record the editorial order of the sequence term itself.
		if ( ! $sequence_term_was_existing || $this->overwrite ) {
			$sequence_order = isset( $sequence['sequence_order'] ) ? max( 1, (int) $sequence['sequence_order'] ) : 1;
			\WorldGraph\Utils\worldgraph_set_sequence_order( (int) $term_id, $sequence_order );
		}

		$this->report['sequence'] = [
			'term_id'     => $term_id,
			'external_id' => $sequence_external_id,
			'title'       => $sequence_title,
			'order'       => $order - 1,
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
			if ( ! $this->entity_was_skipped( (string) $this->document['project']['id'] ) ) {
				\WorldGraph\Utils\add_relationship( $project_id, 'worldgraph_project', $world_id, 'worldgraph_world', 'contains' );
			}
			if ( ! $this->entity_was_skipped( (string) $this->document['world']['id'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $world_id, 'project', $project_id );
			}
		}

		if ( $project_id && ! $this->entity_was_skipped( (string) $this->document['project']['id'] ) && array_key_exists( 'team_members', $this->document['project'] ) ) {
			$team_member_ids = [];
			foreach ( $this->document['project']['team_members'] as $character_external_id ) {
				$character_id = $this->id_map[ (string) $character_external_id ] ?? 0;
				if ( $character_id ) {
					$team_member_ids[] = $character_id;
				}
			}
			$result = \WorldGraph\Utils\set_relationships_for_field( $project_id, 'worldgraph_project', $team_member_ids, 'worldgraph_character', 'contains', 'team_members', true );
			if ( is_wp_error( $result ) ) {
				$this->report['errors'][] = 'Project Team Members: ' . $result->get_error_message();
			} else {
				\WorldGraph\Utils\worldgraph_update_field_value( $project_id, 'team_members', $team_member_ids );
			}
		}

		// World → Characters, Locations, Props.
		if ( $world_id ) {
			$world_was_skipped = $this->entity_was_skipped( (string) $this->document['world']['id'] );
			foreach ( $this->document['characters'] as $character ) {
				$char_id = $this->id_map[ $character['id'] ] ?? 0;
				if ( $char_id ) {
					if ( ! $world_was_skipped ) {
						\WorldGraph\Utils\add_relationship( $world_id, 'worldgraph_world', $char_id, 'worldgraph_character', 'contains' );
					}
					if ( ! $this->entity_was_skipped( (string) $character['id'] ) ) {
						\WorldGraph\Utils\worldgraph_update_field_value( $char_id, 'story_world', $world_id );
					}
				}
			}

			foreach ( $this->document['locations'] as $location ) {
				$loc_id = $this->id_map[ $location['id'] ] ?? 0;
				if ( $loc_id ) {
					if ( ! $world_was_skipped ) {
						\WorldGraph\Utils\add_relationship( $world_id, 'worldgraph_world', $loc_id, 'worldgraph_location', 'contains' );
					}
					if ( ! $this->entity_was_skipped( (string) $location['id'] ) ) {
						\WorldGraph\Utils\worldgraph_update_field_value( $loc_id, 'story_world', $world_id );
					}
				}
			}

			foreach ( $this->document['props'] as $prop ) {
				$prop_id = $this->id_map[ $prop['id'] ] ?? 0;
				if ( $prop_id && ! $world_was_skipped ) {
					\WorldGraph\Utils\add_relationship( $world_id, 'worldgraph_world', $prop_id, 'worldgraph_prop', 'contains' );
				}
			}

			foreach ( $this->document['organizations'] as $organization ) {
				$organization_id = $this->id_map[ $organization['id'] ] ?? 0;
				if ( $organization_id ) {
					if ( ! $world_was_skipped ) {
						\WorldGraph\Utils\add_relationship( $world_id, 'worldgraph_world', $organization_id, 'worldgraph_org', 'contains' );
					}
					if ( ! $this->entity_was_skipped( (string) $organization['id'] ) ) {
						\WorldGraph\Utils\worldgraph_update_field_value( $organization_id, 'story_world', $world_id );
					}
				}
			}
		}

		// Project membership used by project views, exports, and API counts.
		$project_was_skipped = $this->entity_was_skipped( (string) $this->document['project']['id'] );
		foreach ( $this->document['episodes'] as $episode ) {
			$episode_id = $this->id_map[ $episode['id'] ] ?? 0;
			if ( $project_id && $episode_id ) {
				if ( ! $project_was_skipped ) {
					\WorldGraph\Utils\add_relationship( $project_id, 'worldgraph_project', $episode_id, 'worldgraph_episode', 'contains' );
				}
				if ( ! $this->entity_was_skipped( (string) $episode['id'] ) ) {
					\WorldGraph\Utils\worldgraph_update_field_value( $episode_id, 'project', $project_id );
				}
			}
		}
		foreach ( $this->document['assets'] as $asset ) {
			$asset_id = $this->id_map[ $asset['id'] ] ?? 0;
			if ( $project_id && $asset_id && ! $project_was_skipped ) {
				\WorldGraph\Utils\add_relationship( $project_id, 'worldgraph_project', $asset_id, 'worldgraph_asset', 'contains' );
			}
		}

		// Prop → Owner Character.
		foreach ( $this->document['props'] as $prop ) {
			$prop_id = $this->id_map[ $prop['id'] ] ?? 0;
			$char_id = $this->id_map[ $prop['owner_character'] ?? '' ] ?? 0;
			if ( $prop_id && $char_id && ! $this->entity_was_skipped( (string) $prop['id'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $prop_id, 'owner_character', $char_id );
			}
		}

		foreach ( $this->document['characters'] as $character ) {
			if ( empty( $character['avatar_asset'] ) || $this->entity_was_skipped( (string) $character['id'] ) ) {
				continue;
			}
			$character_id = $this->id_map[ $character['id'] ] ?? 0;
			$asset_id     = $this->resolve_external_id( 'worldgraph_asset', (string) $character['avatar_asset'] );
			if ( $character_id && $asset_id ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $character_id, 'avatar_asset', $asset_id );
			}
		}

		foreach ( $this->document['locations'] as $location ) {
			if ( empty( $location['visual_reference'] ) || $this->entity_was_skipped( (string) $location['id'] ) ) {
				continue;
			}
			$location_id = $this->id_map[ $location['id'] ] ?? 0;
			$asset_id    = $this->resolve_external_id( 'worldgraph_asset', (string) $location['visual_reference'] );
			if ( $location_id && $asset_id ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $location_id, 'visual_reference', $asset_id );
			}
		}

		foreach ( $this->document['organizations'] as $organization ) {
			if ( $this->entity_was_skipped( (string) $organization['id'] ) ) {
				continue;
			}
			$organization_id = $this->id_map[ $organization['id'] ] ?? 0;
			if ( ! $organization_id ) {
				continue;
			}

			if ( ! empty( $organization['leadership'] ) ) {
				$leader_id = $this->id_map[ $organization['leadership'] ] ?? 0;
				if ( $leader_id ) {
					\WorldGraph\Utils\worldgraph_update_field_value( $organization_id, 'leadership', $leader_id );
				}
			}

			if ( array_key_exists( 'members', $organization ) ) {
				$member_ids = [];
				foreach ( $organization['members'] as $member_external_id ) {
					$member_id = $this->id_map[ (string) $member_external_id ] ?? 0;
					if ( $member_id ) {
						$member_ids[] = $member_id;
					}
				}
				$result = \WorldGraph\Utils\set_relationships_for_field( $organization_id, 'worldgraph_org', $member_ids, 'worldgraph_character', 'contains', 'members', true );
				if ( is_wp_error( $result ) ) {
					$this->report['errors'][] = sprintf( 'Organization %s Members: %s', $organization['id'], $result->get_error_message() );
				}
			}
		}

		foreach ( $this->document['episodes'] as $episode ) {
			if ( ! array_key_exists( 'scenes', $episode ) || $this->entity_was_skipped( (string) $episode['id'] ) ) {
				continue;
			}
			$episode_id = $this->id_map[ $episode['id'] ] ?? 0;
			if ( ! $episode_id ) {
				continue;
			}
			$scene_ids = [];
			foreach ( $episode['scenes'] as $scene_external_id ) {
				$scene_id = $this->id_map[ (string) $scene_external_id ] ?? 0;
				if ( $scene_id ) {
					$scene_ids[] = $scene_id;
				}
			}
			$result = \WorldGraph\Utils\set_relationships_for_field( $episode_id, 'worldgraph_episode', $scene_ids, 'worldgraph_scene', 'contains', 'scenes', true );
			if ( is_wp_error( $result ) ) {
				$this->report['errors'][] = sprintf( 'Episode %s Scenes: %s', $episode['id'], $result->get_error_message() );
			}
		}

		// Scene relationships.
		foreach ( $this->document['scenes'] as $scene ) {
			if ( $this->entity_was_skipped( (string) $scene['id'] ) ) {
				continue;
			}
			$scene_id = $this->id_map[ $scene['id'] ] ?? 0;
			if ( ! $scene_id ) {
				continue;
			}

			if ( $project_id ) {
				$result = \WorldGraph\Utils\set_relationships_for_field( $scene_id, 'worldgraph_scene', [ $project_id ], 'worldgraph_project', 'belongs_to', 'project', false );
				if ( is_wp_error( $result ) ) {
					$this->report['errors'][] = sprintf( 'Scene %s Project: %s', $scene['id'], $result->get_error_message() );
				}
			}

			if ( ! empty( $scene['episode'] ) || ( $this->overwrite && array_key_exists( 'episode', $scene ) ) ) {
				$episode_id = $this->id_map[ $scene['episode'] ?? '' ] ?? 0;
				\WorldGraph\Utils\worldgraph_update_field_value( $scene_id, 'episode', $episode_id );
			}

			// Scene → Location.
			if ( ! empty( $scene['location'] ) || ( $this->overwrite && array_key_exists( 'location', $scene ) ) ) {
				$loc_id = $this->id_map[ $scene['location'] ?? '' ] ?? 0;
				$result = \WorldGraph\Utils\set_relationships_for_field( $scene_id, 'worldgraph_scene', $loc_id ? [ $loc_id ] : [], 'worldgraph_location', 'located_in', 'location', false );
				if ( is_wp_error( $result ) ) {
					$this->report['errors'][] = sprintf( 'Scene %s Location: %s', $scene['id'], $result->get_error_message() );
				} else {
					\WorldGraph\Utils\worldgraph_update_field_value( $scene_id, 'location', $loc_id );
				}
			}

			// Scene → Characters.
			if ( array_key_exists( 'characters', $scene ) && is_array( $scene['characters'] ) ) {
				$character_ids = [];
				foreach ( $scene['characters'] as $char_external_id ) {
					$char_id = $this->id_map[ $char_external_id ] ?? 0;
					if ( $char_id ) {
						$character_ids[] = $char_id;
					}
				}
				$result = \WorldGraph\Utils\set_relationships_for_field( $scene_id, 'worldgraph_scene', $character_ids, 'worldgraph_character', 'appears_in', 'characters', true );
				if ( is_wp_error( $result ) ) {
					$this->report['errors'][] = sprintf( 'Scene %s Characters: %s', $scene['id'], $result->get_error_message() );
				}
			}

			// Scene → Props.
			if ( array_key_exists( 'props', $scene ) && is_array( $scene['props'] ) ) {
				$prop_ids = [];
				foreach ( $scene['props'] as $prop_external_id ) {
					$prop_id = $this->id_map[ $prop_external_id ] ?? 0;
					if ( $prop_id ) {
						$prop_ids[] = $prop_id;
					}
				}
				$result = \WorldGraph\Utils\set_relationships_for_field( $scene_id, 'worldgraph_scene', $prop_ids, 'worldgraph_prop', 'used_in', 'props', true );
				if ( is_wp_error( $result ) ) {
					$this->report['errors'][] = sprintf( 'Scene %s Props: %s', $scene['id'], $result->get_error_message() );
				}
			}
		}

		// Shot → Scene. The required scalar field owns this edge; graph traversal
		// can discover the Shot from the Scene through the incoming relationship.
		foreach ( $this->document['shots'] as $shot ) {
			if ( $this->entity_was_skipped( (string) $shot['id'] ) ) {
				continue;
			}
			$shot_id = $this->id_map[ $shot['id'] ] ?? 0;
			if ( ! $shot_id ) {
				continue;
			}

			$scene_id = $this->id_map[ $shot['scene'] ] ?? 0;
			if ( $scene_id ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $shot_id, 'scene', $scene_id );

				// Clean up the inverse edge written by importer versions that stored
				// Scene → Shot only after the required Shot.scene edge is verified.
				if (
					! $this->entity_was_skipped( (string) $shot['scene'] ) &&
					$this->relationship_slot_matches( $shot_id, 'worldgraph_shot', 'scene', $scene_id, 'worldgraph_scene' )
				) {
					\WorldGraph\Utils\remove_relationship( $scene_id, $shot_id, 'worldgraph_scene', 'worldgraph_shot' );
				}
			}
		}

		// Sound cues keep their own placement edges. Ordinary dialogue remains
		// structured Scene metadata and is intentionally not converted to Sounds.
		foreach ( $this->document['sounds'] as $sound ) {
			if ( $this->entity_was_skipped( (string) $sound['id'] ) ) {
				continue;
			}

			$sound_id = $this->id_map[ $sound['id'] ] ?? 0;
			if ( ! $sound_id ) {
				continue;
			}

			$scene_id = $this->id_map[ $sound['scene'] ?? '' ] ?? 0;
			\WorldGraph\Utils\worldgraph_update_field_value( $sound_id, 'scene', $scene_id );

			$shot_id = $this->id_map[ $sound['shot'] ?? '' ] ?? 0;
			\WorldGraph\Utils\worldgraph_update_field_value( $sound_id, 'shot', $shot_id );

			$character_id = $this->id_map[ $sound['character'] ?? '' ] ?? 0;
			\WorldGraph\Utils\worldgraph_update_field_value( $sound_id, 'character', $character_id );

			$asset_id = 0;
			if ( ! empty( $sound['asset'] ) ) {
				$asset_id = $this->find_existing( 'worldgraph_asset', sanitize_text_field( (string) $sound['asset'] ) );
			}
			\WorldGraph\Utils\worldgraph_update_field_value( $sound_id, 'asset', $asset_id );
		}

		// Storyboard Frame → Shot.
		foreach ( $this->document['storyboards'] as $frame ) {
			if ( $this->entity_was_skipped( (string) $frame['id'] ) ) {
				continue;
			}
			$frame_id = $this->id_map[ $frame['id'] ] ?? 0;
			if ( ! $frame_id ) {
				continue;
			}

			$shot_id = $this->id_map[ $frame['shot'] ] ?? 0;
			if ( $shot_id ) {
				if ( ! $this->entity_was_skipped( (string) $frame['shot'] ) ) {
					\WorldGraph\Utils\add_relationship( $shot_id, 'worldgraph_shot', $frame_id, 'worldgraph_board', 'contains' );
				}
				\WorldGraph\Utils\worldgraph_update_field_value( $frame_id, 'shot', $shot_id );
			}

			$scene_external_id = $frame['scene'] ?? '';
			if ( ! $scene_external_id && $shot_id ) {
				foreach ( $this->document['shots'] as $shot ) {
					if ( (string) $shot['id'] === (string) $frame['shot'] ) {
						$scene_external_id = $shot['scene'];
						break;
					}
				}
			}
			$scene_id = $this->id_map[ $scene_external_id ] ?? 0;
			if ( $scene_id ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $frame_id, 'scene', $scene_id );
			}

			if ( ! empty( $frame['image_asset'] ) ) {
				$image_asset_id = $this->resolve_external_id( 'worldgraph_asset', (string) $frame['image_asset'] );
				if ( $image_asset_id ) {
					\WorldGraph\Utils\worldgraph_update_field_value( $frame_id, 'image_asset', $image_asset_id );
				}
			}
		}

		foreach ( $this->document['assets'] as $asset ) {
			if ( $this->entity_was_skipped( (string) $asset['id'] ) ) {
				continue;
			}
			$asset_id = $this->id_map[ $asset['id'] ] ?? 0;
			if ( ! $asset_id ) {
				continue;
			}
			foreach ( [ 'character' => 'worldgraph_character', 'location' => 'worldgraph_location', 'scene' => 'worldgraph_scene', 'storyboard' => 'worldgraph_board' ] as $field => $cpt ) {
				if ( ! empty( $asset[ $field ] ) ) {
					$target_id = $this->id_map[ $asset[ $field ] ] ?? 0;
					if ( $target_id ) {
						\WorldGraph\Utils\worldgraph_update_field_value( $asset_id, $field, $target_id );
					}
				}
			}
		}

		foreach ( $this->document['editorial_artifacts'] as $artifact ) {
			if ( $this->entity_was_skipped( (string) $artifact['id'] ) ) {
				continue;
			}
			$artifact_id = $this->id_map[ $artifact['id'] ] ?? 0;
			if ( ! $artifact_id ) {
				continue;
			}

			if ( ! empty( $artifact['source_scene'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $artifact_id, 'source_scene', $this->id_map[ $artifact['source_scene'] ] ?? 0 );
			}
			if ( ! empty( $artifact['source_shot'] ) ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $artifact_id, 'source_shot', $this->id_map[ $artifact['source_shot'] ] ?? 0 );
			}
			if ( $project_id ) {
				\WorldGraph\Utils\worldgraph_update_field_value( $artifact_id, 'project', $project_id );
			}
		}
	}

	/**
	 * Verify the import against expected totals.
	 */
	private function verify_import(): void {
		$entity_sets = [
			'worldgraph_project'          => [ (string) $this->document['project']['id'] ],
			'worldgraph_world'      => [ (string) $this->document['world']['id'] ],
			'worldgraph_character'        => array_map( 'strval', array_column( $this->document['characters'], 'id' ) ),
			'worldgraph_location'         => array_map( 'strval', array_column( $this->document['locations'], 'id' ) ),
			'worldgraph_prop'             => array_map( 'strval', array_column( $this->document['props'], 'id' ) ),
			'worldgraph_org'              => array_map( 'strval', array_column( $this->document['organizations'], 'id' ) ),
			'worldgraph_episode'          => array_map( 'strval', array_column( $this->document['episodes'], 'id' ) ),
			'worldgraph_scene'            => array_map( 'strval', array_column( $this->document['scenes'], 'id' ) ),
			'worldgraph_shot'             => array_map( 'strval', array_column( $this->document['shots'], 'id' ) ),
			'worldgraph_sound'            => array_map( 'strval', array_column( $this->document['sounds'], 'id' ) ),
			'worldgraph_board'      => array_map( 'strval', array_column( $this->document['storyboards'], 'id' ) ),
			'worldgraph_asset'            => array_map( 'strval', array_column( $this->document['assets'], 'id' ) ),
			'worldgraph_editorial'        => array_map( 'strval', array_column( $this->document['editorial_artifacts'], 'id' ) ),
		];

		$totals   = [];
		$expected = [];
		foreach ( $entity_sets as $cpt => $external_ids ) {
			$expected[ $cpt ] = count( $external_ids );
			$totals[ $cpt ]   = 0;
			foreach ( $external_ids as $external_id ) {
				$post_id = (int) ( $this->id_map[ $external_id ] ?? 0 );
				if ( $post_id && $cpt === get_post_type( $post_id ) ) {
					$totals[ $cpt ]++;
				}
			}

			if ( $totals[ $cpt ] !== $expected[ $cpt ] ) {
				$this->report['errors'][] = sprintf( 'Verification failed for %s: expected %d, resolved %d.', $cpt, $expected[ $cpt ], $totals[ $cpt ] );
			}
		}

		$sequence_term_id            = (int) ( $this->report['sequence']['term_id'] ?? 0 );
		$sequence_term               = $sequence_term_id ? get_term( $sequence_term_id, 'worldgraph_sequence' ) : null;
		$expected['worldgraph_sequence'] = 1;
		$totals['worldgraph_sequence']   = ( $sequence_term && ! is_wp_error( $sequence_term ) ) ? 1 : 0;
		if ( 1 !== $totals['worldgraph_sequence'] ) {
			$this->report['errors'][] = 'Verification failed for worldgraph_sequence: expected 1, resolved 0.';
		}

		$project_id = (int) ( $this->id_map[ (string) $this->document['project']['id'] ] ?? 0 );
		$world_id   = (int) ( $this->id_map[ (string) $this->document['world']['id'] ] ?? 0 );
		if ( ! $this->relationship_slot_matches( $world_id, 'worldgraph_world', 'project', $project_id, 'worldgraph_project' ) ) {
			$this->report['errors'][] = 'Story World did not retain its Project relationship.';
		}

		foreach ( $this->document['characters'] as $character ) {
			$character_id = (int) ( $this->id_map[ (string) $character['id'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $character_id, 'worldgraph_character', 'story_world', $world_id, 'worldgraph_world' ) ) {
				$this->report['errors'][] = sprintf( 'Character %s did not retain its Story World relationship.', $character['id'] );
			}
		}

		foreach ( $this->document['locations'] as $location ) {
			$location_id = (int) ( $this->id_map[ (string) $location['id'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $location_id, 'worldgraph_location', 'story_world', $world_id, 'worldgraph_world' ) ) {
				$this->report['errors'][] = sprintf( 'Location %s did not retain its Story World relationship.', $location['id'] );
			}
		}

		foreach ( $this->document['props'] as $prop ) {
			if ( empty( $prop['owner_character'] ) ) {
				continue;
			}

			$prop_id      = (int) ( $this->id_map[ (string) $prop['id'] ] ?? 0 );
			$character_id = (int) ( $this->id_map[ (string) $prop['owner_character'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $prop_id, 'worldgraph_prop', 'owner_character', $character_id, 'worldgraph_character' ) ) {
				$this->report['errors'][] = sprintf( 'Prop %s did not retain its owner Character relationship.', $prop['id'] );
			}
		}

		foreach ( $this->document['scenes'] as $scene ) {
			if ( empty( $scene['location'] ) ) {
				continue;
			}

			$scene_id    = (int) ( $this->id_map[ (string) $scene['id'] ] ?? 0 );
			$location_id = (int) ( $this->id_map[ (string) $scene['location'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $scene_id, 'worldgraph_scene', 'location', $location_id, 'worldgraph_location' ) ) {
				$this->report['errors'][] = sprintf( 'Scene %s did not retain its Location relationship.', $scene['id'] );
			}
		}

		foreach ( $this->document['shots'] as $shot ) {
			$shot_id  = (int) ( $this->id_map[ (string) $shot['id'] ] ?? 0 );
			$scene_id = (int) ( $this->id_map[ (string) $shot['scene'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $shot_id, 'worldgraph_shot', 'scene', $scene_id, 'worldgraph_scene' ) ) {
				$this->report['errors'][] = sprintf( 'Shot %s did not retain its required Scene relationship.', $shot['id'] );
			}
		}

		foreach ( $this->document['sounds'] as $sound ) {
			$sound_id = (int) ( $this->id_map[ (string) $sound['id'] ] ?? 0 );
			$scene_id = (int) ( $this->id_map[ (string) $sound['scene'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $sound_id, 'worldgraph_sound', 'scene', $scene_id, 'worldgraph_scene' ) ) {
				$this->report['errors'][] = sprintf( 'Sound %s did not retain its required Scene relationship.', $sound['id'] );
			}

			if ( ! empty( $sound['shot'] ) ) {
				$shot_id = (int) ( $this->id_map[ (string) $sound['shot'] ] ?? 0 );
				if ( ! $this->relationship_slot_matches( $sound_id, 'worldgraph_sound', 'shot', $shot_id, 'worldgraph_shot' ) ) {
					$this->report['errors'][] = sprintf( 'Sound %s did not retain its Shot relationship.', $sound['id'] );
				}
			}
		}

		foreach ( $this->document['scenes'] as $scene ) {
			$scene_id          = (int) ( $this->id_map[ (string) $scene['id'] ] ?? 0 );
			$stored_dialogue   = \WorldGraph\Utils\worldgraph_get_field_value( $scene_id, 'dialogue' );
			$expected_dialogue = count( (array) ( $scene['dialogue'] ?? [] ) );
			if ( $expected_dialogue !== count( is_array( $stored_dialogue ) ? $stored_dialogue : [] ) ) {
				$this->report['errors'][] = sprintf( 'Scene %s dialogue verification failed.', $scene['id'] );
			}
		}

		foreach ( $this->document['storyboards'] as $frame ) {
			$frame_id = (int) ( $this->id_map[ (string) $frame['id'] ] ?? 0 );
			$shot_id  = (int) ( $this->id_map[ (string) $frame['shot'] ] ?? 0 );
			if ( ! $this->relationship_slot_matches( $frame_id, 'worldgraph_board', 'shot', $shot_id, 'worldgraph_shot' ) ) {
				$this->report['errors'][] = sprintf( 'Storyboard Frame %s did not retain its Shot relationship.', $frame['id'] );
			}
		}

		$this->report['totals']          = $totals;
		$this->report['expected_totals'] = $expected;
		$this->report['verified']        = empty( $this->report['errors'] );
	}

	/**
	 * Check one scalar relationship slot during import verification.
	 *
	 * @param int    $from_id   Source post ID.
	 * @param string $from_type Expected source CPT.
	 * @param string $field     Relationship field slot.
	 * @param int    $target_id Expected target post ID.
	 * @param string $to_type   Expected target CPT.
	 * @return bool
	 */
	private function relationship_slot_matches(
		int $from_id,
		string $from_type,
		string $field,
		int $target_id,
		string $to_type
	): bool {
		if ( ! $from_id || ! $target_id ) {
			return false;
		}

		foreach ( \WorldGraph\Utils\get_relationships( $from_id, $from_type, 'outgoing' ) as $relationship ) {
			if (
				$target_id === (int) ( $relationship['to_id'] ?? 0 ) &&
				$to_type === (string) ( $relationship['to_type'] ?? '' ) &&
				$field === (string) ( $relationship['metadata']['field'] ?? '' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persist portable scalar values through the canonical SCF field layer.
	 *
	 * @param int                  $post_id Post ID.
	 * @param string               $cpt     CPT slug.
	 * @param array                $record  Source JSON record.
	 * @param array<string,string> $mapping JSON key to SCF field mapping.
	 */
	private function update_scalar_fields( int $post_id, string $cpt, array $record, array $mapping ): void {
		$fields = \WorldGraph\Utils\worldgraph_get_fields( $cpt );
		foreach ( $mapping as $json_field => $meta_field ) {
			if ( ! array_key_exists( $json_field, $record ) || ! isset( $fields[ $meta_field ] ) ) {
				continue;
			}

			$value = $record[ $json_field ];
			if ( 'generation_parameters' === $meta_field && is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES );
			}
			$raw_value = $value;

			if ( null === $value || '' === $value ) {
				\WorldGraph\Utils\worldgraph_delete_field_value( $post_id, $meta_field );
				continue;
			}

			$value = \WorldGraph\Utils\worldgraph_sanitize_field_value( $value, $fields[ $meta_field ] );
			if ( '' === $value && '0' !== (string) $raw_value ) {
				\WorldGraph\Utils\worldgraph_delete_field_value( $post_id, $meta_field );
				continue;
			}

			\WorldGraph\Utils\worldgraph_update_field_value( $post_id, $meta_field, $value );
		}
	}

	/**
	 * Assign one or more taxonomy terms expressed as IDs, slugs, or names.
	 *
	 * @param int          $post_id      Post ID.
	 * @param string       $taxonomy     Taxonomy slug.
	 * @param mixed        $raw_terms    One term or a list of terms.
	 * @param bool         $create_terms Whether missing terms may be created.
	 * @param bool         $single       Whether only one term is accepted.
	 * @param string       $context      Report context.
	 */
	private function assign_taxonomy_terms( int $post_id, string $taxonomy, $raw_terms, bool $create_terms, bool $single, string $context ): void {
		$raw_terms = is_array( $raw_terms ) ? $raw_terms : [ $raw_terms ];
		$term_ids  = [];
		foreach ( $raw_terms as $raw_term ) {
			if ( '' === (string) $raw_term ) {
				continue;
			}

			$term = is_numeric( $raw_term )
				? get_term( absint( $raw_term ), $taxonomy )
				: get_term_by( 'slug', sanitize_title( (string) $raw_term ), $taxonomy );
			if ( ( ! $term || is_wp_error( $term ) ) && $create_terms ) {
				$slug = sanitize_title( (string) $raw_term );
				$term = wp_insert_term(
					ucwords( str_replace( [ '-', '_' ], ' ', $slug ) ),
					$taxonomy,
					[ 'slug' => $slug ]
				);
			}

			if ( is_wp_error( $term ) ) {
				$this->report['errors'][] = sprintf( '%s: %s', $context, $term->get_error_message() );
				continue;
			}
			if ( ! $term ) {
				$this->report['errors'][] = sprintf( '%s: taxonomy term "%s" was not found.', $context, sanitize_text_field( (string) $raw_term ) );
				continue;
			}

			$term_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term->term_id;
			if ( $single ) {
				break;
			}
		}

		wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), $taxonomy, false );
	}

	/**
	 * Check whether overwrite protection skipped an existing entity.
	 *
	 * @param string $external_id Portable external ID.
	 * @return bool
	 */
	private function entity_was_skipped( string $external_id ): bool {
		return isset( $this->skipped_entities[ $external_id ] );
	}

	/**
	 * Resolve an imported or pre-existing entity by portable external ID.
	 *
	 * @param string $cpt         Expected CPT.
	 * @param string $external_id Portable external ID.
	 * @return int Post ID or zero.
	 */
	private function resolve_external_id( string $cpt, string $external_id ): int {
		$post_id = (int) ( $this->id_map[ $external_id ] ?? 0 );
		if ( $post_id && $cpt === get_post_type( $post_id ) ) {
			return $post_id;
		}

		return $this->find_existing( $cpt, sanitize_text_field( $external_id ) );
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
