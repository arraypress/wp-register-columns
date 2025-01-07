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

namespace ArrayPress\WP\Register\Columns\EDD;

use ArrayPress\WP\Register\Columns\Abstracts\BaseColumns;
use ArrayPress\WP\Register\Columns\Utils\Helpers;
use function add_filter;

/**
 * Easy Digital Downloads Customer class for custom columns.
 *
 * Provides a custom table view for media,
 * including support for custom columns, sorting, and actions.
 */
class Discounts extends BaseColumns {

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom customer columns.
	 *
	 * @param array $columns
	 *
	 * @return void
	 */
	protected function load_hooks( array $columns ): void {
		add_filter( 'edd_discounts_table_columns', [ $this, 'register_columns' ] );
		add_filter( 'edd_discounts_table_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_filter( 'edd_discounts_table_column', [ $this, 'render_discount_column_content' ], 10, 3 );
		add_filter( 'edd_adjustments_query_clauses', [ $this, 'query_clauses' ], 10, 2 );
	}

	/**
	 * Render the custom column content.
	 *
	 * This wrapper function is used to render the custom column content for discounts.
	 *
	 * @param string $value       The current value of the column.
	 * @param object $discount    The discount object.
	 * @param string $column_name The name of the column.
	 *
	 * @return string The rendered column content.
	 */
	public function render_discount_column_content( string $value, $discount, string $column_name ): string {
		return $this->render_column_content( $value, $column_name, $discount->id );
	}

	/**
	 * Modify the SQL clauses for retrieving discounts, allowing for sorting by custom meta keys.
	 *
	 * This method intercepts the discounts query and adjusts the SQL clauses to enable sorting
	 * by custom meta keys. It checks if the requested sorting column is a registered
	 * custom column with a meta key and applies the appropriate modifications to the
	 * query clauses.
	 *
	 * @param array               $clauses Existing SQL clauses for the discounts query.
	 * @param \EDD\Database\Query $base    Instance passed by reference.
	 *
	 * @return array Modified SQL clauses with additional joins and orderby clauses for custom meta sorting.
	 */
	public function query_clauses( array $clauses, \EDD\Database\Query $base ): array {

		// Bail if not admin
		if ( ! is_admin() ) {
			return $clauses;
		}

		global $wpdb;

		// Get the column name for ordering
		$orderby = Helpers::get_orderby();
		$order   = Helpers::get_order();

		// Get the column details using the column name
		$column = self::get_column_by_name( $orderby, $this->object_type, $this->object_subtype );

		// Bail if column is not sortable or meta key is not set
		if ( ! $column || empty( $column['meta_key'] ) || ! $column['sortable'] ) {
			return $clauses;
		}

		// Get the meta key for ordering
		$meta_key = $column['meta_key'];

		// Join discount meta data
		$clauses['join'] .= $wpdb->prepare( " INNER JOIN {$wpdb->prefix}edd_adjustmentmeta AS edd_am ON edd_a.id = edd_am.edd_adjustment_id AND edd_am.meta_key = %s", $meta_key );

		// Determine the order by clause based on column type
		if ( $column['numeric'] ) {
			$clauses['orderby'] = sprintf( "CAST(edd_am.meta_value AS SIGNED) %s", $order );
		} else {
			$clauses['orderby'] = sprintf( "edd_am.meta_value %s", $order );
		}

		// Return modified clauses
		return $clauses;
	}

}