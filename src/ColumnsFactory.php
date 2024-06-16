<?php
/**
 * Factory class for creating instances of RegisterColumns.
 *
 * This class provides a centralized way to create and manage instances of RegisterColumns,
 * ensuring that each instance is properly configured with its own columns and metadata type.
 *
 * @package         arraypress/register-custom-columns
 * @version         0.1.0
 */

namespace ArrayPress\RegisterCustomColumns;

use Exception;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Check if the class `ColumnsFactory` is defined, and if not, define it.
 */
if ( ! class_exists( 'ColumnsFactory' ) ) :

	class ColumnsFactory {

		/**
		 * @var array Holds instances of RegisterColumns.
		 */
		protected static array $instances = [];

		/**
		 * Get an instance of RegisterColumns.
		 *
		 * @param string      $class_name     The class name of the columns.
		 * @param array       $columns        The columns configuration.
		 * @param string      $object_type    The object type (e.g., 'post').
		 * @param string|null $object_subtype The object subtype (e.g., 'page').
		 * @param string|null $custom_filter  The custom filter to use in hooks.
		 * @param array       $keys_to_remove Optional. Array of column keys to remove. Default empty array.
		 *
		 * @return RegisterColumns
		 * @throws Exception
		 */
		public static function getInstance( string $class_name, array $columns, string $object_type, string $object_subtype = null, ?string $custom_filter = null, array $keys_to_remove = [] ): RegisterColumns {
			$object_subtype = $object_subtype ?? $object_type;
			$key            = self::generateKey( $class_name, $object_type, $object_subtype, $custom_filter );

			if ( ! isset( self::$instances[ $key ] ) ) {
				self::$instances[ $key ] = self::createInstance( $class_name, $columns, $object_type, $object_subtype, $custom_filter, $keys_to_remove );
			} else {
				self::$instances[ $key ]->add_columns( $columns );
			}

			return self::$instances[ $key ];
		}

		/**
		 * Create an instance of RegisterColumns.
		 *
		 * @param string      $class_name     The class name of the columns.
		 * @param array       $columns        The columns configuration.
		 * @param string      $object_type    The object type (e.g., 'post').
		 * @param string      $object_subtype The object subtype (e.g., 'page').
		 * @param string|null $custom_filter  The custom filter to use in hooks.
		 * @param array       $keys_to_remove Optional. Array of column keys to remove. Default empty array.
		 *
		 * @return RegisterColumns
		 * @throws InvalidArgumentException
		 */
		protected static function createInstance( string $class_name, array $columns, string $object_type, string $object_subtype, ?string $custom_filter, array $keys_to_remove ): RegisterColumns {
			if ( class_exists( $class_name ) ) {
				$reflection  = new ReflectionClass( $class_name );
				$constructor = $reflection->getConstructor();
				$num_params  = $constructor ? $constructor->getNumberOfParameters() : 0;

				if ( $num_params > 4 ) {
					return $class_name::createInstance( $columns, $object_type, $object_subtype, $custom_filter, $keys_to_remove );
				} elseif ( $num_params > 3 ) {
					return $class_name::createInstance( $columns, $object_type, $object_subtype, $custom_filter );
				} else {
					return $class_name::createInstance( $columns, $object_type, $object_subtype );
				}
			} else {
				throw new InvalidArgumentException( "Unknown class: $class_name" );
			}
		}

		/**
		 * Generate a unique key for the instance.
		 *
		 * @param string      $class_name     The class name of the columns.
		 * @param string      $object_type    The object type (e.g., 'post').
		 * @param string|null $object_subtype The object subtype (e.g., 'page').
		 * @param string|null $custom_filter  The custom filter to use in hooks.
		 *
		 * @return string
		 */
		protected static function generateKey( string $class_name, string $object_type, string $object_subtype = null, ?string $custom_filter = null ): string {
			$object_subtype = $object_subtype ?? $object_type;
			$key            = $class_name . ':' . $object_type . ':' . $object_subtype;
			if ( $custom_filter ) {
				$key .= ':' . $custom_filter;
			}

			return $key;
		}

	}
endif;