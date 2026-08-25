<?php
/**
 * PHPUnit bootstrap.
 *
 * This is a library, not a plugin: there is no WordPress to load. The handful
 * of WordPress functions the testable paths touch are stubbed here, kept as
 * close to core's real behaviour as the tests depend on and no closer.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Hooks are recorded rather than run, so a test can ask what the library
 * asked WordPress for. A column library is mostly hooks: registering one is
 * almost entirely a matter of attaching the right callback to the right
 * filter, and a wrong hook name is invisible until somebody loads the screen.
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rc_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rc_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );

		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

/*
 * $GLOBALS['rc_caps'] is what the current user may do. Null means everything,
 * which keeps every test that is not about permissions from having to care.
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$allowed = $GLOBALS['rc_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['post'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['user'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['term'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	function get_comment_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['comment'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return $GLOBALS['rc_screen'] ?? null;
	}
}

/**
 * Forget everything a previous test set up.
 *
 * @return void
 */
function rc_reset_globals(): void {
	$GLOBALS['rc_hooks'] = [];
	$GLOBALS['rc_meta']  = [];
	$GLOBALS['rc_caps']  = null;
	$GLOBALS['rc_screen'] = null;
}
