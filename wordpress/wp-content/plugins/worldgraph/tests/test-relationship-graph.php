<?php
/**
 * Tests for Story Graph analytics.
 *
 * @package WorldGraph
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_WorldGraph_Relationship_Graph.
 */
class Test_WorldGraph_Relationship_Graph extends TestCase {

	/**
	 * Analytics include totals and dashboard-ready entities.
	 */
	public function test_graph_analytics_match_dashboard_contract() {
		$analytics = \WorldGraph\Utils\analyze_relationship_graph( $this->graph() );

		$this->assertSame( 5, $analytics['total_entities'] );
		$this->assertSame( 5, $analytics['total_relationships'] );
		$this->assertSame( 'Ada', $analytics['most_connected'][0]['name'] );
		$this->assertSame( 3, $analytics['most_connected'][0]['connection_count'] );
		$this->assertSame( 1, $analytics['relationship_distribution']['related_to'] );
		$this->assertCount( 0, $analytics['isolated_entities'] );
	}

	/**
	 * Character analytics combine direct links and shared scenes.
	 */
	public function test_character_network_includes_relationships_and_presence() {
		$network = \WorldGraph\Utils\analyze_character_network( $this->graph() );

		$this->assertCount( 2, $network['nodes'] );
		$this->assertSame( 'Ada', $network['strongest_relationships'][0]['character_a'] );
		$this->assertSame( 'Ben', $network['strongest_relationships'][0]['character_b'] );
		$this->assertSame( 1, $network['strongest_relationships'][0]['cooccurrences'] );
		$this->assertSame( 'Related To', $network['strongest_relationships'][0]['relationship'] );
		$this->assertSame( [ 'name' => 'Ada', 'scenes' => 1, 'shots' => 1 ], $network['character_scene_presence'][0] );
	}

	/**
	 * Project filtering excludes entities owned by a nearer project.
	 */
	public function test_project_filter_prevents_cross_project_leakage() {
		$graph = $this->graph();
		$graph['nodes'][] = [ 'id' => 10, 'type' => 'worldgraph_project', 'title' => 'Project A' ];
		$graph['nodes'][] = [ 'id' => 11, 'type' => 'worldgraph_project', 'title' => 'Project B' ];
		$graph['nodes'][] = [ 'id' => 12, 'type' => 'worldgraph_world', 'title' => 'World A' ];
		$graph['nodes'][] = [ 'id' => 13, 'type' => 'worldgraph_world', 'title' => 'World B' ];
		$graph['edges'][] = [ 'source' => 10, 'target' => 12, 'type' => 'contains' ];
		$graph['edges'][] = [ 'source' => 11, 'target' => 13, 'type' => 'contains' ];
		$graph['edges'][] = [ 'source' => 12, 'target' => 1, 'type' => 'contains' ];
		$graph['edges'][] = [ 'source' => 13, 'target' => 2, 'type' => 'contains' ];

		$filtered = \WorldGraph\Utils\filter_relationship_graph_by_project( $graph, 10 );
		$ids      = array_column( $filtered['nodes'], 'id' );

		$this->assertContains( 1, $ids );
		$this->assertContains( 3, $ids );
		$this->assertNotContains( 2, $ids );
		$this->assertNotContains( 11, $ids );
		$this->assertNotContains( 13, $ids );
	}

	/**
	 * Fixture graph.
	 *
	 * @return array
	 */
	private function graph(): array {
		return [
			'nodes' => [
				[ 'id' => 1, 'type' => 'worldgraph_character', 'title' => 'Ada' ],
				[ 'id' => 2, 'type' => 'worldgraph_character', 'title' => 'Ben' ],
				[ 'id' => 3, 'type' => 'worldgraph_scene', 'title' => 'The Arrival' ],
				[ 'id' => 4, 'type' => 'worldgraph_shot', 'title' => 'Close-up' ],
				[ 'id' => 5, 'type' => 'worldgraph_location', 'title' => 'Station' ],
			],
			'edges' => [
				[ 'source' => 1, 'target' => 2, 'type' => 'related_to' ],
				[ 'source' => 1, 'target' => 3, 'type' => 'appears_in' ],
				[ 'source' => 2, 'target' => 3, 'type' => 'appears_in' ],
				[ 'source' => 1, 'target' => 4, 'type' => 'appears_in' ],
				[ 'source' => 3, 'target' => 5, 'type' => 'located_in' ],
			],
		];
	}
}