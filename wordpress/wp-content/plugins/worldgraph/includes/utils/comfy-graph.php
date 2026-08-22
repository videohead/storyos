<?php
/**
 * Conversion of ComfyUI editor graphs into the API prompt format.
 *
 * Every workflow ComfyUI publishes is an editor graph: nodes carry positional
 * `widgets_values`, connections live in a separate link table, and whole
 * regions can be collapsed into reusable subgraphs. ComfyUI's executor accepts
 * none of that; it wants a flat `{id: {class_type, inputs}}` map. The editor
 * normally does the translation in the browser, which is why "Save (API
 * Format)" exists and why a downloaded template cannot be submitted as-is.
 *
 * This class performs that translation server-side so a published template can
 * be discovered, converted, and stored as a World Graph Studio Template without
 * a human ever opening the ComfyUI editor. Positional widget values can only be
 * named by consulting the target instance's `/object_info`, so conversion is
 * deliberately bound to a specific ComfyUI: a graph that converts is a graph
 * that instance can actually run.
 *
 * @package WorldGraph
 */

namespace WorldGraph\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ComfyUI editor-graph to API-prompt converter.
 */
class Comfy_Graph {

	/**
	 * Socket types that a widget can supply a value for. Anything else is a
	 * link-only socket and never consumes a `widgets_values` slot.
	 */
	const WIDGET_TYPES = [ 'INT', 'FLOAT', 'STRING', 'BOOLEAN', 'COMBO' ];

	/**
	 * Node types that only pass a value through and never execute.
	 */
	const PASSTHROUGH_TYPES = [ 'Reroute', 'Reroute (rgthree)', 'PrimitiveNode' ];

	/**
	 * Seed inputs that must be re-rolled per job or every render repeats.
	 */
	const SEED_FIELDS = [ 'seed', 'noise_seed' ];

	/**
	 * Inputs that carry prompt text. Encoders disagree: Flux splits a prompt
	 * across `clip_l` and `t5xxl`, SDXL across `text_g` and `text_l`, and newer
	 * graphs hoist it into a standalone string node feeding several encoders.
	 */
	const PROMPT_FIELDS = [ 'text', 'prompt', 'clip_l', 't5xxl', 'text_g', 'text_l', 'string' ];

	/**
	 * Recursion ceiling for link resolution through subgraphs and reroutes.
	 */
	const MAX_DEPTH = 128;

	/**
	 * Decoded `/object_info` for the instance being converted against.
	 *
	 * @var array
	 */
	private static $object_info = [];

	/**
	 * Subgraph definitions, keyed by definition ID.
	 *
	 * @var array
	 */
	private static $subgraphs = [];

	/**
	 * Graph contexts (the root graph plus one per subgraph instance).
	 *
	 * @var array
	 */
	private static $contexts = [];

	/**
	 * Executable nodes discovered across every context, keyed by unique ID.
	 *
	 * @var array
	 */
	private static $nodes = [];

	/**
	 * Whether a decoded workflow is an editor graph rather than API format.
	 *
	 * @param array $graph Decoded workflow.
	 * @return bool
	 */
	public static function is_editor_graph( array $graph ): bool {
		return isset( $graph['nodes'] ) && is_array( $graph['nodes'] );
	}

