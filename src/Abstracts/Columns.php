<?php
/**
 * Base Columns Class
 *
 * A lightweight class designed to simplify the registration of custom columns in WordPress.
 * This class focuses on display-only functionality, allowing you to easily add custom columns
 * to WordPress admin tables without the complexity of inline editing or custom UI components.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Abstracts;

use ArrayPress\RegisterColumns\Traits\Request;
use ArrayPress\RegisterColumns\Utils\Arr;
use Exception;

/**
 * Class Columns
 *
 * Base class for registering custom columns in WordPress.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
abstract class Columns {

	use Request;

	/**
	 * Object type constant that must be defined by child classes.
	 *
	 * Examples: 'post', 'user', 'term', 'comment'
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = '';

	/**
	 * Array of custom column configurations.
	 *
	 * @var array
	 */
	protected static array $columns = [];

	/**
	 * Object type for the current instance.
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype for the current instance (e.g., post type, taxonomy).
	 *
	 * @var string
	 */
	protected string $object_subtype;

	/**
	 * Array of column keys to remove from being registered.
	 *
	 * @var array
	 */
	protected array $keys_to_remove = [];

	/**
	 * Columns constructor.
	 *
	 * @param array  $columns        Custom columns configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 * @param array  $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @throws Exception If a column key is invalid or OBJECT_TYPE is not defined.
	 */
	public function __construct( array $columns, string $object_subtype, array $keys_to_remove = [] ) {
		// Validate that child class defined OBJECT_TYPE
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->set_keys_to_remove( $keys_to_remove );
		$this->add_columns( $columns );
		$this->load_hooks();
	}

	/**
	 * Set the array of column keys to remove from being registered.
	 *
	 * @param array $keys Array of column keys to remove.
	 *
	 * @return void
	 */
	public function set_keys_to_remove( array $keys ): void {
		$this->keys_to_remove = $keys;
	}

	/**
	 * Add new columns to the existing configuration.
	 *
	 * @param array $columns Custom columns configuration.
	 *
	 * @return void
	 * @throws Exception If a column key is invalid.
	 */
	public function add_columns( array $columns ): void {
		$default_column = [
			'label'               => '',
			'meta_key'            => '',
			'position'            => '',
			'sortable'            => false,
			'numeric'             => false,
			'sortby'              => '',
			'display_callback'    => null,
			'permission_callback' => null,
			'width'               => null,
		];

		foreach ( $columns as $key => $column ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid column key provided. It must be a non-empty string.' );
			}

			self::$columns[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $column, $default_column );
		}

		$this->add_column_filters();
	}

	/**
	 * Add filters to manage columns and content.
	 *
	 * @return void
	 */
	protected function add_column_filters(): void {
		add_action( 'admin_head', [ $this, 'add_custom_column_styles' ] );
	}

	/**
	 * Get columns array for the given object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array
	 */
	public static function get_columns( string $object_type, string $object_subtype ): array {
		return self::$columns[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Get the configuration for a specific column by name.
	 *
	 * @param string $column_name    The name of the column.
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array|null The column configuration if exists, null otherwise.
	 */
	public function get_column_by_name( string $column_name, string $object_type, string $object_subtype ): ?array {
		$columns = self::get_columns( $object_type, $object_subtype );

		return $columns[ $column_name ] ?? null;
	}

	/**
	 * Register custom columns with their labels.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array Updated array of columns with custom columns.
	 */
	public function register_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		// Remove specified keys from existing columns
		$columns = $this->remove_keys_from_columns( $columns );

		foreach ( $custom_columns as $key => $column ) {
			if ( ! $this->check_column_permission( $column ) ) {
				continue;
			}

			$position         = $column['position'];
			$reference_column = str_replace( [ 'before:', 'after:' ], '', $position );
			$label            = esc_html( $column['label'] );

			if ( str_starts_with( $position, 'after:' ) ) {
				$columns = Arr::insert_after( $columns, $reference_column, [ $key => $label ] );
			} elseif ( str_starts_with( $position, 'before:' ) ) {
				$columns = Arr::insert_before( $columns, $reference_column, [ $key => $label ] );
			} else {
				$columns[ $key ] = $label;
			}
		}

		return $columns;
	}

	/**
	 * Remove specified keys from the columns array.
	 *
	 * This method verifies if the keys exist before attempting to remove them.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array The columns array with specified keys removed.
	 */
	protected function remove_keys_from_columns( array $columns ): array {
		foreach ( $this->keys_to_remove as $key ) {
			if ( array_key_exists( $key, $columns ) ) {
				unset( $columns[ $key ] );
			}
		}

		return $columns;
	}

	/**
	 * Register custom columns as sortable.
	 *
	 * @param array $columns Array of existing sortable columns.
	 *
	 * @return array Updated array of sortable columns with custom columns.
	 */
	public function register_sortable_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		foreach ( $custom_columns as $key => $column ) {
			if ( $column['sortable'] ) {
				$columns[ $key ] = [ $key, $column['numeric'] ?? false ];
			}
		}

		return $columns;
	}

	/**
	 * Add custom column styles for width control.
	 *
	 * @return void
	 */
	public function add_custom_column_styles(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		echo '<style>';
		foreach ( self::get_columns( $this->object_type, $this->object_subtype ) as $key => $column ) {
			if ( ! empty( $column['width'] ) ) {
				$width = esc_attr( $column['width'] );
				echo ".column-$key { width: {$width}; }";
			}
		}
		echo '</style>';
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the correct screen, false otherwise.
	 */
	abstract protected function is_screen(): bool;

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $object_id   The object ID.
	 *
	 * @return string The rendered column content.
	 */
	abstract public function render_column_content( string $value, string $column_name, $object_id ): string;

	/**
	 * Check column permission.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return bool True if permission is granted, false otherwise.
	 */
	protected function check_column_permission( array $column ): bool {
		if ( isset( $column['permission_callback'] ) && is_callable( $column['permission_callback'] ) ) {
			return call_user_func( $column['permission_callback'] );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom columns.
	 *
	 * @return void
	 */
	abstract protected function load_hooks(): void;

}