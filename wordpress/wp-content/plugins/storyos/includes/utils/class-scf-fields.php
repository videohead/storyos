<?php
/**
 * Secure Custom Fields integration for the StoryOS content model.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

/**
 * Persists StoryOS field contracts as SCF-managed field groups and adapts SCF
 * fields back to the small field dialect used by the StoryOS APIs.
 */
final class SCF_Fields {

	/**
	 * Increment when the code-defined field contract adds required fields.
	 */
	private const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option used to make field-group synchronization an idempotent migration.
	 */
	private const SCHEMA_VERSION_OPTION = 'storyos_scf_schema_version';

	/**
	 * Whether runtime hooks have been registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Runtime field cache, keyed by CPT.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static $field_cache = [];

	/**
	 * Initialize persisted groups and value synchronization.
	 *
	 * StoryOS runs on init priority 10, after SCF has registered its internal
	 * post types and APIs at priority 5. Persisted groups are used deliberately:
	 * unlike PHP-local groups, administrators can edit them in SCF's Field
	 * Groups screen.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $definitions Code-defined fields.
	 */
	public static function boot( array $definitions ): void {
		if ( ! self::is_available() ) {
			return;
		}

		self::sync_groups( $definitions );

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;
		add_filter( 'acf/update_value', [ __CLASS__, 'filter_update_value' ], 20, 4 );
		add_filter( 'acf/load_value', [ __CLASS__, 'filter_load_value' ], 20, 3 );
		add_filter( 'acf/prepare_field', [ __CLASS__, 'prepare_field' ] );
		add_action( 'added_post_meta', [ __CLASS__, 'sync_reference_meta' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'sync_reference_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'delete_reference_meta' ], 10, 4 );
	}

	/**
	 * Whether the installed SCF API is ready for persisted field groups.
	 */
	private static function is_available(): bool {
		return function_exists( 'acf_get_field_group' )
			&& function_exists( 'acf_get_field_groups' )
			&& function_exists( 'acf_get_fields' )
			&& function_exists( 'acf_import_field_group' );
	}

	/**
	 * Get the stable SCF group key for a StoryOS CPT.
	 *
	 * @param string $cpt CPT slug.
	 */
	public static function group_key( string $cpt ): string {
		return 'group_' . self::key_fragment( $cpt );
	}

	/**
	 * Get a globally unique, stable SCF field key.
	 *
	 * Field names repeat across StoryOS CPTs, so the CPT must be part of the key.
	 *
	 * @param string $cpt        CPT slug.
	 * @param string $field_name Field name.
	 */
	public static function field_key( string $cpt, string $field_name ): string {
		return 'field_' . self::key_fragment( $cpt . '_' . $field_name );
	}

	/**
	 * Normalize a string for an SCF key without requiring WordPress in unit tests.
	 */
	private static function key_fragment( string $value ): string {
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
		return trim( (string) $value, '_' );
	}

	/**
	 * Synchronize Local JSON groups to editable SCF database records.
	 *
	 * The committed JSON files are the portable archive. Database copies provide
	 * SCF's normal editing UI; when an administrator saves a StoryOS group, SCF
	 * writes it back to the plugin JSON directory through the save-path filter.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $definitions Code-defined fields.
	 */
	private static function sync_groups( array $definitions ): void {
		$schema_changed = self::SCHEMA_VERSION !== (string) get_option( self::SCHEMA_VERSION_OPTION, '' );

		foreach ( $definitions as $cpt => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$group = acf_get_field_group( self::group_key( $cpt ) );
			if ( ! $group ) {
				// Defensive fallback for a missing archive file. The alignment tests
				// still fail until the generated group is committed to acf-json/.
				$group = self::build_group( $cpt, $fields );
			}

			// acf_get_raw_field_group() bypasses Local JSON and checks the
			// database. Import only when no editable copy exists.
			$db_group = function_exists( 'acf_get_raw_field_group' )
				? acf_get_raw_field_group( self::group_key( $cpt ) )
				: false;
			if ( ! $db_group ) {
				$import = $group;
				unset( $import['ID'], $import['local'], $import['local_file'] );
				acf_import_field_group( $import );
			}
		}

		if ( $schema_changed ) {
			update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		}

		self::$field_cache = [];
	}

	/**
	 * Build one SCF field group located on a StoryOS CPT.
	 *
	 * @param string                                      $cpt    CPT slug.
	 * @param array<string, array<string, mixed>>          $fields StoryOS fields.
	 * @return array<string, mixed>
	 */
	private static function build_group( string $cpt, array $fields ): array {
		$labels = storyos_get_all_cpts();
		$title  = isset( $labels[ $cpt ] ) ? $labels[ $cpt ] : ucwords( str_replace( '_', ' ', $cpt ) );
		$scf_fields = [];
		$menu_order = 0;

		foreach ( $fields as $field_name => $field ) {
			$scf_fields[] = self::to_scf_field( $cpt, $field_name, $field, $menu_order++ );
		}

		return [
			'key'                   => self::group_key( $cpt ),
			'title'                 => sprintf( 'StoryOS: %s Fields', $title ),
			'fields'                => $scf_fields,
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => $cpt,
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => 'Structured metadata for the StoryOS Story Graph. This persisted group may be managed in Secure Custom Fields.',
			'show_in_rest'          => 1,
		];
	}

	/**
	 * Convert one StoryOS field definition to SCF's field schema.
	 *
	 * @param string               $cpt        CPT slug.
	 * @param string               $field_name Field name.
	 * @param array<string, mixed> $field      StoryOS field definition.
	 * @param int                  $menu_order Field order.
	 * @return array<string, mixed>
	 */
	public static function to_scf_field( string $cpt, string $field_name, array $field, int $menu_order = 0 ): array {
		$type = (string) ( $field['type'] ?? 'text' );
		$key  = self::field_key( $cpt, $field_name );
		$scf  = [
			'key'               => $key,
			'label'             => (string) ( $field['label'] ?? ucwords( str_replace( '_', ' ', $field_name ) ) ),
			'name'              => $field_name,
			'aria-label'        => '',
			'type'              => $type,
			'instructions'      => (string) ( $field['description'] ?? '' ),
			'required'          => empty( $field['required'] ) ? 0 : 1,
			'conditional_logic' => 0,
			'wrapper'           => [
				'width' => '',
				'class' => '',
				'id'    => '',
			],
			'menu_order'        => $menu_order,
		];

		if ( array_key_exists( 'default', $field ) ) {
			$scf['default_value'] = $field['default'];
		}

		switch ( $type ) {
			case 'date':
				$scf['type']           = 'date_picker';
				$scf['display_format'] = 'Y-m-d';
				$scf['return_format']  = 'Y-m-d';
				$scf['first_day']      = 1;
				break;

			case 'select':
				$scf['choices']      = (array) ( $field['options'] ?? [] );
				$scf['allow_null']   = empty( $field['required'] ) ? 1 : 0;
				$scf['multiple']     = empty( $field['multiple'] ) ? 0 : 1;
				$scf['ui']           = 1;
				$scf['return_format'] = 'value';
				break;

			case 'taxonomy':
				$multiple             = ! empty( $field['multiple'] );
				$scf['taxonomy']      = (string) ( $field['taxonomy'] ?? 'category' );
				$scf['field_type']    = $multiple ? 'multi_select' : 'select';
				$scf['multiple']      = $multiple ? 1 : 0;
				$scf['allow_null']    = empty( $field['required'] ) ? 1 : 0;
				$scf['return_format'] = 'id';
				$scf['add_term']      = 1;
				$scf['load_terms']    = 1;
				$scf['save_terms']    = 1;
				break;

			case 'relationship':
				$multiple             = ! empty( $field['multiple'] );
				$scf['type']          = $multiple ? 'relationship' : 'post_object';
				$scf['post_type']     = [ (string) ( $field['related_cpt'] ?? '' ) ];
				$scf['return_format'] = 'id';
				if ( $multiple ) {
					$scf['filters'] = [ 'search' ];
					$scf['min']     = empty( $field['required'] ) ? 0 : 1;
					$scf['max']     = 0;
				} else {
					$scf['allow_null'] = empty( $field['required'] ) ? 1 : 0;
					$scf['multiple']   = 0;
					$scf['ui']         = 1;
				}

				$taxonomy_filters = self::relationship_taxonomy_filters( $field );
				if ( ! empty( $taxonomy_filters ) ) {
					$scf['taxonomy'] = $taxonomy_filters;
				}
				break;

			case 'user':
				$scf['role']          = '';
				$scf['multiple']      = empty( $field['multiple'] ) ? 0 : 1;
				$scf['allow_null']    = empty( $field['required'] ) ? 1 : 0;
				$scf['return_format'] = 'id';
				break;

			case 'structured':
				$scf['type']         = 'repeater';
				$scf['layout']       = 'row';
				$scf['button_label'] = 'Add Row';
				$scf['min']          = 0;
				$scf['max']          = 0;
				$scf['sub_fields']   = self::structured_sub_fields( $cpt, $key, $field );
				break;
		}

		return $scf;
	}

	/**
	 * Convert StoryOS relationship query args into SCF taxonomy filters.
	 *
	 * @param array<string, mixed> $field StoryOS field definition.
	 * @return array<int, string>
	 */
	private static function relationship_taxonomy_filters( array $field ): array {
		$filters = [];
		$queries = (array) ( $field['query_args']['tax_query'] ?? [] );
		foreach ( $queries as $query ) {
			if ( empty( $query['taxonomy'] ) || empty( $query['terms'] ) ) {
				continue;
			}

			foreach ( (array) $query['terms'] as $term ) {
				$filters[] = (string) $query['taxonomy'] . ':' . (string) $term;
			}
		}

		return $filters;
	}

	/**
	 * Build SCF repeater sub-fields for structured StoryOS metadata.
	 *
	 * @param string               $cpt       CPT slug.
	 * @param string               $parent_key Parent field key.
	 * @param array<string, mixed> $field     StoryOS field definition.
	 * @return array<int, array<string, mixed>>
	 */
	private static function structured_sub_fields( string $cpt, string $parent_key, array $field ): array {
		$sub_fields = $field['sub_fields'] ?? [
			'speaker'     => [ 'type' => 'text', 'label' => 'Speaker' ],
			'line'        => [ 'type' => 'textarea', 'label' => 'Line' ],
			'description' => [ 'type' => 'textarea', 'label' => 'Description' ],
			'sequence'    => [ 'type' => 'number', 'label' => 'Sequence' ],
		];

		$converted = [];
		$order     = 0;
		foreach ( (array) $sub_fields as $name => $config ) {
			$sub_field           = self::to_scf_field( $cpt, $field['name'] . '_' . $name, (array) $config, $order++ );
			$sub_field['name']   = $name;
			$sub_field['parent'] = $parent_key;
			$converted[]         = $sub_field;
		}

		return $converted;
	}

	/**
	 * Get SCF-managed fields for a CPT in StoryOS's field dialect.
	 *
	 * @param string                             $cpt      CPT slug.
	 * @param array<string, array<string, mixed>> $defaults Code-defined fields.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields( string $cpt, array $defaults = [] ): array {
		if ( isset( self::$field_cache[ $cpt ] ) ) {
			return self::$field_cache[ $cpt ];
		}

		if ( ! self::is_available() ) {
			return $defaults;
		}

		$groups = acf_get_field_groups( [ 'post_type' => $cpt ] );
		$groups = is_array( $groups ) ? $groups : [];
		$core_group = acf_get_field_group( self::group_key( $cpt ) );
		if ( $core_group && ! self::contains_group( $groups, (string) $core_group['key'] ) ) {
			array_unshift( $groups, $core_group );
		}

		$fields = [];
		foreach ( $groups as $group ) {
			$scf_fields = acf_get_fields( $group );
			if ( ! is_array( $scf_fields ) ) {
				continue;
			}

			foreach ( $scf_fields as $scf_field ) {
				$name = (string) ( $scf_field['name'] ?? '' );
				if ( '' === $name || in_array( (string) ( $scf_field['type'] ?? '' ), [ 'accordion', 'message', 'tab' ], true ) ) {
					continue;
				}

				$fields[ $name ] = self::from_scf_field( $scf_field, $defaults[ $name ] ?? [] );
			}
		}

		self::$field_cache[ $cpt ] = ! empty( $fields ) ? $fields : $defaults;
		return self::$field_cache[ $cpt ];
	}

	/**
	 * Whether a group list already contains a group key.
	 *
	 * @param array<int, array<string, mixed>> $groups Groups.
	 * @param string                           $key    Group key.
	 */
	private static function contains_group( array $groups, string $key ): bool {
		foreach ( $groups as $group ) {
			if ( $key === (string) ( $group['key'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert an SCF field to StoryOS's runtime field dialect.
	 *
	 * @param array<string, mixed> $field    SCF field.
	 * @param array<string, mixed> $defaults StoryOS-only defaults.
	 * @return array<string, mixed>
	 */
	public static function from_scf_field( array $field, array $defaults = [] ): array {
		$scf_type = (string) ( $field['type'] ?? 'text' );
		$type     = $scf_type;
		if ( 'date_picker' === $scf_type ) {
			$type = 'date';
		} elseif ( in_array( $scf_type, [ 'post_object', 'relationship' ], true ) ) {
			$type = 'relationship';
		} elseif ( 'repeater' === $scf_type ) {
			$type = 'structured';
		}

		$mapped = [
			'name'        => (string) ( $field['name'] ?? '' ),
			'label'       => (string) ( $field['label'] ?? '' ),
			'type'        => $type,
			'required'    => ! empty( $field['required'] ),
			'description' => (string) ( $field['instructions'] ?? '' ),
			'scf_key'     => (string) ( $field['key'] ?? '' ),
		];

		if ( array_key_exists( 'default_value', $field ) ) {
			$mapped['default'] = $field['default_value'];
		}

		if ( 'select' === $scf_type ) {
			$mapped['options']  = (array) ( $field['choices'] ?? [] );
			$mapped['multiple'] = ! empty( $field['multiple'] );
		} elseif ( 'taxonomy' === $scf_type ) {
			$mapped['taxonomy'] = (string) ( $field['taxonomy'] ?? '' );
			$mapped['multiple'] = ! empty( $field['multiple'] ) || in_array( (string) ( $field['field_type'] ?? '' ), [ 'checkbox', 'multi_select' ], true );
		} elseif ( 'relationship' === $type ) {
			$post_types = array_values( array_filter( (array) ( $field['post_type'] ?? [] ) ) );
			$mapped['related_cpt']  = (string) ( $post_types[0] ?? '' );
			$mapped['related_cpts'] = $post_types;
			$mapped['multiple']     = 'relationship' === $scf_type || ! empty( $field['multiple'] );
		} elseif ( 'user' === $scf_type ) {
			$mapped['multiple'] = ! empty( $field['multiple'] );
		}

		return array_replace( $defaults, $mapped );
	}

	/**
	 * Resolve an SCF field by CPT and field name.
	 *
	 * @return array<string, mixed>|false
	 */
	public static function get_field_object( string $cpt, string $field_name ) {
		if ( ! function_exists( 'acf_get_field' ) ) {
			return false;
		}

		$field = acf_get_field( self::field_key( $cpt, $field_name ) );
		if ( $field ) {
			return $field;
		}

		$groups = function_exists( 'acf_get_field_groups' ) ? acf_get_field_groups( [ 'post_type' => $cpt ] ) : [];
		foreach ( (array) $groups as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $candidate ) {
				if ( $field_name === (string) ( $candidate['name'] ?? '' ) ) {
					return $candidate;
				}
			}
		}

		return false;
	}

	/**
	 * Read a scalar/structured field through SCF.
	 *
	 * @return mixed
	 */
	public static function get_value( int $post_id, string $field_name ) {
		$cpt   = (string) get_post_type( $post_id );
		$field = self::get_field_object( $cpt, $field_name );
		if ( $field && function_exists( 'get_field' ) ) {
			$format = in_array( (string) ( $field['type'] ?? '' ), [ 'date_picker', 'repeater' ], true );
			return get_field( (string) $field['key'], $post_id, $format );
		}

		return get_post_meta( $post_id, $field_name, true );
	}

	/**
	 * Update a field through SCF, falling back to post meta for undeclared data.
	 */
	public static function update_value( int $post_id, string $field_name, $value ): bool {
		$cpt   = (string) get_post_type( $post_id );
		$field = self::get_field_object( $cpt, $field_name );
		if ( $field && function_exists( 'update_field' ) ) {
			$result = update_field( (string) $field['key'], $value, $post_id );
			return false !== $result;
		}

		return false !== update_post_meta( $post_id, $field_name, $value );
	}

	/**
	 * Delete a field through SCF, including its hidden field-key reference.
	 */
	public static function delete_value( int $post_id, string $field_name ): bool {
		$cpt   = (string) get_post_type( $post_id );
		$field = self::get_field_object( $cpt, $field_name );
		if ( $field && function_exists( 'delete_field' ) ) {
			return (bool) delete_field( (string) $field['key'], $post_id );
		}

		return delete_post_meta( $post_id, $field_name );
	}

	/**
	 * Sanitize StoryOS scalar values and mirror relational SCF fields to the
	 * canonical Story Graph.
	 *
	 * @param mixed                $value     New value.
	 * @param int|string           $post_id   SCF object ID.
	 * @param array<string, mixed> $field     SCF field.
	 * @param mixed                $original  Original value.
	 * @return mixed
	 */
	public static function filter_update_value( $value, $post_id, array $field, $original ) {
		if ( ! is_numeric( $post_id ) ) {
			return $value;
		}

		$post_id = (int) $post_id;
		$cpt     = (string) get_post_type( $post_id );
		if ( ! isset( storyos_get_all_cpts()[ $cpt ] ) ) {
			return $value;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		$config   = self::from_scf_field( $field, $defaults[ $field['name'] ] ?? [] );
		if ( 'relationship' === $config['type'] && ! empty( $config['related_cpt'] ) && function_exists( __NAMESPACE__ . '\\set_relationships_for_field' ) ) {
			$target_ids = [];
			foreach ( (array) $value as $target ) {
				$target_ids[] = is_object( $target ) && isset( $target->ID ) ? (int) $target->ID : (int) $target;
			}

			set_relationships_for_field(
				$post_id,
				$cpt,
				array_values( array_filter( $target_ids ) ),
				(string) $config['related_cpt'],
				(string) ( $config['relationship_type'] ?? 'belongs_to' ),
				(string) $field['name']
			);
			return $value;
		}

		if ( in_array( (string) $config['type'], [ 'taxonomy', 'structured', 'user' ], true ) || is_array( $value ) || is_object( $value ) ) {
			return $value;
		}

		return storyos_sanitize_field_value( $value, $config );
	}

	/**
	 * Load relational SCF controls from the canonical Story Graph.
	 *
	 * @param mixed                $value   Stored SCF value.
	 * @param int|string           $post_id SCF object ID.
	 * @param array<string, mixed> $field   SCF field.
	 * @return mixed
	 */
	public static function filter_load_value( $value, $post_id, array $field ) {
		if ( ! is_numeric( $post_id ) || ! in_array( (string) ( $field['type'] ?? '' ), [ 'post_object', 'relationship' ], true ) ) {
			return $value;
		}

		$post_id = (int) $post_id;
		$cpt     = (string) get_post_type( $post_id );
		$defaults = storyos_get_field_defaults( $cpt );
		$config   = self::from_scf_field( $field, $defaults[ $field['name'] ] ?? [] );
		$to_type  = (string) ( $config['related_cpt'] ?? '' );
		if ( '' === $to_type || ! function_exists( __NAMESPACE__ . '\\get_relationships' ) ) {
			return $value;
		}

		$matches = [];
		foreach ( get_relationships( $post_id, $cpt, 'outgoing' ) as $relationship ) {
			if ( $to_type !== (string) ( $relationship['to_type'] ?? '' ) ) {
				continue;
			}

			$relationship_field = (string) ( $relationship['metadata']['field'] ?? '' );
			if ( '' !== $relationship_field && (string) $field['name'] !== $relationship_field ) {
				continue;
			}

			$matches[] = (int) $relationship['to_id'];
		}

		if ( ! empty( $matches ) ) {
			return 'relationship' === (string) $field['type'] || ! empty( $field['multiple'] ) ? $matches : $matches[0];
		}

		// Preserve legacy named relationship meta only until a Story Graph slot
		// exists. A present (even empty) graph meta value is authoritative.
		if ( metadata_exists( 'post', $post_id, STORYOS_CPT_PREFIX . 'relationships' ) ) {
			return 'relationship' === (string) $field['type'] || ! empty( $field['multiple'] ) ? [] : '';
		}

		return $value;
	}

	/**
	 * Disable importer-managed fields in the content editing form.
	 *
	 * @param array<string, mixed>|false $field SCF field.
	 * @return array<string, mixed>|false
	 */
	public static function prepare_field( $field ) {
		if ( ! is_array( $field ) || empty( $field['name'] ) ) {
			return $field;
		}

		$cpt = self::cpt_from_field_key( (string) ( $field['key'] ?? '' ) );
		if ( '' === $cpt ) {
			return $field;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		if ( ! empty( $defaults[ $field['name'] ]['read_only'] ) ) {
			$field['disabled'] = 1;
			$field['instructions'] = trim( (string) ( $field['instructions'] ?? '' ) . ' This field is managed by the StoryOS importer.' );
		}

		return $field;
	}

	/**
	 * Resolve a StoryOS CPT from one of this adapter's stable field keys.
	 */
	private static function cpt_from_field_key( string $field_key ): string {
		foreach ( array_keys( storyos_get_all_cpts() ) as $cpt ) {
			$prefix = 'field_' . self::key_fragment( $cpt ) . '_';
			if ( 0 === strpos( $field_key, $prefix ) ) {
				return $cpt;
			}
		}

		return '';
	}

	/**
	 * Add SCF's hidden name-to-key reference when legacy code writes raw meta.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function sync_reference_meta( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
		if ( '' === $meta_key || '_' === $meta_key[0] ) {
			return;
		}

		$cpt   = (string) get_post_type( $post_id );
		$field = self::get_field_object( $cpt, $meta_key );
		if ( ! $field || empty( $field['key'] ) ) {
			return;
		}

		$reference_key = '_' . $meta_key;
		if ( (string) get_post_meta( $post_id, $reference_key, true ) !== (string) $field['key'] ) {
			update_post_meta( $post_id, $reference_key, (string) $field['key'] );
		}
	}

	/**
	 * Remove an orphaned SCF field-key reference after raw meta deletion.
	 *
	 * @param int    $meta_ids   Deleted meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function delete_reference_meta( $meta_ids, int $post_id, string $meta_key, $meta_value ): void {
		if ( '' === $meta_key || '_' === $meta_key[0] || metadata_exists( 'post', $post_id, $meta_key ) ) {
			return;
		}

		$cpt = (string) get_post_type( $post_id );
		if ( self::get_field_object( $cpt, $meta_key ) ) {
			delete_post_meta( $post_id, '_' . $meta_key );
		}
	}
}
