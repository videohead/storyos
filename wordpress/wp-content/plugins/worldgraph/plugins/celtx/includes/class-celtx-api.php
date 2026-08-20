<?php
/**
 * Celtx API Client.
 *
 * Handles all communication with the Celtx GEM Bi-Directional API using WordPress native functions.
 *
 * @package WorldGraphCeltx
 */

namespace WorldGraphCeltx\API;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Celtx API Client class.
 *
 * Provides methods for making authenticated requests to the Celtx API.
 */
class Client {

	/**
	 * Base URL for the Celtx API.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://games-api.celtx.com/api';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Project ID.
	 *
	 * @var string
	 */
	private string $project_id;

	/**
	 * Constructor.
	 *
	 * @param string $api_key    The Celtx API key.
	 * @param string $project_id The Celtx project ID.
	 */
	public function __construct( string $api_key, string $project_id ) {
		$this->api_key    = $api_key;
		$this->project_id = $project_id;
	}

	/**
	 * Create a client instance from stored credentials.
	 *
	 * @return \WorldGraphCeltx\API\Client|null
	 */
	public static function from_credentials(): ?self {
		$creds = \WorldGraphCeltx\get_celtx_credentials();
		
		if ( empty( $creds['api_key'] ) || empty( $creds['project_id'] ) ) {
			return null;
		}

		return new self( $creds['api_key'], $creds['project_id'] );
	}

	/**
	 * Get the base URL.
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		return self::BASE_URL;
	}

	/**
	 * Get the API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Get the project ID.
	 *
	 * @return string
	 */
	public function get_project_id(): string {
		return $this->project_id;
	}

	/**
	 * Make a GET request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $args     Additional arguments for wp_remote_get.
	 * @return array|WP_Error
	 */
	public function get( string $endpoint, array $args = [] ) {
		$url = $this->build_url( $endpoint );
		$headers = $this->build_headers();
		
		$args = wp_parse_args( $args, [
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 30,
		] );

		return wp_remote_get( $url, $args );
	}

	/**
	 * Make a POST request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body data.
	 * @param array  $args     Additional arguments for wp_remote_post.
	 * @return array|WP_Error
	 */
	public function post( string $endpoint, array $data = [], array $args = [] ) {
		$url = $this->build_url( $endpoint );
		$headers = $this->build_headers();
		$headers['Content-Type'] = 'application/json';
		
		$args = wp_parse_args( $args, [
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $data ),
			'timeout'   => 30,
		] );

