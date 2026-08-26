<?php
/**
 * Registration Functions
 *
 * Provides convenient helper functions for registering custom row actions in WordPress.
 * These functions are in the global namespace for easy use throughout your codebase.
 *
 * @package     ArrayPress\RegisterColumns\
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterColumns\RowActions\Post;
use ArrayPress\RegisterColumns\RowActions\User;
use ArrayPress\RegisterColumns\RowActions\Taxonomy;
use ArrayPress\RegisterColumns\RowActions\Comment;
use ArrayPress\RegisterColumns\RowActions\Media;

if ( ! function_exists( 'register_post_row_actions' ) ) {
	/**
	 * Register custom row actions for posts or custom post types.
	 *
	 * @param string|array $post_types     Post type(s) to register actions for.
	 * @param array        $actions        Array of custom actions configuration.
	 * @param array        $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @return array Array of Post instances or empty array on error.
	 */
	function register_post_row_actions( $post_types, array $actions, array $keys_to_remove = [] ): array {
		$instances = [];

		// Convert single post type to array
		if ( is_string( $post_types ) ) {
			$post_types = [ $post_types ];
		}

		foreach ( $post_types as $post_type ) {
			try {
				$instances[] = new Post( $actions, $post_type, $keys_to_remove );
			} catch ( Exception $e ) {
				error_log( 'WP Register Row Actions Error: ' . $e->getMessage() );
			}
		}

		return $instances;
	}
}

if ( ! function_exists( 'register_user_row_actions' ) ) {
	/**
	 * Register custom row actions for users.
	 *
	 * @param array $actions        Array of custom actions configuration.
	 * @param array $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @return User|null The User instance or null on error.
	 */
	function register_user_row_actions( array $actions, array $keys_to_remove = [] ): ?User {
		try {
			return new User( $actions, 'user', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Row Actions Error: ' . $e->getMessage() );

			return null;
		}
	}
}

if ( ! function_exists( 'register_taxonomy_row_actions' ) ) {
	/**
	 * Register custom row actions for taxonomies.
	 *
	 * @param string|array $taxonomies     Taxonomy/taxonomies to register actions for.
	 * @param array        $actions        Array of custom actions configuration.
	 * @param array        $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @return array Array of Taxonomy instances or empty array on error.
	 */
	function register_taxonomy_row_actions( $taxonomies, array $actions, array $keys_to_remove = [] ): array {
		$instances = [];

		// Convert single taxonomy to array
		if ( is_string( $taxonomies ) ) {
			$taxonomies = [ $taxonomies ];
		}

		foreach ( $taxonomies as $taxonomy ) {
			try {
				$instances[] = new Taxonomy( $actions, $taxonomy, $keys_to_remove );
			} catch ( Exception $e ) {
				error_log( 'WP Register Row Actions Error: ' . $e->getMessage() );
			}
		}

		return $instances;
	}
}

if ( ! function_exists( 'register_comment_row_actions' ) ) {
	/**
	 * Register custom row actions for comments.
	 *
	 * @param array $actions        Array of custom actions configuration.
	 * @param array $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @return Comment|null The Comment instance or null on error.
	 */
	function register_comment_row_actions( array $actions, array $keys_to_remove = [] ): ?Comment {
		try {
			return new Comment( $actions, 'comment', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Row Actions Error: ' . $e->getMessage() );

			return null;
		}
	}
}

if ( ! function_exists( 'register_media_row_actions' ) ) {
	/**
	 * Register custom row actions for media library.
	 *
	 * @param array $actions        Array of custom actions configuration.
	 * @param array $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @return Media|null The Media instance or null on error.
	 */
	function register_media_row_actions( array $actions, array $keys_to_remove = [] ): ?Media {
		try {
			return new Media( $actions, 'attachment', $keys_to_remove );
		} catch ( Exception $e ) {
			error_log( 'WP Register Row Actions Error: ' . $e->getMessage() );

			return null;
		}
	}
}
