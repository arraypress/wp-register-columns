<?php
/**
 * Taxonomy Row Actions Class
 *
 * Handles custom row action registration for WordPress taxonomies and terms.
 * Integrates with WordPress taxonomy row action filters.
 *
 * @package     ArrayPress\RegisterColumns\
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\RowActions;

use ArrayPress\RegisterColumns\Utils\Runtime;

use ArrayPress\RegisterColumns\Abstracts\RowActions;
use WP_Term;

/**
 * Class Taxonomy
 *
 * Manages custom row actions for taxonomy terms in the WordPress admin.
 *
 * @package ArrayPress\RegisterColumns\
 */
class Taxonomy extends RowActions {

	/**
	 * Object type for taxonomy terms.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'term';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom term row actions.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_filter( "{$this->object_subtype}_row_actions", [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'wp_ajax_' . Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ), [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array   $actions Array of existing actions.
	 * @param WP_Term $term    The term object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Term $term ): array {
		return $this->register_actions( $actions, $term, $term->term_id );
	}
}
