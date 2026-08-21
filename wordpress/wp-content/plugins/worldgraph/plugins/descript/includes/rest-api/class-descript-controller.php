<?php
/**
 * REST routes for Descript transcript import and project media export.
 *
 * @package WorldGraphDescript
 */

namespace WorldGraphDescript\REST;

use WorldGraphDescript\Settings;
use WorldGraphDescript\Sync;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Descript REST controller. */
class Descript_Controller {

	/** Register routes on rest_api_init. */
	public static function init(): void {
		$controller = new self();
		add_action( 'rest_api_init', [ $controller, 'register_routes' ] );
	}

	/** Register the sync surface. */
	public function register_routes(): void {
		$permission = [ $this, 'check_permission' ];

		register_rest_route( 'worldgraph/v1', '/descript/projects', [
			'methods' => 'GET', 'callback' => [ $this, 'projects' ], 'permission_callback' => $permission,
			'args' => [ 'connection_id' => [ 'type' => 'integer', 'default' => 0 ] ],
		] );
		register_rest_route( 'worldgraph/v1', '/descript/pull', [
			'methods' => 'POST', 'callback' => [ $this, 'pull' ], 'permission_callback' => $permission,
			'args' => [
				'remote_project_id' => [ 'type' => 'string', 'required' => true ],
				'composition_id'    => [ 'type' => 'string', 'default' => '' ],
				'connection_id'     => [ 'type' => 'integer', 'default' => 0 ],
				'format'            => [ 'type' => 'string', 'default' => 'markdown' ],
				'force'             => [ 'type' => 'boolean', 'default' => false ],
			],
		] );
		register_rest_route( 'worldgraph/v1', '/descript/push', [
			'methods' => 'POST', 'callback' => [ $this, 'push' ], 'permission_callback' => $permission,
			'args' => [
				'project_id'        => [ 'type' => 'integer', 'required' => true ],
				'connection_id'     => [ 'type' => 'integer', 'default' => 0 ],
				'remote_project_id' => [ 'type' => 'string', 'default' => '' ],
				'folder_name'       => [ 'type' => 'string', 'default' => '' ],
			],
		] );
		register_rest_route( 'worldgraph/v1', '/descript/jobs/(?P<job_id>[\w-]+)', [
			'methods' => 'GET', 'callback' => [ $this, 'job' ], 'permission_callback' => $permission,
			'args' => [
				'connection_id' => [ 'type' => 'integer', 'default' => 0 ],
				'project_id'    => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );
		register_rest_route( 'worldgraph/v1', '/descript/mapping/(?P<project_id>\d+)', [
			[
				'methods' => 'GET', 'callback' => [ $this, 'mapping' ], 'permission_callback' => $permission,
				'args' => [ 'connection_id' => [ 'type' => 'integer', 'default' => 0 ] ],
			],
			[
				'methods' => 'DELETE', 'callback' => [ $this, 'unsync' ], 'permission_callback' => $permission,
				'args' => [ 'connection_id' => [ 'type' => 'integer', 'default' => 0 ] ],
			],
		] );
	}

	/** Require plugin enablement and administrator capability. */
	public function check_permission() {
		if ( ! Settings::is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'worldgraph_descript_forbidden', __( 'Descript Sync is disabled or you are not allowed to use it.', 'worldgraph' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/** List remote projects. */
	public function projects( WP_REST_Request $request ) {
		return $this->response( Sync::list_projects( absint( $request->get_param( 'connection_id' ) ) ) );
	}

	/** Import a transcript into the Story Graph. */
	public function pull( WP_REST_Request $request ) {
		return $this->response( Sync::pull_transcript(
			(string) $request->get_param( 'remote_project_id' ),
			(string) $request->get_param( 'composition_id' ),
			absint( $request->get_param( 'connection_id' ) ),
			(string) $request->get_param( 'format' ),
			rest_sanitize_boolean( $request->get_param( 'force' ) )
		) );
	}

	/** Submit a local Project's bound media as a new Descript import job. */
	public function push( WP_REST_Request $request ) {
		return $this->response( Sync::push_media(
			absint( $request->get_param( 'project_id' ) ),
			absint( $request->get_param( 'connection_id' ) ),
			(string) $request->get_param( 'remote_project_id' ),
			(string) $request->get_param( 'folder_name' )
		) );
	}

	/** Poll an import job's status. */
	public function job( WP_REST_Request $request ) {
		return $this->response( Sync::poll_job(
			(string) $request->get_param( 'job_id' ),
			absint( $request->get_param( 'connection_id' ) ),
			absint( $request->get_param( 'project_id' ) )
		) );
	}

	/** Return one mapping. */
	public function mapping( WP_REST_Request $request ) {
		$connection_id = absint( $request->get_param( 'connection_id' ) ) ?: Settings::connection_id();
		$project_id = absint( $request->get_param( 'project_id' ) );
		$valid = $this->validate_mapping_target( $project_id, $connection_id );
		return is_wp_error( $valid ) ? $valid : rest_ensure_response( Sync::mapping( $project_id, $connection_id ) );
	}

	/** Remove one mapping. */
	public function unsync( WP_REST_Request $request ) {
		$connection_id = absint( $request->get_param( 'connection_id' ) ) ?: Settings::connection_id();
		$project_id = absint( $request->get_param( 'project_id' ) );
		$valid = $this->validate_mapping_target( $project_id, $connection_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! Sync::unsync( $project_id, $connection_id ) ) {
			return new WP_Error( 'descript_mapping_missing', __( 'No Descript mapping was found for that Project and Connection.', 'worldgraph' ), [ 'status' => 404 ] );
		}
		return rest_ensure_response( [ 'success' => true ] );
	}

	/** Validate the Project and Descript Connection used by mapping routes. */
	private function validate_mapping_target( int $project_id, int $connection_id ) {
		$project = get_post( $project_id );
		if ( ! $project instanceof \WP_Post || 'worldgraph_project' !== $project->post_type ) {
			return new WP_Error( 'descript_project_invalid', __( 'The requested World Graph Studio Project was not found.', 'worldgraph' ), [ 'status' => 404 ] );
		}
		$connection = \WorldGraph\Utils\Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'descript' !== ( $connection['provider_type'] ?? '' ) || 'disabled' === ( $connection['status'] ?? '' ) ) {
			return new WP_Error( 'descript_connection_invalid', __( 'Select an available Descript Connection first.', 'worldgraph' ), [ 'status' => 400 ] );
		}
		return true;
	}

	/** Convert service results to REST responses without swallowing WP_Error. */
	private function response( $result ) {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
