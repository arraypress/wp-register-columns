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

namespace ArrayPress\RegisterCustomColumns;

use Exception;
use function add_filter;
use function add_action;
use function class_exists;

/**
 * Check if the class `BaseColumns` is defined, and if not, define it.
 */
if ( ! class_exists( 'BaseColumns' ) ) :
	/**
	 * Base class for registering custom columns in WordPress.
	 */
	abstract class BaseColumns extends RegisterColumns {

		/**
		 * Create an instance of the subclass.
		 *
		 * @param array   $columns        An associative array of custom columns with their configurations.
		 * @param string  $object_type    The object type to register columns for.
		 * @param string  $object_subtype The object subtype to register columns for.
		 * @param ?string $custom_filter  Optional custom filter to use in hooks.
		 *
		 * @return static The instance of the subclass.
		 * @throws Exception If an invalid or empty array is passed.
		 */
		public static function createInstance( array $columns, string $object_type, string $object_subtype, ?string $custom_filter = null ) {
			$instance = new static( $columns, $object_type, $object_subtype, $custom_filter );
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
		 *
		 * @throws Exception If an invalid or empty array is passed.
		 */
		protected function __construct( array $columns, string $object_type, string $object_subtype, ?string $custom_filter ) {
			if ( empty( $object_type ) ) {
				throw new Exception( 'Invalid object type provided.' );
			}

			if ( empty( $object_subtype ) ) {
				throw new Exception( 'Invalid object subtype provided.' );
			}

			if ( empty( $columns ) ) {
				throw new Exception( 'Invalid or empty columns array provided.' );
			}

			parent::__construct( $columns, $object_type, $object_subtype );

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
endif;