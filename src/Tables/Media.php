<?php
/**
 * Media Columns Class
 *
 * Handles custom column registration for WordPress media library.
 * Since media are a special post type (attachment), this class integrates with
 * WordPress's media-specific filters while maintaining post-like functionality.
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
 * Class Media
 *
 * Manages custom columns for media in the WordPress admin.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class Media extends Columns {

	/**
	 * Object type for media.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom media columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'manage_media_columns', [ $this, 'register_columns' ] );
		add_filter( 'manage_upload_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_column_content_wrapper' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'sort_items' ] );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the media library screen, false otherwise.
	 */
	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->id === 'upload';
	}

	/**
	 * Render the custom column content wrapper for media.
	 *
	 * @param string $column_name   The name of the column.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function render_column_content_wrapper( string $column_name, int $attachment_id ): void {
		echo $this->render_column_content( '', $column_name, $attachment_id );
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value         The current value of the column.
	 * @param string $column_name   The name/key of the current column.
	 * @param mixed  $attachment_id The attachment ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $attachment_id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		return $this->render_custom_column_content( $attachment_id, $column );
	}

	/**
	 * Render the content for a custom column.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $column        The configuration array of the current column.
	 *
	 * @return string The rendered content.
	 */
	private function render_custom_column_content( int $attachment_id, array $column ): string {
		// If there's a display callback, use it
		if ( is_callable( $column['display_callback'] ) ) {
			// If there's a meta_key, pass the value as first parameter
			if ( ! empty( $column['meta_key'] ) ) {
				$value = get_post_meta( $attachment_id, $column['meta_key'], true );

				return (string) call_user_func( $column['display_callback'], $value, $attachment_id );
			}

			// Otherwise just pass the attachment ID
			return (string) call_user_func( $column['display_callback'], $attachment_id );
		}

		// Default behavior: show meta value
		$meta_key = $column['meta_key'] ?? '';
		$value    = get_post_meta( $attachment_id, $meta_key, true );

		if ( empty( $value ) ) {
			return '—';
		}

		return esc_html( $value );
	}

	/**
	 * Sort the media based on custom columns.
	 *
	 * @param \WP_Query $query The query instance.
	 *
	 * @return void
	 */
	public function sort_items( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only apply to attachment queries
		if ( $query->get( 'post_type' ) !== 'attachment' ) {
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