<?php
/**
 * Comment Columns Class
 *
 * Handles custom column registration for WordPress comments.
 * Integrates with WordPress comment table filters and query system.
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
use ArrayPress\RegisterColumns\Support\Screen;
use ArrayPress\RegisterColumns\Support\Image;

/**
 * Class Comment
 *
 * Manages custom columns for comments in the WordPress admin.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class Comment extends Columns {

	/**
	 * Object type for comments.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'comment';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom comment columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'manage_edit-comments_columns', [ $this, 'register_columns' ] );
		add_filter( 'manage_edit-comments_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_action( 'manage_comments_custom_column', [ $this, 'render_column_content_wrapper' ], 10, 2 );
		add_action( 'pre_get_comments', [ $this, 'sort_items' ] );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the comments screen, false otherwise.
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
		return get_comment_meta( (int) $object_id, $meta_key, true );
	}

	protected function is_screen(): bool {
		return Screen::is( $this->object_type, $this->object_subtype );
	}

	/**
	 * Render the custom column content wrapper for comments.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $comment_id  The comment ID.
	 *
	 * @return void
	 */
	public function render_column_content_wrapper( string $column_name, int $comment_id ): void {
		echo $this->render_column_content( '', $column_name, $comment_id );
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $comment_id  The comment ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $comment_id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		return $this->render_custom_column_content( $comment_id, $column );
	}

	/**
	 * Sort the comments based on custom columns.
	 *
	 * @param \WP_Comment_Query $query The query instance.
	 *
	 * @return void
	 */
	public function sort_items( $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->query_vars['orderby'] ?? '';

		// Ensure $orderby is a valid string before proceeding
		if ( ! is_string( $orderby ) || empty( $orderby ) ) {
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
			$query->query_vars['orderby'] = $sortby;
		} elseif ( ! empty( $meta_key ) ) {
			// Priority 2: If there's a meta_key, sort by meta value
			$query->query_vars['meta_key'] = $meta_key;
			$query->query_vars['orderby']  = $sort_numeric ? 'meta_value_num' : 'meta_value';
		}
	}

	/**
	 * The comment author's avatar, as core shows it in the author column.
	 *
	 * @param int|string       $object_id The comment.
	 * @param int|string|array $size      Configured size.
	 *
	 * @return string
	 */
	protected function render_default_image( $object_id, $size ): string {
		[ $width ] = Image::pixels( $size );

		$html = get_avatar( get_comment( (int) $object_id ), $width );

		return is_string( $html ) ? $html : '';
	}
}
