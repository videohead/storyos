<?php
/**
 * Tests for the StoryOS Sound framework.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Sound framework source and integration contract tests.
 */
class Test_StoryOS_Sound extends TestCase {

	/**
	 * The plugin bootstraps the CPT, taxonomy, and custom REST controller.
	 */
	public function test_sound_components_are_bootstrapped() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/storyos.php' );

		$this->assertStringContainsString( 'CPT\Sound::init()', $plugin );
		$this->assertStringContainsString( 'Taxonomies\SoundType::init()', $plugin );
		$this->assertStringContainsString( 'REST\Sounds_Controller::init()', $plugin );
	}

	/**
	 * Relationship intent remains specific to each Sound field.
	 */
	public function test_sound_relationship_contract() {
		$sound = file_get_contents( dirname( __DIR__ ) . '/includes/cpts/sound.php' );

		$this->assertStringContainsString( "'related_cpt'       => 'storyos_scene'", $sound );
		$this->assertStringContainsString( "'related_cpt'       => 'storyos_shot'", $sound );
		$this->assertStringContainsString( "'related_cpt'       => 'storyos_character'", $sound );
		$this->assertStringContainsString( "'related_cpt'       => 'storyos_asset'", $sound );
		$this->assertStringContainsString( "'relationship_type' => 'belongs_to'", $sound );
		$this->assertStringContainsString( "'relationship_type' => 'linked_to'", $sound );
		$this->assertStringContainsString( "'lyrics'", $sound );
		$this->assertStringContainsString( "'spoken_text'", $sound );
	}

	/**
	 * New REST callbacks use controller instances and expose real filters.
	 */
	public function test_sound_rest_controller_uses_instance_callbacks_and_filters() {
		$controller = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/sounds-controller.php' );

		$this->assertStringContainsString( "[ \$this, 'get_items' ]", $controller );
		$this->assertStringContainsString( "[ \$this, 'create_item' ]", $controller );
		$this->assertStringContainsString( "'sound_type' => [ 'type' => 'string' ]", $controller );
		$this->assertStringContainsString( 'shot_belongs_to_scene', $controller );
	}

	/**
	 * Scalar relationships replace their previous target and graph traversal is bidirectional.
	 */
	public function test_relationship_layer_supports_sound_round_trips() {
		$relationships = file_get_contents( dirname( __DIR__ ) . '/includes/utils/relationships.php' );

		$this->assertStringContainsString( 'function set_relationship(', $relationships );
		$this->assertStringContainsString( "'compare' => 'EXISTS'", $relationships );
		$this->assertStringContainsString( "get_relationships( \$current['id'], \$current['type'], 'incoming' )", $relationships );
	}
}
