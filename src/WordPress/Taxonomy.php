<?php
/**
 * A robust class designed to simplify the registration of custom term columns in WordPress.
 * By encapsulating common functionalities and configurations, this class streamlines
 * the process of setting up custom columns for taxonomies, including their labels,
 * capabilities, and supports. It provides a structured and extendable approach to
 * declaring new columns, ensuring consistency and reducing boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom columns with minimal code.
 * - Customizable column labels for enhanced admin UI integration.
 * - Supports sortable columns with text or numeric sorting.
 * - Enforces best practices by setting public visibility flags appropriately.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @autor           David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterCustomColumns\WordPress;

use ArrayPress\RegisterCustomColumns\BaseColumns;
use ArrayPress\RegisterCustomColumns\Utils\Utils;
use function add_action;
use function add_filter;
use function class_exists;
use function is_admin;
use function in_array;

/**
 * Check if the class `Taxonomy` is defined, and if not, define it.
 */
if ( ! class_exists( 'Taxonomy' ) ) :

	/**
	 * Taxonomy class for custom term columns.
	 *
	 * Extends the WP_Term_Query class to provide a custom table view for a specific taxonomy,
	 * including support for custom columns, sorting, and actions.
	 */
	class Taxonomy extends BaseColumns {

		/**
		 * Load the necessary hooks for custom columns.
		 *
		 * Registers WordPress hooks for adding, sorting, and displaying custom term columns.
		 *
		 * @param array $columns An associative array of custom columns with their configurations.
		 *
		 * @return void
		 */
		protected function load_hooks( array $columns ): void {
			add_filter( "manage_edit-{$this->object_subtype}_columns", [ $this, 'register_columns' ] );
			add_filter( "manage_edit-{$this->object_subtype}_sortable_columns", [
				$this,
				'register_sortable_columns'
			] );
			add_action( "manage_{$this->object_subtype}_custom_column", [ $this, 'render_column_content' ], 10, 3 );
			add_filter( 'terms_clauses', [ $this, 'terms_clauses' ], 10, 3 );
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
		 * This method intercepts the terms query and adjusts the SQL clauses to enable sorting
		 * by custom meta keys. It checks if the requested sorting column is a registered
		 * custom column with a meta key and applies the appropriate modifications to the
		 * query clauses.
		 *
		 * @param array $clauses    Existing SQL clauses for the terms query.
		 * @param array $taxonomies Taxonomies being queried.
		 * @param array $args       Query arguments, including 'orderby' to specify the sorting column.
		 *
		 * @return array Modified SQL clauses with additional joins and orderby clauses for custom meta sorting.
		 */
		public function terms_clauses( array $clauses = [], array $taxonomies = [], array $args = [] ) {
			global $wpdb;

			// Bail if not a target taxonomy
			if ( ! $this->is_taxonomy( $taxonomies ) || ! is_admin() ) {
				return $clauses;
			}

			$orderby = Utils::get_orderby();
			$order   = Utils::get_order();

			// Get the column details using the column name
			$column = self::get_column_by_name( $orderby, $this->object_type, $this->object_subtype );

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

endif;