	/**
	 * Convert an editor graph into an API prompt.
	 *
	 * @param array $graph       Decoded editor graph, or an already-API graph.
	 * @param array $object_info Decoded ComfyUI `/object_info` payload.
	 * @return array|WP_Error API-format workflow.
	 */
	public static function to_api( array $graph, array $object_info ) {
		if ( ! self::is_editor_graph( $graph ) ) {
			return $graph;
		}
		if ( empty( $object_info ) ) {
			return new WP_Error(
				'worldgraph_comfy_graph_no_catalog',
				__( 'Converting a published ComfyUI workflow needs the target instance\'s node catalog. Check that ComfyUI is reachable and try again.', 'worldgraph' )
			);
		}

		self::$object_info = $object_info;
		self::$subgraphs   = [];
		self::$contexts    = [];
		self::$nodes       = [];

		foreach ( (array) ( $graph['definitions']['subgraphs'] ?? [] ) as $definition ) {
			if ( is_array( $definition ) && ! empty( $definition['id'] ) ) {
				self::$subgraphs[ (string) $definition['id'] ] = $definition;
			}
		}

		self::register_context( '', $graph, null, [] );

		$api      = [];
		$is_link  = [];
		$missing  = [];
		foreach ( self::$nodes as $uid => $record ) {
			$node  = $record['node'];
			$class = (string) ( $node['type'] ?? '' );
			$mode  = (int) ( $node['mode'] ?? 0 );

			// 2 is muted and 4 is bypassed; both are removed from execution and
			// their consumers were already re-pointed during link resolution.
			if ( 2 === $mode || 4 === $mode ) {
				continue;
			}
			if ( ! isset( self::$object_info[ $class ] ) ) {
				// Notes, markdown, and other editor-only nodes are absent from
				// /object_info by design; a genuinely missing custom node is
				// reported so the operator learns what to install.
				if ( ! self::is_editor_only( $class ) ) {
					$missing[ $class ] = true;
				}
				continue;
			}

			$inputs = self::widget_inputs( $class, $node );
			foreach ( (array) ( $node['inputs'] ?? [] ) as $entry ) {
				$name = (string) ( $entry['name'] ?? '' );
				if ( '' === $name || ! isset( $entry['link'] ) || null === $entry['link'] ) {
					continue;
				}

				$resolved = self::resolve_link( $record['ctx'], $entry['link'] );
				if ( null === $resolved ) {
					continue;
				}
				if ( '__literal__' === $resolved[0] ) {
					$inputs[ $name ] = $resolved[1];
					continue;
				}

				$inputs[ $name ]   = [ (string) $resolved[0], (int) $resolved[1] ];
				$is_link[ $uid ][] = $name;
			}

			$api[ $uid ] = [
				'class_type' => $class,
				'inputs'     => $inputs,
				'_meta'      => [ 'title' => (string) ( $node['title'] ?? $class ) ],
			];
		}

		if ( empty( $api ) ) {
			return new WP_Error(
				'worldgraph_comfy_graph_empty',
				! empty( $missing )
					? sprintf(
						/* translators: %s: comma-separated node class names. */
						__( 'This workflow needs ComfyUI nodes that are not installed: %s', 'worldgraph' ),
						implode( ', ', array_keys( $missing ) )
					)
					: __( 'This workflow contained no executable ComfyUI nodes.', 'worldgraph' )
			);
		}

		$api = self::drop_dangling_links( $api, $is_link );
		$api = self::prune_unreachable( $api, $is_link );

		if ( ! empty( $missing ) ) {
			// A workflow that lost a node is a workflow that will not run, so
			// this is an error rather than a silently degraded graph.
			return new WP_Error(
				'worldgraph_comfy_graph_missing_nodes',
				sprintf(
					/* translators: %s: comma-separated node class names. */
					__( 'This workflow needs ComfyUI nodes that are not installed: %s', 'worldgraph' ),
					implode( ', ', array_keys( $missing ) )
				),
				[ 'missing_nodes' => array_keys( $missing ) ]
			);
		}

		return self::renumber( $api, $is_link );
	}

	/**
	 * Replace the positive and negative prompt text of a converted workflow
	 * with the placeholders the generation runner substitutes per job.
	 *
	 * @param array $api API-format workflow.
	 * @return array
	 */
	public static function apply_prompt_placeholders( array $api ): array {
		$assigned = [];

		foreach ( $api as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			foreach ( [ 'positive' => '{{prompt}}', 'negative' => '{{negative_prompt}}' ] as $socket => $placeholder ) {
				$reference = $node['inputs'][ $socket ] ?? null;
				if ( ! self::is_reference( $reference ) ) {
					continue;
				}
				$target = self::find_text_input( $api, (string) $reference[0], [] );
				if ( null !== $target && ! isset( $assigned[ $target ] ) ) {
					$assigned[ $target ] = $placeholder;
				}
			}
		}

		// A sampler-less graph (API-node workflows, for one) still has exactly
		// one text field that means "the prompt".
		if ( empty( $assigned ) ) {
			$candidates = [];
			foreach ( $api as $uid => $node ) {
				if ( ! empty( self::prompt_fields( $node ) ) ) {
					$candidates[] = (string) $uid;
				}
			}
			if ( 1 === count( $candidates ) ) {
				$assigned[ $candidates[0] ] = '{{prompt}}';
			}
		}

		foreach ( $assigned as $uid => $placeholder ) {
			foreach ( self::prompt_fields( $api[ $uid ] ) as $field ) {
				$api[ $uid ]['inputs'][ $field ] = $placeholder;
			}
		}

		return $api;
	}

