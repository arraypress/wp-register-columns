<?php
/**
 * A robust class designed to simplify the registration of custom user columns in WordPress.
 * By encapsulating common functionalities and configurations, this class streamlines
 * the process of setting up custom user columns, including their labels, capabilities,
 * and supports. It provides a structured and extendable approach to declaring new
 * user columns, ensuring consistency and reducing boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom user columns with minimal code.
 * - Customizable column labels for enhanced admin UI integration.
 * - Supports sortable columns with text or numeric sorting.
 * - Enforces best practices by setting public visibility flags appropriately.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterCustomColumns\EDD;

use ArrayPress\RegisterCustomColumns\BaseColumns;
use ArrayPress\RegisterCustomColumns\Utils\Utils;
use function add_filter;
use function class_exists;

/**
 * Check if the class `Orders` is defined, and if not, define it.
 */
if ( ! class_exists( 'Orders' ) ) :

	/**
	 * Easy Digital Downloads Customer class for custom columns.
	 *
	 * Provides a custom table view for media,
	 * including support for custom columns, sorting, and actions.
	 */
	class Orders extends BaseColumns {

		/**
		 * Load the necessary hooks for custom columns.
		 *
		 * Registers WordPress hooks for adding, sorting, and displaying custom customer columns.
		 *
		 * @param array $columns An associative array of custom columns with their configurations.
         *
		 * @return void
		 */
		protected function load_hooks( array $columns ): void {
			add_filter( 'edd_payments_table_columns', [ $this, 'register_columns' ] );
			add_filter( 'edd_payments_table_sortable_columns', [ $this, 'register_sortable_columns' ] );
			add_filter( 'edd_payments_table_column', [ $this, 'render_order_column_content' ], 10, 3 );
			add_filter( 'edd_orders_query_clauses', array( $this, 'query_clauses' ), 10, 2 );
		}

		/**
		 * Render the custom column content.
		 *
		 * This wrapper function is used to render the custom column content for Orders.
		 *
		 * @param string $value       The current value of the column.
		 * @param int    $order_id    The order ID.
		 * @param string $column_name The name of the column.
		 *
		 * @return string The rendered column content.
		 */
		public function render_order_column_content( string $value, int $order_id, string $column_name ): string {
			return $this->render_column_content( $value, $column_name, $order_id );
		}

		/**
		 * Modify the SQL clauses for retrieving Orders, allowing for sorting by custom meta keys.
		 *
		 * This method intercepts the Orders query and adjusts the SQL clauses to enable sorting
		 * by custom meta keys. It checks if the requested sorting column is a registered
		 * custom column with a meta key and applies the appropriate modifications to the
		 * query clauses.
		 *
		 * @param array               $clauses Existing SQL clauses for the Orders query.
		 * @param \EDD\Database\Query $base    Instance passed by reference.
		 *
		 * @return array Modified SQL clauses with additional joins and orderby clauses for custom meta sorting.
		 */
		public function query_clauses( array $clauses, \EDD\Database\Query $base ): array {
			global $wpdb;

			if ( ! is_admin() ) {
				return $clauses;
			}

			// Get the column name for ordering
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

			// Join discount meta data
			$clauses['join'] .= $wpdb->prepare( " INNER JOIN {$wpdb->prefix}edd_ordermeta AS edd_om ON edd_o.id = edd_om.edd_order_id AND edd_om.meta_key = %s", $meta_key );

			// Determine the order by clause based on column type
			if ( $column['numeric'] ) {
				$clauses['orderby'] = sprintf( "CAST(edd_om.meta_value AS SIGNED) %s", $order );
			} else {
				$clauses['orderby'] = sprintf( "edd_om.meta_value %s", $order );
			}

			// Return modified clauses
			return $clauses;
		}

	}
endif;