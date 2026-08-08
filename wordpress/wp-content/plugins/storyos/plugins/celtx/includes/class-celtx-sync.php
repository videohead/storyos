<?php
/**
 * Celtx Sync Service.
 *
 * Handles synchronization between StoryOS elements and Celtx elements.
 *
 * @package StoryOSCeltx
 */

namespace StoryOSCeltx;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Celtx Sync class.
 *
 * Manages bidirectional sync between StoryOS and Celtx.
 */
class Sync {

	/**
	 * Sync instance.
	 *
	 * @var Sync|null
	 */
	private static $instance = null;

	/**
	 * Get the sync instance.
	 *
	 * @return Sync
	 */
	public static function init(): Sync {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register hooks.
		add_action( 'storyos_celtx_sync_project', [ $this, 'sync_project' ], 10, 2 );
		add_action( 'storyos_celtx_sync_character', [ $this, 'sync_character' ], 10, 2 );
		add_action( 'storyos_celtx_sync_location', [ $this, 'sync_location' ], 10, 2 );
		add_action( 'storyos_celtx_sync_scene', [ $this, 'sync_scene' ], 10, 2 );
		add_action( 'storyos_celtx_sync_shot', [ $this, 'sync_shot' ], 10, 2 );
		add_action( 'storyos_celtx_sync_all', [ $this, 'sync_all' ], 10, 0 );
	}

	/**
	 * Get the Celtx API client.
	 *
	 * @return \StoryOSCeltx\API\Client|null
	 */
	private function get_client(): ?\StoryOSCeltx\API\Client {
		return \StoryOSCeltx\API\Client::from_credentials();
	}

	/**
	 * Check if sync is possible.
	 *
	 * @return array {
	 *     @type bool   $success Whether sync is possible.
	 *     @type string $message Message explaining status.
	 * }
	 */
	private function can_sync(): array {
		if ( ! celtx_sync_enabled() ) {
			return [
				'success' => false,
				'message' => 'Celtx sync is not enabled or credentials are missing.',
			];
		}

		$client = $this->get_client();
		if ( ! $client ) {
			return [
				'success' => false,
				'message' => 'Could not initialize Celtx API client.',
			];
		}

		return [
			'success' => true,
			'message' => '',
		];
	}

	/**
	 * Get stored Celtx element IDs for a StoryOS post.
	 *
	 * @param int $post_id The StoryOS post ID.
	 * @return array
	 */
	public function get_celtx_mapping( int $post_id ): array {
		return get_post_meta( $post_id, '_storyos_celtx_mapping', true ) ?: [];
	}

	/**
	 * Store Celtx element IDs for a StoryOS post.
	 *
	 * @param int   $post_id The StoryOS post ID.
	 * @param array $mapping The mapping data.
	 */
	public function set_celtx_mapping( int $post_id, array $mapping ): void {
		update_post_meta( $post_id, '_storyos_celtx_mapping', $mapping );
	}

	/**
	 * Get the Celtx element ID for a StoryOS post by category.
	 *
	 * @param int    $post_id The StoryOS post ID.
	 * @param string $category The Celtx category (character, location, prop, scene).
	 * @return string|null
	 */
	public function get_celtx_element_id( int $post_id, string $category ): ?string {
		$mapping = $this->get_celtx_mapping( $post_id );
		return $mapping[ $category ]['element_id'] ?? null;
	}

	/**
	 * Sync a StoryOS Project to Celtx.
	 *
	 * @param int   $post_id The StoryOS project post ID.
	 * @param array $project_data Optional project data (defaults to fetching from post).
	 * @return array
	 */
	public function sync_project( int $post_id, array $project_data = [] ): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$client = $this->get_client();
		
		// Fetch project data if not provided.
		if ( empty( $project_data ) ) {
			$post = get_post( $post_id );
			if ( ! $post || 'storyos_project' !== $post->post_type ) {
				return [ 'success' => false, 'message' => 'Invalid StoryOS project post.' ];
			}

			$project_data = [
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'project_name' => get_post_meta( $post_id, 'project_name', true ),
				'description'  => get_post_meta( $post_id, 'description', true ),
				'genre'        => get_post_meta( $post_id, 'genre', true ),
				'target_medium'=> get_post_meta( $post_id, 'target_medium', true ),
				'status'       => get_post_meta( $post_id, 'production_stage', true ),
			];
		}