	/**
	 * The prompt-carrying string inputs a node holds a literal value for.
	 *
	 * @param array $node API-format node.
	 * @return array<int, string>
	 */
	private static function prompt_fields( array $node ): array {
		$fields = self::PROMPT_FIELDS;
		if ( 0 === strpos( (string) ( $node['class_type'] ?? '' ), 'PrimitiveString' ) ) {
			$fields[] = 'value';
		}

		return array_values( array_filter( $fields, static function ( string $field ) use ( $node ): bool {
			return is_string( $node['inputs'][ $field ] ?? null );
		} ) );
	}

	/**
	 * Re-roll every literal seed in a workflow.
	 *
	 * @param array $api API-format workflow.
	 * @return array
	 */
	public static function randomize_seeds( array $api ): array {
		foreach ( $api as $uid => $node ) {
			foreach ( self::SEED_FIELDS as $field ) {
				if ( isset( $node['inputs'][ $field ] ) && is_numeric( $node['inputs'][ $field ] ) ) {
					$api[ $uid ]['inputs'][ $field ] = wp_rand( 0, PHP_INT_MAX >> 1 );
				}
			}
		}

		return $api;
	}

	/**
	 * Register a graph body and every subgraph instance inside it.
	 *
	 * @param string      $key      Context key; the empty string is the root.
	 * @param array       $body     Graph body with `nodes` and `links`.
	 * @param string|null $parent   Parent context key, or null for the root.
	 * @param array       $instance Subgraph instance node in the parent context.
	 */
	private static function register_context( string $key, array $body, ?string $parent, array $instance ): void {
		if ( isset( self::$contexts[ $key ] ) ) {
			return;
		}

		$context = [
			'prefix'   => '' === $key ? '' : $key . ':',
			'parent'   => $parent,
			'instance' => $instance,
			'def'      => $body,
			'nodes'    => [],
			'links'    => [],
		];

		foreach ( (array) ( $body['links'] ?? [] ) as $link ) {
			$normalized = self::normalize_link( $link );
			if ( null !== $normalized ) {
				$context['links'][ $normalized['id'] ] = $normalized;
			}
		}
		foreach ( (array) ( $body['nodes'] ?? [] ) as $node ) {
			if ( is_array( $node ) && isset( $node['id'] ) ) {
				$context['nodes'][ (string) $node['id'] ] = $node;
			}
		}

		self::$contexts[ $key ] = $context;

		foreach ( $context['nodes'] as $id => $node ) {
			$type = (string) ( $node['type'] ?? '' );
			$uid  = $context['prefix'] . $id;

			if ( isset( self::$subgraphs[ $type ] ) ) {
				self::register_context( $uid, self::$subgraphs[ $type ], $key, $node );
				continue;
			}
			if ( in_array( $type, self::PASSTHROUGH_TYPES, true ) ) {
				continue;
			}

			self::$nodes[ $uid ] = [ 'ctx' => $key, 'node' => $node ];
		}
	}

	/**
	 * Normalize the two link encodings ComfyUI emits: positional arrays in the
	 * root graph and objects inside subgraph definitions.
	 *
	 * @param mixed $link Raw link.
	 * @return array|null
	 */
	private static function normalize_link( $link ): ?array {
		if ( ! is_array( $link ) ) {
			return null;
		}
		if ( isset( $link['id'] ) ) {
			return [
				'id'          => (string) $link['id'],
				'origin_id'   => (string) ( $link['origin_id'] ?? '' ),
				'origin_slot' => (int) ( $link['origin_slot'] ?? 0 ),
				'target_id'   => (string) ( $link['target_id'] ?? '' ),
				'target_slot' => (int) ( $link['target_slot'] ?? 0 ),
			];
		}
		if ( count( $link ) < 5 ) {
			return null;
		}

		return [
			'id'          => (string) $link[0],
			'origin_id'   => (string) $link[1],
			'origin_slot' => (int) $link[2],
			'target_id'   => (string) $link[3],
			'target_slot' => (int) $link[4],
		];
	}

	/**
	 * Resolve a link to the executable node and output slot that feeds it.
	 *
	 * @param string $ctx_key Context key the link belongs to.
	 * @param mixed  $link_id Link ID.
	 * @param int    $depth   Recursion depth.
	 * @return array|null `[uid, slot]`, `['__literal__', value]`, or null.
	 */
	private static function resolve_link( string $ctx_key, $link_id, int $depth = 0 ): ?array {
		if ( null === $link_id || $depth > self::MAX_DEPTH ) {
			return null;
		}
		$link = self::$contexts[ $ctx_key ]['links'][ (string) $link_id ] ?? null;
		if ( null === $link ) {
			return null;
		}

		return self::resolve_output( $ctx_key, $link['origin_id'], $link['origin_slot'], $depth + 1 );
	}

