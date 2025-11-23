<?php
/**
 * Array Utilities Class
 *
 * Provides utility methods for array manipulation, particularly for inserting
 * elements before or after specific keys.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Utils;

/**
 * Class Arr
 *
 * Utility class for array operations.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class Arr {

	/**
	 * Insert an element after a specific key in an array.
	 *
	 * @param array  $array The original array.
	 * @param string $key   The key to insert after.
	 * @param array  $new   The new element to insert.
	 *
	 * @return array The updated array.
	 */
	public static function insert_after( array $array, string $key, array $new ): array {
		$position = array_search( $key, array_keys( $array ) );

		if ( $position === false ) {
			$position = count( $array );
		} else {
			$position += 1;
		}

		return array_slice( $array, 0, $position, true ) +
		       $new +
		       array_slice( $array, $position, null, true );
	}

	/**
	 * Insert an element before a specific key in an array.
	 *
	 * @param array  $array The original array.
	 * @param string $key   The key to insert before.
	 * @param array  $new   The new element to insert.
	 *
	 * @return array The updated array.
	 */
	public static function insert_before( array $array, string $key, array $new ): array {
		$position = array_search( $key, array_keys( $array ) );

		if ( $position === false ) {
			$position = 0;
		}

		return array_slice( $array, 0, $position, true ) +
		       $new +
		       array_slice( $array, $position, null, true );
	}

}