<?php
/**
 * Base Row Actions Class
 *
 * A lightweight class designed to simplify the registration of custom row actions in WordPress.
 * Supports static URLs, dynamic URL generation via callbacks, and AJAX-powered actions with
 * automatic security handling.
 *
 * @package     ArrayPress\RegisterColumns\
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Abstracts;

use ArrayPress\RegisterColumns\Utils\Runtime;

use ArrayPress\ArrayUtils\Arr;
use Exception;

/**
 * Class RowActions
 *
 * Base class for registering custom row actions in WordPress.
 *
 * @package ArrayPress\RegisterColumns\
 */
abstract class RowActions {

	/**
	 * Object type constant that must be defined by child classes.
	 *
	 * Examples: 'post', 'user', 'term', 'comment'
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = '';

	/**
	 * Array of custom action configurations.
	 *
	 * @var array
	 */
	protected static array $actions = [];

	/**
	 * Object type for the current instance.
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype for the current instance (e.g., post type, taxonomy).
	 *
	 * @var string
	 */
	protected string $object_subtype;

	/**
	 * Array of action keys to remove from being registered.
	 *
	 * @var array
	 */
	protected array $keys_to_remove = [];

	/**
	 * Whether assets have been enqueued.
	 *
	 * @var bool
	 */
	protected static bool $assets_enqueued = false;

	/**
	 * RowActions constructor.
	 *
	 * @param array  $actions        Custom actions configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 * @param array  $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @throws Exception If an action key is invalid or OBJECT_TYPE is not defined.
	 */
	public function __construct( array $actions, string $object_subtype, array $keys_to_remove = [] ) {
		// Validate that child class defined OBJECT_TYPE
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->set_keys_to_remove( $keys_to_remove );
		$this->add_actions( $actions );

		// Load hooks immediately if already in admin, otherwise wait for admin_init
		if ( did_action( 'admin_init' ) ) {
			$this->load_hooks();
		} else {
			add_action( 'admin_init', [ $this, 'load_hooks' ] );
		}
	}

	/**
	 * Set the array of action keys to remove from being registered.
	 *
	 * @param array $keys Array of action keys to remove.
	 *
	 * @return void
	 */
	public function set_keys_to_remove( array $keys ): void {
		$this->keys_to_remove = $keys;
	}

