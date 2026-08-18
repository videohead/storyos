<?php
/**
 * StoryOS markdown exporter.
 *
 * Exports live StoryOS project data into a screenplay-style Markdown document
 * that mirrors the example workflow export and stays aligned with the current
 * WordPress project state rather than a JSON snapshot.
 *
 * @package StoryOS
 */

namespace StoryOS\Exporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export StoryOS data as Markdown screenplay text.
 */
class StoryOS_Exporter {

	/**
	 * Export a live StoryOS project to a screenplay Markdown document.
	 *
	 * @param int|array $project_id_or_data Project ID or project data array.
	 * @return string Markdown document.
	 */
	public function export_project_markdown( $project_id_or_data = 0, array $project_data = [] ): string {
		$project = $this->resolve_project_data( $project_id_or_data, $project_data );
		if ( empty( $project['title'] ) ) {
			return "# StoryOS Export\n\n_There is no project data to export._\n";
		}

		$project_title = $this->clean_text( $project['title'] );
		$world_title   = $this->clean_text( $project['world'] ?? 'Story World' );
		$scenes        = $this->get_project_scenes( $project_id_or_data );
		$lines         = [];

		$lines[] = '# ' . $project_title;
		$lines[] = '';
		$lines[] = '## StoryOS Sample Export';
		$lines[] = '### Screenplay Format';
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## FADE IN:';
		$lines[] = '';

		if ( empty( $scenes ) ) {
			$lines[] = '_No scenes found for this project yet._';
			$lines[] = '';
		} else {
			foreach ( $scenes as $scene ) {
				$lines[] = $this->format_scene_block( $scene );
				$lines[] = '';
			}
		}

		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## SEQUENCE BREAKDOWN';
		foreach ( $scenes as $index => $scene ) {
			$scene_number = $scene['scene_number'] ?? ( $index + 1 );
			$scene_title  = $scene['title'] ?? 'Untitled Scene';
			$lines[] = '';
			$lines[] = '### Sequence ' . ( $index + 1 ) . ' - ' . $this->clean_text( $scene_title );
			$lines[] = '';
			$lines[] = '**Scene ' . $scene_number . '**';
			$lines[] = $this->clean_text( $scene_title );
			$lines[] = '';
		}
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## PRODUCTION NOTES';
		$lines[] = '';
		$lines[] = '### Visual Style';
		$lines[] = 'StoryOS Project Export';
		$lines[] = '';
		$lines[] = '### World';
		$lines[] = $world_title;
		$lines[] = '';
		$lines[] = '## STORYOS EXPORT METADATA';
		$lines[] = '';
		$lines[] = '```yaml';
		$lines[] = 'project: ' . $project_title;
		$lines[] = 'world: ' . $world_title;
		$lines[] = 'scenes: ' . count( $scenes );
		$lines[] = 'export_format:';
		$lines[] = '  - markdown';
		$lines[] = '  - screenplay';
		$lines[] = '```';
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## Export Summary';
		$lines[] = '';
		$lines[] = 'This export was generated from the live StoryOS project data and reflects the current project state in WordPress.';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Resolve project title and related data from project ID or direct data.
	 *
	 * @param int|array $project_id_or_data Project ID or project data.
	 * @param array     $project_data Optional fallback data.
	 * @return array
	 */
	private function resolve_project_data( $project_id_or_data, array $project_data = [] ): array {
		if ( is_array( $project_id_or_data ) ) {
			return $project_id_or_data;
		}

		if ( ! function_exists( 'get_post' ) || ! function_exists( 'get_post_meta' ) ) {
			return $project_data;
		}

		$project_id = (int) $project_id_or_data;
		if ( ! $project_id ) {
			return $project_data;
		}

		$post = get_post( $project_id );
		if ( ! $post || 'storyos_project' !== $post->post_type ) {
			return $project_data;
		}

		$world_name = '';
		$world_rels = \StoryOS\Utils\get_relationships( $project_id, 'storyos_project', 'outgoing' );
		foreach ( $world_rels as $rel ) {
			if ( 'storyos_story_world' === ( $rel['to_type'] ?? '' ) ) {
				$world = get_post( (int) $rel['to_id'] );
				if ( $world ) {
					$world_name = $world->post_title;
				}
			}
		}

		return [
			'title' => $post->post_title,
			'world' => $world_name,
			'project_id' => $project_id,
		];
	}

	/**
	 * Retrieve scenes for a project from live StoryOS relationships or direct data.
	 *
	 * @param int|array $project_id_or_data Project ID or data.
	 * @return array
	 */
	private function get_project_scenes( $project_id_or_data ): array {
		if ( is_array( $project_id_or_data ) && ! empty( $project_id_or_data['scenes'] ) && is_array( $project_id_or_data['scenes'] ) ) {
			return $project_id_or_data['scenes'];
		}

		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_meta' ) ) {
			return [];
		}

		$project_id = is_numeric( $project_id_or_data ) ? (int) $project_id_or_data : 0;
		if ( ! $project_id ) {
			return [];
		}

		$scene_posts = get_posts( [
			'post_type'      => 'storyos_scene',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'scene_number',
			'order'          => 'ASC',
		] );

		$scenes = [];
		foreach ( $scene_posts as $scene_post ) {
			$scene_meta = [
				'id' => $scene_post->ID,
				'title' => $scene_post->post_title,
				'summary' => get_post_meta( $scene_post->ID, 'summary', true ),
				'script_content' => get_post_meta( $scene_post->ID, 'script_content', true ),
				'location' => $this->get_scene_location_name( $scene_post->ID ),
				'time_of_day' => get_post_meta( $scene_post->ID, 'time_of_day', true ),
				'scene_number' => (int) get_post_meta( $scene_post->ID, 'scene_number', true ),
				'content' => $scene_post->post_content,
			];

			$scene_rels = \StoryOS\Utils\get_relationships( $scene_post->ID, 'storyos_scene', 'outgoing' );
			foreach ( $scene_rels as $rel ) {
				if ( 'storyos_project' === ( $rel['to_type'] ?? '' ) ) {
					$scene_meta['project_id'] = (int) $rel['to_id'];
				}
			}

			if ( ! empty( $project_id ) && ( ( $scene_meta['project_id'] ?? 0 ) !== $project_id ) ) {
				continue;
			}

			$scenes[] = $scene_meta;
		}

		return $scenes;
	}