		return wp_remote_post( $url, $args );
	}

	/**
	 * Make a PUT request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body data.
	 * @param array  $args     Additional arguments.
	 * @return array|WP_Error
	 */
	public function put( string $endpoint, array $data = [], array $args = [] ) {
		$url = $this->build_url( $endpoint );
		$headers = $this->build_headers();
		$headers['Content-Type'] = 'application/json';
		
		$args = wp_parse_args( $args, [
			'method'    => 'PUT',
			'headers'   => $headers,
			'body'      => wp_json_encode( $data ),
			'timeout'   => 30,
		] );

		return wp_remote_request( $url, $args );
	}

	/**
	 * Make a DELETE request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $args     Additional arguments.
	 * @return array|WP_Error
	 */
	public function delete( string $endpoint, array $args = [] ) {
		$url = $this->build_url( $endpoint );
		$headers = $this->build_headers();
		
		$args = wp_parse_args( $args, [
			'method'    => 'DELETE',
			'headers'   => $headers,
			'timeout'   => 30,
		] );

		return wp_remote_request( $url, $args );
	}

	/**
	 * Build a full API URL.
	 *
	 * @param string $endpoint The endpoint.
	 * @return string
	 */
	private function build_url( string $endpoint ): string {
		// Ensure endpoint starts with /
		if ( strpos( $endpoint, '/' ) !== 0 ) {
			$endpoint = '/' . $endpoint;
		}
		
		return self::BASE_URL . $endpoint;
	}

	/**
	 * Build request headers.
	 *
	 * @return array
	 */
	private function build_headers(): array {
		return [
			'Accept'        => 'application/json',
			'x-api-key'     => $this->api_key,
			'X-Project-ID'  => $this->project_id,
		];
	}

	/**
	 * Parse the response.
	 *
	 * @param array|WP_Error $response The response from wp_remote.
	 * @return array {
	 *     @type int   $status  The HTTP status code.
	 *     @type array $headers The response headers.
	 *     @type mixed $body    The decoded response body.
	 *     @type string|WP_Error $error Error message if any.
	 * }
	 */
	public function parse_response( $response ): array {
		$result = [
			'status'  => 0,
			'headers' => [],
			'body'    => null,
			'error'   => null,
		];

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$result['status']  = wp_remote_retrieve_response_code( $response );
		$result['headers'] = wp_remote_retrieve_headers( $response );
		$body              = wp_remote_retrieve_body( $response );

		// Try to decode JSON.
		$decoded = json_decode( $body, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$result['body'] = $decoded;
		} else {
			$result['body'] = $body;
		}

		return $result;
	}

	/**
	 * Check if the response was successful.
	 *
	 * @param array|WP_Error $response The response from wp_remote.
	 * @return bool
	 */
	public function is_success( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		return ( $status >= 200 && $status < 300 );
	}

	// =========================================
	// API Endpoint Methods
	// =========================================

	/**
	 * Get all projects.
	 *
	 * @return array
	 */
	public function get_projects(): array {
		return $this->parse_response( $this->get( '/project' ) );
	}

	/**
	 * Get a specific project.
	 *
	 * @param string $project_id The project ID.
	 * @return array
	 */
	public function get_project( string $project_id ): array {
		return $this->parse_response( $this->get( '/project/' . urlencode( $project_id ) ) );
	}

	/**
	 * Get all episodes for a project.
	 *
	 * @param string|null $project_id The project ID (uses configured if null).
	 * @return array
	 */
	public function get_episodes( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/episode' ) );
	}

	/**
	 * Get an episode by ID.
	 *
	 * @param string $episode_id The episode ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_episode( string $episode_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/episode/' . urlencode( $episode_id ) ) );
	}

	/**
	 * Get all scenes for an episode.
	 *
	 * @param string $episode_id The episode ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_scenes( string $episode_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/episode/' . urlencode( $episode_id ) . '/scene' ) );
	}

	/**
	 * Get a specific scene.
	 *
	 * @param string $scene_id The scene ID.
	 * @param string $episode_id The episode ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_scene( string $scene_id, string $episode_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/episode/' . urlencode( $episode_id ) . '/scene/' . urlencode( $scene_id ) ) );
	}

	/**
	 * Create a scene.
	 *
	 * @param string $episode_id The episode ID.
	 * @param array  $data The scene data.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function create_scene( string $episode_id, array $data, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->post( '/project/' . urlencode( $id ) . '/episode/' . urlencode( $episode_id ) . '/scene', $data ) );
	}

	/**
	 * Update a scene.
	 *
	 * @param string $scene_id The scene ID.
	 * @param string $episode_id The episode ID.
	 * @param array  $data The scene data.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function update_scene( string $scene_id, string $episode_id, array $data, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->put( '/project/' . urlencode( $id ) . '/episode/' . urlencode( $episode_id ) . '/scene/' . urlencode( $scene_id ), $data ) );
	}

	/**
	 * Get all elements (characters, props, locations).
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_elements( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element' ) );
	}

	/**
	 * Get a specific element.
	 *
	 * @param string $element_id The element ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_element( string $element_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element/' . urlencode( $element_id ) ) );
	}

	/**
	 * Create an element.
	 *
	 * @param array  $data The element data.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function create_element( array $data, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->post( '/project/' . urlencode( $id ) . '/element', $data ) );
	}

	/**
	 * Update an element.
	 *
	 * @param string $element_id The element ID.
	 * @param array  $data The element data.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function update_element( string $element_id, array $data, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->put( '/project/' . urlencode( $id ) . '/element/' . urlencode( $element_id ), $data ) );
	}

	/**
	 * Delete an element.
	 *
	 * @param string $element_id The element ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function delete_element( string $element_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->delete( '/project/' . urlencode( $id ) . '/element/' . urlencode( $element_id ) ) );
	}

	/**
	 * Get all locations.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_locations( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element?category=location' ) );
	}

	/**
	 * Get all characters.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_characters( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element?category=character' ) );
	}

	/**
	 * Get all props.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_props( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element?category=prop' ) );
	}

	/**
	 * Get all scripts.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_scripts( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/script' ) );
	}

	/**
	 * Get a specific script.
	 *
	 * @param string $script_id The script ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_script( string $script_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/script/' . urlencode( $script_id ) ) );
	}

	/**
	 * Get all breakdowns.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_breakdowns( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/breakdown' ) );
	}

	/**
	 * Get all comments for an element.
	 *
	 * @param string $element_id The element ID.
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_comments( string $element_id, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/element/' . urlencode( $element_id ) . '/comment' ) );
	}

	/**
	 * Add a comment to an element.
	 *
	 * @param string $element_id The element ID.
	 * @param array  $data The comment data (must include 'text').
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function add_comment( string $element_id, array $data, ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->post( '/project/' . urlencode( $id ) . '/element/' . urlencode( $element_id ) . '/comment', $data ) );
	}

	/**
	 * Get all catalog items.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_catalog( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/catalog' ) );
	}

	/**
	 * Get all custom fields.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_custom_fields( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/custom-field' ) );
	}

	/**
	 * Get all lanes.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_lanes( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/lane' ) );
	}

	/**
	 * Get all modes.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_modes( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/mode' ) );
	}

	/**
	 * Get all conditions.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_conditions( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/condition' ) );
	}

	/**
	 * Get all variables.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_variables( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/variable' ) );
	}

	/**
	 * Get all nodes.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_nodes( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/node' ) );
	}

	/**
	 * Get all edges.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_edges( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/edge' ) );
	}

	/**
	 * Get all subdocuments.
	 *
	 * @param string|null $project_id The project ID.
	 * @return array
	 */
	public function get_subdocuments( ?string $project_id = null ): array {
		$id = $project_id ?: $this->project_id;
		return $this->parse_response( $this->get( '/project/' . urlencode( $id ) . '/subdocument' ) );
	}

	/**
	 * Get API status.
	 *
	 * @return array
	 */
	public function get_status(): array {
		return $this->parse_response( $this->get( '/status' ) );
	}
}
