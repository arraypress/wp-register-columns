<?php
/**
 * A utility class providing common array operations.
 * This class includes methods to insert elements before or after a specific key in an array.
 *
 * Features:
 * - Insert elements after a specified key in an array.
 * - Insert elements before a specified key in an array.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Columns\Utils;

use function array_search;
use function array_keys;
use function count;
use function array_slice;

class Helpers {

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
			$position = count( $array ); // Insert at the end if the key is not found
		} else {
			$position += 1; // Insert after the found key
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
			$position = 0; // Insert at the beginning if the key is not found
		}

		return array_slice( $array, 0, $position, true ) +
		       $new +
		       array_slice( $array, $position, null, true );
	}

	/**
	 * Get the sanitized and uppercased order value.
	 *
	 * Ensures the order value is either 'ASC' or 'DESC'. Defaults to 'ASC'.
	 *
	 * @return string The sanitized and uppercased order value.
	 */
	public static function get_order(): string {
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'ASC';
		$order = strtoupper( $order );

		// Ensure the order is either ASC or DESC
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'ASC';
		}

		return $order;
	}

	/**
	 * Get the sanitized orderby value.
	 *
	 * @return string The sanitized orderby value.
	 */
	public static function get_orderby(): string {
		return isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : '';
	}

}
