<?php
/**
 * Functions file for registering custom columns within a WordPress environment.
 *
 * This file contains utility functions that leverage the RegisterColumns class to handle the registration of custom
 * columns for various WordPress objects. It simplifies the inclusion of custom columns for posts, taxonomies,
 * comments, media, and users.
 *
 * The column registration functions accept an array of column definitions and optional extra arguments, along with an
 * error callback to handle exceptions. These functions ensure that the columns are correctly set up according to
 * WordPress standards and best practices.
 *
 * @package     ArrayPress/Utils/WP/RegisterCustomColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @since       0.1.0
 * @autor       David Sherlock
 */

namespace ArrayPress\RegisterCustomColumns;

use ArrayPress\RegisterCustomColumns\EDD\Customers;
use ArrayPress\RegisterCustomColumns\EDD\Discounts;
use ArrayPress\RegisterCustomColumns\EDD\Orders;
use ArrayPress\RegisterCustomColumns\WordPress\Comments;
use ArrayPress\RegisterCustomColumns\WordPress\Media;
use ArrayPress\RegisterCustomColumns\WordPress\Post;
use ArrayPress\RegisterCustomColumns\WordPress\Taxonomy;
use ArrayPress\RegisterCustomColumns\WordPress\User;
use Exception;
use function call_user_func;
use function function_exists;
use function is_callable;

if ( ! function_exists( 'register_columns' ) ) {
	/**
	 * Base function to register custom columns for different object types.
	 *
	 * @param string|array  $object_types   Array or string of object types.
	 * @param array         $columns        Array of custom columns configuration.
	 * @param string        $object_class   The class to use for registering columns.
	 * @param string        $primary_type   The primary object type (e.g., 'post', 'user').
	 * @param string|null   $custom_filter  Custom filter to use in hooks.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_columns( $object_types, array $columns, string $object_class, string $primary_type, ?string $custom_filter = null, array $keys_to_remove = [], ?callable $error_callback = null ) {
		try {
			if ( is_string( $object_types ) ) {
				$object_types = [ $object_types ];
			}

			foreach ( $object_types as $object_type ) {
				ColumnsFactory::getInstance( $object_class, $columns, $primary_type, $object_type, $custom_filter, $keys_to_remove );
			}
		} catch ( Exception $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}
		}
	}
}

if ( ! function_exists( 'register_post_columns' ) ) {
	/**
	 * Helper function to register custom columns for posts.
	 *
	 * @param array|string  $post_types     Array or string of post types.
	 * @param array         $columns        Array of custom columns configuration.
	 * @param string|null   $custom_filter  Custom filter to use in hooks.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_post_columns( $post_types, array $columns, ?string $custom_filter = null, array $keys_to_remove = [], ?callable $error_callback = null ) {
		register_columns( $post_types, $columns, Post::class, 'post', $custom_filter, $keys_to_remove, $error_callback );
	}
}

if ( ! function_exists( 'register_user_columns' ) ) {
	/**
	 * Helper function to register custom columns for users.
	 *
	 * @param array         $columns        Array of custom columns configuration.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_user_columns( array $columns, array $keys_to_remove = [], ?callable $error_callback = null ) {
		register_columns( 'user', $columns, User::class, 'user', null, $keys_to_remove, $error_callback );
	}
}

if ( ! function_exists( 'register_taxonomy_columns' ) ) {
	/**
	 * Helper function to register custom columns for taxonomies.
	 *
	 * @param array|string  $taxonomies     Array or string of taxonomies.
	 * @param array         $columns        Array of custom columns configuration.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_taxonomy_columns( $taxonomies, array $columns, array $keys_to_remove = [], ?callable $error_callback = null ) {
		register_columns( $taxonomies, $columns, Taxonomy::class, 'term', null, $keys_to_remove, $error_callback );
	}
}

if ( ! function_exists( 'register_media_columns' ) ) {
	/**
	 * Helper function to register custom columns for media.
	 *
	 * @param array         $columns        Array of custom columns configuration.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_media_columns( array $columns, array $keys_to_remove = [], ?callable $error_callback = null ) {
		register_columns( 'attachment', $columns, Media::class, 'post', null, $keys_to_remove, $error_callback );
	}
}

if ( ! function_exists( 'register_comment_columns' ) ) {
	/**
	 * Helper function to register custom columns for comments.
	 *
	 * @param array         $columns        Array of custom columns configuration.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 */
	function register_comment_columns( array $columns, array $keys_to_remove = [], ?callable $error_callback = null ) {
		register_columns( 'comment', $columns, Comments::class, 'comment', null, $keys_to_remove, $error_callback );
	}
}

if ( ! function_exists( 'register_edd_columns' ) ) {
	/**
	 * Helper function to register custom columns for EDD.
	 *
	 * @param string        $type           The type of EDD columns to register.
	 * @param array         $columns        Array of custom columns configuration.
	 * @param array         $keys_to_remove Array of column keys to remove.
	 * @param callable|null $error_callback Callback for handling errors.
	 *
	 * @return void|null
	 * @throws Exception If the type or class is invalid.
	 */
	function register_edd_columns( string $type, array $columns, array $keys_to_remove = [], ?callable $error_callback = null ) {
		static $edd_column_mapping = [
			'discounts' => [
				'class'          => Discounts::class,
				'object_type'    => 'edd_adjustment',
				'object_subtype' => 'edd_adjustment',
			],
			'customers' => [
				'class'          => Customers::class,
				'object_type'    => 'edd_customer',
				'object_subtype' => 'edd_customer',
			],
			'orders'    => [
				'class'          => Orders::class,
				'object_type'    => 'edd_order',
				'object_subtype' => 'edd_order',
			],
		];

		if ( ! isset( $edd_column_mapping[ $type ] ) ) {
			throw new Exception( "Invalid EDD column type: {$type}." );
		}

		$mapping = $edd_column_mapping[ $type ];

		if ( ! class_exists( $mapping['class'] ) ) {
			throw new Exception( "Class {$mapping['class']} does not exist." );
		}

		try {
			register_columns(
				$mapping['object_type'],
				$columns,
				$mapping['class'],
				$mapping['object_type'],
				null,
				$keys_to_remove,
				$error_callback
			);
		} catch ( Exception $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}
		}
	}
}