	/**
	 * Resolve an output slot, following subgraph boundaries, reroutes, and
	 * bypassed nodes until an executable node is reached.
	 *
	 * @param string $ctx_key   Context key.
	 * @param string $origin_id Origin node ID within that context.
	 * @param int    $slot      Output slot index.
	 * @param int    $depth     Recursion depth.
	 * @return array|null
	 */
	private static function resolve_output( string $ctx_key, string $origin_id, int $slot, int $depth ): ?array {
		if ( $depth > self::MAX_DEPTH ) {
			return null;
		}
		$context = self::$contexts[ $ctx_key ] ?? null;
		if ( null === $context ) {
			return null;
		}

		$input_boundary = (string) ( $context['def']['inputNode']['id'] ?? '-10' );
		if ( null !== $context['parent'] && $origin_id === $input_boundary ) {
			return self::resolve_boundary_input( $ctx_key, $slot, $depth + 1 );
		}

		$node = $context['nodes'][ $origin_id ] ?? null;
		if ( null === $node ) {
			return null;
		}

		$type = (string) ( $node['type'] ?? '' );
		$uid  = $context['prefix'] . $origin_id;

		if ( isset( self::$subgraphs[ $type ] ) ) {
			return self::resolve_subgraph_output( $uid, $slot, $depth + 1 );
		}

		$mode = (int) ( $node['mode'] ?? 0 );
		if ( 2 === $mode ) {
			return null;
		}
		if ( 4 === $mode || in_array( $type, self::PASSTHROUGH_TYPES, true ) ) {
			return self::resolve_passthrough( $ctx_key, $node, $slot, $depth + 1 );
		}

		return [ $uid, $slot ];
	}

	/**
	 * Resolve a subgraph input socket to whatever the parent bound to it.
	 *
	 * @param string $ctx_key Subgraph context key.
	 * @param int    $slot    Input slot index.
	 * @param int    $depth   Recursion depth.
	 * @return array|null
	 */
	private static function resolve_boundary_input( string $ctx_key, int $slot, int $depth ): ?array {
		$context  = self::$contexts[ $ctx_key ];
		$name     = (string) ( $context['def']['inputs'][ $slot ]['name'] ?? '' );
		$instance = $context['instance'];

		foreach ( (array) ( $instance['inputs'] ?? [] ) as $entry ) {
			if ( (string) ( $entry['name'] ?? '' ) !== $name ) {
				continue;
			}
			if ( isset( $entry['link'] ) && null !== $entry['link'] ) {
				return self::resolve_link( (string) $context['parent'], $entry['link'], $depth );
			}
			break;
		}

		$promoted = self::promoted_values( $context );
		if ( array_key_exists( $name, $promoted ) ) {
			return [ '__literal__', $promoted[ $name ] ];
		}

		// Nothing bound the socket, so the node inside the subgraph keeps the
		// widget value it was saved with.
		return null;
	}

	/**
	 * Resolve a subgraph output socket to the node inside that feeds it.
	 *
	 * @param string $ctx_key Subgraph context key.
	 * @param int    $slot    Output slot index.
	 * @param int    $depth   Recursion depth.
	 * @return array|null
	 */
	private static function resolve_subgraph_output( string $ctx_key, int $slot, int $depth ): ?array {
		$context = self::$contexts[ $ctx_key ] ?? null;
		if ( null === $context ) {
			return null;
		}

		$boundary = (string) ( $context['def']['outputNode']['id'] ?? '-20' );
		foreach ( $context['links'] as $id => $link ) {
			if ( $link['target_id'] === $boundary && $link['target_slot'] === $slot ) {
				return self::resolve_link( $ctx_key, $id, $depth );
			}
		}
		foreach ( (array) ( $context['def']['outputs'][ $slot ]['linkIds'] ?? [] ) as $id ) {
			$resolved = self::resolve_link( $ctx_key, $id, $depth );
			if ( null !== $resolved ) {
				return $resolved;
			}
		}

		return null;
	}

