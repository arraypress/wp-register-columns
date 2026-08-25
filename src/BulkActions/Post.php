<?php
/**
 * Post Bulk Actions
 *
 * @package     ArrayPress\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.1.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\BulkActions;

use ArrayPress\RegisterColumns\Abstracts\BulkActions;
use ArrayPress\RegisterColumns\Support\Screen;

/**
 * Custom bulk actions on a post type's list table.
 */
class Post extends BulkActions {

	/**
	 * The object type these actions are for.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Attach to the two hooks a bulk action needs.
	 *
	 * Both are named for the screen — core builds them as
	 * `bulk_actions-{$screen->id}` and `handle_bulk_actions-{$screen->id}` —
	 * which is why the screen id is worked out in one place rather than
	 * spelled out per table.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		$screen = Screen::id( $this->object_type, $this->object_subtype );

		add_filter( "bulk_actions-{$screen}", [ $this, 'register_bulk_actions' ] );
		add_filter( "handle_bulk_actions-{$screen}", [ $this, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'display_admin_notice' ] );
	}
}
