<?php
/**
 * Request Trait
 *
 * Provides methods for handling request parameters, particularly for sorting.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Traits;

/**
 * Trait Request
 *
 * Request parameter handling utilities.
 *
 * @package ArrayPress\RegisterColumns\Traits
 */
trait Request {

	/**
	 * Get the sanitized and uppercased order value.
	 *
	 * Ensures the order value is either 'ASC' or 'DESC'. Defaults to 'ASC'.
	 *
	 * @return string The sanitized and uppercased order value.
	 */
	protected function get_order(): string {
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'ASC';
		$order = strtoupper( $order );

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
	protected function get_orderby(): string {
		return isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : '';
	}

}