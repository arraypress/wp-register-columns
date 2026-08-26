<?php
/**
 * Post Row Actions Class
 *
 * Handles custom row action registration for WordPress posts and custom post types.
 * Integrates with WordPress post row action filters.
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
 * Class Post
 *
 * Manages custom row actions for posts in the WordPress admin.
 *
 * @package ArrayPress\RegisterColumns\
 */
class Post extends RowActions {

	/**
	 * Object type for posts.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom post row actions.
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
	 * @param WP_Post $post    The post object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Post $post ): array {
		return $this->register_actions( $actions, $post, $post->ID );
	}
}