		// Check if already synced.
		$celtx_id = $this->get_celtx_element_id( $post_id, 'project' );
		
		$result = [
			'success'    => true,
			'action'     => 'created',
			'celtx_id'   => null,
			'mapping'    => [],
			'response'   => null,
		];

		if ( $celtx_id ) {
			// Update existing.
			$result['action'] = 'updated';
			$result['celtx_id'] = $celtx_id;
			$result['message'] = 'Project already exists in Celtx (ID: ' . esc_html( $celtx_id ) . '). Elements will be synced to this project.';
		} else {
			// Create new project in Celtx.
			$celtx_data = [
				'name' => $project_data['project_name'] ?? $project_data['post_title'] ?? '',
				'description' => $project_data['description'] ?? $project_data['post_content'] ?? '',
			];

			$response = $client->create_element( $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( $client->is_success( $response ) && ! empty( $parsed['body']['id'] ) ) {
				$result['celtx_id'] = $parsed['body']['id'];
				$result['mapping'] = [
					'project' => [
						'element_id' => $parsed['body']['id'],
						'synced_at'  => current_time( 'mysql' ),
					],
				];
				$this->set_celtx_mapping( $post_id, $result['mapping'] );
			} else {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to create project in Celtx.';
			}
		}

		return $result;
	}

	/**
	 * Sync a StoryOS Character to Celtx.
	 *
	 * @param int   $post_id The StoryOS character post ID.
	 * @param array $character_data Optional character data.
	 * @return array
	 */
	public function sync_character( int $post_id, array $character_data = [] ): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$client = $this->get_client();
		
		if ( empty( $character_data ) ) {
			$post = get_post( $post_id );
			if ( ! $post || 'storyos_character' !== $post->post_type ) {
				return [ 'success' => false, 'message' => 'Invalid StoryOS character post.' ];
			}

			$character_data = [
				'post_title'   => $post->post_title,
				'display_name' => get_post_meta( $post_id, 'display_name', true ),
				'biography'    => get_post_meta( $post_id, 'biography', true ),
				'age'          => get_post_meta( $post_id, 'age', true ),
				'appearance'   => get_post_meta( $post_id, 'appearance', true ),
				'personality'  => get_post_meta( $post_id, 'personality', true ),
				'motivation'   => get_post_meta( $post_id, 'motivation', true ),
				'backstory'    => get_post_meta( $post_id, 'backstory', true ),
				'voice_profile'=> get_post_meta( $post_id, 'voice_profile', true ),
			];
		}

		$celtx_id = $this->get_celtx_element_id( $post_id, 'character' );
		
		$result = [
			'success'    => true,
			'action'     => 'created',
			'celtx_id'   => null,
			'mapping'    => [],
		];

