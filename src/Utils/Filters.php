<?php
/**
 * List Filter Registration
 *
 * @package     ArrayPress\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.1.0
 */

declare( strict_types=1 );

use ArrayPress\RegisterColumns\Filters\Post;
use ArrayPress\RegisterColumns\Filters\User;

if ( ! function_exists( 'register_post_list_filters' ) ) {
	/**
	 * Add dropdown filters above a post type's list table.
	 *
	 * @param string|string[]      $post_types One post type or several.
	 * @param array<string, mixed> $filters    Filters, keyed by name.
	 *
	 * @return void
	 */
	function register_post_list_filters( $post_types, array $filters ): void {
		foreach ( (array) $post_types as $post_type ) {
			new Post( $filters, (string) $post_type );
		}
	}
}

if ( ! function_exists( 'register_user_list_filters' ) ) {
	/**
	 * Add dropdown filters above the users list table.
	 *
	 * @param array<string, mixed> $filters Filters, keyed by name.
	 *
	 * @return void
	 */
	function register_user_list_filters( array $filters ): void {
		new User( $filters, 'user' );
	}
}
