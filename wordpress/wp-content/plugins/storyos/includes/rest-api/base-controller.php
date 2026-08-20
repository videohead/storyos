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
			if ( in_array( $field['type'], [ 'relationship', 'taxonomy' ], true ) ) {
				continue;
			}
			$value = \StoryOS\Utils\storyos_get_field_value( $post->ID, $key );
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

		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		$featured_image = $thumbnail_id ? [
			'id'            => $thumbnail_id,
			'url'           => wp_get_attachment_url( $thumbnail_id ),
			'thumbnail_url' => wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ),
			'alt'           => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
		] : null;
		$gallery_ids = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_storyos_asset_gallery_ids', true ) ) ) );
		$asset_gallery = array_values( array_filter( array_map( static function ( int $attachment_id ): ?array {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				return null;
			}

			return [
				'id'            => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
				'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'mime_type'     => get_post_mime_type( $attachment_id ),
			];
		}, $gallery_ids ) ) );

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
			'menu_order'   => (int) $post->menu_order,
			'content'      => $post->post_content,
			'excerpt'      => $post->post_excerpt,
			'featured_image' => $featured_image,
			'asset_gallery'  => $asset_gallery,
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
		$permission = $this->check_create_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$meta = $request->get_param( 'meta' );
		$meta = is_array( $meta ) ? $meta : [];
		$post_data = [
			'post_type'   => $this->cpt,
			'post_title'  => $request->get_param( 'title' ) ?: ( $meta['display_name'] ?? 'Untitled' ),
			'post_status' => $request->get_param( 'status' ) ?: 'draft',
			'post_excerpt'=> (string) ( $request->get_param( 'excerpt' ) ?? '' ),
			'post_content'=> (string) ( $request->get_param( 'content' ) ?? '' ),
			'menu_order'  => absint( $request->get_param( 'menu_order' ) ),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta fields.
		$this->save_meta_fields( $post_id, $request );

		// Save relationships.
		$relationship_result = $this->save_relationships( $post_id, $request );
		if ( is_wp_error( $relationship_result ) ) {
			return $relationship_result;
		}
		do_action( 'storyos_after_rest_entity_save', $post_id, $this->cpt, $request );

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
		$meta   = $request->get_param( 'meta' );
		$meta   = is_array( $meta ) ? $meta : [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] || ! empty( $field['read_only'] ) ) {
				continue;
			}

			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}

			if ( 'taxonomy' === $field['type'] ) {
				$taxonomy = (string) ( $field['taxonomy'] ?? '' );
				if ( '' !== $taxonomy ) {
					$raw_terms = is_array( $meta[ $key ] ) ? $meta[ $key ] : [ $meta[ $key ] ];
					$terms     = [];
					foreach ( $raw_terms as $raw_term ) {
						if ( is_numeric( $raw_term ) ) {
							$terms[] = absint( $raw_term );
							continue;
						}

						$slug = sanitize_title( (string) $raw_term );
						$term = get_term_by( 'slug', $slug, $taxonomy );
						$terms[] = $term ? (int) $term->term_id : sanitize_text_field( (string) $raw_term );
					}
					wp_set_object_terms( $post_id, array_values( array_filter( $terms ) ), $taxonomy, false );
				}
				continue;
			}

			if ( '' === $meta[ $key ] || null === $meta[ $key ] ) {
				\StoryOS\Utils\storyos_delete_field_value( $post_id, $key );
			} else {
				\StoryOS\Utils\storyos_update_field_value(
					$post_id,
					$key,
					$meta[ $key ]
				);
			}
		}
	}

	/**
	 * Save relationships from request.
	 *
	 * @param int            $post_id
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	protected function save_relationships( int $post_id, \WP_REST_Request $request ) {
		$fields = \StoryOS\Utils\storyos_get_fields( $this->cpt );
		$meta   = $request->get_param( 'meta' );
		$meta   = is_array( $meta ) ? $meta : [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] && array_key_exists( $key, $meta ) ) {
				$target_ids = is_array( $meta[ $key ] ) ? $meta[ $key ] : [ $meta[ $key ] ];
				$target_ids = array_values( array_filter( array_map( 'absint', $target_ids ) ) );
				$result = \StoryOS\Utils\set_relationships_for_field(
					$post_id,
					$this->cpt,
					$target_ids,
					$field['related_cpt'],
					(string) ( $field['relationship_type'] ?? 'belongs_to' ),
					$key,
					! empty( $field['multiple'] )
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				\StoryOS\Utils\storyos_update_field_value(
					$post_id,
					$key,
					! empty( $field['multiple'] ) ? $target_ids : ( $target_ids[0] ?? '' )
				);
			}
		}

		return true;
	}

	/**
	 * Get a single item.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$permission = $this->check_read_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
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
		$permission = $this->check_read_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$args = [
			'post_type'   => $this->cpt,
			'post_status' => 'any',
			'posts_per_page' => absint( $request->get_param( 'per_page' ) ) ?: 10,
			'paged'       => absint( $request->get_param( 'page' ) ) ?: 1,
		];

		// Optional ordering (e.g. orderby=menu_order&order=ASC for editorial cuts).
		$orderby = $request->get_param( 'orderby' );
		if ( $orderby && in_array( (string) $orderby, [ 'date', 'title', 'menu_order', 'modified' ], true ) ) {
			$args['orderby'] = (string) $orderby;
		}
		$order = $request->get_param( 'order' );
		if ( $order && in_array( strtoupper( (string) $order ), [ 'ASC', 'DESC' ], true ) ) {
			$args['order'] = strtoupper( (string) $order );
		}

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
		$permission = $this->check_update_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
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
		];

		if ( null !== $request->get_param( 'excerpt' ) ) {
			$post_data['post_excerpt'] = (string) $request->get_param( 'excerpt' );
		}

		if ( null !== $request->get_param( 'content' ) ) {
			$post_data['post_content'] = (string) $request->get_param( 'content' );
		}

		if ( $request->get_param( 'menu_order' ) !== null ) {
			$post_data['menu_order'] = absint( $request->get_param( 'menu_order' ) );
		}

		wp_update_post( $post_data );

		// Update meta fields.
		$this->save_meta_fields( $post_id, $request );

		// Update relationships.
		$relationship_result = $this->save_relationships( $post_id, $request );
		if ( is_wp_error( $relationship_result ) ) {
			return $relationship_result;
		}
		do_action( 'storyos_after_rest_entity_save', $post_id, $this->cpt, $request );

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
		$permission = $this->check_delete_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
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
