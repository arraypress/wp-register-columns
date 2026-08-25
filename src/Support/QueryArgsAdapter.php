<?php
/**
 * Query Arguments Wrapper Class
 *
 * Wraps an array to provide a query-like interface for callbacks.
 * This allows user callbacks to use the same $query->set() syntax
 * for both posts (WP_Query) and users (array args).
 *
 * @package     ArrayPress\WP\RegisterListFilters
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Support;

class QueryArgsAdapter {

	/**
	 * Reference to the arguments array.
	 *
	 * @var array
	 */
	private array $args;

	/**
	 * Constructor.
	 *
	 * @param array $args Query arguments array to wrap.
	 */
	public function __construct( array &$args ) {
		$this->args = &$args;
	}

	/**
	 * Set a query argument.
	 *
	 * Mimics WP_Query::set() method for consistent API.
	 *
	 * @param string $key   Argument key.
	 * @param mixed  $value Argument value.
	 *
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->args[ $key ] = $value;
	}

	/**
	 * Get a query argument.
	 *
	 * @param string $key     Argument key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $this->args[ $key ] ?? $default;
	}

	/**
	 * Get all arguments.
	 *
	 * @return array
	 */
	public function get_args(): array {
		return $this->args;
	}
}
