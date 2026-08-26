<?php
/**
 * Comment Row Actions Class
 *
 * Handles custom row action registration for WordPress comments.
 * Integrates with WordPress comment row action filters.
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
use WP_Comment;

/**
 * Class Comment
 *
 * Manages custom row actions for comments in the WordPress admin.
 *
 * @package ArrayPress\RegisterColumns\
 */
class Comment extends RowActions {

	/**
	 * Object type for comments.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'comment';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom comment row actions.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_filter( 'comment_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'wp_ajax_' . Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ), [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array      $actions Array of existing actions.
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Comment $comment ): array {
		return $this->register_actions( $actions, $comment, (int) $comment->comment_ID );
	}
}
