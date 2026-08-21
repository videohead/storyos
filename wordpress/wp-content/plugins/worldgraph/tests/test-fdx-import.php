<?php
/**
 * Tests for the Final Draft FDX child importer.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_FDX_Import
 */
class Test_WorldGraph_FDX_Import extends TestCase {

	/**
	 * The child loader must register the bundled FDX extension.
	 */
	public function test_child_loader_registers_fdx_importer(): void {
		$path   = dirname( __DIR__ ) . '/includes/admin/child-plugin-loader.php';
		$source = file_get_contents( $path );

		$this->assertNotFalse( $source );
		$this->assertStringContainsString( "plugins/fdx/fdx-import.php", $source );
		$this->assertStringContainsString( "plugins/fountain/fountain-import.php", $source );
	}

	/**
	 * The browser parser must emit the fields required by the canonical importer.
	 */
	public function test_fdx_parser_emits_worldgraph_contract(): void {
		$path   = dirname( __DIR__ ) . '/plugins/fdx/js/fdx-import.js';
		$source = file_get_contents( $path );

		$this->assertNotFalse( $source );
		foreach ( [ 'project', 'world', 'characters', 'locations', 'scenes', 'shots', 'sounds', 'storyboards', 'sequence' ] as $section ) {
			$this->assertStringContainsString( $section . ':', $source );
		}
		$this->assertStringContainsString( "'Scene Heading'", $source );
		$this->assertStringContainsString( "'Parenthetical'", $source );
		$this->assertStringContainsString( 'DualDialogue', $source );
		$this->assertStringContainsString( 'worldgraph_fdx_json', file_get_contents( dirname( __DIR__ ) . '/plugins/fdx/fdx-import.php' ) );
	}

	/**
	 * Fountain must convert to FDX and call the existing FDX parser contract.
	 */
	public function test_fountain_plugin_routes_through_fdx_parser(): void {
		$php = file_get_contents( dirname( __DIR__ ) . '/plugins/fountain/fountain-import.php' );
		$js  = file_get_contents( dirname( __DIR__ ) . '/plugins/fountain/js/fountain-import.js' );

		$this->assertNotFalse( $php );
		$this->assertNotFalse( $js );
		$this->assertStringContainsString( 'worldgraph-fountain-fdx-parser', $php );
		$this->assertStringContainsString( 'parseFountain', $js );
		$this->assertStringContainsString( 'scriptToFdx', $js );
		$this->assertStringContainsString( 'window.worldgraphParseFdx', $js );
		$this->assertStringContainsString( 'worldgraph_fountain_json', $php );
	}
}
