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
use function add_filter;
use function class_exists;

/**
 * Check if the class `Customers` is defined, and if not, define it.
 */
if ( ! class_exists( 'Customers' ) ) :

	/**
	 * Easy Digital Downloads Customer class for custom columns.
	 *
	 * Provides a custom table view for media,
	 * including support for custom columns, sorting, and actions.
	 */
	class Customers extends BaseColumns {

		/**
		 * Load the necessary hooks for custom columns.
		 *
		 * Registers WordPress hooks for adding, sorting, and displaying custom customer columns.
		 *
		 * @param array $columns Array of columns to be registered.
		 *
		 * @return void
		 */
		protected function load_hooks( array $columns ): void {
			add_filter( 'edd_report_customer_columns', [ $this, 'register_columns' ] );
			add_filter( "manage_edit-edd_customer_sortable_columns", [ $this, 'register_sortable_columns' ] );

			foreach ( $columns as $column_name => $column_config ) {
				add_filter( "edd_customers_column_{$column_name}", function ( $value, $id ) use ( $column_name ) {
					return $this->render_column_content( (string) $value, (string) $column_name, (int) $id );
				}, 10, 2 );
			}
		}

	}
endif;