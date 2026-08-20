<?php
/**
 * AI Context Builder — assembles Story Graph context for LLM queries.
 *
 * @package WorldGraph
 */

namespace WorldGraph\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Builder class.
 */
class AI_Context_Builder {

	/**
	 * Build context for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Context data.
	 */
	public function build_post_context( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$context = [
			'post_id'    => $post_id,
			'post_type'  => $post->post_type,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
		];

		// Add character data if post type is character.
		if ( 'character' === $post->post_type ) {
			$context = array_merge( $context, $this->build_character_context( $post_id ) );
		}

		// Add scene data if post type is scene.
		if ( 'scene' === $post->post_type ) {
			$context = array_merge( $context, $this->build_scene_context( $post_id ) );
		}

		// Add project context.
		$context = array_merge( $context, $this->build_project_context( $post_id ) );

		return $context;
	}

	/**
	 * Build character context.
	 *
	 * @param int $post_id Character post ID.
	 * @return array Character context.
	 */
	private function build_character_context( int $post_id ): array {
		$context = [
			'character_name' => get_post_meta( $post_id, 'character_name', true ) ?: get_the_title( $post_id ),
			'character_arc'  => get_post_meta( $post_id, 'character_arc', true ) ?: '',
			'personality'    => get_post_meta( $post_id, 'personality', true ) ?: '',
			'motivation'     => get_post_meta( $post_id, 'motivation', true ) ?: '',
		];

		// Get relationships.
		$relationships = get_post_meta( $post_id, 'relationships', true );
		if ( $relationships ) {
			$context['relationships'] = is_array( $relationships ) ? $relationships : [];
		}

		// Get scenes this character appears in.
		$scenes = get_posts( [
			'post_type'   => 'scene',
			'numberposts' => -1,
			'meta_query'  => [
				[
					'key'     => 'characters',
					'value'   => '"' . $post_id . '"',
					'compare' => 'LIKE',
				],
			],
		] );

		if ( $scenes ) {
			$context['appears_in_scenes'] = array_map( function( $scene ) {
				return [
					'id'   => $scene->ID,
					'title' => $scene->post_title,
				];
			}, $scenes );
		}

		return $context;
	}

	/**
	 * Build scene context.
	 *
	 * @param int $post_id Scene post ID.
	 * @return array Scene context.
	 */
	private function build_scene_context( int $post_id ): array {
		$context = [
			'scene_title'   => get_post_meta( $post_id, 'scene_title', true ) ?: get_the_title( $post_id ),
			'setting'       => get_post_meta( $post_id, 'setting', true ) ?: '',
			'time_of_day'   => get_post_meta( $post_id, 'time_of_day', true ) ?: '',
			'tone'          => get_post_meta( $post_id, 'tone', true ) ?: '',
			'scene_content' => get_post_meta( $post_id, 'scene_content', true ) ?: $post->post_content,
		];

		// Get characters in this scene.
		$character_ids = get_post_meta( $post_id, 'characters', true );
		if ( $character_ids && is_array( $character_ids ) ) {
			$context['characters'] = array_map( function( $char_id ) {
				return [
					'id'    => $char_id,
					'name'  => get_the_title( $char_id ),
				];
			}, $character_ids );
		}

		// Get previous and next scenes.
		$prev_scene = get_previous_post( $post_id, true, 'scene' );
		$next_scene = get_next_post( $post_id, true, 'scene' );

		if ( $prev_scene ) {
			$context['previous_scene'] = [
				'id'    => $prev_scene->ID,
				'title' => $prev_scene->post_title,
			];
		}
		if ( $next_scene ) {
			$context['next_scene'] = [
				'id'    => $next_scene->ID,
				'title' => $next_scene->post_title,
			];
		}

		return $context;
	}

	/**
	 * Build project-level context.
	 *
	 * @param int $post_id Post ID.
	 * @return array Project context.
	 */
	private function build_project_context( int $post_id ): array {
		$context = [];

		// Get all characters.
		$characters = get_posts( [
			'post_type'   => 'character',
			'numberposts' => -1,
		] );

		if ( $characters ) {
			$context['all_characters'] = array_map( function( $char ) {
				return [
					'id'   => $char->ID,
					'name' => $char->post_title,
				];
			}, $characters );
		}

		// Get all scenes.
		$scenes = get_posts( [
			'post_type'   => 'scene',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		] );

		if ( $scenes ) {
			$context['all_scenes'] = array_map( function( $scene ) {
				return [
					'id'    => $scene->ID,
					'title' => $scene->post_title,
					'status' => $scene->post_status,
				];
			}, $scenes );
		}

		// Get project metadata.
		$project_title = get_option( 'worldgraph_project_title', '' );
		if ( $project_title ) {
			$context['project_title'] = $project_title;
		}

		$project_logline = get_option( 'worldgraph_project_logline', '' );
		if ( $project_logline ) {
			$context['project_logline'] = $project_logline;
		}

		$project_genre = get_option( 'worldgraph_project_genre', '' );
		if ( $project_genre ) {
			$context['project_genre'] = $project_genre;
		}

		return $context;
	}

	/**
	 * Build context formatted for LLM consumption.
	 *
	 * @param array $context Context data.
	 * @return string Formatted context string.
	 */
	public function build_context_for_llm( array $context ): string {
		if ( empty( $context ) ) {
			return '';
		}

		$output = "Story Graph Context:\n\n";

		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$output .= "## {$key}\n";
				$output .= $this->format_array_recursive( $value, 2 ) . "\n\n";
			} else {
				$output .= "{$key}: {$value}\n\n";
			}
		}

		return $output;
	}

	/**
	 * Recursively format an array.
	 *
	 * @param array  $array The array.
	 * @param int    $depth Current depth.
	 * @return string Formatted string.
	 */
	private function format_array_recursive( array $array, int $depth = 0 ): string {
		$output = '';
		$indent = str_repeat('  ', $depth);

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$output .= "{$indent}{$key}:\n";
				$output .= $this->format_array_recursive( $value, $depth + 1 );
			} else {
				$output .= "{$indent}{$key}: {$value}\n";
			}
		}

		return $output;
	}
}
