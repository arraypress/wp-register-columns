<?php
/**
 * Screen
 *
 * @package     ArrayPress\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.1.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Support;

/**
 * Which admin screen is a given list table, and are we on it.
 *
 * The one fact all three halves of this library need. Columns ask it to
 * decide whether to print their widths; bulk actions need it to build a hook
 * name, because core's are `bulk_actions-{$screen->id}`; filters need both.
 *
 * It was written three times, once per package, and the three did not quite
 * agree: the taxonomy check matched the single-term edit screen as well as
 * the list, because both carry the taxonomy and only one has columns.
 *
 * The ids are core's own, and none of them is guessable — a post list is
 * `edit-{post_type}` but the media list is `upload`, users is `users` but
 * comments is `edit-comments`. Verified against WP_Screen rather than
 * remembered, and the test says so.
 */
final class Screen {

	/**
	 * The screen id for a list table.
	 *
	 * @param string $object_type    post, user, term, comment or media.
	 * @param string $object_subtype The post type or taxonomy, where there is one.
	 *
	 * @return string The screen id, or an empty string for an unknown type.
	 */
	public static function id( string $object_type, string $object_subtype = '' ): string {
		return match ( $object_type ) {
			'post'    => 'edit-' . $object_subtype,
			'term'    => 'edit-' . $object_subtype,
			'user'    => 'users',
			'comment' => 'edit-comments',
			'media'   => 'upload',
			default   => '',
		};
	}

	/**
	 * Whether the screen being rendered is that list table.
	 *
	 * @param string $object_type    post, user, term, comment or media.
	 * @param string $object_subtype The post type or taxonomy, where there is one.
	 *
	 * @return bool
	 */
	public static function is( string $object_type, string $object_subtype = '' ): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return match ( $object_type ) {
			// Not the screen id: a post list is edit-{type} and so is a term
			// list, so `edit-category` would satisfy both. post_type and base
			// together are unambiguous.
			'post'    => 'edit' === $screen->base && $screen->post_type === $object_subtype,

			// base separates the term list from the single-term edit screen,
			// which is `term` and carries the same taxonomy.
			'term'    => 'edit-tags' === $screen->base && $screen->taxonomy === $object_subtype,

			'user'    => 'users' === $screen->id,
			'comment' => 'edit-comments' === $screen->id,
			'media'   => 'upload' === $screen->id,
			default   => false,
		};
	}

	/**
	 * The object types this library knows about.
	 *
	 * @return string[]
	 */
	public static function types(): array {
		return [ 'post', 'user', 'term', 'comment', 'media' ];
	}
}
