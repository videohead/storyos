<?php
/**
 * REST API bridge regression tests.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils {
	if ( ! function_exists( __NAMESPACE__ . '\\get_posts' ) ) {
		/** Lightweight post query used by incoming relationship traversal. */
		function get_posts( array $args = [] ): array {
			$post_type = (string) ( $args['post_type'] ?? '' );
			$posts     = [];
			foreach ( (array) ( $GLOBALS['worldgraph_rest_api_posts'] ?? [] ) as $post_id => $registered_type ) {
				if ( $post_type === $registered_type ) {
					$posts[] = (object) [ 'ID' => (int) $post_id ];
				}
			}
			return $posts;
		}
	}

}

namespace WorldGraph\Taxonomies {
	if ( ! class_exists( __NAMESPACE__ . '\\Sequence' ) ) {
		/** Minimal Sequence taxonomy contract for controller unit tests. */
		final class Sequence {
			public const TAXONOMY      = 'worldgraph_sequence';
			public const ORDER_META_KEY = 'sequence_order';
		}
	}
}

namespace {

use PHPUnit\Framework\TestCase;
use WorldGraph\REST\Base_Controller;
use WorldGraph\REST\Characters_Controller;
use WorldGraph\REST\Graph_Controller;
use WorldGraph\REST\Locations_Controller;
use WorldGraph\REST\Projects_Controller;
use WorldGraph\REST\Sequences_Controller;
use WorldGraph\REST\StoryWorlds_Controller;

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	class WP_REST_Controller {}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params;

