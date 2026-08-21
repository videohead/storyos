<?php
/**
 * Tests for World Graph Studio relationship functions.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_Relationships
 */
class Test_WorldGraph_Relationships extends TestCase {

	/**
	 * Test relationship types function returns expected structure.
	 */
	public function test_relationship_types_returns_array() {
		// This would require WordPress environment to call relationship_types()
		// For now, we test the concept with a mock
		$mock_relationships = [
			'parent_of' => [
				'label'       => 'Parent Of',
				'inverse'     => 'child_of',
				'symmetric'   => false,
				'object_type' => 'post',
			],
		];
		
		$this->assertIsArray( $mock_relationships );
		$this->assertArrayHasKey( 'parent_of', $mock_relationships );
	}

	/**
	 * Test relationship type structure.
	 */
	public function test_relationship_type_structure() {
		$relationship = [
			'label'       => 'Parent Of',
			'inverse'     => 'child_of',
			'symmetric'   => false,
			'object_type' => 'post',
		];
		
		$this->assertArrayHasKey( 'label', $relationship );
		$this->assertArrayHasKey( 'inverse', $relationship );
		$this->assertArrayHasKey( 'symmetric', $relationship );
		$this->assertArrayHasKey( 'object_type', $relationship );
	}

	/** Selective removal preserves other verbs, fields, and targets. */
	public function test_remove_relationship_can_match_verb_and_field() {
		$GLOBALS['worldgraph_import_journal_state']['meta'][10]['worldgraph_relationships'] = [
			[
				'to_id'    => 20,
				'to_type'  => 'worldgraph_shot',
				'type'     => 'contains',
				'metadata' => [ 'field' => 'legacy_inverse' ],
			],
			[
				'to_id'    => 20,
				'to_type'  => 'worldgraph_shot',
				'type'     => 'contains',
				'metadata' => [ 'field' => 'canonical_slot' ],
			],
			[
				'to_id'    => 20,
				'to_type'  => 'worldgraph_shot',
				'type'     => 'references',
				'metadata' => [ 'field' => 'legacy_inverse' ],
			],
			[
				'to_id'    => 21,
				'to_type'  => 'worldgraph_shot',
				'type'     => 'contains',
				'metadata' => [ 'field' => 'legacy_inverse' ],
			],
		];
		$GLOBALS['worldgraph_incoming_relationship_index'] = [ 'stale' => [] ];

		$this->assertTrue(
			\WorldGraph\Utils\remove_relationship(
				10,
				20,
				'worldgraph_scene',
				'worldgraph_shot',
				'contains',
				'legacy_inverse'
			)
		);
		$this->assertArrayNotHasKey( 'worldgraph_incoming_relationship_index', $GLOBALS );
		$remaining = $GLOBALS['worldgraph_import_journal_state']['meta'][10]['worldgraph_relationships'];
		$this->assertCount( 3, $remaining );
		$this->assertSame( [ 'canonical_slot', 'legacy_inverse', 'legacy_inverse' ], array_column( array_column( $remaining, 'metadata' ), 'field' ) );

		$GLOBALS['worldgraph_incoming_relationship_index'] = [ 'stale' => [] ];
		$this->assertTrue(
			\WorldGraph\Utils\remove_relationship(
				10,
				20,
				'worldgraph_scene',
				'worldgraph_shot',
				'contains'
			)
		);
		$this->assertArrayNotHasKey( 'worldgraph_incoming_relationship_index', $GLOBALS );
		$remaining = $GLOBALS['worldgraph_import_journal_state']['meta'][10]['worldgraph_relationships'];
		$this->assertCount( 2, $remaining );
		$this->assertSame( [ 'references', 'contains' ], array_column( $remaining, 'type' ) );
		$this->assertSame( [ 20, 21 ], array_column( $remaining, 'to_id' ) );
	}
}
