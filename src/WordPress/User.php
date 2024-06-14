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

namespace ArrayPress\RegisterCustomColumns\WordPress;

use ArrayPress\RegisterCustomColumns\BaseColumns;
use function add_action;
use function add_filter;
use function class_exists;

/**
 * Check if the class `User` is defined, and if not, define it.
 */
if ( ! class_exists( 'User' ) ) :

	/**
	 * User class for custom user columns.
	 *
	 * Extends the BaseColumns class to provide a custom table view for users,
	 * including support for custom columns, sorting, and actions.
	 */
	class User extends BaseColumns {

		/**
		 * Load the necessary hooks for custom columns.
		 *
		 * Registers WordPress hooks for adding, sorting, and displaying custom user columns.
		 *
		 * @param array $columns An associative array of custom columns with their configurations.
		 *
		 * @return void
		 */
		protected function load_hooks( array $columns ): void {
			add_filter( 'manage_users_columns', [ $this, 'register_columns' ] );
			add_filter( 'manage_users_sortable_columns', [ $this, 'register_sortable_columns' ] );
			add_action( 'manage_users_custom_column', [ $this, 'render_column_content' ], 10, 3 );
			add_action( 'pre_get_users', [ $this, 'sort_items' ] );
		}
	}

endif;