	/**
	 * Route around a reroute or bypassed node by finding the input carrying the
	 * same socket type as the requested output.
	 *
	 * @param string $ctx_key Context key.
	 * @param array  $node    Editor node.
	 * @param int    $slot    Output slot index.
	 * @param int    $depth   Recursion depth.
	 * @return array|null
	 */
	private static function resolve_passthrough( string $ctx_key, array $node, int $slot, int $depth ): ?array {
		$wanted = (string) ( $node['outputs'][ $slot ]['type'] ?? '' );

		foreach ( (array) ( $node['inputs'] ?? [] ) as $entry ) {
			$type = (string) ( $entry['type'] ?? '' );
			if ( '' !== $wanted && '*' !== $type && $type !== $wanted ) {
				continue;
			}
			if ( isset( $entry['link'] ) && null !== $entry['link'] ) {
				return self::resolve_link( $ctx_key, $entry['link'], $depth );
			}
		}

		return null;
	}

	/**
	 * Promoted widget values a subgraph instance overrides its interior with.
	 *
	 * @param array $context Subgraph context.
	 * @return array<string, mixed>
	 */
	private static function promoted_values( array $context ): array {
		$values = $context['instance']['widgets_values'] ?? [];
		if ( ! is_array( $values ) || empty( $values ) ) {
			return [];
		}
		if ( self::is_map( $values ) ) {
			return $values;
		}

		$promoted = [];
		foreach ( array_values( (array) ( $context['def']['inputs'] ?? [] ) ) as $index => $input ) {
			if ( ! array_key_exists( $index, $values ) ) {
				break;
			}
			$name = (string) ( $input['name'] ?? '' );
			if ( '' !== $name ) {
				$promoted[ $name ] = $values[ $index ];
			}
		}

		return $promoted;
	}

	/**
	 * Name a node's positional widget values by walking the input order that
	 * `/object_info` declares for its class.
	 *
	 * @param string $class Node class type.
	 * @param array  $node  Editor node.
	 * @return array<string, mixed>
	 */
	private static function widget_inputs( string $class, array $node ): array {
		$values = $node['widgets_values'] ?? [];
		if ( ! is_array( $values ) || empty( $values ) ) {
			return [];
		}

		$spec  = (array) ( self::$object_info[ $class ]['input'] ?? [] );
		$order = (array) ( self::$object_info[ $class ]['input_order'] ?? [] );
		$names = [];

		foreach ( [ 'required', 'optional' ] as $group ) {
			$group_spec  = (array) ( $spec[ $group ] ?? [] );
			$group_names = isset( $order[ $group ] ) && is_array( $order[ $group ] )
				? array_map( 'strval', $order[ $group ] )
				: array_map( 'strval', array_keys( $group_spec ) );

			foreach ( $group_names as $name ) {
				$definition = $group_spec[ $name ] ?? null;
				if ( ! is_array( $definition ) || ! self::is_widget_type( $definition[0] ?? null ) ) {
					continue;
				}

				$names[] = $name;

				// A seed widget is followed by an unnamed "control after
				// generate" value that has no place in the API prompt, unless
				// this ComfyUI already exposes it as a real input.
				$options = is_array( $definition[1] ?? null ) ? $definition[1] : [];
				if ( ! empty( $options['control_after_generate'] ) && ! in_array( 'control_after_generate', $group_names, true ) ) {
					$names[] = '';
				}
			}
		}

		if ( self::is_map( $values ) ) {
			return array_intersect_key( $values, array_flip( array_filter( $names ) ) );
		}

		$inputs = [];
		foreach ( array_values( $values ) as $index => $value ) {
			$name = $names[ $index ] ?? '';
			if ( '' !== $name ) {
				$inputs[ $name ] = $value;
			}
		}

		return $inputs;
	}

	/**
	 * Whether a socket type accepts a widget value.
	 *
	 * @param mixed $type Socket type from `/object_info`.
	 * @return bool
	 */
	private static function is_widget_type( $type ): bool {
		if ( is_array( $type ) ) {
			return true;
		}

		return is_string( $type ) && in_array( strtoupper( $type ), self::WIDGET_TYPES, true );
	}

	/**
	 * Node classes that exist only in the editor and never execute.
	 *
	 * @param string $class Node class type.
	 * @return bool
	 */
	private static function is_editor_only( string $class ): bool {
		if ( '' === $class || in_array( $class, self::PASSTHROUGH_TYPES, true ) ) {
			return true;
		}

		return (bool) preg_match( '/^(Note|MarkdownNote|Bookmark|Anything Everywhere.*|Fast Groups.*)$/', $class );
	}

