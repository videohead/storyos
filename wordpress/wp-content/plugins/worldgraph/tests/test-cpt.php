<?php
/**
 * Tests for World Graph Studio CPT registration.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_CPT
 */
class Test_WorldGraph_CPT extends TestCase {

	/**
	 * Test register_cpt function signature.
	 */
	public function test_register_cpt_parameters() {
		// Mock the parameters that would be passed to register_cpt
		$post_type = 'worldgraph_project';
		$args = [
			'label'        => 'Projects',
			'public'       => true,
			'show_ui'      => true,
			'supports'     => [ 'title', 'editor' ],
			'rewrite'      => [ 'slug' => 'project' ],
			'capabilities' => [
				'edit_post'          => 'edit_project',
				'read_post'          => 'read_project',
				'delete_post'        => 'delete_project',
				'edit_posts'         => 'edit_projects',
				'edit_others_posts'  => 'edit_others_projects',
				'publish_posts'      => 'publish_projects',
				'read_private_posts' => 'read_private_projects',
			],
		];
		
		$this->assertIsString( $post_type );
		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'label', $args );
		$this->assertArrayHasKey( 'public', $args );
		$this->assertArrayHasKey( 'supports', $args );
	}

	/**
	 * Test CPT post type structure.
	 */
	public function test_cpt_post_type_structure() {
		// Test that a CPT definition has the required structure
		$cpt_definition = [
			'post_type' => 'worldgraph_character',
			'label'     => 'Characters',
			'public'    => true,
		];
		
		$this->assertArrayHasKey( 'post_type', $cpt_definition );
		$this->assertArrayHasKey( 'label', $cpt_definition );
		$this->assertArrayHasKey( 'public', $cpt_definition );
		$this->assertEquals( 'worldgraph_character', $cpt_definition['post_type'] );
	}

	/**
	 * Test CPT capabilities structure.
	 */
	public function test_cpt_capabilities_structure() {
		$capabilities = [
			'edit_post'          => 'edit_project',
			'read_post'          => 'read_project',
			'delete_post'        => 'delete_project',
			'edit_posts'         => 'edit_projects',
			'edit_others_posts'  => 'edit_others_projects',
			'publish_posts'      => 'publish_projects',
			'read_private_posts' => 'read_private_projects',
		];
		
		$this->assertArrayHasKey( 'edit_post', $capabilities );
		$this->assertArrayHasKey( 'read_post', $capabilities );
		$this->assertArrayHasKey( 'delete_post', $capabilities );
		$this->assertArrayHasKey( 'edit_posts', $capabilities );
	}

	/**
	 * Test World Graph Studio CPTs are mounted under the World Graph Studio menu.
	 */
	public function test_worldgraph_cpt_defaults_use_worldgraph_menu() {
		$args = \WorldGraph\Utils\worldgraph_get_default_cpt_args( 'worldgraph_project', 'Project' );

		$this->assertSame( 'worldgraph', $args['show_in_menu'] );
		$this->assertTrue( $args['show_ui'] );
	}

	/**
	 * SCF is the only custom-field editing surface for schema-backed CPTs.
	 */
	public function test_worldgraph_cpt_defaults_do_not_enable_native_custom_fields() {
		$args = \WorldGraph\Utils\worldgraph_get_default_cpt_args(
			'worldgraph_project',
			'Project',
			[ 'supports' => [ 'title', 'editor', 'custom-fields' ] ]
		);

		$this->assertNotContains( 'custom-fields', $args['supports'] );
		$this->assertSame( [ 'title', 'editor' ], $args['supports'] );
	}

	/**
	 * Content CPT files must leave canonical field rendering and persistence to SCF.
	 */
	public function test_content_cpts_have_no_legacy_named_field_save_paths() {
		$files = glob( dirname( __DIR__ ) . '/includes/cpts/*.php' ) ?: [];

		foreach ( $files as $path ) {
			$file = basename( $path );
			if ( 'class-generation-job.php' === $file ) {
				continue;
			}
			$source = file_get_contents( $path );

			$this->assertNotFalse( $source, "Could not read CPT source {$file}." );
			$this->assertStringNotContainsString( 'function save_meta(', $source, "Legacy save_meta() remains in {$file}." );
			$this->assertStringNotContainsString( 'function save_project_meta(', $source, "Legacy Project save handler remains in {$file}." );
			$this->assertStringNotContainsString( '$_POST[ $key ]', $source, "Named-field POST persistence remains in {$file}." );
			$this->assertStringNotContainsString( "add_action( 'save_post_worldgraph_", $source, "Legacy save_post hook remains in {$file}." );
		}
	}

	/**
	 * WordPress must recognize SCF as a plugin dependency on every activation path.
	 */
	public function test_scf_dependency_and_activation_guards() {
		$source = file_get_contents( dirname( __DIR__ ) . '/worldgraph.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'Requires Plugins: secure-custom-fields', $source );
		$this->assertStringNotContainsString( 'Requires Plugins: secure-custom-fields/secure-custom-fields.php', $source );
		$this->assertStringContainsString( "if ( ! scf_is_active() ) {\n\t\tworldgraph_missing_scf_dependency();", $source );
		$this->assertStringContainsString( "is_plugin_active_for_network( \$plugin )", $source );
	}

	/**
	 * World Graph Studio CPT keys must fit WordPress's 20-character database limit.
	 */
	public function test_worldgraph_cpt_keys_fit_wordpress_limit() {
		$cpts = \WorldGraph\Utils\worldgraph_get_all_cpts();

		$this->assertArrayHasKey( 'worldgraph_board', $cpts );
		$this->assertArrayHasKey( 'worldgraph_editorial', $cpts );
		$this->assertArrayNotHasKey( 'worldgraph_board_frame', $cpts );
		$this->assertArrayNotHasKey( 'worldgraph_editorial_artifact', $cpts );

		foreach ( array_keys( $cpts ) as $cpt ) {
			$this->assertLessThanOrEqual( 20, strlen( $cpt ), "CPT key {$cpt} exceeds WordPress's 20-character limit." );
		}
	}

	/**
	 * Legacy and database-truncated CPT keys map to the current keys.
	 */
	public function test_legacy_cpt_key_migration_map() {
		$legacy_keys = \WorldGraph\Utils\worldgraph_legacy_cpt_key_map();

		$this->assertSame( 'worldgraph_project', $legacy_keys['storyos_project'] );
		$this->assertSame( 'worldgraph_world', $legacy_keys['storyos_story_world'] );
		$this->assertSame( 'worldgraph_character', $legacy_keys['storyos_character'] );
		$this->assertSame( 'worldgraph_location', $legacy_keys['storyos_location'] );
		$this->assertSame( 'worldgraph_prop', $legacy_keys['storyos_prop'] );
		$this->assertSame( 'worldgraph_org', $legacy_keys['storyos_organization'] );
		$this->assertSame( 'worldgraph_episode', $legacy_keys['storyos_episode'] );
		$this->assertSame( 'worldgraph_scene', $legacy_keys['storyos_scene'] );
		$this->assertSame( 'worldgraph_shot', $legacy_keys['storyos_shot'] );
		$this->assertSame( 'worldgraph_sound', $legacy_keys['storyos_sound'] );
		$this->assertSame( 'worldgraph_board', $legacy_keys['storyos_storyboard'] );
		$this->assertSame( 'worldgraph_board', $legacy_keys['storyos_storyboard_frame'] );
		$this->assertSame( 'worldgraph_board', $legacy_keys['storyos_storyboard_f'] );
		$this->assertSame( 'worldgraph_asset', $legacy_keys['storyos_asset'] );
		$this->assertSame( 'worldgraph_editorial', $legacy_keys['storyos_editorial'] );
		$this->assertSame( 'worldgraph_editorial', $legacy_keys['storyos_editorial_artifact'] );
		$this->assertSame( 'worldgraph_editorial', $legacy_keys['storyos_editorial_ar'] );
		$this->assertSame( 'worldgraph_template', $legacy_keys['storyos_template'] );
		$this->assertSame( 'worldgraph_conn', $legacy_keys['storyos_connection'] );
		$this->assertSame( 'worldgraph_gen', $legacy_keys['storyos_generation'] );

		// Interrupted builds of the rename are safe to retry too.
		$this->assertSame( 'worldgraph_board', $legacy_keys['worldgraph_board_frame'] );
		$this->assertSame( 'worldgraph_board', $legacy_keys['worldgraph_board_f'] );
		$this->assertSame( 'worldgraph_editorial', $legacy_keys['worldgraph_editorial_artifact'] );
		$this->assertSame( 'worldgraph_editorial', $legacy_keys['worldgraph_editorial_ar'] );

		foreach ( array_unique( $legacy_keys ) as $canonical_key ) {
			$this->assertLessThanOrEqual( 20, strlen( $canonical_key ), "Migrated CPT key {$canonical_key} exceeds WordPress's limit." );
		}
	}

	/**
	 * All persisted taxonomy names receive a canonical namespace.
	 */
	public function test_legacy_taxonomy_key_migration_map() {
		$this->assertSame(
			[
				'storyos_asset_type'         => 'worldgraph_asset_type',
				'storyos_character_relation' => 'worldgraph_character_relation',
				'storyos_character_role'     => 'worldgraph_character_role',
				'storyos_genre'              => 'worldgraph_genre',
				'storyos_status'             => 'worldgraph_status',
				'storyos_scene_tag'          => 'worldgraph_scene_tag',
				'storyos_sequence'           => 'worldgraph_sequence',
				'storyos_sound_type'         => 'worldgraph_sound_type',
				'storyos_template_category'  => 'worldgraph_template_category',
			],
			\WorldGraph\Utils\worldgraph_legacy_taxonomy_key_map()
		);
	}

	/**
	 * SCF keys follow abbreviated canonical CPT identities exactly.
	 */
	public function test_namespace_migration_maps_scf_and_embedded_machine_keys() {
		$this->assertSame(
			'group_worldgraph_world',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'group_storyos_story_world' )
		);
		$this->assertSame(
			'field_worldgraph_org_story_world',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'field_storyos_organization_story_world' )
		);
		$this->assertSame(
			'field_worldgraph_board_image_asset',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'field_storyos_storyboard_image_asset' )
		);
		$this->assertSame(
			'field_worldgraph_editorial_artifact_type',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'field_storyos_editorial_artifact_type' )
		);
		$this->assertSame(
			'field_worldgraph_board_frame_number',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'field_storyos_storyboard_frame_number' )
		);
		$this->assertSame(
			'field_worldgraph_board_frame_description',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'field_storyos_storyboard_frame_description' )
		);
		$this->assertSame(
			'closedpostboxes_worldgraph_world',
			\WorldGraph\Utils\worldgraph_migrate_machine_name( 'closedpostboxes_storyos_story_world' )
		);
		$this->assertSame(
			'_worldgraph_relationship_field_scene',
			\WorldGraph\Utils\worldgraph_migrate_machine_name( '_storyos_relationship_field_scene' )
		);
		$this->assertSame(
			'worldgraph_process_generation_batch',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'storyos_process_generation_batch' )
		);
		$this->assertSame(
			'manage_worldgraph',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'manage_storyos' )
		);
		$this->assertSame(
			'worldgraph/worldgraph.php',
			\WorldGraph\Utils\worldgraph_migrate_machine_identifier( 'storyos/storyos.php' )
		);
		$this->assertSame( 'worldgraph_scene', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos_scene' ) );
		$this->assertSame( 'worldgraph/v1', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos/v1' ) );
		$this->assertSame( 'worldgraph/chat', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos/chat' ) );
		$this->assertSame( 'worldgraph/ai-editor-panel', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos/ai-editor-panel' ) );
		$this->assertSame( 'worldgraph/v1', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos-celtx/v1' ) );
		$this->assertSame( 'worldgraph-web-stories/v1', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos-web-stories/v1' ) );
		$this->assertSame( 'WorldGraphCeltx\\REST\\Sync', \WorldGraph\Utils\worldgraph_transform_stored_value( 'StoryOSCeltx\\REST\\Sync' ) );

		// A credential or endpoint is data, not a namespace token.
		$this->assertSame( 'storyos_private_token', \WorldGraph\Utils\worldgraph_transform_stored_value( 'storyos_private_token' ) );
		$this->assertSame( 'https://storyos.internal/v1', \WorldGraph\Utils\worldgraph_transform_stored_value( 'https://storyos.internal/v1' ) );
	}

	/** Legacy migration state cannot mark the new migration complete early. */
	public function test_namespace_migration_discards_legacy_state_options() {
		$this->assertTrue( \WorldGraph\Utils\worldgraph_is_legacy_lock_option( 'storyos_namespace_migration_version' ) );
		$this->assertTrue( \WorldGraph\Utils\worldgraph_is_legacy_lock_option( 'storyos_namespace_migration_lock' ) );
		$this->assertTrue( \WorldGraph\Utils\worldgraph_is_legacy_lock_option( 'storyos_scf_archive_hash' ) );
		$this->assertFalse( \WorldGraph\Utils\worldgraph_is_legacy_lock_option( 'storyos_fields' ) );
	}

	/**
	 * Serialized structures are decoded and re-serialized after exact mappings.
	 */
	public function test_namespace_migration_transforms_serialized_values_without_rewriting_prose() {
		$legacy = serialize(
			[
				'storyos_story_world' => [
					'from_type'  => 'storyos_story_world',
					'to_type'    => 'storyos_organization',
					'taxonomy'   => 'storyos_status',
					'field_key'  => 'field_storyos_story_world_project',
					'group_key'  => 'group_storyos_story_world',
					'artifact_key' => 'field_storyos_editorial_artifact_type',
					'frame_number_key' => 'field_storyos_storyboard_frame_number',
					'frame_description_key' => 'field_storyos_storyboard_frame_description',
					'description' => 'A StoryOS migration note belongs to the author.',
				],
			]
		);

		$migrated = unserialize( \WorldGraph\Utils\worldgraph_transform_stored_value( $legacy ) );
		$this->assertArrayHasKey( 'worldgraph_world', $migrated );
		$this->assertSame( 'worldgraph_world', $migrated['worldgraph_world']['from_type'] );
		$this->assertSame( 'worldgraph_org', $migrated['worldgraph_world']['to_type'] );
		$this->assertSame( 'worldgraph_status', $migrated['worldgraph_world']['taxonomy'] );
		$this->assertSame( 'field_worldgraph_world_project', $migrated['worldgraph_world']['field_key'] );
		$this->assertSame( 'group_worldgraph_world', $migrated['worldgraph_world']['group_key'] );
		$this->assertSame( 'field_worldgraph_editorial_artifact_type', $migrated['worldgraph_world']['artifact_key'] );
		$this->assertSame( 'field_worldgraph_board_frame_number', $migrated['worldgraph_world']['frame_number_key'] );
		$this->assertSame( 'field_worldgraph_board_frame_description', $migrated['worldgraph_world']['frame_description_key'] );
		$this->assertSame( 'A StoryOS migration note belongs to the author.', $migrated['worldgraph_world']['description'] );
	}

	/**
	 * JSON values receive the same exact identifier mapping as serialized data.
	 */
	public function test_namespace_migration_transforms_json_machine_identifiers() {
		$legacy   = '{"rest":"storyos/v1","block":"storyos/ai-editor-panel","note":"StoryOS prose"}';
		$migrated = json_decode( \WorldGraph\Utils\worldgraph_transform_stored_value( $legacy ), true );

		$this->assertSame( 'worldgraph/v1', $migrated['rest'] );
		$this->assertSame( 'worldgraph/ai-editor-panel', $migrated['block'] );
		$this->assertSame( 'StoryOS prose', $migrated['note'] );
	}

	/**
	 * Authored content changes only exact shortcode and Gutenberg block tokens.
	 */
	public function test_namespace_migration_transforms_persisted_content_tokens() {
		$legacy = 'Intro StoryOS [storyos_search mode="hybrid"] body '
			. '<!-- wp:storyos/ai-editor-panel {"mode":"chat"} /--> '
			. '[storyos_searching]keep[/storyos_searching] [/storyos_search]';
		$changed = false;
		$migrated = \WorldGraph\Utils\worldgraph_transform_post_content_identifiers( $legacy, $changed );

		$this->assertTrue( $changed );
		$this->assertStringContainsString( '[worldgraph_search mode="hybrid"]', $migrated );
		$this->assertStringContainsString( 'wp:worldgraph/ai-editor-panel', $migrated );
		$this->assertStringContainsString( '[/worldgraph_search]', $migrated );
		$this->assertStringContainsString( '[storyos_searching]keep[/storyos_searching]', $migrated );
		$this->assertStringContainsString( 'Intro StoryOS', $migrated );
	}

	/**
	 * Cron hook keys and signatures follow transformed argument identifiers.
	 */
	public function test_namespace_migration_transforms_cron_hooks_and_signatures() {
		$legacy_args      = [ 'post_type' => 'storyos_scene', 'rest' => 'storyos/v1' ];
		$legacy_signature = md5( serialize( $legacy_args ) );
		$cron             = [
			1234567890 => [
				'storyos_process_generation_batch' => [
					$legacy_signature => [ 'schedule' => false, 'args' => $legacy_args ],
				],
			],
			1234567891 => [
				'worldgraph_process_generation_batch' => [
					$legacy_signature => [ 'schedule' => false, 'args' => $legacy_args ],
				],
			],
		];
		$errors           = [];
		$changed          = false;
		$migrated         = \WorldGraph\Utils\worldgraph_transform_cron_array( $cron, $errors, $changed );
		$canonical_args   = [ 'post_type' => 'worldgraph_scene', 'rest' => 'worldgraph/v1' ];
		$signature        = md5( serialize( $canonical_args ) );

		$this->assertTrue( $changed );
		$this->assertSame( [], $errors );
		$this->assertArrayNotHasKey( 'storyos_process_generation_batch', $migrated[1234567890] );
		$this->assertSame( $canonical_args, $migrated[1234567890]['worldgraph_process_generation_batch'][ $signature ]['args'] );
		$this->assertSame( $canonical_args, $migrated[1234567891]['worldgraph_process_generation_batch'][ $signature ]['args'] );
	}

	/** Conflicting cron events retain the complete original timestamp bucket. */
	public function test_namespace_migration_does_not_overwrite_cron_collisions() {
		$args      = [ 'post_type' => 'storyos_scene' ];
		$signature = md5( serialize( [ 'post_type' => 'worldgraph_scene' ] ) );
		$cron      = [
			1234567890 => [
				'storyos_process_generation_batch' => [
					md5( serialize( $args ) ) => [ 'schedule' => false, 'args' => $args ],
				],
				'worldgraph_process_generation_batch' => [
					$signature => [ 'schedule' => 'hourly', 'args' => [ 'post_type' => 'worldgraph_scene' ] ],
				],
			],
		];
		$errors  = [];
		$changed = false;

		$migrated = \WorldGraph\Utils\worldgraph_transform_cron_array( $cron, $errors, $changed );

		$this->assertFalse( $changed );
		$this->assertNotEmpty( $errors );
		$this->assertSame( $cron, $migrated );
	}

	/**
	 * SCF-owned presentation strings may be rebranded alongside schema values.
	 */
	public function test_namespace_migration_rebrands_only_when_explicitly_requested() {
		$stored = serialize(
			[
				'title'    => 'StoryOS: Story World Fields',
				'location' => [ [ [ 'param' => 'post_type', 'value' => 'storyos_story_world' ] ] ],
			]
		);
		$migrated = unserialize( \WorldGraph\Utils\worldgraph_transform_stored_value( $stored, true ) );

		$this->assertSame( 'World Graph Studio: Story World Fields', $migrated['title'] );
		$this->assertSame( 'worldgraph_world', $migrated['location'][0][0]['value'] );
	}

	/**
	 * Differing canonical and legacy keys are both retained for manual review.
	 */
	public function test_namespace_migration_does_not_overwrite_tree_key_collisions() {
		$changed   = false;
		$collision = false;
		$result    = \WorldGraph\Utils\worldgraph_transform_identifier_tree(
			[
				'storyos_scene'   => [ 'source' => 'legacy' ],
				'worldgraph_scene' => [ 'source' => 'canonical' ],
			],
			false,
			$changed,
			$collision
		);

		$this->assertArrayHasKey( 'storyos_scene', $result );
		$this->assertArrayHasKey( 'worldgraph_scene', $result );
		$this->assertSame( 'legacy', $result['storyos_scene']['source'] );
		$this->assertSame( 'canonical', $result['worldgraph_scene']['source'] );
		$this->assertFalse( $changed );
		$this->assertTrue( $collision );
	}

	/** Identical canonicalized branches can be safely de-duplicated. */
	public function test_namespace_migration_deduplicates_equivalent_tree_keys() {
		$changed   = false;
		$collision = false;
		$result    = \WorldGraph\Utils\worldgraph_transform_identifier_tree(
			[
				'storyos_scene'   => 'storyos_scene',
				'worldgraph_scene' => 'worldgraph_scene',
			],
			false,
			$changed,
			$collision
		);

		$this->assertSame( [ 'worldgraph_scene' => 'worldgraph_scene' ], $result );
		$this->assertTrue( $changed );
		$this->assertFalse( $collision );
	}

	/**
	 * Test the generic Details meta box excludes redundant built-in name/description fields.
	 */
	public function test_worldgraph_details_filters_redundant_name_and_description_fields() {
		$this->assertTrue( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'description' ) );
		$this->assertTrue( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'project_name' ) );
		$this->assertTrue( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'shot_description' ) );
		$this->assertFalse( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'status' ) );
		$this->assertFalse( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'target_medium' ) );
		$this->assertFalse( \WorldGraph\Utils\worldgraph_should_exclude_from_details( 'story_world' ) );
	}

	/**
	 * Sound is a first-class Story Graph entity with the planned-cue fields.
	 */
	public function test_sound_cpt_contract_is_registered_in_helpers() {
		$cpts = \WorldGraph\Utils\worldgraph_get_all_cpts();
		$this->assertArrayHasKey( 'worldgraph_sound', $cpts );

		$fields = \WorldGraph\Utils\worldgraph_expected_fields_for_cpt( 'worldgraph_sound' );
		$this->assertSame(
			[ 'sound_type', 'production_status', 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ],
			$fields
		);
	}

	/**
	 * Existing structured Scene dialogue is declared independently of Sounds.
	 */
	public function test_scene_contract_declares_structured_dialogue() {
		$this->assertContains( 'dialogue', \WorldGraph\Utils\worldgraph_expected_fields_for_cpt( 'worldgraph_scene' ) );
	}

	/**
	 * Scene dialogue remains separate from the Sound taxonomy.
	 */
	public function test_sound_type_vocabulary_does_not_duplicate_dialogue() {
		$types = \WorldGraph\Utils\worldgraph_sound_types();

		foreach ( [ 'narration', 'voiceover', 'music', 'sound-effect', 'ambience', 'foley', 'silence', 'adr' ] as $required_type ) {
			$this->assertArrayHasKey( $required_type, $types );
		}

		$this->assertArrayNotHasKey( 'dialogue', $types );
	}
}
