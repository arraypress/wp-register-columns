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

use ArrayPress\RegisterColumns\Support\Image;

use ArrayPress\RegisterColumns\Traits\Request;
use ArrayPress\ArrayUtils\Arr;
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
	 * The tables whose hooks are already attached.
	 *
	 * Keyed by object type and subtype, because that is what the column
	 * registry above is keyed by — one set of hooks renders everything
	 * registered for a table, whoever registered it.
	 *
	 * Without this, calling the registration function twice for the same
	 * table attaches a second copy of every hook, and each copy renders
	 * every column: two registrations for `page`, one shared with `post` and
	 * one for pages alone, printed every cell on the Pages screen twice.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected static array $hooked = [];

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

		if ( ! isset( self::$hooked[ $this->object_type ][ $this->object_subtype ] ) ) {
			self::$hooked[ $this->object_type ][ $this->object_subtype ] = true;

			$this->load_hooks();
			$this->add_column_filters();
		}
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
			'desc_first'          => false,
			'sortby'              => '',
			'display_callback'    => null,
			'permission_callback' => null,
			'width'               => null,
			'image'               => false,
			'image_size'          => Image::DEFAULT_SIZE,
		];

		foreach ( $columns as $key => $column ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid column key provided. It must be a non-empty string.' );
			}

			self::$columns[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $column, $default_column );
		}
	}

	/**
	 * Add the hooks that are the same whatever the table is.
	 *
	 * Attached once per table, beside load_hooks(), rather than once per
	 * call to add_columns() — which printed the width stylesheet once for
	 * every registration.
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
				// The second element is core's "sort descending on the first
				// click", not a type. `numeric` was being passed here, so
				// every numeric column silently started descending — while
				// `numeric` also, correctly, drives meta_value_num in the
				// query. Two different jobs, one config key, one of them
				// wrong.
				$columns[ $key ] = [ $key, (bool) ( $column['desc_first'] ?? false ) ];
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

		$rules = '';

		foreach ( self::get_columns( $this->object_type, $this->object_subtype ) as $key => $column ) {
			$class    = sanitize_html_class( (string) $key );
			$width    = self::sanitize_width( (string) ( $column['width'] ?? '' ) );
			$is_image = false !== ( $column['image'] ?? false ) && null !== ( $column['image'] ?? false );

			if ( $is_image ) {
				// An image column with no width set stretches like any other,
				// which puts a 32px thumbnail in the middle of a third of the
				// table. Fall back to the image's own width plus core's cell
				// padding rather than making every caller say it.
				if ( '' === $width ) {
					$width = ( Image::pixels( $column['image_size'] ?? Image::DEFAULT_SIZE )[0] + 20 ) . 'px';
				}

				// Killing the margin core puts on list-table images: that rule
				// exists to sit an avatar beside a username, and here there is
				// nothing beside it.
				$rules .= sprintf(
					'.column-%1$s img{display:block;margin:0;float:none;max-width:100%%;height:auto}',
					$class
				);
			}

			if ( '' === $width ) {
				continue;
			}

			$rules .= sprintf( '.column-%s{width:%s}', $class, $width );
		}

		// Nothing to say, so nothing said. An empty <style> element in every
		// admin head is not harmful and is not defensible either.
		if ( '' === $rules ) {
			return;
		}

		printf( '<style>%s</style>', $rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * A width, if it is one.
	 *
	 * A CSS length, a percentage, or `auto` — which is a real width and the
	 * way to say "leave this column alone".
	 *
	 * This goes into a stylesheet, and esc_attr() does nothing useful there:
	 * it escapes for an HTML attribute, so a brace and a semicolon pass
	 * straight through and `10px;} body{display:none` closes the rule and
	 * writes another. A CSS length is a number and a unit, so that is all
	 * that is allowed through.
	 *
	 * @param string $width The configured width.
	 *
	 * @return string The width, or an empty string if it is not one.
	 */
	protected static function sanitize_width( string $width ): string {
		$width = trim( $width );

		return preg_match( '/^(auto|\d+(\.\d+)?(px|em|rem|%|ch|vw))$/', $width ) ? $width : '';
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the correct screen, false otherwise.
	 */
	abstract protected function is_screen(): bool;

	/**
	 * One meta value for an object of this type.
	 *
	 * The only thing that differs between a post column and a term column
	 * once the configuration has been read — which is why the rendering
	 * below is here and not copied into each table class, as it was, five
	 * times, identically.
	 *
	 * @param int|string $object_id The object.
	 * @param string     $meta_key  The key.
	 *
	 * @return mixed
	 */
	abstract protected function get_meta( $object_id, string $meta_key );

	/**
	 * What one cell of a custom column contains.
	 *
	 * A display callback is given the meta value first when the column names
	 * a meta_key, and the object id alone when it does not — so a column that
	 * is about a stored value does not have to fetch it, and one that is
	 * about the object can still do anything.
	 *
	 * @param int|string           $object_id The object.
	 * @param array<string, mixed> $column    The column's configuration.
	 *
	 * @return string
	 */
	protected function render_custom_column_content( $object_id, array $column ): string {
		$meta_key = (string) ( $column['meta_key'] ?? '' );

		if ( is_callable( $column['display_callback'] ) ) {
			return '' === $meta_key
				? (string) call_user_func( $column['display_callback'], $object_id )
				: (string) call_user_func( $column['display_callback'], $this->get_meta( $object_id, $meta_key ), $object_id );
		}

		if ( false !== $column['image'] && null !== $column['image'] ) {
			return $this->render_image_cell( $object_id, $column );
		}

		$value = '' === $meta_key ? '' : $this->get_meta( $object_id, $meta_key );

		// Not empty(): a stored nought is a value, and a column of counts
		// showing a dash where it should show 0 is a column that looks
		// broken rather than empty.
		if ( null === $value || '' === $value || false === $value || [] === $value ) {
			return self::placeholder();
		}

		return esc_html( (string) $value );
	}

	/**
	 * What one cell of an image column contains.
	 *
	 * With a meta_key the stored value is the image -- an attachment id or a
	 * URL. Without one the table says what its own natural image is: the
	 * featured image on a post, the avatar on a user, the file itself on a
	 * media item. That is the same relationship core has between the users
	 * table and the avatar it puts in the username column, which is what
	 * this is for.
	 *
	 * @param int|string           $object_id The object.
	 * @param array<string, mixed> $column    The column's configuration.
	 *
	 * @return string
	 */
	protected function render_image_cell( $object_id, array $column ): string {
		$meta_key = (string) ( $column['meta_key'] ?? '' );
		$size     = $column['image_size'] ?? Image::DEFAULT_SIZE;

		$html = '' === $meta_key
			? $this->render_default_image( $object_id, $size )
			: Image::render( $this->get_meta( $object_id, $meta_key ), $size );

		// A post with no featured image, or an attachment id whose file has
		// been deleted. Either way the cell shows what every other empty
		// cell shows rather than a broken image.
		return '' === $html ? self::placeholder() : $html;
	}

	/**
	 * The image this table shows when the column names no meta_key.
	 *
	 * Empty by default: a table with no natural image of its own -- terms --
	 * should ask for one with a meta_key rather than invent it.
	 *
	 * @param int|string       $object_id The object.
	 * @param int|string|array $size      Configured size.
	 *
	 * @return string
	 */
	protected function render_default_image( $object_id, $size ): string {
		return '';
	}

	/**
	 * What an empty cell shows.
	 *
	 * A dash for the eye and a word for anything reading the page aloud: an
	 * em dash announces as "em dash", or as nothing at all, and a blank cell
	 * in a list table reads as a column that failed to load.
	 *
	 * @return string
	 */
	protected static function placeholder(): string {
		return '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">'
			. esc_html__( 'None', 'arraypress' ) . '</span>';
	}

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