	/**
	 * Drop input references to nodes that were removed during conversion.
	 *
	 * @param array $api     API-format workflow.
	 * @param array $is_link Map of node ID to the input names holding links.
	 * @return array
	 */
	private static function drop_dangling_links( array $api, array &$is_link ): array {
		foreach ( $is_link as $uid => $fields ) {
			foreach ( $fields as $index => $field ) {
				$reference = $api[ $uid ]['inputs'][ $field ] ?? null;
				if ( ! self::is_reference( $reference ) || ! isset( $api[ (string) $reference[0] ] ) ) {
					unset( $api[ $uid ]['inputs'][ $field ], $is_link[ $uid ][ $index ] );
				}
			}
		}

		return $api;
	}

	/**
	 * Keep only the nodes an output node depends on, so editor scratch space
	 * and disabled branches never reach the executor.
	 *
	 * @param array $api     API-format workflow.
	 * @param array $is_link Map of node ID to the input names holding links.
	 * @return array
	 */
	private static function prune_unreachable( array $api, array &$is_link ): array {
		$queue = [];
		foreach ( $api as $uid => $node ) {
			if ( ! empty( self::$object_info[ $node['class_type'] ]['output_node'] ) ) {
				$queue[] = (string) $uid;
			}
		}
		if ( empty( $queue ) ) {
			return $api;
		}

		$reached = [];
		while ( ! empty( $queue ) ) {
			$uid = array_pop( $queue );
			if ( isset( $reached[ $uid ] ) || ! isset( $api[ $uid ] ) ) {
				continue;
			}
			$reached[ $uid ] = true;

			foreach ( (array) ( $is_link[ $uid ] ?? [] ) as $field ) {
				$reference = $api[ $uid ]['inputs'][ $field ] ?? null;
				if ( self::is_reference( $reference ) ) {
					$queue[] = (string) $reference[0];
				}
			}
		}

		foreach ( array_keys( $api ) as $uid ) {
			if ( ! isset( $reached[ (string) $uid ] ) ) {
				unset( $api[ $uid ], $is_link[ $uid ] );
			}
		}

		return $api;
	}

	/**
	 * Replace namespaced subgraph IDs with sequential numeric ones.
	 *
	 * @param array $api     API-format workflow.
	 * @param array $is_link Map of node ID to the input names holding links.
	 * @return array
	 */
	private static function renumber( array $api, array $is_link ): array {
		$map    = [];
		$number = 1;
		foreach ( array_keys( $api ) as $uid ) {
			$map[ (string) $uid ] = (string) $number++;
		}

		$renumbered = [];
		foreach ( $api as $uid => $node ) {
			foreach ( (array) ( $is_link[ $uid ] ?? [] ) as $field ) {
				$reference = $node['inputs'][ $field ] ?? null;
				if ( self::is_reference( $reference ) && isset( $map[ (string) $reference[0] ] ) ) {
					$node['inputs'][ $field ] = [ $map[ (string) $reference[0] ], (int) $reference[1] ];
				}
			}
			$renumbered[ $map[ (string) $uid ] ] = $node;
		}

		return $renumbered;
	}

	/**
	 * Walk a conditioning chain back to the node holding its prompt text.
	 *
	 * @param array $api  API-format workflow.
	 * @param string $uid Node ID to inspect.
	 * @param array $seen Visited node IDs.
	 * @return string|null
	 */
	private static function find_text_input( array $api, string $uid, array $seen ): ?string {
		if ( isset( $seen[ $uid ] ) || ! isset( $api[ $uid ] ) ) {
			return null;
		}
		$seen[ $uid ] = true;

		if ( ! empty( self::prompt_fields( $api[ $uid ] ) ) ) {
			return $uid;
		}

		foreach ( (array) ( $api[ $uid ]['inputs'] ?? [] ) as $value ) {
			if ( ! self::is_reference( $value ) ) {
				continue;
			}
			$found = self::find_text_input( $api, (string) $value[0], $seen );
			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Whether a value is a `[node_id, slot]` link reference.
	 *
	 * @param mixed $value Input value.
	 * @return bool
	 */
	private static function is_reference( $value ): bool {
		return is_array( $value )
			&& 2 === count( $value )
			&& isset( $value[0], $value[1] )
			&& is_scalar( $value[0] )
			&& is_int( $value[1] );
	}

	/**
	 * Whether an array is keyed by name rather than position.
	 *
	 * @param array $value Array to test.
	 * @return bool
	 */
	private static function is_map( array $value ): bool {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
