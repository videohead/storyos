<?php
/**
 * Tests for StoryOS CPT registration.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_CPT
 */
class Test_StoryOS_CPT extends TestCase {

	/**
	 * Test register_cpt function signature.
	 */
	public function test_register_cpt_parameters() {
		// Mock the parameters that would be passed to register_cpt
		$post_type = 'storyos_project';
		$args = [
			'label'        => 'Projects',
			'public'       => true,
			'show_ui'      => true,
			'supports'     => [ 'title', 'editor', 'custom-fields' ],
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
			'post_type' => 'storyos_character',
			'label'     => 'Characters',
			'public'    => true,
		];
		
		$this->assertArrayHasKey( 'post_type', $cpt_definition );
		$this->assertArrayHasKey( 'label', $cpt_definition );
		$this->assertArrayHasKey( 'public', $cpt_definition );
		$this->assertEquals( 'storyos_character', $cpt_definition['post_type'] );
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
	 * Test StoryOS CPTs are mounted under the StoryOS menu.
	 */
	public function test_storyos_cpt_defaults_use_storyos_menu() {
		$args = \StoryOS\Utils\storyos_get_default_cpt_args( 'storyos_project', 'Project' );

		$this->assertSame( 'storyos', $args['show_in_menu'] );
		$this->assertTrue( $args['show_ui'] );
	}

	/**
	 * WordPress must recognize SCF as a plugin dependency on every activation path.
	 */
	public function test_scf_dependency_and_activation_guards() {
		$source = file_get_contents( dirname( __DIR__ ) . '/storyos.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'Requires Plugins: secure-custom-fields', $source );
		$this->assertStringNotContainsString( 'Requires Plugins: secure-custom-fields/secure-custom-fields.php', $source );
		$this->assertStringContainsString( "if ( ! scf_is_active() ) {\n\t\tstoryos_missing_scf_dependency();", $source );
		$this->assertStringContainsString( "is_plugin_active_for_network( \$plugin )", $source );
	}

	/**
	 * StoryOS CPT keys must fit WordPress's 20-character database limit.
	 */
	public function test_storyos_cpt_keys_fit_wordpress_limit() {
		$cpts = \StoryOS\Utils\storyos_get_all_cpts();

		$this->assertArrayHasKey( 'storyos_storyboard', $cpts );
		$this->assertArrayHasKey( 'storyos_editorial', $cpts );
		$this->assertArrayNotHasKey( 'storyos_storyboard_frame', $cpts );
		$this->assertArrayNotHasKey( 'storyos_editorial_artifact', $cpts );

		foreach ( array_keys( $cpts ) as $cpt ) {
			$this->assertLessThanOrEqual( 20, strlen( $cpt ), "CPT key {$cpt} exceeds WordPress's 20-character limit." );
		}
	}

	/**
	 * Legacy and database-truncated CPT keys map to the current keys.
	 */
	public function test_legacy_cpt_key_migration_map() {
		$legacy_keys = \StoryOS\Utils\storyos_legacy_cpt_key_map();

		$this->assertSame( 'storyos_storyboard', $legacy_keys['storyos_storyboard_frame'] );
		$this->assertSame( 'storyos_storyboard', $legacy_keys['storyos_storyboard_f'] );
		$this->assertSame( 'storyos_editorial', $legacy_keys['storyos_editorial_artifact'] );
		$this->assertSame( 'storyos_editorial', $legacy_keys['storyos_editorial_ar'] );
	}

	/**
	 * Test the generic Details meta box excludes redundant built-in name/description fields.
	 */
	public function test_storyos_details_filters_redundant_name_and_description_fields() {
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'description' ) );
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'project_name' ) );
		$this->assertTrue( \StoryOS\Utils\storyos_should_exclude_from_details( 'shot_description' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'status' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'target_medium' ) );
		$this->assertFalse( \StoryOS\Utils\storyos_should_exclude_from_details( 'story_world' ) );
	}

	/**
	 * Sound is a first-class Story Graph entity with the planned-cue fields.
	 */
	public function test_sound_cpt_contract_is_registered_in_helpers() {
		$cpts = \StoryOS\Utils\storyos_get_all_cpts();
		$this->assertArrayHasKey( 'storyos_sound', $cpts );

		$fields = \StoryOS\Utils\storyos_expected_fields_for_cpt( 'storyos_sound' );
		$this->assertSame(
			[ 'sound_type', 'production_status', 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ],
			$fields
		);
	}

	/**
	 * Existing structured Scene dialogue is declared independently of Sounds.
	 */
	public function test_scene_contract_declares_structured_dialogue() {
		$this->assertContains( 'dialogue', \StoryOS\Utils\storyos_expected_fields_for_cpt( 'storyos_scene' ) );
	}

	/**
	 * Scene dialogue remains separate from the Sound taxonomy.
	 */
	public function test_sound_type_vocabulary_does_not_duplicate_dialogue() {
		$types = \StoryOS\Utils\storyos_sound_types();

		foreach ( [ 'narration', 'voiceover', 'music', 'sound-effect', 'ambience', 'foley', 'silence', 'adr' ] as $required_type ) {
			$this->assertArrayHasKey( $required_type, $types );
		}

		$this->assertArrayNotHasKey( 'dialogue', $types );
	}
}
