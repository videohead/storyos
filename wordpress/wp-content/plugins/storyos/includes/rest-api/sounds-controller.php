<?php
/**
 * Sounds REST API Controller for StoryOS.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

/**
 * CRUD and graph access for planned soundtrack cues.
 */
class Sounds_Controller extends Base_Controller {

	/**
	 * CPT slug.
	 *
	 * @var string
	 */
	protected $cpt = 'storyos_sound';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'sounds';

	/**
	 * Initialize the controller.
	 */
	public static function init(): void {
		$instance = new self();
		add_action( 'rest_api_init', [ $instance, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/sounds',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_read_permission' ],
					'args'                => [
						'page'              => [ 'default' => 1, 'type' => 'integer', 'minimum' => 1 ],
						'per_page'          => [ 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ],
						'scene'             => [ 'type' => 'integer' ],
						'shot'              => [ 'type' => 'integer' ],
						'sound_type'        => [ 'type' => 'string' ],
						'production_status' => [ 'type' => 'string' ],
						'status'            => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'publish', 'private' ] ],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_create_permission' ],
					'args'                => [
						'title'  => [ 'type' => 'string', 'required' => true, 'minLength' => 1 ],
						'meta'   => [ 'type' => 'object', 'required' => true ],
						'status' => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'publish', 'private' ] ],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/sounds/(?P<id>\d+)',
			[
				'args' => [ 'id' => [ 'type' => 'integer' ] ],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_read_permission' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_update_permission' ],
					'args'                => [
						'meta'   => [ 'type' => 'object' ],
						'status' => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'publish', 'private' ] ],
					],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_delete_permission' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/sounds/(?P<id>\d+)/graph',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_graph' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			]
		);
	}

	/**
	 * Validate required cue relationships before creation.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$valid = $this->validate_sound_request( $request, true );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return parent::create_item( $request );
	}

	/**
	 * Validate relationship changes before updating a cue.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$valid = $this->validate_sound_request( $request, false );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return parent::update_item( $request );
	}

	/**
	 * Restrict direct reads to posts the current user may read.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_read_permission( \WP_REST_Request $request ) {
		$permission = parent::check_read_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$post_id = absint( $request->get_param( 'id' ) );
		if ( $post_id && ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'You cannot read this Sound.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Require edit capability and prevent unauthorized publishing.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_create_permission( \WP_REST_Request $request ) {
		$permission = parent::check_create_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		return $this->check_status_permission( $request );
	}

	/**
	 * Require permission for the specific Sound being updated.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_update_permission( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || $this->cpt !== $post->post_type ) {
			return new \WP_Error( 'rest_post_not_found', 'Sound not found.', [ 'status' => 404 ] );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'You cannot edit this Sound.', [ 'status' => 403 ] );
		}

		return $this->check_status_permission( $request );
	}

	/**
	 * Require delete permission for the specific Sound.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_delete_permission( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || $this->cpt !== $post->post_type ) {
			return new \WP_Error( 'rest_post_not_found', 'Sound not found.', [ 'status' => 404 ] );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'You cannot delete this Sound.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * List sounds with functional taxonomy and relationship filters.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$permission = $this->check_read_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$args = [
			'post_type'      => $this->cpt,
			'post_status'    => $request->get_param( 'status' ) ?: 'any',
			'posts_per_page' => -1,
			'orderby'        => [
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			],
		];

		$tax_query = [];
		foreach ( [ 'sound_type' => 'storyos_sound_type', 'production_status' => 'storyos_status' ] as $parameter => $taxonomy ) {
			$value = $request->get_param( $parameter );
			if ( empty( $value ) ) {
				continue;
			}

			$terms = array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) $value ) ) ) );
			if ( ! empty( $terms ) ) {
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
				];
			}
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = array_merge( [ 'relation' => 'AND' ], $tax_query );
		}

		$query    = new \WP_Query( $args );
		$scene_id = absint( $request->get_param( 'scene' ) );
		$shot_id  = absint( $request->get_param( 'shot' ) );
		$posts    = array_values(
			array_filter(
				$query->posts,
				function( \WP_Post $post ) use ( $scene_id, $shot_id ): bool {
					if ( ! current_user_can( 'read_post', $post->ID ) ) {
						return false;
					}

					if ( $scene_id && ! $this->has_relationship( $post->ID, $scene_id, 'storyos_scene' ) ) {
						return false;
					}

					if ( $shot_id && ! $this->has_relationship( $post->ID, $shot_id, 'storyos_shot' ) ) {
						return false;
					}

					return true;
				}
			)
		);

		$total    = count( $posts );
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 10 ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) ?: 1 );
		$posts    = array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
		$items    = [];

		foreach ( $posts as $post ) {
			$prepared = $this->prepare_item( $post, $request->get_params() );
			$items[]  = rest_get_server()->response_to_data( $prepared, false );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Return graph entities connected to one sound.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_graph( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || $this->cpt !== $post->post_type ) {
			return new \WP_Error( 'rest_post_not_found', 'Sound not found.', [ 'status' => 404 ] );
		}

		$permission = $this->check_read_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$entities = \StoryOS\Utils\get_graph_entities( $post_id, $this->cpt );
		return rest_ensure_response( $entities );
	}

	/**
	 * Add convenient sound-type metadata to the standard resource response.
	 *
	 * @param \WP_Post $post   Sound post.
	 * @param array    $params Request parameters.
	 * @return array
	 */
	protected function get_item_data( \WP_Post $post, array $params = [] ): array {
		$data     = parent::get_item_data( $post, $params );
		$types    = get_the_terms( $post->ID, 'storyos_sound_type' );
		$statuses = get_the_terms( $post->ID, 'storyos_status' );

		if ( $types && ! is_wp_error( $types ) ) {
			$data['meta']['sound_type'] = (string) $types[0]->slug;
		}
		if ( $statuses && ! is_wp_error( $statuses ) ) {
			$data['meta']['production_status'] = (string) $statuses[0]->slug;
		}

		return $data;
	}

	/**
	 * Build links with public StoryOS route bases instead of CPT slugs.
	 *
	 * @param \WP_Post $post Sound post.
	 * @return array
	 */
	protected function prepare_links( \WP_Post $post ): array {
		$links       = parent::prepare_links( $post );
		$route_bases = [
			'storyos_project'   => 'projects',
			'storyos_scene'     => 'scenes',
			'storyos_shot'      => 'shots',
			'storyos_character' => 'characters',
			'storyos_asset'     => 'assets',
			'storyos_sound'     => 'sounds',
		];

		foreach ( $links['relationships'] ?? [] as $index => $relationship ) {
			$route_base = $route_bases[ $relationship['to_type'] ] ?? $relationship['to_type'];
			$links['relationships'][ $index ]['href'] = rest_url( "{$this->namespace}/{$route_base}/{$relationship['to_id']}" );
		}

		return $links;
	}

	/**
	 * Validate Sound request fields and relationship targets.
	 *
	 * @param \WP_REST_Request $request  Request object.
	 * @param bool             $creating Whether a new Sound is being created.
	 * @return true|\WP_Error
	 */
	private function validate_sound_request( \WP_REST_Request $request, bool $creating ) {
		$meta = $request->get_param( 'meta' );
		$meta = is_array( $meta ) ? $meta : [];

		$title = $request->get_param( 'title' );
		if ( $creating && ( ! is_scalar( $title ) || '' === trim( (string) $title ) ) ) {
			return new \WP_Error( 'storyos_sound_title_required', 'A Sound title is required.', [ 'status' => 400 ] );
		}

		foreach ( [ 'spoken_text', 'lyrics', 'start_timecode', 'duration', 'diegetic', 'production_notes', 'scene', 'shot', 'character', 'asset' ] as $field ) {
			if ( array_key_exists( $field, $meta ) && null !== $meta[ $field ] && ! is_scalar( $meta[ $field ] ) ) {
				return new \WP_Error( 'storyos_sound_field_invalid', sprintf( 'The %s field must be a scalar value.', $field ), [ 'status' => 400 ] );
			}
		}

		if ( array_key_exists( 'diegetic', $meta ) && ! in_array( (string) $meta['diegetic'], [ '', 'unspecified', 'diegetic', 'non_diegetic', 'internal', 'mixed' ], true ) ) {
			return new \WP_Error( 'storyos_sound_diegetic_invalid', 'The diegetic value is invalid.', [ 'status' => 400 ] );
		}

		if ( ( $creating || array_key_exists( 'sound_type', $meta ) ) && empty( $meta['sound_type'] ) ) {
			return new \WP_Error( 'storyos_sound_type_required', 'A sound_type is required.', [ 'status' => 400 ] );
		}

		$sound_type_term = null;
		if ( array_key_exists( 'sound_type', $meta ) ) {
			if ( ! is_scalar( $meta['sound_type'] ) ) {
				return new \WP_Error( 'storyos_sound_type_invalid', 'A Sound has exactly one sound_type.', [ 'status' => 400 ] );
			}

			$term = is_numeric( $meta['sound_type'] )
				? get_term( absint( $meta['sound_type'] ), 'storyos_sound_type' )
				: get_term_by( 'slug', sanitize_title( (string) $meta['sound_type'] ), 'storyos_sound_type' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new \WP_Error( 'storyos_sound_type_invalid', 'The sound_type must be an existing Sound Type term.', [ 'status' => 400 ] );
			}

			if ( \StoryOS\Utils\storyos_is_reserved_sound_type( $term ) ) {
				return new \WP_Error( 'storyos_sound_type_reserved', 'Ordinary dialogue belongs in Scene dialogue metadata.', [ 'status' => 400 ] );
			}
			$sound_type_term = $term;
		} elseif ( ! $creating ) {
			$current_types = get_the_terms( absint( $request->get_param( 'id' ) ), 'storyos_sound_type' );
			$sound_type_term = ( $current_types && ! is_wp_error( $current_types ) ) ? $current_types[0] : null;
		}

		if ( array_key_exists( 'production_status', $meta ) ) {
			if ( ! is_scalar( $meta['production_status'] ) ) {
				return new \WP_Error( 'storyos_sound_status_invalid', 'A Sound has at most one production_status.', [ 'status' => 400 ] );
			}
			if ( '' !== (string) $meta['production_status'] ) {
				$status_term = is_numeric( $meta['production_status'] )
					? get_term( absint( $meta['production_status'] ), 'storyos_status' )
					: get_term_by( 'slug', sanitize_title( (string) $meta['production_status'] ), 'storyos_status' );
				if ( ! $status_term || is_wp_error( $status_term ) ) {
					return new \WP_Error( 'storyos_sound_status_invalid', 'The production_status must be an existing Status term.', [ 'status' => 400 ] );
				}
			}
		}

		$scene_supplied = array_key_exists( 'scene', $meta );
		$scene_id       = $scene_supplied ? absint( $meta['scene'] ) : 0;
		if ( ! $scene_supplied && ! $creating ) {
			$scene_id = $this->current_relationship_target( absint( $request->get_param( 'id' ) ), 'scene' );
		}

		if ( ! $scene_id ) {
			return new \WP_Error( 'storyos_sound_scene_required', 'A Sound must belong to a Scene.', [ 'status' => 400 ] );
		}

		$targets = [
			'scene'     => 'storyos_scene',
			'shot'      => 'storyos_shot',
			'character' => 'storyos_character',
			'asset'     => 'storyos_asset',
		];
		foreach ( $targets as $field => $post_type ) {
			if ( ! array_key_exists( $field, $meta ) || ! absint( $meta[ $field ] ) ) {
				continue;
			}

			$target = get_post( absint( $meta[ $field ] ) );
			if ( ! $target || $post_type !== $target->post_type ) {
				return new \WP_Error( 'storyos_sound_invalid_relationship', sprintf( 'The %s relationship is invalid.', $field ), [ 'status' => 400 ] );
			}

			if ( 'asset' === $field && ! \StoryOS\Utils\storyos_is_audio_asset( $target->ID ) ) {
				return new \WP_Error( 'storyos_sound_asset_not_audio', 'The rendered Asset must have the Audio asset type.', [ 'status' => 400 ] );
			}
		}

		if ( ! empty( $meta['lyrics'] ) && ( ! $sound_type_term || 'music' !== $sound_type_term->slug ) ) {
			return new \WP_Error( 'storyos_sound_lyrics_music_only', 'Lyrics may only be stored on a Music Sound.', [ 'status' => 400 ] );
		}

		$shot_id = array_key_exists( 'shot', $meta )
			? absint( $meta['shot'] )
			: ( $creating ? 0 : $this->current_relationship_target( absint( $request->get_param( 'id' ) ), 'shot' ) );
		if ( $shot_id && ! $this->shot_belongs_to_scene( $shot_id, $scene_id ) ) {
			return new \WP_Error( 'storyos_sound_shot_scene_mismatch', 'The selected Shot does not belong to the selected Scene.', [ 'status' => 400 ] );
		}

		return true;
	}

	/**
	 * Check whether a sound has an outgoing relationship to a target.
	 *
	 * @param int    $sound_id   Sound ID.
	 * @param int    $target_id  Target ID.
	 * @param string $target_cpt Target CPT.
	 * @return bool
	 */
	private function has_relationship( int $sound_id, int $target_id, string $target_cpt ): bool {
		foreach ( \StoryOS\Utils\get_relationships( $sound_id, $this->cpt, 'outgoing' ) as $relationship ) {
			$field = 'storyos_scene' === $target_cpt ? 'scene' : 'shot';
			if (
				$target_id === (int) ( $relationship['to_id'] ?? 0 ) &&
				$target_cpt === (string) ( $relationship['to_type'] ?? '' ) &&
				'belongs_to' === (string) ( $relationship['type'] ?? '' ) &&
				$field === (string) ( $relationship['metadata']['field'] ?? '' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a Sound relationship target by its field slot.
	 *
	 * @param int    $sound_id Sound ID.
	 * @param string $field    Relationship field.
	 * @return int
	 */
	private function current_relationship_target( int $sound_id, string $field ): int {
		foreach ( \StoryOS\Utils\get_relationships( $sound_id, $this->cpt, 'outgoing' ) as $relationship ) {
			if ( $field === (string) ( $relationship['metadata']['field'] ?? '' ) ) {
				return (int) ( $relationship['to_id'] ?? 0 );
			}
		}

		return 0;
	}

	/**
	 * Confirm a Shot and Scene are connected in either canonical graph direction.
	 *
	 * @param int $shot_id  Shot ID.
	 * @param int $scene_id Scene ID.
	 * @return bool
	 */
	private function shot_belongs_to_scene( int $shot_id, int $scene_id ): bool {
		foreach ( \StoryOS\Utils\get_relationships( $shot_id, 'storyos_shot', 'outgoing' ) as $relationship ) {
			if ( $scene_id === (int) ( $relationship['to_id'] ?? 0 ) && 'storyos_scene' === (string) ( $relationship['to_type'] ?? '' ) ) {
				return true;
			}
		}

		foreach ( \StoryOS\Utils\get_relationships( $scene_id, 'storyos_scene', 'outgoing' ) as $relationship ) {
			if ( $shot_id === (int) ( $relationship['to_id'] ?? 0 ) && 'storyos_shot' === (string) ( $relationship['to_type'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prevent roles without publishing capability from escalating post status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	private function check_status_permission( \WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( in_array( $status, [ 'publish', 'private' ], true ) && ! current_user_can( 'publish_posts' ) ) {
			return new \WP_Error( 'rest_cannot_publish', 'You cannot publish this Sound.', [ 'status' => 403 ] );
		}

		return true;
	}
}