	/**
	 * Build one Markdown scene block.
	 *
	 * @param array $scene Scene data.
	 * @return string
	 */
	private function format_scene_block( array $scene ): string {
		$location = $this->clean_text( $scene['location'] ?? 'Location' );
		$time     = strtoupper( (string) ( $scene['time_of_day'] ?? 'DAY' ) );
		$title    = $this->clean_text( $scene['title'] ?? 'Untitled Scene' );
		$summary  = $this->clean_text( $scene['summary'] ?? $scene['content'] ?? '' );
		$script   = $this->clean_text( $scene['script_content'] ?? $summary );
		$lines    = [];

		$lines[] = '### ' . strtoupper( $location ) . ' - ' . $time;
		$lines[] = '';
		$lines[] = $summary ?: $script;
		$lines[] = '';
		if ( $script && $script !== $summary ) {
			$lines[] = $script;
			$lines[] = '';
		}

		$character_names = $this->get_scene_character_names( $scene['id'] ?? 0 );
		if ( ! empty( $character_names ) ) {
			foreach ( $character_names as $character_name ) {
				$lines[] = '**' . strtoupper( $this->clean_text( $character_name ) ) . '**';
				$lines[] = ''; 
			}
		}

		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## SHOT LIST';
		$shot_lines = $this->get_scene_shot_list( $scene['id'] ?? 0 );
		if ( empty( $shot_lines ) ) {
			$lines[] = '_No shot list available yet._';
		} else {
			foreach ( $shot_lines as $shot_line ) {
				$lines[] = $shot_line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get location name for a scene.
	 *
	 * @param int $scene_id Scene ID.
	 * @return string
	 */
	private function get_scene_location_name( int $scene_id ): string {
		if ( ! function_exists( 'get_post' ) ) {
			return 'Location';
		}

		$relationships = \StoryOS\Utils\get_relationships( $scene_id, 'storyos_scene', 'outgoing' );
		foreach ( $relationships as $rel ) {
			if ( 'storyos_location' === ( $rel['to_type'] ?? '' ) ) {
				$post = get_post( (int) $rel['to_id'] );
				if ( $post ) {
					return $post->post_title;
				}
			}
		}

		return 'Location';
	}

	/**
	 * Get character names linked to a scene.
	 *
	 * @param int $scene_id Scene ID.
	 * @return array
	 */
	private function get_scene_character_names( int $scene_id ): array {
		if ( ! function_exists( 'get_post' ) ) {
			return [];
		}

		$characters = [];
		$relationships = \StoryOS\Utils\get_relationships( $scene_id, 'storyos_scene', 'outgoing' );
		foreach ( $relationships as $rel ) {
			if ( 'storyos_character' !== ( $rel['to_type'] ?? '' ) ) {
				continue;
			}
			$post = get_post( (int) $rel['to_id'] );
			if ( $post ) {
				$characters[] = $post->post_title;
			}
		}

		return array_values( array_unique( $characters ) );
	}

	/**
	 * Get shot list for a scene.
	 *
	 * @param int $scene_id Scene ID.
	 * @return array
	 */
	private function get_scene_shot_list( int $scene_id ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		$shots = get_posts( [
			'post_type'      => 'storyos_shot',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'shot_number',
			'order'          => 'ASC',
		] );

		$scene_shots = [];
		foreach ( $shots as $shot ) {
			$relationship_found = false;
			foreach ( \StoryOS\Utils\get_relationships( $shot->ID, 'storyos_shot', 'outgoing' ) as $rel ) {
				if ( $scene_id === (int) ( $rel['to_id'] ?? 0 ) && 'storyos_scene' === ( $rel['to_type'] ?? '' ) ) {
					$relationship_found = true;
					break;
				}
			}

			if ( $relationship_found ) {
				$shot_type = get_post_meta( $shot->ID, 'shot_type', true );
				$scene_shots[] = '### ' . \StoryOS\Utils\storyos_get_shot_display_name( $shot->ID ) . ( $shot_type ? ' — ' . ucfirst( (string) $shot_type ) : '' );
			}
		}

		return $scene_shots;
	}

	/**
	 * Normalize export text for Markdown output.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	private function clean_text( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( [ $this, 'clean_text' ], $value ) ) );
		}

		$value = (string) $value;
		$value = strip_tags( $value );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
		$value = preg_replace( '/\s+/', ' ', $value );
		$value = trim( $value );
		return $value;
	}
}
