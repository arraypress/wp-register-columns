<?php
/**
 * User List Filters Class
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
use ArrayPress\RegisterColumns\Support\QueryArgsAdapter;

class User extends ListFilters {

	/**
	 * Object type constant
	 */
	protected const OBJECT_TYPE = 'user';

	/**
	 * Load the necessary hooks for user list filters.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_action( 'restrict_manage_users', [ $this, 'render_filters_and_button' ] );
		add_filter( 'users_list_table_query_args', [ $this, 'modify_list_table_query' ] );
	}

	/**
	 * Track if filters have been rendered to prevent duplicates
	 *
	 * @var bool
	 */
	protected static bool $rendered = false;

	/**
	 * Render filters and button for users.
	 *
	 * @return void
	 */
	public function render_filters_and_button(): void {
		// Prevent duplicate rendering (action fires twice: top and bottom of table)
		if ( self::$rendered ) {
			return;
		}

		$filters = self::get_filters( $this->object_type, $this->object_subtype );

		if ( empty( $filters ) ) {
			return;
		}

		self::$rendered = true;

		// Wrap in span to prevent float issues
		echo '<span style="display: inline-block; margin-left: 8px;">';

		$this->render_filters();

		// Add filter button
		submit_button( __( 'Filter' ), '', 'filter_action', false );

		echo '</span>';
	}

	/**
	 * Modify the user list table query arguments.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array Modified query arguments.
	 */
	public function modify_list_table_query( $args ): array {
		$filters = self::get_filters( $this->object_type, $this->object_subtype );

		// Initialize meta_query array for AND logic
		$meta_query       = [];
		$has_meta_filters = false;

		foreach ( $filters as $key => $filter ) {
			// Skip if filter not set or empty - check REQUEST
			if ( ! isset( $_REQUEST[ $key ] ) || $_REQUEST[ $key ] === '' ) {
				continue;
			}

			// Check capability
			if ( ! empty( $filter['capability'] ) && ! current_user_can( $filter['capability'] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );

			// Priority 1: Custom query callback
			if ( ! empty( $filter['query_callback'] ) && is_callable( $filter['query_callback'] ) ) {
				$query_wrapper = new QueryArgsAdapter( $args );
				call_user_func( $filter['query_callback'], $query_wrapper, $value );
				$args = $query_wrapper->get_args();
			} elseif ( ! empty( $filter['taxonomy'] ) ) {
				// Priority 2: Taxonomy query
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = [];
				}
				$args['tax_query'][] = [
					'taxonomy' => $filter['taxonomy'],
					'field'    => 'slug',
					'terms'    => $value,
				];
				// Set relation to AND if multiple tax queries
				if ( count( $args['tax_query'] ) > 1 && ! isset( $args['tax_query']['relation'] ) ) {
					$args['tax_query']['relation'] = 'AND';
				}
			} else {
				// Priority 3: Auto meta query (use filter key as meta_key)
				$meta_query[]     = [
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				];
				$has_meta_filters = true;
			}
		}

		// Apply meta query with AND relation if we have multiple filters
		if ( $has_meta_filters ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}

	/**
	 * Modify the query based on selected filters.
	 * Kept for backwards compatibility but not used for list table.
	 *
	 * @param \WP_User_Query $query The query object.
	 *
	 * @return void
	 */
	public function modify_query( $query ): void {
		// This method is not used for the users list table
		// The list table uses users_list_table_query_args filter instead
	}
}
