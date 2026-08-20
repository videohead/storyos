<?php
/**
 * Tests for StoryOS markdown export flow.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_StoryOS_Exporter
 */
class Test_StoryOS_Exporter extends TestCase {

	/**
	 * The exporter should work from live StoryOS project records rather than import JSON snapshots.
	 */
	public function test_exporter_builds_markdown_from_live_project_data() {
		$this->assertTrue( class_exists( '\\StoryOS\\Exporter\\StoryOS_Exporter' ), 'Exporter class must exist.' );

		$exporter = new \StoryOS\Exporter\StoryOS_Exporter();
		$this->assertIsObject( $exporter );
		$this->assertTrue( method_exists( $exporter, 'export_project_markdown' ) );
	}

	/**
	 * The exporter should build a storyboard document from scene, shot, and frame data.
	 */
	public function test_exporter_builds_storyboard_markdown_from_scene_and_shot_data() {
		$exporter = new \StoryOS\Exporter\StoryOS_Exporter();

		$markdown = $exporter->export_project_storyboard_markdown( [
			'title'  => 'Little Red Riding Hood',
			'world'  => 'Forest Edge',
			'scenes' => [
				[
					'id'             => 101,
					'title'          => 'The Warning',
					'scene_number'   => 1,
					'location'       => 'Mother\'s Cottage',
					'time_of_day'    => 'morning',
					'summary'        => 'Mother warns Red to stay on the path.',
					'shots'          => [
						[
							'id'               => 201,
							'title'            => 'Basket Close-Up',
							'shot_number'      => 2,
							'shot_type'        => 'close_up',
							'camera_angle'     => 'eye_level',
							'lens'             => '50mm',
							'duration'         => '00:00:04',
							'shot_description' => 'The basket is packed and handed to Red.',
							'storyboard_frames' => [
								[
									'frame_number'      => 1,
									'frame_description' => 'Hands tie a cloth over the basket.',
									'camera_notes'      => 'Static close-up',
									'prompt_text'       => 'storybook cottage basket close-up',
								],
							],
						],
					],
				],
			],
		] );

		$this->assertStringContainsString( '# Little Red Riding Hood Storyboard', $markdown );
		$this->assertStringContainsString( '## Scene 1: The Warning', $markdown );
		$this->assertStringContainsString( '### Basket Close-Up - Close Up', $markdown );
		$this->assertStringContainsString( '- **Frame 1:** Hands tie a cloth over the basket.', $markdown );
		$this->assertStringContainsString( 'storyboard_frames: 1', $markdown );
	}

	/**
	 * The storyboard exporter should include frames linked directly to scenes.
	 */
	public function test_exporter_includes_scene_level_storyboard_frames() {
		$exporter = new \StoryOS\Exporter\StoryOS_Exporter();

		$markdown = $exporter->export_project_storyboard_markdown( [
			'title'  => 'Scene Board',
			'scenes' => [
				[
					'title'             => 'Forest Path',
					'scene_number'      => 3,
					'storyboard_frames' => [
						[
							'frame_number'      => 7,
							'frame_description' => 'A wide view of the branching path.',
						],
					],
				],
			],
		] );

		$this->assertStringContainsString( '### Scene Storyboard Frames', $markdown );
		$this->assertStringContainsString( '- **Frame 7:** A wide view of the branching path.', $markdown );
		$this->assertStringContainsString( 'storyboard_frames: 1', $markdown );
	}
}
