<?php
/**
 * Base REST API Controller for World Graph Studio.
 *
 * Provides common functionality for all World Graph Studio REST API controllers.
 *
 * @package WorldGraph
 */

namespace WorldGraph\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base controller class for World Graph Studio REST API endpoints.
 */
abstract class Base_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'worldgraph/v1';

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
		$fields = \WorldGraph\Utils\worldgraph_get_fields( $post->post_type );
		$meta = [];

		foreach ( $fields as $key => $field ) {
			if ( in_array( $field['type'], [ 'relationship', 'taxonomy' ], true ) ) {
				continue;
			}
			$value = \WorldGraph\Utils\worldgraph_get_field_value( $post->ID, $key );
			if ( '' !== $value ) {
				$meta[ $key ] = $value;
			}
		}

		// Get relationships the current user may read at both ends.
		$relationships = array_values(
			array_filter(
				\WorldGraph\Utils\get_relationships( $post->ID, $post->post_type, 'outgoing' ),
				static function( array $relationship ): bool {
					$target_id = absint( $relationship['to_id'] ?? 0 );
					return $target_id > 0 && current_user_can( 'read_post', $target_id );
				}
			)
		);

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

		$schema_type = \WorldGraph\Utils\worldgraph_schema_type_for_entity( $post->post_type, $meta, $taxonomies );
		$schema_field_map = \WorldGraph\Utils\worldgraph_schema_field_map()[ $post->post_type ] ?? [];
		$schema_hints = \WorldGraph\Utils\worldgraph_schema_hints_from_meta( $post->post_type, $meta );

		if ( '' !== $post->post_title ) {
			$schema_hints['name'] = $post->post_title;
		}

		if ( '' !== $post->post_content && empty( $schema_hints['description'] ) ) {
			$schema_hints['description'] = wp_strip_all_tags( $post->post_content );
		}

		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		$thumbnail_id = $thumbnail_id && current_user_can( 'read_post', $thumbnail_id ) ? $thumbnail_id : 0;
		$featured_image = $thumbnail_id ? [
			'id'            => $thumbnail_id,
			'url'           => wp_get_attachment_url( $thumbnail_id ),
			'thumbnail_url' => wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ),
			'alt'           => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
		] : null;
		$gallery_ids = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_worldgraph_asset_gallery_ids', true ) ) ) );
		$asset_gallery = array_values( array_filter( array_map( static function ( int $attachment_id ): ?array {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) ) {
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

		$external_id = (string) get_post_meta( $post->ID, 'external_id', true );
		$schema_relationships = array_map(
			static function( array $rel ) use ( $post, $external_id ) {
				$rel['schema_property'] = \WorldGraph\Utils\worldgraph_schema_property_for_relationship(
					(string) ( $rel['type'] ?? '' ),
					$post->post_type,
					(string) ( $rel['to_type'] ?? '' )
				);
				$rel['from_external_id'] = $external_id;
				$rel['to_external_id']   = (string) get_post_meta( absint( $rel['to_id'] ?? 0 ), 'external_id', true );

				return $rel;
			},
			$relationships
		);

		return [
			'id'           => $post->ID,
			'external_id'  => $external_id,
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
		$relationships = \WorldGraph\Utils\get_relationships( $post->ID, $post->post_type, 'outgoing' );
		if ( ! empty( $relationships ) ) {
			$links['relationships'] = [];
			foreach ( $relationships as $rel ) {
				$target_id = absint( $rel['to_id'] ?? 0 );
				if ( ! $target_id || ! current_user_can( 'read_post', $target_id ) ) {
					continue;
				}
				$links['relationships'][] = [
					'to_id'   => $target_id,
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

		$post_id = absint( $request->get_param( 'id' ) );
		if ( ! $this->cpt || ! $post_id ) {
			return true;
		}

		$post = get_post( $post_id );
		if ( ! $post || ( $this->cpt && $this->cpt !== $post->post_type ) ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot read this post.', [ 'status' => 403 ] );
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
		$post_type  = $this->cpt ? get_post_type_object( $this->cpt ) : null;
		$capability = $post_type && ! empty( $post_type->cap->create_posts ) ? $post_type->cap->create_posts : 'edit_posts';
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in with edit permissions.', [ 'status' => 403 ] );
		}

		return $post_type ? $this->check_requested_status_permission( $request ) : true;
	}

	/**
	 * Check permissions for updating an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_update_permission( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		if ( ! $post_id ) {
			return $this->check_create_permission( $request );
		}

		$post = get_post( $post_id );
		if ( ! $post || ( $this->cpt && $this->cpt !== $post->post_type ) ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot edit this post.', [ 'status' => 403 ] );
		}

		return $this->check_requested_status_permission( $request, $post );
	}

	/**
	 * Validate a requested post lifecycle state and publishing capability.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param \WP_Post|null    $post    Existing post for updates.
	 * @return true|WP_Error
	 */
	protected function check_requested_status_permission( \WP_REST_Request $request, ?\WP_Post $post = null ) {
		$raw_status = $request->get_param( 'status' );
		if ( null === $raw_status || '' === $raw_status ) {
			return true;
		}

		$status = sanitize_key( (string) $raw_status );
		if ( ! in_array( $status, [ 'draft', 'pending', 'publish', 'private' ], true ) ) {
			return new WP_Error( 'rest_invalid_status', 'Unsupported post status.', [ 'status' => 400 ] );
		}

		$current_status = $post ? (string) $post->post_status : '';
		if ( $status === $current_status || ! in_array( $status, [ 'publish', 'private' ], true ) ) {
			return true;
		}

		$post_type = get_post_type_object( $post ? $post->post_type : $this->cpt );
		if ( ! $post_type || empty( $post_type->cap->publish_posts ) || ! current_user_can( $post_type->cap->publish_posts ) ) {
			return new WP_Error( 'rest_cannot_publish', 'You cannot publish this post.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Check permissions for deleting an item.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_delete_permission( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		if ( ! $post_id ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'delete_posts' ) ) {
				return new WP_Error( 'rest_forbidden', 'You must be logged in with delete permissions.', [ 'status' => 403 ] );
			}
			return true;
		}

		$post = get_post( $post_id );
		if ( ! $post || ( $this->cpt && $this->cpt !== $post->post_type ) ) {
			return new WP_Error( 'rest_post_not_found', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot delete this post.', [ 'status' => 403 ] );
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
		do_action( 'worldgraph_after_rest_entity_save', $post_id, $this->cpt, $request );

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
		$fields = \WorldGraph\Utils\worldgraph_get_fields( $this->cpt );
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
							$term = get_term( absint( $raw_term ), $taxonomy );
							if ( $term && ! is_wp_error( $term ) ) {
								$terms[] = (int) $term->term_id;
							}
							continue;
						}

						$slug = sanitize_title( (string) $raw_term );
						$term = get_term_by( 'slug', $slug, $taxonomy );
						if ( $term ) {
							$terms[] = (int) $term->term_id;
							continue;
						}

						$term_name = sanitize_text_field( (string) $raw_term );
						if ( '' === $term_name ) {
							continue;
						}

						$created = wp_insert_term( $term_name, $taxonomy, [ 'slug' => $slug ] );
						if ( ! is_wp_error( $created ) ) {
							$terms[] = (int) $created['term_id'];
						} elseif ( 'term_exists' === $created->get_error_code() ) {
							$terms[] = absint( $created->get_error_data() );
						}
					}

					$terms = array_values( array_unique( array_filter( $terms ) ) );
					\WorldGraph\Utils\worldgraph_update_field_value(
						$post_id,
						$key,
						! empty( $field['multiple'] ) ? $terms : ( $terms[0] ?? '' )
					);
				}
				continue;
			}

			if ( '' === $meta[ $key ] || null === $meta[ $key ] ) {
				\WorldGraph\Utils\worldgraph_delete_field_value( $post_id, $key );
			} else {
				\WorldGraph\Utils\worldgraph_update_field_value(
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
		$fields = \WorldGraph\Utils\worldgraph_get_fields( $this->cpt );
		$meta   = $request->get_param( 'meta' );
		$meta   = is_array( $meta ) ? $meta : [];

		foreach ( $fields as $key => $field ) {
			if ( 'relationship' === $field['type'] && array_key_exists( $key, $meta ) ) {
				$target_ids = is_array( $meta[ $key ] ) ? $meta[ $key ] : [ $meta[ $key ] ];
				$target_ids = array_values( array_filter( array_map( 'absint', $target_ids ) ) );
				$result = \WorldGraph\Utils\set_relationships_for_field(
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

				\WorldGraph\Utils\worldgraph_update_field_value(
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

		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 10 ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) ?: 1 );
		$args     = [
			'post_type'      => $this->cpt,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'nopaging'       => true,
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
				'taxonomy' => 'worldgraph_status',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', is_array( $status ) ? $status : explode( ',', (string) $status ) ),
			];
		}

		// Shared taxonomy filters used by World Graph Studio resources.
		$taxonomy_filters = [
			'character_role' => 'worldgraph_character_role',
			'sequence'       => 'worldgraph_sequence',
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

		$readable_posts = array_values(
			array_filter(
				$query->posts,
				static function( \WP_Post $post ): bool {
					return current_user_can( 'read_post', $post->ID );
				}
			)
		);
		$total          = count( $readable_posts );
		$page_posts     = array_slice( $readable_posts, ( $page - 1 ) * $per_page, $per_page );
		$items          = [];
		if ( ! empty( $page_posts ) ) {
			foreach ( $page_posts as $post ) {
				$items[] = $this->prepare_response_for_collection(
					$this->prepare_item( $post, $request->get_params() )
				);
			}
			wp_reset_postdata();
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

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

		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta fields.
		$this->save_meta_fields( $post_id, $request );

		// Update relationships.
		$relationship_result = $this->save_relationships( $post_id, $request );
		if ( is_wp_error( $relationship_result ) ) {
			return $relationship_result;
		}
		do_action( 'worldgraph_after_rest_entity_save', $post_id, $this->cpt, $request );

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
