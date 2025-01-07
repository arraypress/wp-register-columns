<?php
/**
 * A robust class designed to simplify the registration of custom post columns in WordPress.
 * By encapsulating common functionalities and configurations, this class streamlines
 * the process of setting up custom post columns, including their labels, capabilities,
 * and supports. It provides a structured and extendable approach to declaring new
 * post columns, ensuring consistency and reducing boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom post columns with minimal code.
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
 * Post class for custom post columns.
 *
 * This class provides a structured approach to adding and managing custom columns
 * for posts in the WordPress admin area. It supports custom column configurations,
 * sortable columns, and custom rendering of column content.
 */
class Post extends BaseColumns {

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom post columns.
	 *
	 * @param array $columns An associative array of custom columns with their configurations.
	 *
	 * @return void
	 */
	protected function load_hooks( array $columns ): void {
		add_filter( "manage_{$this->object_subtype}_posts_columns", [ $this, 'register_columns' ] );
		add_filter( "manage_edit-{$this->object_subtype}_sortable_columns", [
			$this,
			'register_sortable_columns'
		] );
		add_action( "manage_{$this->object_subtype}_posts_custom_column", [
			$this,
			'render_column_content_wrapper'
		], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'sort_items' ] );

		if ( $this->custom_filter ) {
			add_filter( $this->custom_filter, [ $this, 'register_columns' ] );
		}
	}

}