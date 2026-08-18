<?php
/**
 * Template input bindings.
 *
 * Resolves a Template's `input_bindings` JSON (image, start_frame, end_frame,
 * video, audio) against a specific Story Graph post, so a Template whose
 * modality needs more than a prompt can still run unattended by pulling its
 * reference from that post's featured image, media gallery, or a StoryOS
 * Details / SCF field, instead of requiring a form upload every time.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template input binding resolver.
 */
class Template_Bindings {

	/**
	 * A Template's declared input bindings.
	 *
	 * @param int $template_id Template post ID.
	 * @return array<string, array{source: string}>
	 */
	public static function bindings( int $template_id ): array {
		$decoded = json_decode( (string) get_post_meta( $template_id, 'input_bindings', true ), true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Resolve every bound media slot for a Template against a source post.
	 *
	 * @param int $template_id Template post ID.
	 * @param int $post_id     Source Story Graph post ID.
	 * @return array<string, string> Slot => attachment ID or URL, for slots that resolved.
	 */
	public static function resolve( int $template_id, int $post_id ): array {
		$modality = Generation_Modality::sanitize( (string) get_post_meta( $template_id, 'modality', true ) );
		$bindings = self::bindings( $template_id );

		$resolved = [];
		foreach ( Generation_Modality::media_inputs( $modality ) as $slot ) {
			$binding = $bindings[ $slot ] ?? null;
			if ( ! is_array( $binding ) || empty( $binding['source'] ) ) {
				continue;
			}

			$value = self::resolve_source( (string) $binding['source'], $post_id );
			if ( '' !== $value ) {
				$resolved[ $slot ] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Required input slots (beyond `prompt`) a Template's bindings cannot
	 * resolve for a given post, so a caller can refuse the job before it ever
	 * reaches ComfyUI instead of failing mid-generation.
	 *
	 * @param int $template_id Template post ID.
	 * @param int $post_id     Source Story Graph post ID.
	 * @return array<int, string> Missing slot names.
	 */
	public static function missing_required( int $template_id, int $post_id ): array {
		$modality = Generation_Modality::sanitize( (string) get_post_meta( $template_id, 'modality', true ) );
		$resolved = self::resolve( $template_id, $post_id );

		$missing = [];
		foreach ( Generation_Modality::required_inputs( $modality ) as $slot ) {
			if ( 'prompt' === $slot ) {
				continue;
			}
			if ( empty( $resolved[ $slot ] ) ) {
				$missing[] = $slot;
			}
		}

		return $missing;
	}

	/**
	 * Resolve one binding source against a post: its featured image, media
	 * gallery, or a StoryOS Details / SCF post meta field.
	 *
	 * @param string $source Binding source, e.g. `featured_image`, `gallery`,
	 *                       `gallery:1`, or an SCF field name.
	 * @param int    $post_id Source post ID.
	 * @return string Attachment ID or URL, or '' when unresolved.
	 */
	private static function resolve_source( string $source, int $post_id ): string {
		$source = trim( $source );

		if ( 'featured_image' === $source ) {
			$attachment_id = get_post_thumbnail_id( $post_id );

			return $attachment_id ? (string) $attachment_id : '';
		}

		if ( 'gallery' === $source || 0 === strpos( $source, 'gallery:' ) ) {
			$gallery_ids = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, Asset_Generator::GALLERY_META, true ) ) ) );
			if ( empty( $gallery_ids ) ) {
				return '';
			}

			$index = false !== strpos( $source, ':' ) ? max( 0, (int) substr( $source, strpos( $source, ':' ) + 1 ) ) : 0;

			return isset( $gallery_ids[ $index ] ) ? (string) $gallery_ids[ $index ] : '';
		}

		// Fall back to a StoryOS Details / SCF post meta field.
		$value = get_post_meta( $post_id, $source, true );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return is_scalar( $value ) && '' !== (string) $value ? (string) $value : '';
	}
}