		public function __construct( array $params = [] ) {
			$this->params = $params;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;

		/** @var array<string, mixed> */
		private array $links = [];

		/** @var array<string, mixed> */
		private array $headers = [];

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function get_data() {
			return $this->data;
		}

		public function add_links( array $links ): void {
			$this->links = $links;
		}

		public function header( string $key, $value ): void {
			$this->headers[ $key ] = $value;
		}

		public function get_headers(): array {
			return $this->headers;
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, WP_Post> */
		public array $posts;
		public int $found_posts;
		public int $max_num_pages;

		public function __construct( array $args = [] ) {
			$post_type   = (string) ( $args['post_type'] ?? '' );
			$this->posts = array_values(
				array_filter(
					(array) ( $GLOBALS['worldgraph_rest_api_query_posts'] ?? [] ),
					static fn( WP_Post $post ): bool => '' === $post_type || $post_type === $post->post_type
				)
			);
			$this->found_posts   = count( $this->posts );
			$this->max_num_pages = empty( $this->posts ) ? 0 : 1;
		}

		public function have_posts(): bool {
			return ! empty( $this->posts );
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID;
		public string $post_type;
		public int $post_parent;

		public function __construct( int $id, string $post_type, int $post_parent = 0 ) {
			$this->ID          = $id;
			$this->post_type   = $post_type;
			$this->post_parent = $post_parent;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$value = $GLOBALS['worldgraph_rest_api_post_meta'][ (int) $post_id ][ (string) $key ] ?? null;
		return $single ? ( null === $value ? '' : $value ) : ( null === $value ? [] : [ $value ] );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id ): int {
		return 0;
	}
}

if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $post_type ): array {
		return [];
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post_id ) {
		$post_id = is_object( $post_id ) ? $post_id->ID : $post_id;
		return $GLOBALS['worldgraph_rest_api_posts'][ (int) $post_id ] ?? false;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return $GLOBALS['worldgraph_rest_api_post_objects'][ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = [] ): array {
		$post_type = (string) ( $args['post_type'] ?? '' );
		$include   = array_map( 'intval', (array) ( $args['include'] ?? [] ) );
		$posts     = array_values(
			array_filter(
				(array) ( $GLOBALS['worldgraph_rest_api_post_objects'] ?? [] ),
				static function( WP_Post $post ) use ( $post_type, $include ): bool {
					return ( '' === $post_type || $post_type === $post->post_type )
						&& ( empty( $include ) || in_array( $post->ID, $include, true ) );
				}
			)
		);
		if ( 'menu_order' === ( $args['orderby'] ?? '' ) ) {
			usort( $posts, static fn( WP_Post $left, WP_Post $right ): int => $left->menu_order <=> $right->menu_order );
		}
		return $posts;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['worldgraph_rest_api_post_meta'][ (int) $post_id ][ (string) $key ] = $value;
		$GLOBALS['worldgraph_generation_auth_meta'][ (int) $post_id ][ (string) $key ] = $value;
		return 1;
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ): array {
		return array_map( 'intval', (array) $terms );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = [] ): array {
		return array_values( (array) ( $GLOBALS['worldgraph_rest_api_terms'] ?? [] ) );
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		return $GLOBALS['worldgraph_rest_api_terms'][ (int) $term_id ] ?? null;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $key = '', $single = false ) {
		$value = $GLOBALS['worldgraph_rest_api_term_meta'][ (int) $term_id ][ (string) $key ] ?? null;
		return $single ? ( null === $value ? '' : $value ) : ( null === $value ? [] : [ $value ] );
	}
}

if ( ! function_exists( 'get_objects_in_term' ) ) {
	/** Lightweight WordPress taxonomy lookup used by the Sequence controller. */
	function get_objects_in_term( $term_id, $taxonomy, $args = [] ): array {
		$object_ids = [];
		foreach ( (array) $term_id as $id ) {
			$object_ids = array_merge(
				$object_ids,
				(array) ( $GLOBALS['worldgraph_rest_api_term_objects'][ (int) $id ] ?? [] )
			);
		}
		return array_values( array_unique( array_map( 'intval', $object_ids ) ) );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata(): void {}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ): WP_REST_Response {
		return $data instanceof WP_REST_Response ? $data : new WP_REST_Response( $data );
	}
}

require_once dirname( __DIR__ ) . '/includes/rest-api/base-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/graph-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/projects-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/characters-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/locations-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/storyworlds-controller.php';
require_once dirname( __DIR__ ) . '/includes/rest-api/sequences-controller.php';

/** Post object with the fields consumed by Base_Controller::get_item_data(). */
final class WorldGraph_REST_Test_Post extends WP_Post {
	public string $post_slug = '';
	public string $post_name = '';
	public string $post_title = '';
	public int $menu_order = 0;
	public string $post_content = '';
	public string $post_excerpt = '';
	public string $post_status = 'publish';
	public int $post_author = 1;
	public string $post_date = '2026-08-21 12:00:00';
	public string $post_modified = '2026-08-21 12:00:00';
}

/** Expose Base_Controller's normalized resource data for direct unit testing. */
final class WorldGraph_REST_Test_Controller extends Base_Controller {
	protected $cpt       = 'worldgraph_scene';
	protected $rest_base = 'scenes';

	public static function init(): void {}

	public function item_data( WP_Post $post ): array {
		return $this->get_item_data( $post );
	}
}

/** API responses preserve portable IDs and direction-neutral graph counts. */
final class Test_WorldGraph_REST_API extends TestCase {
	/** @var array<string, mixed>|null */
	private ?array $previous_field_definitions = null;

	protected function setUp(): void {
		$this->previous_field_definitions = $GLOBALS['worldgraph_field_definitions'] ?? null;
		$GLOBALS['worldgraph_field_definitions']['worldgraph_scene'] = [];
		$this->reset_rest_state();
	}

	protected function tearDown(): void {
		if ( null === $this->previous_field_definitions ) {
			unset( $GLOBALS['worldgraph_field_definitions'] );
		} else {
			$GLOBALS['worldgraph_field_definitions'] = $this->previous_field_definitions;
		}
	}

	/** Resource and embedded relationship data expose string external IDs. */
	public function test_base_resource_data_exposes_external_ids(): void {
		$this->store_post_meta( 10, 'external_id', 'scene_external_10' );
		$this->store_post_meta( 20, 'external_id', 'character_external_20' );
		$this->store_post_meta(
			10,
			'worldgraph_relationships',
			[
				[
					'to_id'   => 20,
					'to_type' => 'worldgraph_character',
					'type'    => 'linked_to',
				],
				[
					'to_id'   => 21,
					'to_type' => 'worldgraph_character',
					'type'    => 'linked_to',
				],
			]
		);

		$post             = new WorldGraph_REST_Test_Post( 10, 'worldgraph_scene' );
		$post->post_name  = 'scene-10';
		$post->post_title = 'Scene 10';
		$data             = ( new WorldGraph_REST_Test_Controller() )->item_data( $post );

		$this->assertSame( 'scene_external_10', $data['external_id'] );
		$this->assertSame( 20, $data['relationships'][0]['to_id'] );
		$this->assertSame( 'worldgraph_character', $data['relationships'][0]['to_type'] );
		$this->assertSame( 'linked_to', $data['relationships'][0]['type'] );
		$this->assertArrayHasKey( 'schema_property', $data['relationships'][0] );
		$this->assertSame( 'scene_external_10', $data['relationships'][0]['from_external_id'] );
		$this->assertSame( 'character_external_20', $data['relationships'][0]['to_external_id'] );
		$this->assertSame( '', $data['relationships'][1]['to_external_id'] );

		$unimported = new WorldGraph_REST_Test_Post( 11, 'worldgraph_scene' );
		$this->assertSame( '', ( new WorldGraph_REST_Test_Controller() )->item_data( $unimported )['external_id'] );
	}

	/** Collection responses contain flat resource arrays, not nested REST responses. */
	public function test_base_collection_contains_flat_resource_data(): void {
		$this->store_post_meta( 10, 'external_id', 'scene_external_10' );
		$post             = new WorldGraph_REST_Test_Post( 10, 'worldgraph_scene' );
		$post->post_name  = 'scene-10';
		$post->post_title = 'Scene 10';
		$GLOBALS['worldgraph_rest_api_query_posts'] = [ $post ];

		$response = ( new WorldGraph_REST_Test_Controller() )->get_items( new WP_REST_Request() );
		$items    = $response->get_data();

		$this->assertCount( 1, $items );
		$this->assertIsArray( $items[0] );
		$this->assertNotInstanceOf( WP_REST_Response::class, $items[0] );
		$this->assertSame( 'scene_external_10', $items[0]['external_id'] );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
	}

	/** Dedicated graph entity and relationship endpoints expose portable IDs. */
	public function test_graph_endpoints_expose_external_ids(): void {
		$this->store_post_meta( 10, 'external_id', 'scene_external_10' );
		$this->store_post_meta( 20, 'external_id', 'character_external_20' );
		$this->store_post_meta(
			10,
			'worldgraph_relationships',
			[
				[
					'to_id'   => 20,
					'to_type' => 'worldgraph_character',
					'type'    => 'linked_to',
				],
			]
		);
		$post             = new WorldGraph_REST_Test_Post( 10, 'worldgraph_scene' );
		$post->post_name  = 'scene-10';
		$post->post_title = 'Scene 10';
		$GLOBALS['worldgraph_rest_api_query_posts'] = [ $post ];

		$entities = Graph_Controller::get_entities( new WP_REST_Request( [ 'type' => 'worldgraph_scene' ] ) )->get_data();
		$this->assertSame( 'scene_external_10', $entities[0]['external_id'] );

		$relationships = Graph_Controller::get_relationships(
			new WP_REST_Request(
				[
					'from_id'   => 10,
					'from_type' => 'worldgraph_scene',
				]
			)
		)->get_data();
		$this->assertCount( 1, $relationships );
		$this->assertSame( 'scene_external_10', $relationships[0]['from_external_id'] );
		$this->assertSame( 'character_external_20', $relationships[0]['to_external_id'] );
	}

	/**
	 * Related counts include both edge directions and deduplicate entity IDs.
	 *
	 * @dataProvider related_count_controllers
	 */
	public function test_related_counts_are_bidirectional_and_unique(
		string $controller_class,
		string $from_cpt,
		string $related_cpt,
		array $computed_fields
	): void {
		$this->configure_bidirectional_graph( 100, $from_cpt, $related_cpt );

		$method = new ReflectionMethod( $controller_class, 'count_related' );
		$method->setAccessible( true );
		$this->assertSame( 3, $method->invoke( null, 100, $related_cpt, $from_cpt ) );

		$source = file_get_contents( $this->controller_path( $controller_class ) );
		$this->assertNotFalse( $source );
		foreach ( $computed_fields as $field ) {
			$this->assertStringContainsString( "\$data['meta']['{$field}'] = self::count_related", $source );
		}
	}

	/** Data for every controller with bidirectional computed counts. */
	public static function related_count_controllers(): array {
		return [
			'project' => [
				Projects_Controller::class,
				'worldgraph_project',
				'worldgraph_scene',
				[ 'scene_count', 'character_count', 'asset_count' ],
			],
			'character' => [
				Characters_Controller::class,
				'worldgraph_character',
				'worldgraph_shot',
				[ 'scene_count', 'shot_count', 'asset_count' ],
			],
			'location' => [
				Locations_Controller::class,
				'worldgraph_location',
				'worldgraph_scene',
				[ 'scene_count' ],
			],
			'story_world' => [
				StoryWorlds_Controller::class,
				'worldgraph_world',
				'worldgraph_location',
				[ 'location_count', 'character_count', 'organization_count' ],
			],
		];
	}

	/** Story World routes and graph/count calls use the canonical CPT key everywhere. */
	public function test_storyworld_controller_uses_canonical_world_cpt(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/storyworlds-controller.php' );

		$this->assertNotFalse( $source );
		$this->assertSame( 'worldgraph_world', ( new StoryWorlds_Controller() )->get_cpt() );
		$this->assertStringNotContainsString( 'worldgraph_storyworld', $source );
		$this->assertStringContainsString( "get_graph_entities( \$post_id, 'worldgraph_world' )", $source );
		$this->assertGreaterThanOrEqual( 5, substr_count( $source, "'worldgraph_world'" ) );
	}

	/** Sequence collection and item responses expose their term external IDs. */
	public function test_sequence_responses_expose_term_external_ids(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api/sequences-controller.php' );
		$this->assertNotFalse( $source );
		$this->assertStringNotContainsString( '\\WorldGraph\\Utils\\get_objects_in_term', $source );
		$this->assertStringContainsString( 'worldgraph_get_sequence_object_ids', $source );

		$GLOBALS['worldgraph_rest_api_terms'] = [
			7 => (object) [
				'term_id' => 7,
				'name'    => 'Second Sequence',
				'slug'    => 'second-sequence',
			],
			8 => (object) [
				'term_id' => 8,
				'name'    => 'First Sequence',
				'slug'    => 'first-sequence',
			],
		];
		$GLOBALS['worldgraph_rest_api_term_meta'] = [
			7 => [
				'sequence_order' => 2,
				'external_id'    => 7007,
			],
			8 => [
				'sequence_order' => 1,
			],
		];
		$GLOBALS['worldgraph_rest_api_term_objects'] = [
			7 => [ 701, 702, 703 ],
			8 => [ 801, 802 ],
		];
		$GLOBALS['worldgraph_rest_api_posts'] = [
			701 => 'worldgraph_shot',
			702 => 'worldgraph_scene',
			703 => 'worldgraph_shot',
			801 => 'worldgraph_scene',
			802 => 'worldgraph_shot',
		];
		$GLOBALS['worldgraph_import_journal_state']['post_types'] = $GLOBALS['worldgraph_rest_api_posts'];

		$controller = new Sequences_Controller();
		$collection = $controller->get_items( null )->get_data();
		$this->assertSame( [ 8, 7 ], array_column( $collection, 'id' ) );
		$this->assertSame( [ '', '7007' ], array_column( $collection, 'external_id' ) );
		$this->assertSame( [ 1, 2 ], array_column( $collection, 'shot_count' ) );
		$this->assertSame( [ 1, 1 ], array_column( $collection, 'scene_count' ) );

		$item = $controller->get_item( new WP_REST_Request( [ 'id' => 7 ] ) )->get_data();
		$this->assertSame( 7, $item['id'] );
		$this->assertSame( '7007', $item['external_id'] );
	}

	/** Assigning Scenes starts after existing Scenes, not every object in the term. */
	public function test_sequence_assignment_counts_only_existing_scenes(): void {
		$GLOBALS['worldgraph_rest_api_terms'][7] = (object) [
			'term_id' => 7,
			'name'    => 'Mixed Sequence',
			'slug'    => 'mixed-sequence',
		];
		$GLOBALS['worldgraph_rest_api_term_objects'][7] = [ 701, 702, 703 ];
		$GLOBALS['worldgraph_rest_api_posts'] = [
			701 => 'worldgraph_shot',
			702 => 'worldgraph_scene',
			703 => 'worldgraph_shot',
		];
		$GLOBALS['worldgraph_import_journal_state']['post_types'] = $GLOBALS['worldgraph_rest_api_posts'];
		$new_scene             = new WorldGraph_REST_Test_Post( 704, 'worldgraph_scene' );
		$new_scene->post_title = 'New Scene';
		$this->register_post_object( $new_scene );

		$response = ( new Sequences_Controller() )->assign_scenes(
			new WP_REST_Request(
				[
					'id'        => 7,
					'scene_ids' => [ 704 ],
				]
			)
		)->get_data();

		$this->assertSame( [ 704 ], $response['updated'] );
		$this->assertSame( 2, $GLOBALS['worldgraph_rest_api_post_meta'][704]['sequence_order'] );
	}

	/** Single-Sequence Scenes use explicit order, then menu order for unset values. */
	public function test_single_sequence_orders_scenes_with_menu_order_fallback(): void {
		$GLOBALS['worldgraph_rest_api_terms'][7] = (object) [
			'term_id' => 7,
			'name'    => 'Ordered Sequence',
			'slug'    => 'ordered-sequence',
		];
		$GLOBALS['worldgraph_rest_api_term_objects'][7] = [ 711, 712, 713, 714 ];

		foreach ( [ 711 => 1, 712 => 9, 713 => 2, 714 => 1 ] as $scene_id => $menu_order ) {
			$scene             = new WorldGraph_REST_Test_Post( $scene_id, 'worldgraph_scene' );
			$scene->post_title = "Scene {$scene_id}";
			$scene->menu_order = $menu_order;
			$this->register_post_object( $scene );
			$this->store_post_meta( $scene_id, 'external_id', "scene_external_{$scene_id}" );
		}
		$this->store_post_meta( 711, 'sequence_order', 3 );
		$this->store_post_meta( 712, 'sequence_order', 1 );

		$item = ( new Sequences_Controller() )->get_item( new WP_REST_Request( [ 'id' => 7 ] ) )->get_data();

		$this->assertSame( [ 712, 711, 714, 713 ], array_column( $item['scenes'], 'id' ) );
		$this->assertSame(
			[ 'scene_external_712', 'scene_external_711', 'scene_external_714', 'scene_external_713' ],
			array_column( $item['scenes'], 'external_id' )
		);
	}

	/** Reset shared WordPress-stub state between tests and data-provider cases. */
	private function reset_rest_state(): void {
		$GLOBALS['worldgraph_rest_api_posts']        = [];
		$GLOBALS['worldgraph_rest_api_post_objects'] = [];
		$GLOBALS['worldgraph_rest_api_post_meta']    = [];
		$GLOBALS['worldgraph_rest_api_terms']        = [];
		$GLOBALS['worldgraph_rest_api_term_meta']    = [];
		$GLOBALS['worldgraph_rest_api_term_objects'] = [];
		$GLOBALS['worldgraph_rest_api_query_posts']  = [];
		$GLOBALS['worldgraph_generation_auth_meta']  = [];
		$GLOBALS['worldgraph_generation_auth_posts'] = [];
		$GLOBALS['worldgraph_import_journal_state']['meta'] = [];
		$GLOBALS['worldgraph_import_journal_state']['post_types'] = [];
	}

	/** Register a post object in each test-suite lookup backend. */
	private function register_post_object( WP_Post $post ): void {
		$GLOBALS['worldgraph_rest_api_posts'][ $post->ID ]        = $post->post_type;
		$GLOBALS['worldgraph_rest_api_post_objects'][ $post->ID ] = $post;
		$GLOBALS['worldgraph_generation_auth_posts'][ $post->ID ] = $post;
		$GLOBALS['worldgraph_import_journal_state']['post_types'][ $post->ID ] = $post->post_type;
	}

	/** Store post metadata in each test-suite stub backend. */
	private function store_post_meta( int $post_id, string $key, $value ): void {
		$GLOBALS['worldgraph_rest_api_post_meta'][ $post_id ][ $key ] = $value;
		$GLOBALS['worldgraph_generation_auth_meta'][ $post_id ][ $key ] = $value;
		$GLOBALS['worldgraph_import_journal_state']['meta'][ $post_id ][ $key ] = $value;
	}

	/** Build duplicates plus direction-only and reciprocal related entities. */
	private function configure_bidirectional_graph( int $target_id, string $target_cpt, string $related_cpt ): void {
		$GLOBALS['worldgraph_rest_api_posts'] = [
			$target_id => $target_cpt,
			201        => $related_cpt,
			202        => $related_cpt,
			203        => $related_cpt,
			301        => 'worldgraph_asset',
		];
		$this->store_post_meta(
			$target_id,
			'worldgraph_relationships',
			[
				[ 'to_id' => 201, 'to_type' => $related_cpt, 'type' => 'linked_to' ],
				[ 'to_id' => 201, 'to_type' => $related_cpt, 'type' => 'references' ],
				[ 'to_id' => 203, 'to_type' => $related_cpt, 'type' => 'linked_to' ],
				[ 'to_id' => 0, 'to_type' => $related_cpt, 'type' => 'linked_to' ],
				[ 'to_id' => 301, 'to_type' => 'worldgraph_asset', 'type' => 'linked_to' ],
			]
		);
		$this->store_post_meta(
			201,
			'worldgraph_relationships',
			[
				[ 'to_id' => $target_id, 'to_type' => $target_cpt, 'type' => 'linked_to' ],
				[ 'to_id' => $target_id, 'to_type' => $target_cpt, 'type' => 'references' ],
			]
		);
		$this->store_post_meta(
			202,
			'worldgraph_relationships',
			[
				[ 'to_id' => $target_id, 'to_type' => $target_cpt, 'type' => 'linked_to' ],
				[ 'to_id' => $target_id, 'to_type' => $target_cpt, 'type' => 'references' ],
			]
		);
	}

	/** Resolve a loaded controller class to its source file. */
	private function controller_path( string $controller_class ): string {
		return ( new ReflectionClass( $controller_class ) )->getFileName();
	}
}

}
