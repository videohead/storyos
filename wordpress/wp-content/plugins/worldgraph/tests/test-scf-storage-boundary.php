<?php
/**
 * Static contracts that keep schema-backed values inside the SCF lifecycle.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/** Protect canonical fields from accidental native-meta and term bypasses. */
class Test_SCF_Storage_Boundary extends TestCase {

	/** The plugin directory. */
	private function plugin_root(): string {
		return dirname( __DIR__ );
	}

	/**
	 * Top-level canonical field names and SCF-owned taxonomy names.
	 *
	 * @return array{fields:array<string, bool>,taxonomies:array<string, bool>}
	 */
	private function schema_names(): array {
		$fields     = [];
		$taxonomies = [];
		foreach ( glob( $this->plugin_root() . '/acf-json/group_worldgraph_*.json' ) ?: [] as $path ) {
			$group = json_decode( (string) file_get_contents( $path ), true );
			foreach ( (array) ( $group['fields'] ?? [] ) as $field ) {
				if ( ! is_array( $field ) || empty( $field['name'] ) ) {
					continue;
				}
				$fields[ (string) $field['name'] ] = true;
				if ( 'taxonomy' === ( $field['type'] ?? '' ) && ! empty( $field['taxonomy'] ) ) {
					$taxonomies[ (string) $field['taxonomy'] ] = true;
				}
			}
		}

		return [ 'fields' => $fields, 'taxonomies' => $taxonomies ];
	}

	/**
	 * Production PHP files that consume schema fields.
	 *
	 * @return array<string, string> Relative path => source.
	 */
	private function production_sources(): array {
		$root    = $this->plugin_root();
		$sources = [];
		foreach ( [ 'includes', 'plugins' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory ) );
			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
					continue;
				}
				$relative = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );
				if ( in_array( $relative, [ 'includes/utils/class-scf-fields.php', 'includes/utils/helpers.php' ], true ) ) {
					continue;
				}
				$sources[ $relative ] = (string) file_get_contents( $file->getPathname() );
			}
		}

		return $sources;
	}

	/** Canonical field names must not be passed directly to post-meta APIs. */
	public function test_declared_fields_do_not_bypass_scf_with_literal_post_meta_calls(): void {
		$declared   = $this->schema_names()['fields'];
		$violations = [];

		foreach ( $this->production_sources() as $relative => $source ) {
			preg_match_all(
				'/\b(?:get|update|delete)_post_meta\s*\(\s*[^,]+,\s*([\'\"])([^\'\"]+)\1/',
				$source,
				$matches,
				PREG_OFFSET_CAPTURE
			);
			foreach ( $matches[2] ?? [] as $index => $match ) {
				$field_name = (string) $match[0];
				if ( ! isset( $declared[ $field_name ] ) ) {
					continue;
				}

				$offset = (int) ( $matches[0][ $index ][1] ?? $match[1] );
				$line   = 1 + substr_count( substr( $source, 0, $offset ), "\n" );
				$line_source = (string) ( explode( "\n", $source )[ $line - 1 ] ?? '' );
				// VideoDraft's mapper can load in isolation; its wrapper-first branch
				// retains one deliberate raw fallback for that standalone context.
				if ( 'plugins/videodraft/includes/class-videodraft-mapper.php' === $relative && 'dialogue' === $field_name && false !== strpos( $line_source, 'worldgraph_get_field_value' ) ) {
					continue;
				}
				$violations[] = "{$relative}:{$line} ({$field_name})";
			}

			preg_match_all( '/\bconst\s+([A-Z][A-Z0-9_]*)\s*=\s*([\'\"])([^\'\"]+)\2\s*;/', $source, $constants, PREG_SET_ORDER );
			foreach ( $constants as $constant ) {
				if ( ! isset( $declared[ $constant[3] ] ) || ! preg_match( '/\b(?:get|update|delete)_post_meta\s*\(\s*[^,]+,\s*self::' . preg_quote( $constant[1], '/' ) . '\b/', $source, $usage, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				$line = 1 + substr_count( substr( $source, 0, (int) $usage[0][1] ), "\n" );
				$violations[] = "{$relative}:{$line} ({$constant[3]} via self::{$constant[1]})";
			}
		}

		$this->assertSame( [], $violations, "Canonical SCF fields bypass native post meta:\n" . implode( "\n", $violations ) );
	}

	/** SCF taxonomy fields must not be assigned through native term APIs. */
	public function test_scf_taxonomies_do_not_use_literal_native_term_assignment(): void {
		$taxonomies = $this->schema_names()['taxonomies'];
		$violations = [];
		foreach ( $this->production_sources() as $relative => $source ) {
			preg_match_all(
				'/\bwp_set_(?:object|post)_terms\s*\(\s*[^,]+,\s*[^,]+,\s*([\'\"])([^\'\"]+)\1/',
				$source,
				$matches,
				PREG_OFFSET_CAPTURE
			);
			foreach ( $matches[2] ?? [] as $index => $match ) {
				if ( ! isset( $taxonomies[ $match[0] ] ) ) {
					continue;
				}
				$offset = (int) ( $matches[0][ $index ][1] ?? $match[1] );
				$line   = 1 + substr_count( substr( $source, 0, $offset ), "\n" );
				$violations[] = "{$relative}:{$line} ({$match[0]})";
			}
		}

		$this->assertSame( [], $violations, "SCF taxonomy fields bypass native term assignment:\n" . implode( "\n", $violations ) );
	}
}
