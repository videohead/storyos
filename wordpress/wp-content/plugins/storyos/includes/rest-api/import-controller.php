<?php
/**
 * Import REST API Controller for StoryOS.
 *
 * Handles /storyos/v1/import/* endpoints for importing StoryOS JSON documents.
 *
 * @package StoryOS
 */

namespace StoryOS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Import Controller class.
 */
class Import_Controller extends Base_Controller {

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'import';

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
		register_rest_route( 'storyos/v1', '/import', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'import_json' ],
				'permission_callback' => [ $this, 'check_import_permission' ],
				'args'                => [
					'json'      => [
						'description' => 'The StoryOS JSON document to import.',
						'type'        => 'string',
						'required'    => true,
					],
					'overwrite' => [
						'description' => 'Overwrite existing entities with the same external ID.',
						'type'        => 'boolean',
						'default'     => false,
					],
				],
			],
		] );

		register_rest_route( 'storyos/v1', '/import/validate', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'validate_json' ],
				'permission_callback' => [ $this, 'check_import_permission' ],
				'args'                => [
					'json' => [
						'description' => 'The StoryOS JSON document to validate.',
						'type'        => 'string',
						'required'    => true,
					],
				],
			],
		] );
	}

	/**
	 * Import a StoryOS JSON document.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public function import_json( WP_REST_Request $request ) {
		$json      = $request->get_param( 'json' );
		$overwrite = rest_sanitize_boolean( $request->get_param( 'overwrite' ) );

		$importer = new \StoryOS\Importer\StoryOS_Importer();
		$result   = $importer->import( $json, [ 'overwrite' => $overwrite ] );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( [
			'success' => ! empty( $result['verified'] ),
			'report'  => $result,
		] );
	}

	/**
	 * Validate a StoryOS JSON document without importing.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public function validate_json( WP_REST_Request $request ) {
		$json = $request->get_param( 'json' );

		$importer = new \StoryOS\Importer\StoryOS_Importer();
		$result   = $importer->import( $json, [ 'dry_run' => true ] );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( [
			'success' => true,
			'message' => 'JSON is valid.',
		] );
	}

	/**
	 * Check permissions for import endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public function check_import_permission() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'You must be an administrator to import StoryOS data.', [ 'status' => 403 ] );
		}
		return true;
	}
}