	/**
	 * Add new actions to the existing configuration.
	 *
	 * @param array $actions Custom actions configuration.
	 *
	 * @return void
	 * @throws Exception If an action key is invalid.
	 */
	public function add_actions( array $actions ): void {
		$default_action = [
			'label'               => '',
			'label_callback'      => null,
			'url'                 => '',
			'url_callback'        => null,
			'ajax'                => false,
			'callback'            => null,
			'position'            => '',
			'permission_callback' => null,
			'capability'          => 'manage_options',
			'confirm'             => '',
			'class'               => '',
			'target'              => '',
			'icon'                => '',
		];

		foreach ( $actions as $key => $action ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid action key provided. It must be a non-empty string.' );
			}

			self::$actions[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $action, $default_action );
		}
	}

	/**
	 * Get actions array for the given object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array
	 */
	public static function get_actions( string $object_type, string $object_subtype ): array {
		return self::$actions[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Get the configuration for a specific action by name.
	 *
	 * @param string $action_name    The name of the action.
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array|null The action configuration if exists, null otherwise.
	 */
	public function get_action_by_name( string $action_name, string $object_type, string $object_subtype ): ?array {
		$actions = self::get_actions( $object_type, $object_subtype );

		return $actions[ $action_name ] ?? null;
	}

	/**
	 * Register custom row actions.
	 *
	 * @param array $actions   Array of existing actions.
	 * @param mixed $item    The object (post, user, term, comment).
	 * @param int   $object_id The object ID.
	 *
	 * @return array Updated array of actions with custom actions.
	 */
	public function register_actions( array $actions, $item, int $object_id ): array {
		$custom_actions = self::get_actions( $this->object_type, $this->object_subtype );

		// Remove specified keys from existing actions
		$actions = $this->remove_keys_from_actions( $actions );

		foreach ( $custom_actions as $key => $action ) {
			if ( ! $this->check_action_permission( $action, $object_id ) ) {
				continue;
			}

			$action_html = $this->get_action_link( $action, $object_id );
			$position    = $action['position'];

			if ( str_starts_with( $position, 'after:' ) ) {
				$reference_action = str_replace( 'after:', '', $position );
				$actions          = Arr::insert_after( $actions, $reference_action, [ $key => $action_html ] );
			} elseif ( str_starts_with( $position, 'before:' ) ) {
				$reference_action = str_replace( 'before:', '', $position );
				$actions          = Arr::insert_before( $actions, $reference_action, [ $key => $action_html ] );
			} else {
				$actions[ $key ] = $action_html;
			}
		}

		return $actions;
	}

	/**
	 * Remove specified keys from the actions array.
	 *
	 * @param array $actions Array of existing actions.
	 *
	 * @return array The actions array with specified keys removed.
	 */
	protected function remove_keys_from_actions( array $actions ): array {
		foreach ( $this->keys_to_remove as $key ) {
			if ( array_key_exists( $key, $actions ) ) {
				unset( $actions[ $key ] );
			}
		}

		return $actions;
	}

	/**
	 * Generate the action link HTML.
	 *
	 * @param array $action    The action configuration.
	 * @param int   $object_id The object ID.
	 *
	 * @return string The action link HTML.
	 */
	protected function get_action_link( array $action, int $object_id ): string {
		// Use label_callback if provided, otherwise use static label
		if ( ! empty( $action['label_callback'] ) && is_callable( $action['label_callback'] ) ) {
			$label = esc_html( call_user_func( $action['label_callback'], $object_id ) );
		} else {
			$label = esc_html( $action['label'] );
		}

		if ( $action['ajax'] ) {
			return $this->get_ajax_action_link( $action, $object_id, $label );
		}

		return $this->get_url_action_link( $action, $object_id, $label );
	}

	/**
	 * Generate an AJAX action link.
	 *
	 * @param array  $action    The action configuration.
	 * @param int    $object_id The object ID.
	 * @param string $label     The action label.
	 *
	 * @return string The AJAX action link HTML.
	 */
	protected function get_ajax_action_link( array $action, int $object_id, string $label ): string {
		$action_key = array_search( $action, self::$actions[ $this->object_type ][ $this->object_subtype ], true );
		$nonce      = wp_create_nonce( Runtime::key( "{$this->object_type}_{$this->object_subtype}_{$action_key}_{$object_id}" ) );
		$class      = 'row-action-ajax ' . esc_attr( $action['class'] );

		$icon_html = '';
		if ( ! empty( $action['icon'] ) ) {
			$icon_html = sprintf( '<span class="dashicons dashicons-%s"></span> ', esc_attr( $action['icon'] ) );
		}

		// The admin-ajax action goes on the link rather than into a shared
		// JavaScript object. Each prefixed copy has a different action name
		// and they all load the same script file under different handles, so
		// a shared global would be owned by whichever localised last -- and
		// every other copy's links would dispatch to the wrong plugin.
		return sprintf(
			'<a href="#" class="%s" data-ajax-action="%s" data-action-key="%s" data-object-id="%s" data-nonce="%s" data-confirm="%s">%s%s</a>',
			$class,
			esc_attr( Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ) ),
			esc_attr( $action_key ),
			esc_attr( $object_id ),
			esc_attr( $nonce ),
			esc_attr( $action['confirm'] ),
			$icon_html,
			$label
		);
	}

	/**
	 * Generate a URL-based action link.
	 *
	 * @param array  $action    The action configuration.
	 * @param int    $object_id The object ID.
	 * @param string $label     The action label.
	 *
	 * @return string The URL action link HTML.
	 */
	protected function get_url_action_link( array $action, int $object_id, string $label ): string {
		if ( is_callable( $action['url_callback'] ) ) {
			$url = call_user_func( $action['url_callback'], $object_id );
		} else {
			$url = ! empty( $action['url'] ) ? add_query_arg( 'id', $object_id, $action['url'] ) : '#';
		}

		$target = ! empty( $action['target'] ) ? sprintf( ' target="%s"', esc_attr( $action['target'] ) ) : '';
		$class  = ! empty( $action['class'] ) ? esc_attr( $action['class'] ) : '';

		$icon_html = '';
		if ( ! empty( $action['icon'] ) ) {
			$icon_html = sprintf( '<span class="dashicons dashicons-%s"></span> ', esc_attr( $action['icon'] ) );
		}

		return sprintf(
			'<a href="%s" class="%s"%s>%s%s</a>',
			esc_url( $url ),
			$class,
			$target,
			$icon_html,
			$label
		);
	}

	/**
	 * Check action permission.
	 *
	 * @param array $action    The action configuration.
	 * @param int   $object_id The object ID.
	 *
	 * @return bool True if permission is granted, false otherwise.
	 */
	protected function check_action_permission( array $action, int $object_id ): bool {
		// Check capability first
		if ( ! current_user_can( $action['capability'] ) ) {
			return false;
		}

		// Check custom permission callback
		if ( isset( $action['permission_callback'] ) && is_callable( $action['permission_callback'] ) ) {
			return call_user_func( $action['permission_callback'], $object_id );
		}

		return true;
	}

	/**
	 * Handle AJAX request.
	 *
	 * @return void
	 */
	public function handle_ajax(): void {
		// Get parameters
		// Unslashed before anything reads them. WordPress slashes the request
		// superglobals on load, so a quoted value arrives carrying backslashes
		// that were never sent -- and json_decode() refuses the result.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked below, once the action is known.
		$action_key = sanitize_key( wp_unslash( $_POST['action_key'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
		$object_id = absint( wp_unslash( $_POST['object_id'] ?? 0 ) );
		// json_decode() is the sanitizer here: it either produces a structure
		// or it does not, and what comes out is checked below. Running the
		// JSON through a text sanitizer first would corrupt it.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- as above; json_decode validates.
		$decoded = json_decode( (string) wp_unslash( $_POST['options'] ?? '{}' ), true );

		// json_decode() returns null on a parse failure and false for the
		// literal `false`, and `?? []` catches only the first -- so a
		// malformed body used to reach the callback as a boolean where an
		// array was expected.
		$options = is_array( $decoded ) ? $decoded : [];

		// Get action configuration
		$action = $this->get_action_by_name( $action_key, $this->object_type, $this->object_subtype );

		// Verify action exists
		if ( ! $action ) {
			wp_send_json_error( __( 'Invalid action', 'arraypress' ), 400 );
		}

		// Verify nonce
		$nonce_action = Runtime::key( "{$this->object_type}_{$this->object_subtype}_{$action_key}_{$object_id}" );
		if ( ! check_ajax_referer( $nonce_action, '_wpnonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token', 'arraypress' ), 403 );
		}

		// Check permissions
		if ( ! $this->check_action_permission( $action, $object_id ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'arraypress' ), 403 );
		}

		// Verify callback is callable
		if ( ! is_callable( $action['callback'] ) ) {
			wp_send_json_error( __( 'Invalid callback function', 'arraypress' ), 500 );
		}

		try {
			// Call the callback
			$result = call_user_func( $action['callback'], $object_id, $options );

			// Ensure result is an array
			if ( ! is_array( $result ) ) {
				$result = [ 'success' => true ];
			}

			// Add default message if not provided
			if ( ! isset( $result['message'] ) ) {
				$result['message'] = __( 'Action completed successfully', 'arraypress' );
			}

			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Enqueue assets for AJAX actions.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue once
		if ( self::$assets_enqueued ) {
			return;
		}

		// Only enqueue if we have AJAX actions
		$has_ajax_actions = false;
		foreach ( self::get_actions( $this->object_type, $this->object_subtype ) as $action ) {
			if ( ! empty( $action['ajax'] ) ) {
				$has_ajax_actions = true;
				break;
			}
		}

		if ( ! $has_ajax_actions ) {
			return;
		}

		$version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : '1.0.0';

		// Enqueue JavaScript using Composer Assets helper
		wp_enqueue_composer_script(
			Runtime::handle(),
			__FILE__,
			'js/row-actions.js',
			[ 'jquery' ],
			$version
		);

		// Localize script
		wp_localize_script(
			Runtime::handle(),
			'rowActionsConfig',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),

				'strings' => [
					'processing' => __( 'Processing...', 'arraypress' ),
					'error'      => __( 'Error', 'arraypress' ),
					'success'    => __( 'Success', 'arraypress' ),
				],
			]
		);

		// Enqueue CSS using Composer Assets helper
		wp_enqueue_composer_style(
			Runtime::handle(),
			__FILE__,
			'css/row-actions.css',
			[],
			$version
		);

		self::$assets_enqueued = true;
	}

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding and handling custom row actions.
	 *
	 * @return void
	 */
	abstract public function load_hooks(): void;
}
