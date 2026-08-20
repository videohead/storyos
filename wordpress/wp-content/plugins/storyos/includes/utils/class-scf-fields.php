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

	/** Current one-time value migration version. */
	private const VALUE_MIGRATION_VERSION = 1;

	/** Option recording the completed value migration version. */
	private const VALUE_MIGRATION_OPTION = 'storyos_scf_value_migration_version';

	/** Atomic lock protecting serialized Story Graph writes during migration. */
	private const VALUE_MIGRATION_LOCK_OPTION = 'storyos_scf_value_migration_lock';

	/** Cursor for bounded legacy-value migration batches. */
	private const VALUE_MIGRATION_STATE_OPTION = 'storyos_scf_value_migration_state';

	/** Maximum number of StoryOS posts migrated in one request. */
	private const VALUE_MIGRATION_BATCH_SIZE = 100;

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
	 * SCF keys contained in each StoryOS-owned group, including sub-fields.
	 *
	 * @var array<string, array<int, string>>
	 */
	private static $owned_field_keys = [];

	/** Whether an update originated from StoryOS's trusted field helper. */
	private static $internal_update = false;

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
		add_filter( 'acf/update_field', [ __CLASS__, 'enforce_canonical_field_contract' ], 1 );
		add_filter( 'acf/pre_update_value', [ __CLASS__, 'protect_read_only_value' ], 20, 4 );
		add_filter( 'acf/pre_update_value', [ __CLASS__, 'protect_json_value' ], 30, 4 );
		add_filter( 'acf/update_value', [ __CLASS__, 'filter_update_value' ], 20, 4 );
		add_filter( 'acf/load_value', [ __CLASS__, 'filter_load_value' ], 20, 3 );
		add_filter( 'acf/prepare_field', [ __CLASS__, 'prepare_field' ] );
		add_filter( 'acf/validate_rest_value/type=textarea', [ __CLASS__, 'validate_rest_json_value' ], 20, 3 );
		add_action( 'added_post_meta', [ __CLASS__, 'sync_reference_meta' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'sync_reference_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'delete_reference_meta' ], 10, 4 );
		add_action( 'admin_init', [ __CLASS__, 'maybe_migrate_legacy_values' ], 20 );
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
		foreach ( $definitions as $cpt => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$group = self::read_archive_group( $cpt );
			if ( ! $group ) {
				// Defensive fallback for a missing archive file. The alignment tests
				// still fail until the generated group is committed to acf-json/.
				$group = self::build_group( $cpt, $fields );
			}

			$validation_error = self::validate_group_schema( $cpt, $fields, $group );
			if ( '' !== $validation_error ) {
				storyos_log(
					sprintf(
						'SCF archive group %s was not imported: %s',
						self::group_key( $cpt ),
						$validation_error
					)
				);
				continue;
			}

			// acf_get_raw_field_group() bypasses Local JSON and checks the
			// database. Import a missing or older editable copy.
			$db_group = function_exists( 'acf_get_raw_field_group' )
				? acf_get_raw_field_group( self::group_key( $cpt ) )
				: false;
			if ( ! $db_group || self::archive_is_newer( $group, $db_group ) || self::database_group_is_incomplete( $cpt, $db_group, $fields ) ) {
				$import = $group;
				unset( $import['local'], $import['local_file'] );
				$import['ID'] = $db_group ? (int) $db_group['ID'] : 0;

				// Archive-to-database synchronization must not rewrite the source
				// JSON file or advance its timestamp during this same import.
				$local_json = function_exists( 'acf_get_instance' ) ? acf_get_instance( 'ACF_Local_JSON' ) : null;
				if ( $local_json ) {
					remove_action( 'acf/update_field_group', [ $local_json, 'update_field_group' ] );
				}

				try {
					acf_import_field_group( $import );
				} finally {
					if ( $local_json ) {
						add_action( 'acf/update_field_group', [ $local_json, 'update_field_group' ] );
					}
				}
			}
		}

		self::$field_cache      = [];
		self::$owned_field_keys = [];
	}

	/**
	 * Decode an immutable Local JSON payload without applying runtime
	 * `acf/load_field` filters to the schema that will be imported.
	 *
	 * @return array<string, mixed>|false
	 */
	private static function read_archive_group( string $cpt ) {
		$plugin_dir = defined( 'STORYOS_PLUGIN_DIR' ) ? STORYOS_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';
		$file       = $plugin_dir . 'acf-json/' . self::group_key( $cpt ) . '.json';
		if ( ! is_readable( $file ) ) {
			return false;
		}

		$group = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $group ) || JSON_ERROR_NONE !== json_last_error() ) {
			storyos_log( 'Invalid SCF Local JSON archive: ' . $file );
			return false;
		}

		$group['local']      = 'json';
		$group['local_file'] = $file;
		return $group;
	}

	/**
	 * Validate the canonical, storage-sensitive portion of a StoryOS group.
	 * Administrators may add fields and presentation settings, but canonical
	 * names, keys, types, locations, and persistence contracts remain stable.
	 *
	 * @param string                                  $cpt         StoryOS CPT.
	 * @param array<string, array<string, mixed>>      $definitions Canonical fields.
	 * @param array<string, mixed>                     $group       SCF group.
	 * @return string Empty when valid, otherwise a diagnostic.
	 */
	private static function validate_group_schema( string $cpt, array $definitions, array $group ): string {
		if ( self::group_key( $cpt ) !== (string) ( $group['key'] ?? '' ) ) {
			return 'the stable group key does not match its CPT';
		}

		$has_location = false;
		foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( 'post_type' === (string) ( $rule['param'] ?? '' ) && '==' === (string) ( $rule['operator'] ?? '' ) && $cpt === (string) ( $rule['value'] ?? '' ) ) {
					$has_location = true;
				}
			}
		}
		if ( ! $has_location ) {
			return 'the field group is not located on ' . $cpt;
		}

		$fields = (array) ( $group['fields'] ?? [] );
		if ( empty( $fields ) ) {
			return 'the field list is empty';
		}

		$names = array_map( 'strval', array_column( $fields, 'name' ) );
		if ( in_array( '', $names, true ) || count( $names ) !== count( array_unique( $names ) ) ) {
			return 'top-level field names must be nonempty and unique';
		}

		$seen_keys = [];
		$key_error = self::validate_unique_field_keys( $fields, $seen_keys );
		if ( '' !== $key_error ) {
			return $key_error;
		}

		$by_name = array_column( $fields, null, 'name' );
		foreach ( $definitions as $field_name => $definition ) {
			if ( empty( $by_name[ $field_name ] ) || ! is_array( $by_name[ $field_name ] ) ) {
				return 'missing canonical field ' . $field_name;
			}

			$field    = $by_name[ $field_name ];
			$expected = self::to_scf_field( $cpt, $field_name, $definition );
			if ( (string) $expected['key'] !== (string) ( $field['key'] ?? '' ) ) {
				return 'field ' . $field_name . ' does not use its stable key';
			}
			if ( (string) $expected['type'] !== (string) ( $field['type'] ?? '' ) ) {
				return 'field ' . $field_name . ' has an incompatible SCF type';
			}
			if ( ! empty( $expected['required'] ) !== ! empty( $field['required'] ) ) {
				return 'field ' . $field_name . ' has incompatible required-state behavior';
			}

			if ( 'taxonomy' === (string) $expected['type'] && (
				(string) ( $expected['taxonomy'] ?? '' ) !== (string) ( $field['taxonomy'] ?? '' )
				|| empty( $field['load_terms'] )
				|| empty( $field['save_terms'] )
			) ) {
				return 'taxonomy field ' . $field_name . ' is not synchronized with WordPress terms';
			}

			if ( in_array( (string) $expected['type'], [ 'post_object', 'relationship' ], true ) ) {
				$expected_types = array_values( array_filter( (array) ( $expected['post_type'] ?? [] ) ) );
				$actual_types   = array_values( array_filter( (array) ( $field['post_type'] ?? [] ) ) );
				if ( ! empty( array_diff( $expected_types, $actual_types ) ) ) {
					return 'relationship field ' . $field_name . ' targets an incompatible CPT';
				}
			}

			if ( 'select' === (string) $expected['type'] ) {
				$expected_choices = array_keys( (array) ( $expected['choices'] ?? [] ) );
				$actual_choices   = array_keys( (array) ( $field['choices'] ?? [] ) );
				if ( ! empty( array_diff( $expected_choices, $actual_choices ) ) ) {
					return 'select field ' . $field_name . ' is missing canonical choices';
				}
			}

			if ( 'repeater' === (string) $expected['type'] ) {
				$expected_subfields = array_column( (array) ( $expected['sub_fields'] ?? [] ), null, 'name' );
				$actual_subfields   = array_column( (array) ( $field['sub_fields'] ?? [] ), null, 'name' );
				foreach ( $expected_subfields as $sub_name => $expected_subfield ) {
					$actual_subfield = $actual_subfields[ $sub_name ] ?? [];
					if ( (string) ( $expected_subfield['key'] ?? '' ) !== (string) ( $actual_subfield['key'] ?? '' ) || (string) ( $expected_subfield['type'] ?? '' ) !== (string) ( $actual_subfield['type'] ?? '' ) ) {
						return 'repeater field ' . $field_name . ' has an incompatible ' . $sub_name . ' subfield';
					}
				}
			}
		}

		return '';
	}

	/** Validate recursive field-key uniqueness. */
	private static function validate_unique_field_keys( array $fields, array &$seen_keys ): string {
		foreach ( $fields as $field ) {
			$key = (string) ( $field['key'] ?? '' );
			if ( '' === $key ) {
				return 'every field and subfield must have a nonempty key';
			}
			if ( isset( $seen_keys[ $key ] ) ) {
				return 'duplicate field key ' . $key;
			}
			$seen_keys[ $key ] = true;

			$error = self::validate_unique_field_keys( (array) ( $field['sub_fields'] ?? [] ), $seen_keys );
			if ( '' !== $error ) {
				return $error;
			}
		}

		return '';
	}

	/**
	 * Keep graph/storage identifiers immutable while allowing administrators to
	 * manage labels, instructions, layouts, choices, and extension fields in SCF.
	 *
	 * @param array<string, mixed> $field Field being saved by SCF.
	 * @return array<string, mixed>
	 */
	public static function enforce_canonical_field_contract( array $field ): array {
		$key = (string) ( $field['key'] ?? '' );
		$cpt = self::cpt_from_field_key( $key );
		if ( '' === $cpt ) {
			return $field;
		}

		$expected = self::canonical_field_by_key( $cpt, $key );
		if ( ! $expected ) {
			return $field;
		}

		foreach ( [ 'key', 'name', 'type', 'required' ] as $property ) {
			$field[ $property ] = $expected[ $property ];
		}

		$storage_properties = [
			'taxonomy',
			'field_type',
			'multiple',
			'load_terms',
			'save_terms',
			'return_format',
			'post_type',
			'display_format',
		];
		foreach ( $storage_properties as $property ) {
			if ( array_key_exists( $property, $expected ) ) {
				$field[ $property ] = $expected[ $property ];
			}
		}

		return $field;
	}

	/** Resolve a canonical top-level field or repeater subfield by stable key. */
	private static function canonical_field_by_key( string $cpt, string $key ) {
		foreach ( storyos_get_field_defaults( $cpt ) as $field_name => $definition ) {
			$expected = self::to_scf_field( $cpt, $field_name, $definition );
			$match    = self::find_field_by_key( [ $expected ], $key );
			if ( $match ) {
				return $match;
			}
		}

		return false;
	}

	/** Recursively find a field in a generated canonical field tree. */
	private static function find_field_by_key( array $fields, string $key ) {
		foreach ( $fields as $field ) {
			if ( $key === (string) ( $field['key'] ?? '' ) ) {
				return $field;
			}

			$match = self::find_field_by_key( (array) ( $field['sub_fields'] ?? [] ), $key );
			if ( $match ) {
				return $match;
			}
		}

		return false;
	}

	/**
	 * Whether an editable database group is missing canonical StoryOS fields.
	 *
	 * This also repairs database copies created by older StoryOS synchronizers
	 * that imported the field-less Local JSON group object.
	 *
	 * @param string                                  $cpt         StoryOS CPT.
	 * @param array<string, mixed>                    $db_group    Raw database group.
	 * @param array<string, array<string, mixed>>     $definitions Canonical fields.
	 */
	private static function database_group_is_incomplete( string $cpt, array $db_group, array $definitions ): bool {
		if ( empty( $db_group['ID'] ) || ! function_exists( 'acf_get_raw_fields' ) ) {
			return false;
		}

		$db_group['fields'] = self::read_raw_database_fields( (int) $db_group['ID'] );
		return '' !== self::validate_group_schema( $cpt, $definitions, $db_group );
	}

	/** Recursively load raw database fields without Local JSON precedence. */
	private static function read_raw_database_fields( int $parent_id ): array {
		$fields = (array) acf_get_raw_fields( $parent_id );
		foreach ( $fields as &$field ) {
			if ( ! empty( $field['ID'] ) ) {
				$sub_fields = self::read_raw_database_fields( (int) $field['ID'] );
				if ( ! empty( $sub_fields ) ) {
					$field['sub_fields'] = $sub_fields;
				}
			}
		}
		unset( $field );

		return $fields;
	}

	/**
	 * Whether a Local JSON group is newer than its editable database copy.
	 *
	 * This mirrors SCF's normal "Sync available" comparison, but performs the
	 * import during StoryOS boot so the SCF editor never presents a stale schema
	 * after a plugin update. A newer database record is left untouched because
	 * it may contain an administrator change whose JSON write failed.
	 *
	 * @param array<string, mixed> $group    Loaded group, normally from JSON.
	 * @param array<string, mixed> $db_group Raw database group.
	 * @return bool
	 */
	private static function archive_is_newer( array $group, array $db_group ): bool {
		if ( 'json' !== (string) ( $group['local'] ?? '' ) || empty( $group['modified'] ) || empty( $db_group['ID'] ) ) {
			return false;
		}

		$db_modified = get_post_modified_time( 'U', true, (int) $db_group['ID'] );
		return (int) $group['modified'] > (int) $db_modified;
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
			'show_in_rest'          => 'storyos_connection' === $cpt ? 0 : 1,
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

		$core_group = acf_get_field_group( self::group_key( $cpt ) );
		$groups     = $core_group ? [ $core_group ] : [];

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
	 * Whether a field belongs to the StoryOS-owned group for this CPT.
	 *
	 * @param string               $cpt   CPT slug.
	 * @param array<string, mixed> $field SCF field.
	 * @return bool
	 */
	private static function is_owned_field( string $cpt, array $field ): bool {
		if ( ! isset( self::$owned_field_keys[ $cpt ] ) ) {
			$group = acf_get_field_group( self::group_key( $cpt ) );
			$keys  = [];
			self::collect_field_keys( $group ? (array) acf_get_fields( $group ) : [], $keys );
			self::$owned_field_keys[ $cpt ] = $keys;
		}

		return in_array( (string) ( $field['key'] ?? '' ), self::$owned_field_keys[ $cpt ], true );
	}

	/**
	 * Collect field and nested sub-field keys.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param array<int, string>               $keys   Collected keys.
	 */
	private static function collect_field_keys( array $fields, array &$keys ): void {
		foreach ( $fields as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$keys[] = (string) $field['key'];
			}

			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				self::collect_field_keys( $field['sub_fields'], $keys );
			}
		}
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

		$group = acf_get_field_group( self::group_key( $cpt ) );
		foreach ( $group ? (array) acf_get_fields( $group ) : [] as $candidate ) {
			if ( $field_name === (string) ( $candidate['name'] ?? '' ) ) {
				return $candidate;
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
			$previous_internal_update = self::$internal_update;
			self::$internal_update = true;
			try {
				$result = update_field( (string) $field['key'], $value, $post_id );
			} finally {
				self::$internal_update = $previous_internal_update;
			}
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
			$defaults = storyos_get_field_defaults( $cpt );
			$config   = self::from_scf_field( $field, $defaults[ $field_name ] ?? [] );
			if ( 'relationship' === $config['type'] && ! empty( $config['related_cpt'] ) && function_exists( __NAMESPACE__ . '\\set_relationships_for_field' ) ) {
				$result = set_relationships_for_field(
					$post_id,
					$cpt,
					[],
					(string) $config['related_cpt'],
					(string) ( $config['relationship_type'] ?? 'belongs_to' ),
					$field_name,
					! empty( $config['multiple'] )
				);
				if ( is_wp_error( $result ) ) {
					return false;
				}
			}

			return (bool) delete_field( (string) $field['key'], $post_id );
		}

		return delete_post_meta( $post_id, $field_name );
	}

	/**
	 * Prevent UI, native REST, and datastore writes to importer-managed fields.
	 * StoryOS's internal helper temporarily opts in for trusted import/migration
	 * writes.
	 *
	 * @param mixed                $check   Short-circuit value.
	 * @param mixed                $value   Proposed value.
	 * @param int|string           $post_id SCF object ID.
	 * @param array<string, mixed> $field   SCF field.
	 * @return mixed
	 */
	public static function protect_read_only_value( $check, $value, $post_id, array $field ) {
		if ( self::$internal_update || ! is_numeric( $post_id ) ) {
			return $check;
		}

		$cpt = (string) get_post_type( (int) $post_id );
		if ( ! isset( storyos_get_all_cpts()[ $cpt ] ) || ! self::is_owned_field( $cpt, $field ) ) {
			return $check;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		return ! empty( $defaults[ $field['name'] ]['read_only'] ) ? false : $check;
	}

	/**
	 * Prevent invalid JSON from reaching any SCF write path.
	 *
	 * SCF's standard form validator does not run for every REST/datastore save,
	 * so the pre-update guard is the final integrity boundary.
	 *
	 * @param mixed                $check   Existing short-circuit value.
	 * @param mixed                $value   Proposed value.
	 * @param int|string           $post_id SCF object ID.
	 * @param array<string, mixed> $field   SCF field.
	 * @return mixed
	 */
	public static function protect_json_value( $check, $value, $post_id, array $field ) {
		if ( null !== $check || ! is_numeric( $post_id ) ) {
			return $check;
		}

		$cpt = (string) get_post_type( (int) $post_id );
		if ( ! self::is_json_field( $cpt, $field ) ) {
			return $check;
		}

		return self::is_valid_json_value( $value ) ? $check : false;
	}

	/**
	 * Return a useful native REST error for invalid StoryOS JSON textareas.
	 *
	 * @param bool|\WP_Error       $valid Current validation result.
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field SCF field.
	 * @return bool|\WP_Error
	 */
	public static function validate_rest_json_value( $valid, $value, array $field ) {
		if ( true !== $valid ) {
			return $valid;
		}

		$cpt = self::cpt_from_field_key( (string) ( $field['key'] ?? '' ) );
		if ( '' === $cpt || ! self::is_json_field( $cpt, $field ) || self::is_valid_json_value( $value ) ) {
			return $valid;
		}

		return new \WP_Error(
			'rest_invalid_param',
			__( 'Enter a valid JSON array or object.', 'storyos' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Whether a field uses StoryOS's explicit JSON storage contract.
	 *
	 * @param string               $cpt   StoryOS CPT.
	 * @param array<string, mixed> $field SCF field.
	 */
	private static function is_json_field( string $cpt, array $field ): bool {
		if ( '' === $cpt || ! isset( storyos_get_all_cpts()[ $cpt ] ) || ! self::is_owned_field( $cpt, $field ) ) {
			return false;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		$name     = (string) ( $field['name'] ?? '' );
		return 'json' === (string) ( $defaults[ $name ]['format'] ?? '' );
	}

	/** Whether a JSON textarea is blank or contains an array/object document. */
	private static function is_valid_json_value( $value ): bool {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return false;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return true;
		}

		$decoded = json_decode( $value, true );
		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded );
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
		if ( ! isset( storyos_get_all_cpts()[ $cpt ] ) || ! self::is_owned_field( $cpt, $field ) ) {
			return $value;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		$config   = self::from_scf_field( $field, $defaults[ $field['name'] ] ?? [] );
		if ( 'relationship' === $config['type'] && ! empty( $config['related_cpt'] ) && function_exists( __NAMESPACE__ . '\\set_relationships_for_field' ) ) {
			$target_ids = [];
			foreach ( (array) $value as $target ) {
				$target_ids[] = is_object( $target ) && isset( $target->ID ) ? (int) $target->ID : (int) $target;
			}

			$result = set_relationships_for_field(
				$post_id,
				$cpt,
				array_values( array_filter( $target_ids ) ),
				(string) $config['related_cpt'],
				(string) ( $config['relationship_type'] ?? 'belongs_to' ),
				(string) $field['name'],
				! empty( $config['multiple'] )
			);
			if ( is_wp_error( $result ) ) {
				return get_post_meta( $post_id, (string) $field['name'], true );
			}

			return $value;
		}

		if ( in_array( (string) $config['type'], [ 'taxonomy', 'structured', 'user' ], true ) || is_array( $value ) || is_object( $value ) ) {
			return $value;
		}
		if ( 'date' === (string) $config['type'] ) {
			$value = trim( (string) $value );
			if ( '' === $value || preg_match( '/^\d{8}$/', $value ) ) {
				return $value;
			}

			$timestamp = strtotime( $value );
			return false === $timestamp ? get_post_meta( $post_id, (string) $field['name'], true ) : gmdate( 'Ymd', $timestamp );
		}
		if ( 'json' === (string) ( $config['format'] ?? '' ) ) {
			// Template/Connection filters normalize this untouched value later in
			// the update pipeline. Generic textarea sanitization would strip valid
			// tokens such as <lora:...> from JSON string values.
			return (string) $value;
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
		if ( ! self::is_owned_field( $cpt, $field ) ) {
			return $value;
		}

		$defaults = storyos_get_field_defaults( $cpt );
		$config   = self::from_scf_field( $field, $defaults[ $field['name'] ] ?? [] );
		$to_type  = (string) ( $config['related_cpt'] ?? '' );
		if ( '' === $to_type || ! function_exists( __NAMESPACE__ . '\\get_relationships' ) ) {
			return $value;
		}

		$matches       = [];
		$expected_type = (string) ( $config['relationship_type'] ?? 'belongs_to' );
		$has_marker    = function_exists( __NAMESPACE__ . '\\relationship_field_marker_key' )
			&& metadata_exists( 'post', $post_id, relationship_field_marker_key( (string) $field['name'] ) );
		foreach ( get_relationships( $post_id, $cpt, 'outgoing' ) as $relationship ) {
			if ( $to_type !== (string) ( $relationship['to_type'] ?? '' ) ) {
				continue;
			}

			$relationship_field = (string) ( $relationship['metadata']['field'] ?? '' );
			if ( '' !== $relationship_field && (string) $field['name'] !== $relationship_field ) {
				continue;
			}
			if ( '' === $relationship_field && ( $has_marker || $expected_type !== (string) ( $relationship['type'] ?? '' ) ) ) {
				continue;
			}

			$matches[] = (int) $relationship['to_id'];
		}

		if ( ! empty( $matches ) ) {
			return 'relationship' === (string) $field['type'] || ! empty( $field['multiple'] ) ? $matches : $matches[0];
		}

		// A per-field marker distinguishes an intentionally empty graph slot from
		// legacy named relationship meta that has not been migrated yet.
		if ( $has_marker ) {
			return 'relationship' === (string) $field['type'] || ! empty( $field['multiple'] ) ? [] : '';
		}

		return $value;
	}

	/**
	 * Run one legacy migration batch from a privileged administration request,
	 * serialized by an atomic option lock.
	 */
	public static function maybe_migrate_legacy_values(): void {
		if ( (int) get_option( self::VALUE_MIGRATION_OPTION, 0 ) >= self::VALUE_MIGRATION_VERSION ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$token = wp_generate_uuid4();
		$lock  = [ 'token' => $token, 'created' => time() ];
		if ( ! add_option( self::VALUE_MIGRATION_LOCK_OPTION, $lock, '', false ) ) {
			$current_lock = get_option( self::VALUE_MIGRATION_LOCK_OPTION, [] );
			if ( is_array( $current_lock ) && time() - (int) ( $current_lock['created'] ?? 0 ) <= 15 * MINUTE_IN_SECONDS ) {
				return;
			}

			delete_option( self::VALUE_MIGRATION_LOCK_OPTION );
			if ( ! add_option( self::VALUE_MIGRATION_LOCK_OPTION, $lock, '', false ) ) {
				return;
			}
		}

		try {
			self::migrate_legacy_values();
		} finally {
			$current_lock = get_option( self::VALUE_MIGRATION_LOCK_OPTION, [] );
			if ( is_array( $current_lock ) && $token === (string) ( $current_lock['token'] ?? '' ) ) {
				delete_option( self::VALUE_MIGRATION_LOCK_OPTION );
			}
		}
	}

	/**
	 * Convert legacy values into SCF's storage contracts once per site.
	 *
	 * This migrates serialized dialogue arrays to repeater rows, normalizes date
	 * storage, and backfills SCF name-to-key references. Legacy Story Graph
	 * edges remain available through the compatibility loader and are adopted
	 * only when their SCF control is explicitly saved.
	 */
	private static function migrate_legacy_values(): void {
		global $wpdb;

		$state        = get_option( self::VALUE_MIGRATION_STATE_OPTION, [] );
		$state        = is_array( $state ) && self::VALUE_MIGRATION_VERSION === (int) ( $state['version'] ?? 0 ) ? $state : [];
		$last_id      = absint( $state['last_id'] ?? 0 );
		$cpts         = array_keys( storyos_get_all_cpts() );
		$placeholders = implode( ', ', array_fill( 0, count( $cpts ), '%s' ) );
		$query_args   = array_merge( [ $last_id ], $cpts, [ self::VALUE_MIGRATION_BATCH_SIZE ] );
		$sql          = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are generated from the canonical CPT count.
			"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ({$placeholders}) ORDER BY ID ASC LIMIT %d",
			$query_args
		);
		$batch_ids    = array_map( 'absint', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if ( '' !== (string) $wpdb->last_error ) {
			storyos_log( 'SCF legacy value migration could not read its next post batch.' );
			return;
		}

		$posts_by_cpt = [];
		foreach ( array_keys( storyos_get_all_cpts() ) as $cpt ) {
			$posts_by_cpt[ $cpt ] = array_map(
				'absint',
				get_posts(
					[
						'post_type'              => $cpt,
						'post__in'               => empty( $batch_ids ) ? [ 0 ] : $batch_ids,
						'post_status'            => 'any',
						'posts_per_page'         => -1,
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					]
				)
			);
		}

		$success = self::migrate_legacy_dialogue( $posts_by_cpt['storyos_scene'] ?? [] );

		foreach ( $posts_by_cpt as $cpt => $post_ids ) {
			$defaults = storyos_get_field_defaults( $cpt );
			foreach ( $post_ids as $post_id ) {
				foreach ( $defaults as $field_name => $config ) {
					$field = self::get_field_object( $cpt, $field_name );
					if ( ! $field ) {
						// An administrator may deliberately remove a field. The alignment
						// report flags canonical omissions without retrying this migration
						// forever on every subsequent administration request.
						continue;
					}

					if ( metadata_exists( 'post', $post_id, $field_name ) ) {
						update_post_meta( $post_id, '_' . $field_name, (string) $field['key'] );
					}

					if ( 'date' === (string) ( $config['type'] ?? '' ) ) {
						$raw_date = (string) get_post_meta( $post_id, $field_name, true );
						if ( '' !== $raw_date && ! preg_match( '/^\d{8}$/', $raw_date ) ) {
							$timestamp = strtotime( $raw_date );
							if ( false !== $timestamp ) {
								storyos_update_field_value( $post_id, $field_name, gmdate( 'Ymd', $timestamp ) );
							}
						}
					}

				}
			}
		}

		if ( ! $success ) {
			storyos_log( 'SCF legacy value migration completed with one or more values that require manual review.' );
		}

		if ( count( $batch_ids ) < self::VALUE_MIGRATION_BATCH_SIZE ) {
			update_option( self::VALUE_MIGRATION_OPTION, self::VALUE_MIGRATION_VERSION, false );
			delete_option( self::VALUE_MIGRATION_STATE_OPTION );
			return;
		}

		update_option(
			self::VALUE_MIGRATION_STATE_OPTION,
			[
				'version' => self::VALUE_MIGRATION_VERSION,
				'last_id' => (int) end( $batch_ids ),
			],
			false
		);
	}

	/**
	 * Convert legacy serialized Scene dialogue arrays to SCF repeater rows.
	 *
	 * @param array<int, int> $scene_ids Scene post IDs.
	 * @return bool
	 */
	private static function migrate_legacy_dialogue( array $scene_ids ): bool {
		$success = true;
		foreach ( $scene_ids as $scene_id ) {
			$raw = get_post_meta( $scene_id, 'dialogue', true );
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$rows = [];
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$rows[] = [
					'speaker'     => (string) ( $row['speaker'] ?? '' ),
					'line'        => (string) ( $row['line'] ?? '' ),
					'description' => (string) ( $row['description'] ?? '' ),
					'sequence'    => isset( $row['sequence'] ) && is_numeric( $row['sequence'] ) ? 0 + $row['sequence'] : '',
				];
			}

			storyos_update_field_value( $scene_id, 'dialogue', $rows );
			if ( is_array( get_post_meta( $scene_id, 'dialogue', true ) ) ) {
				$success = false;
			}
		}

		return $success;
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
		if ( ! self::is_owned_field( $cpt, $field ) ) {
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
		if ( function_exists( 'acf_flush_value_cache' ) ) {
			acf_flush_value_cache( $post_id, $meta_key );
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
			if ( function_exists( 'acf_flush_value_cache' ) ) {
				acf_flush_value_cache( $post_id, $meta_key );
			}
		}
	}
}
