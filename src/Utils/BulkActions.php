<?php
/**
 * Bulk Action Registration
 *
 * @package     ArrayPress\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.1.0
 */

declare( strict_types=1 );

use ArrayPress\RegisterColumns\BulkActions\Comment;
use ArrayPress\RegisterColumns\BulkActions\Media;
use ArrayPress\RegisterColumns\BulkActions\Post;
use ArrayPress\RegisterColumns\BulkActions\Taxonomy;
use ArrayPress\RegisterColumns\BulkActions\User;

if ( ! function_exists( 'register_post_bulk_actions' ) ) {
	/**
	 * Add bulk actions to a post type's list table.
	 *
	 * @param string|string[]      $post_types One post type or several.
	 * @param array<string, mixed> $actions    Actions, keyed by name.
	 *
	 * @return void
	 */
	function register_post_bulk_actions( $post_types, array $actions ): void {
		foreach ( (array) $post_types as $post_type ) {
			new Post( $actions, (string) $post_type );
		}
	}
}

if ( ! function_exists( 'register_user_bulk_actions' ) ) {
	/**
	 * Add bulk actions to the users list table.
	 *
	 * @param array<string, mixed> $actions Actions, keyed by name.
	 *
	 * @return void
	 */
	function register_user_bulk_actions( array $actions ): void {
		new User( $actions, 'user' );
	}
}

if ( ! function_exists( 'register_taxonomy_bulk_actions' ) ) {
	/**
	 * Add bulk actions to a taxonomy's term list.
	 *
	 * @param string|string[]      $taxonomies One taxonomy or several.
	 * @param array<string, mixed> $actions    Actions, keyed by name.
	 *
	 * @return void
	 */
	function register_taxonomy_bulk_actions( $taxonomies, array $actions ): void {
		foreach ( (array) $taxonomies as $taxonomy ) {
			new Taxonomy( $actions, (string) $taxonomy );
		}
	}
}

if ( ! function_exists( 'register_comment_bulk_actions' ) ) {
	/**
	 * Add bulk actions to the comments list table.
	 *
	 * @param array<string, mixed> $actions Actions, keyed by name.
	 *
	 * @return void
	 */
	function register_comment_bulk_actions( array $actions ): void {
		new Comment( $actions, 'comment' );
	}
}

if ( ! function_exists( 'register_media_bulk_actions' ) ) {
	/**
	 * Add bulk actions to the media library list.
	 *
	 * @param array<string, mixed> $actions Actions, keyed by name.
	 *
	 * @return void
	 */
	function register_media_bulk_actions( array $actions ): void {
		new Media( $actions, 'attachment' );
	}
}
