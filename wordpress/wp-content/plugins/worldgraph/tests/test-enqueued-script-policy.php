<?php
/**
 * Regression coverage for first-party JavaScript delivery.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

class Test_Enqueued_Script_Policy extends TestCase {

	/** First-party PHP must not emit executable JavaScript directly. */
	public function test_first_party_php_contains_no_inline_javascript(): void {
		$plugin_dir = dirname( __DIR__ );
		$files      = [ $plugin_dir . '/worldgraph.php' ];

		foreach ( [ 'includes', 'plugins' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$plugin_dir . '/' . $directory,
					FilesystemIterator::SKIP_DOTS
				)
			);

			foreach ( $iterator as $file ) {
				if (
					$file->isFile()
					&& 'php' === strtolower( $file->getExtension() )
					&& false === strpos( $file->getPathname(), '/vendor/' )
				) {
					$files[] = $file->getPathname();
				}
			}
		}

		foreach ( $files as $file ) {
			$source   = file_get_contents( $file );
			$relative = str_replace( $plugin_dir . '/', '', $file );

			$this->assertNotFalse( $source, $relative . ' should be readable.' );
			$this->assertDoesNotMatchRegularExpression(
				'/<script\b/i',
				$source,
				$relative . ' should enqueue JavaScript instead of rendering a script tag.'
			);
			$this->assertDoesNotMatchRegularExpression(
				'/\son(?:click|change|submit|load|error|input|keydown|keyup)\s*=/i',
				$source,
				$relative . ' should bind events from an enqueued script.'
			);
		}
	}
}
