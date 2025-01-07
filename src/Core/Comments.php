<?php
/**
 * A robust class designed to simplify the registration of custom comment columns in WordPress.
 * By encapsulating common functionalities and configurations, this class streamlines
 * the process of setting up custom comment columns, including their labels, capabilities,
 * and supports. It provides a structured and extendable approach to declaring new
 * comment columns, ensuring consistency and reducing boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom comment columns with minimal code.
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

namespace ArrayPress\WP\Register\Columns\Core;

use ArrayPress\WP\Register\Columns\Abstracts\BaseColumns;
use function add_action;
use function add_filter;

/**
 * Comments class for custom comment columns.
 *
 * Provides a custom table view for comments,
 * including support for custom columns, sorting, and actions.
 */
class Comments extends BaseColumns {

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom comment columns.
	 *
	 * @param array $columns An associative array of custom columns with their configurations.
	 *
	 * @return void
	 */
	protected function load_hooks( array $columns ): void {
		add_filter( 'manage_edit-comments_columns', [ $this, 'register_columns' ] );
		add_filter( 'manage_edit-comments_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_action( 'manage_comments_custom_column', [ $this, 'render_column_content_wrapper' ], 10, 2 );
		add_action( 'pre_get_comments', [ $this, 'sort_items' ] );
	}

}