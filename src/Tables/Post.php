<?php
/**
 * Post Columns Class
 *
 * Handles custom column registration for WordPress posts and custom post types.
 * Integrates with WordPress post table filters and query system.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\RegisterColumns\Tables;

use ArrayPress\WP\RegisterColumns\Abstracts\Columns;

/**
 * Class Post
 *
 * Manages custom columns for posts in the WordPress admin.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class Post extends Columns {

	/**
	 * Object type for posts.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom post columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( "manage_{$this->object_subtype}_posts_columns", [ $this, 'register_columns' ] );
		add_filter( "manage_edit-{$this->object_subtype}_sortable_columns", [ $this, 'register_sortable_columns' ] );
		add_action( "manage_{$this->object_subtype}_posts_custom_column", [
			$this,
			'render_column_content_wrapper'
		], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'sort_items' ] );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the post edit screen, false otherwise.
	 */
	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->post_type === $this->object_subtype && $screen->base === 'edit';
	}

	/**
	 * Render the custom column content wrapper for posts.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The post ID.
	 *
	 * @return void
	 */
	public function render_column_content_wrapper( string $column_name, int $post_id ): void {
		echo $this->render_column_content( '', $column_name, $post_id );
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $post_id     The post ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $post_id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		return $this->render_custom_column_content( $post_id, $column );
	}

	/**
	 * Render the content for a custom column.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $column  The configuration array of the current column.
	 *
	 * @return string The rendered content.
	 */
	private function render_custom_column_content( int $post_id, array $column ): string {
		// If there's a display callback, use it
		if ( is_callable( $column['display_callback'] ) ) {
			// If there's a meta_key, pass the value as first parameter
			if ( ! empty( $column['meta_key'] ) ) {
				$value = get_post_meta( $post_id, $column['meta_key'], true );

				return (string) call_user_func( $column['display_callback'], $value, $post_id );
			}

			// Otherwise just pass the post ID
			return (string) call_user_func( $column['display_callback'], $post_id );
		}

		// Default behavior: show meta value
		$meta_key = $column['meta_key'] ?? '';
		$value    = get_post_meta( $post_id, $meta_key, true );

		if ( empty( $value ) ) {
			return '—';
		}

		return esc_html( $value );
	}

	/**
	 * Sort the posts based on custom columns.
	 *
	 * @param \WP_Query $query The query instance.
	 *
	 * @return void
	 */
	public function sort_items( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
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
		} // Priority 2: If there's a meta_key, sort by meta value
		elseif ( ! empty( $meta_key ) ) {
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', $sort_numeric ? 'meta_value_num' : 'meta_value' );
		} // Priority 3: No meta_key or sortby means it's a post property
		else {
			// For numeric sorting without meta, assume ID
			if ( $sort_numeric ) {
				$query->set( 'orderby', 'ID' );
			}
		}
	}

}