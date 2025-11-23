<?php
/**
 * Registration Functions
 *
 * Provides convenient helper functions for registering custom columns in WordPress.
 * These functions are in the global namespace for easy use throughout your codebase.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterColumns\Tables\Post;
use ArrayPress\RegisterColumns\Tables\User;
use ArrayPress\RegisterColumns\Tables\Taxonomy;
use ArrayPress\RegisterColumns\Tables\Comment;
use ArrayPress\RegisterColumns\Tables\Media;

if ( ! function_exists( 'register_post_columns' ) ) {
	/**
	 * Register custom columns for posts or custom post types.
	 *
	 * @param string|array $post_types     Post type(s) to register columns for.
	 * @param array        $columns        Array of custom columns configuration.
	 * @param array        $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @return array Array of Post instances or empty array on error.
	 */
	function register_post_columns( $post_types, array $columns, array $keys_to_remove = [] ): array {
		$instances = [];

		// Convert single post type to array
		if ( is_string( $post_types ) ) {
			$post_types = [ $post_types ];
		}

		foreach ( $post_types as $post_type ) {
			try {
				$instances[] = new Post( $columns, $post_type, $keys_to_remove );
			} catch ( Exception $e ) {
				error_log( 'WP Register Columns Error: ' . $e->getMessage() );
			}
		}

		return $instances;
	}
}

if ( ! function_exists( 'register_user_columns' ) ) {
	/**
	 * Register custom columns for users.
	 *
	 * @param array $columns        Array of custom columns configuration.
	 * @param array $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @return User|null The User instance or null on error.
	 */
	function register_user_columns( array $columns, array $keys_to_remove = [] ): ?User {
		try {
			return new User( $columns, 'user', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Columns Error: ' . $e->getMessage() );

			return null;
		}
	}
}

if ( ! function_exists( 'register_taxonomy_columns' ) ) {
	/**
	 * Register custom columns for taxonomies.
	 *
	 * @param string|array $taxonomies     Taxonomy/taxonomies to register columns for.
	 * @param array        $columns        Array of custom columns configuration.
	 * @param array        $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @return array Array of Taxonomy instances or empty array on error.
	 */
	function register_taxonomy_columns( $taxonomies, array $columns, array $keys_to_remove = [] ): array {
		$instances = [];

		// Convert single taxonomy to array
		if ( is_string( $taxonomies ) ) {
			$taxonomies = [ $taxonomies ];
		}

		foreach ( $taxonomies as $taxonomy ) {
			try {
				$instances[] = new Taxonomy( $columns, $taxonomy, $keys_to_remove );
			} catch ( Exception $e ) {
				error_log( 'WP Register Columns Error: ' . $e->getMessage() );
			}
		}

		return $instances;
	}
}

if ( ! function_exists( 'register_comment_columns' ) ) {
	/**
	 * Register custom columns for comments.
	 *
	 * @param array $columns        Array of custom columns configuration.
	 * @param array $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @return Comment|null The Comment instance or null on error.
	 */
	function register_comment_columns( array $columns, array $keys_to_remove = [] ): ?Comment {
		try {
			return new Comment( $columns, 'comment', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Columns Error: ' . $e->getMessage() );

			return null;
		}
	}
}

if ( ! function_exists( 'register_media_columns' ) ) {
	/**
	 * Register custom columns for media library.
	 *
	 * @param array $columns        Array of custom columns configuration.
	 * @param array $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @return Media|null The Media instance or null on error.
	 */
	function register_media_columns( array $columns, array $keys_to_remove = [] ): ?Media {
		try {
			return new Media( $columns, 'attachment', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Columns Error: ' . $e->getMessage() );

			return null;
		}
	}
}