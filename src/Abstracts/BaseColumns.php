<?php
/**
 * A robust class designed to simplify the registration of custom columns in WordPress.
 * This class encapsulates common functionalities and configurations, streamlining the setup
 * of custom columns for different object types, ensuring consistency and reducing boilerplate code.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Columns\Abstracts;

use ArrayPress\WP\Register\Columns\Columns;
use Exception;

/**
 * Base class for registering custom columns in WordPress.
 */
abstract class BaseColumns extends Columns {

	/**
	 * Create an instance of the subclass.
	 *
	 * @param array   $columns        An associative array of custom columns with their configurations.
	 * @param string  $object_type    The object type to register columns for.
	 * @param string  $object_subtype The object subtype to register columns for.
	 * @param ?string $custom_filter  Optional custom filter to use in hooks.
	 * @param array   $keys_to_remove Optional array of column keys to remove. Default empty array.
	 *
	 * @return static The instance of the subclass.
	 * @throws Exception If an invalid or empty array is passed.
	 */
	public static function create_instance( array $columns, string $object_type, string $object_subtype, ?string $custom_filter = null, array $keys_to_remove = [] ): BaseColumns {
		$instance = new static( $columns, $object_type, $object_subtype, $custom_filter, $keys_to_remove );
		$instance->load_hooks( $columns );

		return $instance;
	}

	/**
	 * BaseColumns constructor.
	 *
	 * @param array   $columns        An associative array of custom columns with their configurations.
	 * @param string  $object_type    The object type to register columns for.
	 * @param string  $object_subtype The object subtype to register columns for.
	 * @param ?string $custom_filter  Optional custom filter to use in hooks.
	 * @param array   $keys_to_remove Optional array of column keys to remove. Default empty array.
	 *
	 * @throws Exception If an invalid or empty array is passed.
	 */
	protected function __construct( array $columns, string $object_type, string $object_subtype, ?string $custom_filter = null, array $keys_to_remove = [] ) {
		if ( empty( $object_type ) ) {
			throw new Exception( 'Invalid object type provided.' );
		}

		if ( empty( $object_subtype ) ) {
			throw new Exception( 'Invalid object subtype provided.' );
		}

		if ( empty( $columns ) ) {
			throw new Exception( 'Invalid or empty columns array provided.' );
		}

		parent::__construct( $columns, $object_type, $object_subtype, $keys_to_remove );

		// Set custom filter if provided
		$this->custom_filter = $custom_filter ?? null;
	}


	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom columns.
	 *
	 * @param array $columns An associative array of custom columns with their configurations.
	 *
	 * @return void
	 */
	abstract protected function load_hooks( array $columns ): void;

}