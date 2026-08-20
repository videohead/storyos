<?php
/**
 * Tests for the internal generation-job record type.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_Generation_Record
 */
class Test_WorldGraph_Generation_Record extends TestCase {

	/** Generation jobs must remain hidden behind their dedicated REST routes. */
	public function test_generation_record_type_is_internal(): void {
		$args = \WorldGraph\Utils\worldgraph_get_generation_record_cpt_args();

		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertFalse( $args['show_ui'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );
		$this->assertFalse( $args['rewrite'] );
		$this->assertFalse( $args['can_export'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertSame( [ 'title' ], $args['supports'] );
	}

	/** Existing legacy jobs must be registered before their post type is migrated. */
	public function test_generation_record_type_registers_before_namespace_migration(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/worldgraph.php' );

		$this->assertNotFalse( $source );
		$registration = strpos( $source, 'Utils\\worldgraph_register_generation_record_type();' );
		$migration    = strpos( $source, 'Utils\\worldgraph_maybe_migrate_cpt_keys();' );

		$this->assertNotFalse( $registration );
		$this->assertNotFalse( $migration );
		$this->assertLessThan( $migration, $registration );
	}
}
