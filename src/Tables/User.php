<?php
/**
 * User Columns Class
 *
 * Handles custom column registration for WordPress users.
 * Integrates with WordPress user table filters and query system.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tables;

use ArrayPress\RegisterColumns\Abstracts\Columns;

/**
 * Class User
 *
 * Manages custom columns for users in the WordPress admin.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class User extends Columns {

	/**
	 * Object type for users.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'user';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom user columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'manage_users_columns', [ $this, 'register_columns' ] );
		add_filter( 'manage_users_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'render_column_content' ], 10, 3 );
		add_action( 'pre_get_users', [ $this, 'sort_items' ] );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the users screen, false otherwise.
	 */
	/**
	 * One meta value for this object type.
	 *
	 * The only thing that differed between the five copies of the renderer
	 * this replaced.
	 *
	 * @param int|string $object_id The object.
	 * @param string     $meta_key  The key.
	 *
	 * @return mixed
	 */
	protected function get_meta( $object_id, string $meta_key ) {
		return get_user_meta( (int) $object_id, $meta_key, true );
	}

	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->id === 'users';
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $user_id     The user ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $user_id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		return $this->render_custom_column_content( $user_id, $column );
	}

	/**
	 * Sort the users based on custom columns.
	 *
	 * @param \WP_User_Query $query The query instance.
	 *
	 * @return void
	 */
	public function sort_items( $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		// Ensure $orderby is a valid string before proceeding
		if ( ! is_string( $orderby ) ) {
			return;
		}

		$column = $this->get_column_by_name( $orderby, $this->object_type, $this->object_subtype );

		// Ensure the column exists and is sortable
		if ( ! $column || ! $column['sortable'] ) {
			return;
		}

		$meta_key     = $column['meta_key'] ?? '';
		$sortby       = $column['sortby'] ?? '';
		$sort_numeric = $column['numeric'] ?? false;

		// Priority 1: Use sortby if explicitly set (most flexible)
		if ( ! empty( $sortby ) ) {
			$query->set( 'orderby', $sortby );
		} elseif ( ! empty( $meta_key ) ) {
			// Priority 2: If there's a meta_key, sort by meta value
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', $sort_numeric ? 'meta_value_num' : 'meta_value' );
		}
	}
}
