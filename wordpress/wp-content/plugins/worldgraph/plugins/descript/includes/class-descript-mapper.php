<?php
/**
 * Structural mapping between Descript's transcript/project data and the
 * World Graph Studio JSON interchange subset, and back the other way for
 * project media export.
 *
 * @package WorldGraphDescript
 */

namespace WorldGraphDescript;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Descript mapper. */
class Mapper {

	/** Local attachment MIME groups accepted for a Descript media import. */
	const MEDIA_MIME_GROUPS = [ 'video', 'audio' ];

	/**
	 * Build a World Graph Studio JSON import document from an exported
	 * Descript transcript.
	 *
	 * @param string $text               Raw transcript text (txt/markdown/html/rtf).
	 * @param string $format             Export format used.
	 * @param array  $meta               project_id, composition_id, project_title.
	 * @param string $scope              Identity scope for stable external IDs.
	 * @param array  $identity_map       Existing kind => candidate => external_id map.
	 * @return array
	 */
	public static function from_transcript( string $text, string $format, array $meta, string $scope = 'default', array $identity_map = [] ): array {
		$remote_project_id     = self::text( $meta['project_id'] ?? '' );
		$remote_composition_id = self::text( $meta['composition_id'] ?? '' );
		$title                 = self::text( $meta['project_title'] ?? '' ) ?: 'Descript Project';

		$project_key  = self::external_id( $remote_project_id, 'project', $remote_project_id ?: 'project', 0, $scope, $identity_map );
		$world_key    = self::external_id( $remote_project_id, 'world', 'world', 0, $scope, $identity_map );
		$scene_lookup = $remote_composition_id ?: 'transcript';
		$scene_key    = self::external_id( $remote_project_id, 'scene', $scene_lookup, 0, $scope, $identity_map );

		return [
			'project' => [
				'id'          => $project_key,
				'title'       => $title,
				'description' => sprintf( 'Imported from Descript (format: %s).', $format ),
			],
			'world' => [
				'id'   => $world_key,
				'name' => $title . ' World',
			],
			'characters' => [],
			'locations'  => [],
			'props'      => [],
			'scenes'     => [
				[
					'id'             => $scene_key,
					'title'          => $title . ' Transcript',
					'summary'        => '',
					'script_content' => $text,
				],
			],
			'shots'       => [],
			'sounds'      => [],
			'storyboards' => [],
			'sequence'    => [
				'id'    => self::external_id( $remote_project_id, 'sequence', 'main', 0, $scope, $identity_map ),
				'title' => $title . ' Sequence',
				'order' => [ $scene_key ],
			],
		];
	}

	/**
	 * Collect bound video/audio attachments from a local Project's Scenes and
	 * Shots into a Descript project-media import payload.
	 *
	 * @param int $project_id Local worldgraph_project post ID.
	 * @return array {project_name, folder_name, add_media: array<string, array>}
	 */
	public static function media_from_worldgraph( int $project_id ): array {
		$project = get_post( $project_id );
		if ( ! $project instanceof \WP_Post || 'worldgraph_project' !== $project->post_type ) {
			return [];
		}

		$scenes = get_posts( [
			'post_type'      => 'worldgraph_scene',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => 'project',
			'meta_value'     => $project_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		] );

		$add_media = [];
		foreach ( $scenes as $scene ) {
			foreach ( self::scene_attachments( $scene->ID ) as $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( ! $url ) {
					continue;
				}
				$path = sprintf( '%s/%s', sanitize_title( $scene->post_title ) ?: ( 'scene-' . $scene->ID ), basename( wp_parse_url( $url, PHP_URL_PATH ) ?: (string) $attachment_id ) );
				$add_media[ $path ] = [ 'url' => esc_url_raw( $url ) ];
			}
		}

		return [
			'project_name' => $project->post_title ?: 'World Graph Studio Project',
			'folder_name'  => 'World Graph Studio',
			'add_media'    => $add_media,
		];
	}

	/** Video/audio attachments bound to one Scene and its Shots. */
	private static function scene_attachments( int $scene_id ): array {
		$attachments = [];
		$thumbnail_id = get_post_thumbnail_id( $scene_id );
		if ( $thumbnail_id && self::is_media_attachment( $thumbnail_id ) ) {
			$attachments[] = $thumbnail_id;
		}

		foreach ( \WorldGraph\Utils\get_relationships( $scene_id, 'worldgraph_scene', 'outgoing' ) as $relationship ) {
			if ( 'worldgraph_shot' !== ( $relationship['to_type'] ?? '' ) ) {
				continue;
			}
			$shot_thumbnail = get_post_thumbnail_id( absint( $relationship['to_id'] ?? 0 ) );
			if ( $shot_thumbnail && self::is_media_attachment( $shot_thumbnail ) ) {
				$attachments[] = $shot_thumbnail;
			}
		}

		return array_values( array_unique( $attachments ) );
	}

	/** Whether an attachment is a video or audio file Descript can import. */
	private static function is_media_attachment( int $attachment_id ): bool {
		$mime = (string) get_post_mime_type( $attachment_id );
		foreach ( self::MEDIA_MIME_GROUPS as $group ) {
			if ( str_starts_with( $mime, $group . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Stable, Connection-scoped external ID for imported objects. */
	private static function external_id( string $remote_project_id, string $kind, $candidate, int $index, string $scope, array $identity_map ): string {
		$candidate_key = is_scalar( $candidate ) ? (string) $candidate : '';
		$mapped = $identity_map[ $kind ][ $candidate_key ] ?? $identity_map[ $kind ]['*'] ?? '';
		if ( is_scalar( $mapped ) && '' !== (string) $mapped ) {
			return sanitize_text_field( (string) $mapped );
		}
		$scope  = substr( sanitize_title( $scope ), 0, 20 ) ?: 'default';
		$remote = substr( sha1( $remote_project_id ), 0, 16 );
		$value  = substr( sanitize_title( is_scalar( $candidate ) ? (string) $candidate : '' ), 0, 40 );
		$value  = $value ?: (string) ( $index + 1 );
		return sprintf( 'dsc-%s-%s-%s-%s', $scope, $remote, sanitize_key( $kind ), $value );
	}

	/** Coerce a scalar to a trimmed string. */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}
}
