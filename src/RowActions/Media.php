<?php
/**
 * Media Row Actions Class
 *
 * Handles custom row action registration for WordPress media library.
 * Since media are a special post type (attachment), this class integrates with
 * WordPress's media-specific row action filters.
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
use WP_Post;

/**
 * Class Media
 *
 * Manages custom row actions for media in the WordPress admin.
 *
 * @package ArrayPress\RegisterColumns\
 */
class Media extends RowActions {

	/**
	 * Object type for media.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom media row actions.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_filter( 'media_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'wp_ajax_' . Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ), [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array   $actions Array of existing actions.
	 * @param WP_Post $post    The attachment post object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Post $post ): array {
		return $this->register_actions( $actions, $post, $post->ID );
	}
}
