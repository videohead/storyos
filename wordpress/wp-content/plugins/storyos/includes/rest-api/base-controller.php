<?php
/**
 * Base REST API Controller for StoryOS.
 *
 * Provides common functionality for all StoryOS REST API controllers.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base controller class for StoryOS REST API endpoints.
 */
abstract class Base_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'storyos/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = '';

	/**
	 * Initialize the controller.
	 */
	abstract public static function init(): void;

	/**
	 * Get the CPT for this controller.
	 *
	 * @return string
	 */
	public function get_cpt(): string {
		return $this->cpt;
	}

	/**
	 * Prepare an item for output.
	 *
	 * @param \WP_Post $post The post object.
	 * @param array    $params Request params.
	 * @return \WP_REST_Response
	 */
	public function prepare_item( \WP_Post $post, array $params = [] ): \WP_REST_Response {
		$data = $this->get_item_data( $post, $params );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $post ) );
		return $response;
	}

	/**
	 * Get item data array.
	 *
	 * @param \WP_Post $post
	 * @param array    $params
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$fields = \StoryOS\Utils\storyos_get_fields( $post->post_type );
		$meta = [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] ) {
				continue;
			}
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value ) {
				$meta[ $key ] = $value;
			}
		}

		// Get relationships.
		$relationships = \StoryOS\Utils\get_relationships( $post->ID, $post->post_type, 'outgoing' );

		// Get taxonomy terms.
		$taxonomies = [];
		$all_tax = get_object_taxonomies( $post->post_type );
		foreach ( $all_tax as $tax ) {
			$terms = get_the_terms( $post->ID, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$taxonomies[ $tax ] = array_map( function( $term ) {
					return [
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					];
				}, $terms );
			}
		}

		$schema_type = \StoryOS\Utils\storyos_schema_type_for_entity( $post->post_type, $meta, $taxonomies );
		$schema_field_map = \StoryOS\Utils\storyos_schema_field_map()[ $post->post_type ] ?? [];
		$schema_hints = \StoryOS\Utils\storyos_schema_hints_from_meta( $post->post_type, $meta );

		if ( '' !== $post->post_title ) {
			$schema_hints['name'] = $post->post_title;
		}

		if ( '' !== $post->post_content && empty( $schema_hints['description'] ) ) {
			$schema_hints['description'] = wp_strip_all_tags( $post->post_content );
		}

		$schema_relationships = array_map(
			static function( array $rel ) use ( $post ) {
				$rel['schema_property'] = \StoryOS\Utils\storyos_schema_property_for_relationship(
					(string) ( $rel['type'] ?? '' ),
					$post->post_type,
					(string) ( $rel['to_type'] ?? '' )
				);

				return $rel;
			},
			$relationships
		);

		return [
			'id'           => $post->ID,
			'slug'         => $post->post_slug ?? $post->post_name,
			'type'         => $post->post_type,
			'title'        => $post->post_title,
			'content'      => $post->post_content,
			'excerpt'      => $post->post_excerpt,
			'status'       => $post->post_status,
			'author'       => (int) $post->post_author,
			'created'      => $post->post_date,
			'modified'     => $post->post_modified,
			'meta'         => $meta,
			'schema'       => [
				'type'       => $schema_type,
				'field_map'  => $schema_field_map,
				'hints'      => $schema_hints,
			],
			'taxonomies'   => $taxonomies,
			'relationships' => $schema_relationships,
		];
	}

	/**
	 * Prepare links for an item.
	 *
	 * @param \WP_Post $post
	 * @return array
	 */
	protected function prepare_links( \WP_Post $post ): array {
		$links = [
			'self'       => [
				['href' => rest_url( "{$this->namespace}/{$this->rest_base}/{$post->ID}" )],
			],
			'collection' => [
				['href' => rest_url( "{$this->namespace}/{$this->rest_base}" )],
			],
		];

		// Add relationship links.
		$relationships = \StoryOS\Utils\get_relationships( $post->ID, $post->post_type, 'outgoing' );
		if ( ! empty( $relationships ) ) {
			$links['relationships'] = [];
			foreach ( $relationships as $rel ) {
				$links['relationships'][] = [
					'to_id'   => $rel['to_id'],
					'to_type' => $rel['to_type'],
					'type'    => $rel['type'],
					'href'    => rest_url( "{$this->namespace}/{$rel['to_type']}/{$rel['to_id']}" ),
				];
			}
		}

		return $links;
	}

	/**
	 * Validate a relationship value.
	 *
	 * @param mixed  $value
	 * @param \WP_REST_Request $request
	 * @param string $param
	 * @return bool|\WP_Error
	 */
	protected function validate_relationship( $value, \WP_REST_Request $request, string $param ) {
		if ( empty( $value ) ) {
			return true;
		}

		$post = get_post( absint( $value ) );
		if ( ! $post ) {
			return new WP_Error( 'invalid_relationship', sprintf( 'Post ID %d not found.', absint( $value ) ) );
		}

		return true;
	}

	/**
	 * Check permissions for reading an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_read_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in to access this resource.', [ 'status' => 401 ] );
		}
		return true;
	}

	/**
	 * Check permissions for creating an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_create_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in with edit permissions.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Check permissions for updating an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_update_permission( \WP_REST_Request $request ) {
		return $this->check_create_permission( $request );
	}

	/**
	 * Check permissions for deleting an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_delete_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'delete_posts' ) ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in with delete permissions.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Create a new item.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		if ( ! $this->check_create_permission( $request ) ) {
			return new WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 403 ] );
		}

		$post_data = [
			'post_type'   => $this->cpt,
			'post_title'  => $request->get_param( 'title' ) ?: $request->get_param( 'meta' )['display_name'] ?? 'Untitled',
			'post_status' => $request->get_param( 'status' ) ?: 'draft',
			'post_excerpt'=> $request->get_param( 'excerpt' ),
			'post_content'=> $request->get_param( 'content' ),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta fields.
		$this->save_meta_fields( $post_id, $request );

		// Save relationships.
		$this->save_relationships( $post_id, $request );

		$post = get_post( $post_id );
		return $this->prepare_item( $post, $request->get_params() );
	}

	/**
	 * Save meta fields from request.
	 *
	 * @param int            $post_id
	 * @param \WP_REST_Request $request
	 */
	protected function save_meta_fields( int $post_id, \WP_REST_Request $request ): void {
		$fields = \StoryOS\Utils\storyos_get_fields( $this->cpt );
		$meta = $request->get_param( 'meta' ) ?? [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] ) {
				continue;
			}

			if ( isset( $meta[ $key ] ) && '' !== $meta[ $key ] ) {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $meta[ $key ] ) );
			}
		}
	}

	/**
	 * Save relationships from request.
	 *
	 * @param int            $post_id
	 * @param \WP_REST_Request $request
	 */
	protected function save_relationships( int $post_id, \WP_REST_Request $request ): void {
		$fields = \StoryOS\Utils\storyos_get_fields( $this->cpt );
		$meta = $request->get_param( 'meta' ) ?? [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] && isset( $meta[ $key ] ) ) {
				\StoryOS\Utils\add_relationship(
					$post_id,
					$this->cpt,
					absint( $meta[ $key ] ),
					$field['related_cpt'],
					'belongs_to'
				);
			}
		}
	}

	/**
	 * Get a single item.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		if ( ! $this->check_read_permission( $request ) ) {
			return new WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 401 ] );
		}

		$post = get_post( absint( $request->get_param( 'id' ) ) );

		if ( ! $post || $post->post_type !== $this->cpt ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}

		return $this->prepare_item( $post, $request->get_params() );
	}

	/**
	 * Get all items.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		if ( ! $this->check_read_permission( $request ) ) {
			return new WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 401 ] );
		}

		$args = [
			'post_type'   => $this->cpt,
			'post_status' => 'any',
			'posts_per_page' => absint( $request->get_param( 'per_page' ) ) ?: 10,
			'paged'       => absint( $request->get_param( 'page' ) ) ?: 1,
		];

		$tax_query = [];

		// Filter by status taxonomy if provided.
		$status = $request->get_param( 'status' );
		if ( $status ) {
			$tax_query[] = [
				'taxonomy' => 'storyos_status',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', is_array( $status ) ? $status : explode( ',', (string) $status ) ),
			];
		}

		// Shared taxonomy filters used by StoryOS resources.
		$taxonomy_filters = [
			'character_role' => 'storyos_character_role',
			'sequence'       => 'storyos_sequence',
		];

		foreach ( $taxonomy_filters as $request_key => $taxonomy ) {
			$value = $request->get_param( $request_key );
			if ( empty( $value ) ) {
				continue;
			}

			$terms = is_array( $value ) ? $value : explode( ',', (string) $value );
			$terms = array_values( array_filter( array_map( 'sanitize_title', $terms ) ) );

			if ( empty( $terms ) ) {
				continue;
			}

			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
			];
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = [ 'relation' => 'AND' ];
			foreach ( $tax_query as $tax_clause ) {
				$args['tax_query'][] = $tax_clause;
			}
		}

		$query = new \WP_Query( $args );

		$items = [];
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$items[] = $this->prepare_item( $post, $request->get_params() );
			}
			wp_reset_postdata();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Update an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		if ( ! $this->check_update_permission( $request ) ) {
			return new WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 403 ] );
		}

		$post_id = absint( $request->get_param( 'id' ) );
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== $this->cpt ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}

		// Update post fields.
		$post_data = [
			'ID'         => $post_id,
			'post_title' => $request->get_param( 'title' ) ?: $post->post_title,
			'post_status'=> $request->get_param( 'status' ) ?: $post->post_status,
			'post_excerpt'=> $request->get_param( 'excerpt' ),
			'post_content'=> $request->get_param( 'content' ),
		];

		wp_update_post( $post_data );

		// Update meta fields.
		$this->save_meta_fields( $post_id, $request );

		// Update relationships.
		$this->save_relationships( $post_id, $request );

		$post = get_post( $post_id );
		return $this->prepare_item( $post, $request->get_params() );
	}

	/**
	 * Delete an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		if ( ! $this->check_delete_permission( $request ) ) {
			return new WP_Error( 'rest_forbidden', 'Unauthorized.', [ 'status' => 403 ] );
		}

		$post_id = absint( $request->get_param( 'id' ) );
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== $this->cpt ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}

		wp_delete_post( $post_id, true );

		return rest_ensure_response( [ 'message' => 'Deleted successfully.' ] );
	}
}
