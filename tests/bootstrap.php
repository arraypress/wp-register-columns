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

/* -----------------------------------------------------------------------
 * Row actions
 *
 * These came with wp-register-row-actions when it was absorbed. Its own
 * stubs for the hooks, capabilities and escaping are not here: this file
 * already had them, and where the two disagreed the stricter one stayed --
 * a real add_query_arg over one returning a fixed string, a real
 * did_action over one that always answered 1.
 *
 * The two that came the other way were wp_parse_args, which handles an
 * object, and wp_unslash, which recurses into an array. Both are what core
 * does and neither was here.
 * -------------------------------------------------------------------- */

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$rest ) {
		foreach ( $GLOBALS['ra_filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$rest );
		}
		return $value;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action, $query_arg = false, $die = true ) {
		$given = $_POST[ $query_arg ] ?? '';
		return 'nonce:' . $action === $given;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return (string) $url; }
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $id = 0, $context = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id; }
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return true; }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		$GLOBALS['ra_nonces'][] = (string) $action;
		return 'nonce:' . $action;
	}
}

if ( ! function_exists( 'wp_enqueue_composer_script' ) ) {
	function wp_enqueue_composer_script( $handle, ...$rest ) { $GLOBALS['ra_scripts'][ $handle ] = true; }
}

if ( ! function_exists( 'wp_enqueue_composer_style' ) ) {
	function wp_enqueue_composer_style( $handle, ...$rest ) { $GLOBALS['ra_scripts'][ $handle ] = true; }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['ra_inline'][ $handle ] = array( 'name' => $name, 'data' => $data );
		return true;
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status = 400 ) {
		$GLOBALS['ra_json'][] = array( 'success' => false, 'data' => $data, 'status' => $status );
		throw new RA_Sent();
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status = 200 ) {
		$GLOBALS['ra_json'][] = array( 'success' => true, 'data' => $data, 'status' => $status );
		throw new RA_Sent();
	}
}

if ( ! class_exists( 'RA_Sent' ) ) {
	/**
	 * wp_send_json_*() exits; this stands in for that so a test can continue.
	 *
	 * Extends Error rather than Exception on purpose. handle_ajax() wraps the
	 * callback in catch ( Exception ), so an Exception-based stub is swallowed by
	 * the code under test and reported as a 500 -- which is not what a real exit
	 * does, and made a passing action look like a crashing one.
	 */
	class RA_Sent extends Error {}
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
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) { $args = get_object_vars( $args ); }
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

/*
 * The bulk actions and filters halves reach for a few more. Recorded or
 * given core's real behaviour, as thinly as the tests depend on.
 */
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return (int) ( $GLOBALS['rc_did'][ $hook ] ?? 0 );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) wp_strip_all_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$parts = explode( '?', (string) $url, 2 );
		parse_str( $parts[1] ?? '', $existing );

		return $parts[0] . '?' . http_build_query( array_merge( $existing, (array) $args ) );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';

		if ( $display ) {
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
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
	$GLOBALS['rc_did']    = [];
	$GLOBALS['ra_scripts'] = [];
	$GLOBALS['ra_inline']  = [];
	$GLOBALS['ra_json']    = [];
	$GLOBALS['ra_nonces']  = [];

	// The column registry and the record of which tables have had their
	// hooks attached are both static, which is right in a request and wrong
	// across tests: the second test would find no hooks attached at all,
	// because the first already claimed the table.
	if ( class_exists( 'ArrayPress\\RegisterColumns\\Abstracts\\RowActions' ) ) {
		( new ReflectionProperty( 'ArrayPress\\RegisterColumns\\Abstracts\\RowActions', 'actions' ) )->setValue( null, [] );
		( new ReflectionProperty( 'ArrayPress\\RegisterColumns\\Abstracts\\RowActions', 'assets_enqueued' ) )->setValue( null, false );
	}

	if ( class_exists( 'ArrayPress\\RegisterColumns\\Abstracts\\Columns' ) ) {
		foreach ( [ 'columns', 'hooked' ] as $property ) {
			( new ReflectionProperty( 'ArrayPress\\RegisterColumns\\Abstracts\\Columns', $property ) )->setValue( null, [] );
		}
	}
}
