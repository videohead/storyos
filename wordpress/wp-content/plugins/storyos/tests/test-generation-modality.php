<?php
/**
 * Tests for the generation modality registry.
 *
 * @package StoryOS
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( int $min = 0, int $max = 0 ): int {
		return $min;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

require_once dirname( __DIR__ ) . '/includes/utils/generation-modality.php';

use StoryOS\Utils\Generation_Modality;

/**
 * Modality registry and built-in workflow contracts.
 */
class Test_Generation_Modality extends TestCase {

	/**
	 * Every modality the product promises is registered.
	 */
	public function test_registry_covers_every_supported_modality(): void {
		$this->assertSame(
			[
				'text_to_image',
				'image_to_image',
				'image_text_to_image',
				'text_to_video',
				'text_image_to_video',
				'video_to_video',
				'video_with_audio',
			],
			Generation_Modality::slugs()
		);
	}

	/**
	 * Unknown modalities fall back to text-to-image rather than fataling.
	 */
	public function test_sanitize_falls_back_to_text_to_image(): void {
		$this->assertSame( Generation_Modality::TEXT_TO_IMAGE, Generation_Modality::sanitize( 'not-a-modality' ) );
		$this->assertSame( Generation_Modality::TEXT_TO_VIDEO, Generation_Modality::sanitize( 'text_to_video' ) );
	}

	/**
	 * Each modality declares the inputs its name implies.
	 *
	 * @dataProvider required_input_provider
	 *
	 * @param string $modality Modality slug.
	 * @param array  $expected Required input slots.
	 */
	public function test_required_inputs( string $modality, array $expected ): void {
		$this->assertSame( $expected, Generation_Modality::required_inputs( $modality ) );
	}

	/**
	 * Required input expectations per modality.
	 *
	 * @return array<string, array>
	 */
	public static function required_input_provider(): array {
		return [
			'text to image'        => [ Generation_Modality::TEXT_TO_IMAGE, [ 'prompt' ] ],
			'image to image'       => [ Generation_Modality::IMAGE_TO_IMAGE, [ 'image' ] ],
			'image and text'       => [ Generation_Modality::IMAGE_TEXT_TO_IMAGE, [ 'image', 'prompt' ] ],
			'text to video'        => [ Generation_Modality::TEXT_TO_VIDEO, [ 'prompt' ] ],
			'text and image video' => [ Generation_Modality::TEXT_IMAGE_TO_VIDEO, [ 'image', 'prompt' ] ],
			'video to video'       => [ Generation_Modality::VIDEO_TO_VIDEO, [ 'start_frame' ] ],
			'video with audio'     => [ Generation_Modality::VIDEO_WITH_AUDIO, [ 'prompt', 'audio' ] ],
		];
	}

	/**
	 * Built-in graphs only reference nodes that exist in the same graph, so
	 * ComfyUI never receives a dangling link.
	 */
	public function test_built_in_workflows_are_internally_consistent(): void {
		foreach ( Generation_Modality::slugs() as $slug ) {
			$graph = Generation_Modality::default_workflow( $slug, [ 'checkpoint' => 'test.safetensors' ] );

			foreach ( $graph as $id => $node ) {
				$this->assertNotEmpty( $node['class_type'], "Node {$id} of {$slug} has no class_type." );

				foreach ( $node['inputs'] as $name => $value ) {
					if ( is_array( $value ) ) {
						$this->assertArrayHasKey( (string) $value[0], $graph, "{$slug}: {$id}.{$name} links to a missing node." );
					}
				}
			}
		}
	}

	/**
	 * Every node a built-in graph emits is declared as a requirement, so the
	 * ComfyUI requirement check cannot miss one.
	 */
	public function test_built_in_workflow_nodes_are_declared(): void {
		foreach ( Generation_Modality::slugs() as $slug ) {
			$graph = Generation_Modality::default_workflow( $slug, [ 'checkpoint' => 'test.safetensors', 'has_end_frame' => true ] );

			$this->assertSame(
				[],
				array_values( array_diff( array_column( $graph, 'class_type' ), Generation_Modality::required_nodes( $slug ) ) ),
				"{$slug} emits an undeclared node class."
			);
		}
	}

	/**
	 * Placeholders in a built-in graph always name a declared input slot.
	 */
	public function test_workflow_placeholders_match_declared_inputs(): void {
		foreach ( Generation_Modality::slugs() as $slug ) {
			$graph        = Generation_Modality::default_workflow( $slug, [ 'checkpoint' => 'test.safetensors', 'has_end_frame' => true ] );
			$placeholders = [];

			array_walk_recursive( $graph, function ( $value ) use ( &$placeholders ): void {
				if ( is_string( $value ) && preg_match_all( '/\{\{(\w+)\}\}/', $value, $matches ) ) {
					$placeholders = array_merge( $placeholders, $matches[1] );
				}
			} );

			$this->assertSame(
				[],
				array_values( array_diff( array_unique( $placeholders ), array_keys( Generation_Modality::inputs( $slug ) ) ) ),
				"{$slug} references an undeclared input placeholder."
			);
		}
	}

	/**
	 * Video modalities mux through CreateVideo/SaveVideo; image ones save an image.
	 */
	public function test_output_nodes_match_output_type(): void {
		foreach ( Generation_Modality::slugs() as $slug ) {
			$classes = array_column( Generation_Modality::default_workflow( $slug, [ 'checkpoint' => 'test.safetensors' ] ), 'class_type' );

			if ( 'video' === Generation_Modality::output_type( $slug ) ) {
				$this->assertContains( 'SaveVideo', $classes, "{$slug} does not save a video." );
			} else {
				$this->assertContains( 'SaveImage', $classes, "{$slug} does not save an image." );
			}
		}
	}

	/**
	 * The optional ending frame only adds a second guide when supplied.
	 */
	public function test_end_frame_guide_is_conditional(): void {
		$without = Generation_Modality::default_workflow( Generation_Modality::VIDEO_TO_VIDEO, [ 'checkpoint' => 'test.safetensors' ] );
		$with    = Generation_Modality::default_workflow( Generation_Modality::VIDEO_TO_VIDEO, [ 'checkpoint' => 'test.safetensors', 'has_end_frame' => true ] );

		$this->assertSame( 1, count( array_keys( array_column( $without, 'class_type' ), 'LTXVAddGuide' ) ) );
		$this->assertSame( 2, count( array_keys( array_column( $with, 'class_type' ), 'LTXVAddGuide' ) ) );
	}

	/**
	 * The checkpoint a Template configures reaches the loader node.
	 */
	public function test_checkpoint_is_applied_to_the_loader(): void {
		$graph = Generation_Modality::default_workflow( Generation_Modality::TEXT_TO_VIDEO, [ 'checkpoint' => 'ltx-2.3.safetensors' ] );

		$this->assertSame( 'ltx-2.3.safetensors', $graph['4']['inputs']['ckpt_name'] );
	}
}
