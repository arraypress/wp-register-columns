<?php
/**
 * Runtime Key Derivation
 *
 * @package     ArrayPress\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Utils;

/**
 * Every runtime string this library registers, derived from its own namespace.
 *
 * Strauss rewrites class namespaces and leaves string literals alone. Two
 * plugins each bundling a prefixed copy of this library therefore get
 * distinct classes but would otherwise register identical script handles and
 * identical transient keys.
 *
 * That is not merely untidy. `wp_enqueue_style()` ignores a handle that is
 * already registered, so the plugin that enqueued second gets the other
 * plugin's stylesheet — from a directory its own build may not even contain.
 * And a shared transient means one plugin's activation sends the user to the
 * other plugin's wizard.
 *
 * The derivation exploits the one thing Strauss does rewrite: this file's
 * namespace. In a prefixed build `__NAMESPACE__` begins with the consumer's
 * prefix ("MyPlugin\ArrayPress\RegisterColumns\Utils"), unique per plugin
 * by construction, so every key comes out distinct with no configuration.
 */
final class Runtime {

	/**
	 * This library's own identifier, used when running unprefixed.
	 */
	private const LIBRARY = 'row-actions';

	/**
	 * The per-build prefix.
	 *
	 * "row-actions" for a plain Composer install — development, or a single
	 * consumer that does not use Strauss — and "{prefix}-row-actions" for a
	 * prefixed build.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		return self::prefix_for( __NAMESPACE__ );
	}

	/**
	 * The prefix a given namespace would produce.
	 *
	 * Split out so the rule can be tested. prefix() reads __NAMESPACE__,
	 * which cannot be changed at runtime, so a test can only reach the
	 * prefixed case by asking the rule directly — this takes the namespace
	 * as an argument, and prefix() hands it __NAMESPACE__.
	 *
	 * The alternative, compiling a copy of this file under another namespace
	 * at runtime, was tried and abandoned: the interpreter that does it
	 * refuses the `declare( strict_types=1 )` at the top of this file on PHP
	 * 8.3 and 8.4, so the test passed on 8.5 alone and reported the local PHP
	 * version rather than the code. It is also a construct that has no place
	 * in a file that ships inside somebody's plugin.
	 *
	 * @param string $under The namespace this class is compiled under, prefixed or not.
	 *
	 * @return string
	 */
	public static function prefix_for( string $under ): string {
		$root = explode( '\\', $under )[0] ?? '';

		if ( '' === $root || 'ArrayPress' === $root ) {
			return self::LIBRARY;
		}

		return self::slug( $root ) . '-' . self::LIBRARY;
	}

	/**
	 * A script or style handle for this build.
	 *
	 * @param string $suffix Optional handle suffix.
	 *
	 * @return string
	 */
	public static function handle( string $suffix = '' ): string {
		return '' === $suffix ? self::prefix() : self::prefix() . '-' . $suffix;
	}

	/**
	 * An option or transient key for this build.
	 *
	 * @param string $suffix Optional key suffix.
	 *
	 * @return string
	 */
	public static function key( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() );

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}

	/**
	 * The admin-ajax action name for this build.
	 *
	 * The collision this avoids is the loud kind, unusually. Two prefixed
	 * copies both hook `wp_ajax_row_action_post_product`; add_action() does
	 * not replace, so both handlers run. Whichever runs first looks the
	 * action key up in *its* registry, does not find the other plugin's
	 * action, and calls wp_send_json_error() -- which exits. The right
	 * handler never gets the request.
	 *
	 * @param string $suffix What follows the action name.
	 *
	 * @return string
	 */
	public static function ajax_action( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() ) . '_action';

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}


	/**
	 * Reduce a namespace segment to a lowercase slug.
	 *
	 * Not sanitize_title(): this runs from `__NAMESPACE__` at class-load
	 * time, which can precede WordPress being fully loaded.
	 *
	 * @param string $value Value to slug.
	 *
	 * @return string
	 */
	private static function slug( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9]+/', '-', $value ) ?? '';

		return strtolower( trim( $value, '-' ) );
	}
}
