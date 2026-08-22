<?php
/**
 * Presentation-layer contract regression tests.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Story_Display_UI extends TestCase {

	/** The public projection must stay read-only and visibility-aware. */
	public function test_story_display_projection_is_read_only_and_permission_scoped(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/utils/story-display.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'worldgraph_register_story_display_rest_field', $source );
		$this->assertStringContainsString( "'worldgraph_display'", $source );
		$this->assertStringNotContainsString( "'update_callback'", $source );
		$this->assertStringContainsString( "current_user_can( 'read_post', \$post_id )", $source );
		$this->assertStringContainsString( 'worldgraph_story_display_can_read( $node_id, true )', $source );
		$this->assertStringContainsString( 'worldgraph_story_display_graph_post_types()', $source );
		$this->assertStringContainsString( 'fetch_relationship_graph();', $source );
		$this->assertStringContainsString( 'filter_relationship_graph_by_project( $graph, $project_id )', $source );
		$this->assertStringNotContainsString( "\t\t'worldgraph_conn',", $source );
		$this->assertStringContainsString( 'post_password_required( $post )', $source );
		$this->assertStringContainsString( "'' === (string) \$node_post->post_password", $source );
		$this->assertStringContainsString( "'publish' === \$post->post_status", $source );
		$this->assertStringContainsString( 'worldgraph_hide_protected_story_rest_fields', $source );
		$this->assertStringContainsString( 'array_keys( worldgraph_get_all_cpts() )', $source );
		$this->assertStringContainsString( "\$data['acf']                = [];", $source );
		$this->assertStringContainsString( "str_starts_with( (string) \$relation, 'acf:' )", $source );
		$this->assertStringContainsString( 'hash_equals( (string) $post->post_password, $request_password )', $source );
	}

	/** Scene details must use canonical ownership and deterministic editorial order. */
	public function test_scene_display_resolves_and_orders_shots(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/utils/story-display.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( 'worldgraph_get_scene_display_shots', $source );
		$this->assertStringContainsString( "[ 'belongs_to', 'contains' ]", $source );
		$this->assertStringContainsString( "'key'     => 'scene'", $source );
		$this->assertStringContainsString( 'worldgraph_get_shot_canonical_scene_id', $source );
		$this->assertStringContainsString( '$scene_id !== $canonical_scene_id', $source );
		$this->assertStringContainsString( '$left->ID <=> $right->ID', $source );
		$this->assertStringContainsString( "\$payload['shots']", $source );
	}

	/** Media DTOs need player metadata and deterministic generated-view intent. */
	public function test_media_projection_supports_gallery_and_players(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/utils/story-display.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "'_worldgraph_asset_gallery_ids'", $source );
		$this->assertStringContainsString( "'_worldgraph_gen_intent'", $source );
		$this->assertStringContainsString( "'mime_type'", $source );
		$this->assertStringContainsString( "'audio/mpeg'", $source );
		$this->assertStringContainsString( "'video/mp4'", $source );
		$this->assertStringContainsString( 'worldgraph_story_display_intent_rank', $source );
		$this->assertStringContainsString( "'shot-video'", $source );
		$this->assertStringContainsString( "get_post_meta( \$asset_id, '_worldgraph_gen_intent', true )", $source );
		$this->assertStringContainsString( "return '';", $source );
	}

	/** Scene ordering must be complete, scoped, authorized, and keyboard operable. */
	public function test_scene_shot_sequencer_validates_complete_membership(): void {
		$controller = file_get_contents( dirname( __DIR__ ) . '/includes/admin/scene-shot-sequencer.php' );
		$service    = file_get_contents( dirname( __DIR__ ) . '/includes/utils/scene-shot-order.php' );
		$rest       = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/shots-controller.php' );
		$base_rest  = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/base-controller.php' );
		$shot_cpt   = file_get_contents( dirname( __DIR__ ) . '/includes/cpts/shot.php' );
		$script     = file_get_contents( dirname( __DIR__ ) . '/assets/js/scene-shot-sequencer.js' );

		$this->assertNotFalse( $controller );
		$this->assertNotFalse( $service );
		$this->assertNotFalse( $rest );
		$this->assertNotFalse( $base_rest );
		$this->assertNotFalse( $shot_cpt );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString( 'worldgraph_reorder_scene_shots', $controller );
		$this->assertStringContainsString( '$submitted_set !== $expected_set', $service );
		$this->assertStringContainsString( "current_user_can( 'edit_post', \$scene_id )", $service );
		$this->assertStringContainsString( "current_user_can( 'edit_post', \$shot_id )", $service );
		$this->assertStringContainsString( '$order_slots[ $index ]', $service );
		$this->assertStringContainsString( "'post__not_in'   => \$scene_shot_ids", $service );
		$this->assertStringContainsString( 'worldgraph_rollback_scene_shot_order', $service );
		$this->assertStringContainsString( "'scene_id'", $rest );
		$this->assertStringContainsString( "'permission_callback' => [ \$this, 'check_reorder_permission' ]", $rest );
		$this->assertStringContainsString( 'worldgraph_shot_order_requires_scene', $rest );
		$this->assertStringContainsString( "current_user_can( 'edit_post', \$post_id )", $base_rest );
		$this->assertStringContainsString( "current_user_can( 'delete_post', \$post_id )", $base_rest );
		$this->assertStringNotContainsString( "'page-attributes'", $shot_cpt );
		$this->assertStringContainsString( 'set_relationships_for_field', $shot_cpt );
		$this->assertStringContainsString( "data-shot-move=\"up\"", $controller );
		$this->assertStringContainsString( 'aria-live="polite"', $controller );
		$this->assertStringContainsString( 'sortable', $script );
		$this->assertStringContainsString( 'queuedSave', $script );
	}

	/** The gallery editor must use core media selection and validate attachments. */
	public function test_story_media_gallery_is_curated_and_nonce_protected(): void {
		$controller = file_get_contents( dirname( __DIR__ ) . '/includes/admin/story-media-gallery.php' );
		$script     = file_get_contents( dirname( __DIR__ ) . '/assets/js/story-media-gallery.js' );

		$this->assertNotFalse( $controller );
		$this->assertNotFalse( $script );
		$this->assertStringContainsString( 'wp_enqueue_media()', $controller );
		$this->assertStringContainsString( 'wp_verify_nonce', $controller );
		$this->assertStringContainsString( "current_user_can( 'upload_files' )", $controller );
		$this->assertStringContainsString( "'attachment' !== get_post_type( \$attachment_id )", $controller );
		$this->assertStringContainsString( "current_user_can( 'edit_post', \$attachment_id )", $controller );
		$this->assertStringContainsString( 'hash_equals( $current_revision, $revision )', $controller );
		$this->assertStringContainsString( '$concurrent_additions', $controller );
		$this->assertStringContainsString( "[ 'image', 'audio', 'video' ]", $script );
		$this->assertStringContainsString( 'const frame = window.wp.media', $script );
		$this->assertStringContainsString( 'sortable', $script );
		$this->assertStringContainsString( 'data-gallery-move="up"', $controller );
		$this->assertStringContainsString( 'dataset.galleryMove', $script );
	}

	/** Story saves must invalidate both broad and route-specific headless caches. */
	public function test_headless_revalidation_covers_story_views_and_dependencies(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/plugins/headless-revalidate/headless-revalidate.php' );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "'worldgraph_scene'     => 'scenes'", $source );
		$this->assertStringContainsString( "'worldgraph_sound'     => 'sounds'", $source );
		$this->assertStringContainsString( "'worldgraph_shot'", $source );
		$this->assertStringContainsString( "'worldgraph_asset'", $source );
		$this->assertStringContainsString( "'storyType'", $source );
		$this->assertStringContainsString( "send_webhook( 'story'", $source );
		$this->assertStringContainsString( 'flush_story_revalidation_queue', $source );
		$this->assertStringContainsString( 'wp_safe_remote_post', $source );
		$this->assertStringContainsString( 'render_failure_notice', $source );
		$this->assertStringContainsString( "'_thumbnail_id'", $source );
		$this->assertStringContainsString( "'production_stage'", $source );
		$this->assertStringContainsString( "'publish' !== \$post->post_status", $source );
		$this->assertStringContainsString( "add_action( 'set_object_terms'", $source );
		$this->assertStringContainsString( 'queue_broad_story_revalidation', $source );
	}
}
