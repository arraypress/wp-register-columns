<?php
/**
 * Taxonomy Columns Class
 *
 * Handles custom column registration for WordPress taxonomies and terms.
 * Integrates with WordPress taxonomy table filters and query system.
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
 * Class Taxonomy
 *
 * Manages custom columns for taxonomy terms in the WordPress admin.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
class Taxonomy extends Columns {

	/**
	 * Object type for taxonomy terms.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'term';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom term columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( "manage_edit-{$this->object_subtype}_columns", [ $this, 'register_columns' ] );
		add_filter( "manage_edit-{$this->object_subtype}_sortable_columns", [ $this, 'register_sortable_columns' ] );
		add_action( "manage_{$this->object_subtype}_custom_column", [ $this, 'render_column_content' ], 10, 3 );
		add_filter( 'terms_clauses', [ $this, 'terms_clauses' ], 10, 3 );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the taxonomy edit screen, false otherwise.
	 */
	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->taxonomy === $this->object_subtype;
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $term_id     The term ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $term_id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		return $this->render_custom_column_content( $term_id, $column );
	}

	/**
	 * Render the content for a custom column.
	 *
	 * @param int   $term_id The term ID.
	 * @param array $column  The configuration array of the current column.
	 *
	 * @return string The rendered content.
	 */
	private function render_custom_column_content( int $term_id, array $column ): string {
		// If there's a display callback, use it
		if ( is_callable( $column['display_callback'] ) ) {
			// If there's a meta_key, pass the value as first parameter
			if ( ! empty( $column['meta_key'] ) ) {
				$value = get_term_meta( $term_id, $column['meta_key'], true );

				return (string) call_user_func( $column['display_callback'], $value, $term_id );
			}

			// Otherwise just pass the term ID
			return (string) call_user_func( $column['display_callback'], $term_id );
		}

		// Default behavior: show meta value
		$meta_key = $column['meta_key'] ?? '';
		$value    = get_term_meta( $term_id, $meta_key, true );

		if ( empty( $value ) ) {
			return '—';
		}

		return esc_html( $value );
	}

	/**
	 * Check if the given taxonomies match the registered taxonomy.
	 *
	 * @param array $taxonomies The taxonomies to check.
	 *
	 * @return bool True if it matches, false otherwise.
	 */
	private function is_taxonomy( array $taxonomies ): bool {
		return in_array( $this->object_subtype, $taxonomies, true );
	}

	/**
	 * Modify the SQL clauses for retrieving terms, allowing for sorting by custom meta keys.
	 *
	 * @param array $clauses    Existing SQL clauses for the terms query.
	 * @param array $taxonomies Taxonomies being queried.
	 * @param array $args       Query arguments, including 'orderby' to specify the sorting column.
	 *
	 * @return array Modified SQL clauses with additional joins and orderby clauses.
	 */
	public function terms_clauses( array $clauses = [], array $taxonomies = [], array $args = [] ): array {
		global $wpdb;

		// Bail if not a target taxonomy
		if ( ! $this->is_taxonomy( $taxonomies ) || ! is_admin() ) {
			return $clauses;
		}

		$orderby = $this->get_orderby();
		$order   = $this->get_order();

		// Get the column details using the column name
		$column = $this->get_column_by_name( $orderby, $this->object_type, $this->object_subtype );

		// Bail if column is not sortable or meta key is not set
		if ( ! $column || empty( $column['meta_key'] ) || ! $column['sortable'] ) {
			return $clauses;
		}

		// Get the meta key for ordering
		$meta_key = $column['meta_key'];

		// Join term meta data
		$clauses['join']   .= " INNER JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id";
		$clauses['fields'] .= ', tm.meta_value';
		$clauses['where']  .= $wpdb->prepare( " AND tm.meta_key = %s", $meta_key );

		// Determine the order by clause based on column type
		if ( $column['numeric'] ) {
			$clauses['orderby'] = "ORDER BY CAST(tm.meta_value AS SIGNED)";
		} else {
			$clauses['orderby'] = "ORDER BY tm.meta_value";
		}

		$clauses['order'] = $order;

		// Return modified clauses
		return $clauses;
	}

}