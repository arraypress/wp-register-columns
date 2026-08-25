<?php
/**
 * Post List Filters Class
 *
 * @package     ArrayPress\WP\RegisterListFilters
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Filters;

use ArrayPress\RegisterColumns\Abstracts\ListFilters;

class Post extends ListFilters {

	/**
	 * Object type constant
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for post list filters.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_action( 'restrict_manage_posts', [ $this, 'render_filters_for_post_type' ] );
		add_filter( 'parse_query', [ $this, 'modify_query' ] );
	}

	/**
	 * Render filters only for the current post type.
	 *
	 * @param string $post_type Current post type.
	 *
	 * @return void
	 */
	public function render_filters_for_post_type( string $post_type ): void {
		if ( $post_type !== $this->object_subtype ) {
			return;
		}

		$this->render_filters();
	}

	/**
	 * Modify the query based on selected filters.
	 *
	 * @param \WP_Query $query The query object.
	 *
	 * @return void
	 */
	public function modify_query( $query ): void {
		global $pagenow;

		// Only modify admin queries for the correct post type
		if ( ! is_admin() || $pagenow !== 'edit.php' ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		// Check if this is the correct post type
		$current_post_type = $query->query['post_type'] ?? 'post';
		if ( $current_post_type !== $this->object_subtype ) {
			return;
		}

		$filters = self::get_filters( $this->object_type, $this->object_subtype );

		// Initialize arrays for AND logic
		$meta_query = [];
		$tax_query  = [];

		foreach ( $filters as $key => $filter ) {
			// Skip if filter not set or empty
			if ( empty( $_GET[ $key ] ) ) {
				continue;
			}

			// Check capability
			if ( ! empty( $filter['capability'] ) && ! current_user_can( $filter['capability'] ) ) {
				continue;
			}

			// Unslashed first. WordPress adds slashes to everything in $_GET,
			// so a filter value containing an apostrophe arrives as O\'Brien
			// and matches nothing — sanitize_text_field does not undo it.
			$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );

			// Priority 1: Custom query callback
			if ( ! empty( $filter['query_callback'] ) && is_callable( $filter['query_callback'] ) ) {
				call_user_func( $filter['query_callback'], $query, $value );
			} elseif ( ! empty( $filter['taxonomy'] ) ) {
				// Priority 2: Taxonomy query
				$tax_query[] = [
					'taxonomy' => $filter['taxonomy'],
					'field'    => 'slug',
					'terms'    => $value,
				];
			} else {
				// Priority 3: Auto meta query (use filter key as meta_key)
				$meta_query[] = [
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				];
			}
		}

		// Apply meta query with AND relation if we have multiple filters
		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$query->set( 'meta_query', $meta_query );
		}

		// Apply tax query with AND relation if we have multiple filters
		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$query->set( 'tax_query', $tax_query );
		}
	}
}