		if ( $celtx_id ) {
			// Update existing character.
			$result['action'] = 'updated';
			$result['celtx_id'] = $celtx_id;

			$celtx_data = $this->build_character_data( $character_data );
			$response = $client->update_element( $celtx_id, $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( ! $client->is_success( $response ) ) {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to update character in Celtx.';
			}
		} else {
			// Create new character.
			$celtx_data = $this->build_character_data( $character_data );
			$response = $client->create_element( $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( $client->is_success( $response ) && ! empty( $parsed['body']['id'] ) ) {
				$result['celtx_id'] = $parsed['body']['id'];
				$result['mapping'] = [
					'character' => [
						'element_id' => $parsed['body']['id'],
						'synced_at'  => current_time( 'mysql' ),
					],
				];
				$this->set_celtx_mapping( $post_id, $result['mapping'] );
			} else {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to create character in Celtx.';
			}
		}

		return $result;
	}

	/**
	 * Build Celtx character data from StoryOS character.
	 *
	 * @param array $data The StoryOS character data.
	 * @return array
	 */
	private function build_character_data( array $data ): array {
		return [
			'name'        => $data['display_name'] ?? $data['post_title'] ?? '',
			'category'    => 'character',
			'description' => $data['biography'] ?? $data['backstory'] ?? '',
			'attributes'  => [
				'age'           => $data['age'] ?? null,
				'appearance'    => $data['appearance'] ?? null,
				'personality'   => $data['personality'] ?? null,
				'motivation'    => $data['motivation'] ?? null,
				'voice_profile' => $data['voice_profile'] ?? null,
			],
		];
	}

	/**
	 * Sync a StoryOS Location to Celtx.
	 *
	 * @param int   $post_id The StoryOS location post ID.
	 * @param array $location_data Optional location data.
	 * @return array
	 */
	public function sync_location( int $post_id, array $location_data = [] ): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$client = $this->get_client();
		
		if ( empty( $location_data ) ) {
			$post = get_post( $post_id );
			if ( ! $post || 'storyos_location' !== $post->post_type ) {
				return [ 'success' => false, 'message' => 'Invalid StoryOS location post.' ];
			}

			$location_data = [
				'post_title'      => $post->post_title,
				'location_name'   => get_post_meta( $post_id, 'location_name', true ),
				'description'     => get_post_meta( $post_id, 'description', true ),
				'environment_type'=> get_post_meta( $post_id, 'environment_type', true ),
				'geography'       => get_post_meta( $post_id, 'geography', true ),
				'mood'            => get_post_meta( $post_id, 'mood', true ),
			];
		}

		$celtx_id = $this->get_celtx_element_id( $post_id, 'location' );
		
		$result = [
			'success'    => true,
			'action'     => 'created',
			'celtx_id'   => null,
			'mapping'    => [],
		];

		if ( $celtx_id ) {
			// Update existing location.
			$result['action'] = 'updated';
			$result['celtx_id'] = $celtx_id;

			$celtx_data = $this->build_location_data( $location_data );
			$response = $client->update_element( $celtx_id, $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( ! $client->is_success( $response ) ) {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to update location in Celtx.';
			}
		} else {
			// Create new location.
			$celtx_data = $this->build_location_data( $location_data );
			$response = $client->create_element( $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( $client->is_success( $response ) && ! empty( $parsed['body']['id'] ) ) {
				$result['celtx_id'] = $parsed['body']['id'];
				$result['mapping'] = [
					'location' => [
						'element_id' => $parsed['body']['id'],
						'synced_at'  => current_time( 'mysql' ),
					],
				];
				$this->set_celtx_mapping( $post_id, $result['mapping'] );
			} else {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to create location in Celtx.';
			}
		}

		return $result;
	}

	/**
	 * Build Celtx location data from StoryOS location.
	 *
	 * @param array $data The StoryOS location data.
	 * @return array
	 */
	private function build_location_data( array $data ): array {
		return [
			'name'        => $data['location_name'] ?? $data['post_title'] ?? '',
			'category'    => 'location',
			'description' => $data['description'] ?? '',
			'attributes'  => [
				'environment_type' => $data['environment_type'] ?? null,
				'geography'        => $data['geography'] ?? null,
				'mood'             => $data['mood'] ?? null,
			],
		];
	}

	/**
	 * Sync a StoryOS Scene to Celtx.
	 *
	 * @param int   $post_id The StoryOS scene post ID.
	 * @param array $scene_data Optional scene data.
	 * @return array
	 */
	public function sync_scene( int $post_id, array $scene_data = [] ): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$client = $this->get_client();
		
		if ( empty( $scene_data ) ) {
			$post = get_post( $post_id );
			if ( ! $post || 'storyos_scene' !== $post->post_type ) {
				return [ 'success' => false, 'message' => 'Invalid StoryOS scene post.' ];
			}

			$scene_data = [
				'post_title'     => $post->post_title,
				'scene_number'   => get_post_meta( $post_id, 'scene_number', true ),
				'title'          => get_post_meta( $post_id, 'title', true ),
				'summary'        => get_post_meta( $post_id, 'summary', true ),
				'script_content' => get_post_meta( $post_id, 'script_content', true ),
				'time_of_day'    => get_post_meta( $post_id, 'time_of_day', true ),
				'emotional_tone' => get_post_meta( $post_id, 'emotional_tone', true ),
			];
		}

		$celtx_id = $this->get_celtx_element_id( $post_id, 'scene' );
		
		$result = [
			'success'    => true,
			'action'     => 'created',
			'celtx_id'   => null,
			'mapping'    => [],
		];

		if ( $celtx_id ) {
			// Update existing scene.
			$result['action'] = 'updated';
			$result['celtx_id'] = $celtx_id;

			$celtx_data = $this->build_scene_data( $scene_data );
			$response = $client->update_scene( 'placeholder_episode', $celtx_id, $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( ! $client->is_success( $response ) ) {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to update scene in Celtx.';
			}
		} else {
			// Create new scene.
			$celtx_data = $this->build_scene_data( $scene_data );
			$response = $client->create_scene( 'placeholder_episode', $celtx_data );
			$parsed = $client->parse_response( $response );

			if ( $client->is_success( $response ) && ! empty( $parsed['body']['id'] ) ) {
				$result['celtx_id'] = $parsed['body']['id'];
				$result['mapping'] = [
					'scene' => [
						'element_id' => $parsed['body']['id'],
						'synced_at'  => current_time( 'mysql' ),
					],
				];
				$this->set_celtx_mapping( $post_id, $result['mapping'] );
			} else {
				$result['success'] = false;
				$result['message'] = $parsed['error'] ?? 'Failed to create scene in Celtx.';
			}
		}

		return $result;
	}

	/**
	 * Build Celtx scene data from StoryOS scene.
	 *
	 * @param array $data The StoryOS scene data.
	 * @return array
	 */
	private function build_scene_data( array $data ): array {
		return [
			'title'       => $data['title'] ?? $data['post_title'] ?? '',
			'scene_number'=> $data['scene_number'] ?? null,
			'summary'     => $data['summary'] ?? $data['script_content'] ?? '',
			'time_of_day' => $data['time_of_day'] ?? null,
			'attributes'  => [
				'emotional_tone' => $data['emotional_tone'] ?? null,
			],
		];
	}

	/**
	 * Sync a StoryOS Shot to Celtx.
	 *
	 * Note: Celtx doesn't have a direct shot-level concept, so shots are
	 * stored as scene attributes or comments on the parent scene.
	 *
	 * @param int   $post_id The StoryOS shot post ID.
	 * @param array $shot_data Optional shot data.
	 * @return array
	 */
	public function sync_shot( int $post_id, array $shot_data = [] ): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$client = $this->get_client();
		
		if ( empty( $shot_data ) ) {
			$post = get_post( $post_id );
			if ( ! $post || 'storyos_shot' !== $post->post_type ) {
				return [ 'success' => false, 'message' => 'Invalid StoryOS shot post.' ];
			}

			$shot_data = [
				'post_title'       => $post->post_title,
				'shot_number'      => get_post_meta( $post_id, 'shot_number', true ),
				'shot_type'        => get_post_meta( $post_id, 'shot_type', true ),
				'camera_angle'     => get_post_meta( $post_id, 'camera_angle', true ),
				'lens'             => get_post_meta( $post_id, 'lens', true ),
				'duration'         => get_post_meta( $post_id, 'duration', true ),
				'shot_description' => get_post_meta( $post_id, 'shot_description', true ),
				'scene_id'         => get_post_meta( $post_id, 'scene', true ),
			];
		}

		// Shots are stored as comments on the parent scene in Celtx.
		$scene_id = $shot_data['scene_id'] ?? null;
		if ( ! $scene_id ) {
			return [ 'success' => false, 'message' => 'Shot has no parent scene to sync to.' ];
		}

		$celtx_scene_id = $this->get_celtx_element_id( $scene_id, 'scene' );
		if ( ! $celtx_scene_id ) {
			return [ 'success' => false, 'message' => 'Parent scene not synced to Celtx yet.' ];
		}

		$shot_text = sprintf(
			'Shot #%s | Type: %s | Angle: %s | Lens: %s | Duration: %s',
			$shot_data['shot_number'] ?? '?',
			$shot_data['shot_type'] ?? 'N/A',
			$shot_data['camera_angle'] ?? 'N/A',
			$shot_data['lens'] ?? 'N/A',
			$shot_data['duration'] ?? 'N/A'
		);

		if ( ! empty( $shot_data['shot_description'] ) ) {
			$shot_text .= "\n\n" . $shot_data['shot_description'];
		}

		$response = $client->add_comment( $celtx_scene_id, [ 'text' => $shot_text ] );
		$parsed = $client->parse_response( $response );

		return [
			'success' => $client->is_success( $response ),
			'action'  => 'shot_comment_added',
			'celtx_id' => $celtx_scene_id,
			'message' => $parsed['error'] ?? ( $client->is_success( $response ) ? 'Shot added as scene comment.' : 'Failed to add shot comment.' ),
		];
	}

	/**
	 * Sync all StoryOS elements to Celtx.
	 *
	 * @return array
	 */
	public function sync_all(): array {
		$check = $this->can_sync();
		if ( ! $check['success'] ) {
			return [ 'success' => false, 'message' => $check['message'] ];
		}

		$results = [
			'projects'  => [],
			'characters'=> [],
			'locations' => [],
			'scenes'    => [],
			'shots'     => [],
			'total'     => 0,
			'successes' => 0,
			'failures'  => 0,
		];

		// Sync projects.
		$projects = get_posts( [
			'post_type'   => 'storyos_project',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $projects as $project ) {
			$results['projects'][ $project->ID ] = $this->sync_project( $project->ID );
			$results['total']++;
			if ( $results['projects'][ $project->ID ]['success'] ) {
				$results['successes']++;
			} else {
				$results['failures']++;
			}
		}

		// Sync characters.
		$characters = get_posts( [
			'post_type'   => 'storyos_character',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $characters as $character ) {
			$results['characters'][ $character->ID ] = $this->sync_character( $character->ID );
			$results['total']++;
			if ( $results['characters'][ $character->ID ]['success'] ) {
				$results['successes']++;
			} else {
				$results['failures']++;
			}
		}

		// Sync locations.
		$locations = get_posts( [
			'post_type'   => 'storyos_location',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $locations as $location ) {
			$results['locations'][ $location->ID ] = $this->sync_location( $location->ID );
			$results['total']++;
			if ( $results['locations'][ $location->ID ]['success'] ) {
				$results['successes']++;
			} else {
				$results['failures']++;
			}
		}

		// Sync scenes.
		$scenes = get_posts( [
			'post_type'   => 'storyos_scene',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $scenes as $scene ) {
			$results['scenes'][ $scene->ID ] = $this->sync_scene( $scene->ID );
			$results['total']++;
			if ( $results['scenes'][ $scene->ID ]['success'] ) {
				$results['successes']++;
			} else {
				$results['failures']++;
			}
		}

		// Sync shots.
		$shots = get_posts( [
			'post_type'   => 'storyos_shot',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $shots as $shot ) {
			$results['shots'][ $shot->ID ] = $this->sync_shot( $shot->ID );
			$results['total']++;
			if ( $results['shots'][ $shot->ID ]['success'] ) {
				$results['successes']++;
			} else {
				$results['failures']++;
			}
		}

		return $results;
	}

	/**
	 * Unsync a StoryOS element from Celtx (remove local mapping).
	 *
	 * @param int    $post_id The StoryOS post ID.
	 * @param string $category The Celtx category.
	 * @return bool
	 */
	public function unsync( int $post_id, string $category ): bool {
		$mapping = $this->get_celtx_mapping( $post_id );
		if ( isset( $mapping[ $category ] ) ) {
			unset( $mapping[ $category ] );
			update_post_meta( $post_id, '_storyos_celtx_mapping', $mapping );
			return true;
		}
		return false;
	}
}
