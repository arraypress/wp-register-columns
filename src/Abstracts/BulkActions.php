<?php
/**
 * Bulk Actions Abstract Class
 *
 * @package     ArrayPress\WP\RegisterBulkActions
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Abstracts;

use Exception;

abstract class BulkActions {

	/**
	 * Object type constant (e.g., 'post', 'user', 'term', 'comment', 'attachment')
	 */
	protected const OBJECT_TYPE = '';

	/**
	 * Object type
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype (e.g., post type, taxonomy)
	 *
	 * @var string
	 */
	protected string $object_subtype;

	/**
	 * Registered bulk actions storage
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	protected static array $actions = [];

	/**
	 * BulkActions constructor.
	 *
	 * @param array  $actions        Bulk actions configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 *
	 * @throws Exception If OBJECT_TYPE is not defined.
	 */
	public function __construct( array $actions, string $object_subtype ) {
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->add_actions( $actions );

		// Registered now rather than deferred to admin_init. add_filter() only
		// has to happen before the hook fires, and deferring introduced a
		// case that then had to be guarded against — something registering
		// *during* admin_init would attach to a hook that had already run and
		// never load at all. The columns half never deferred, and now that
		// these are one library they should not differ on something this
		// basic.
		$this->load_hooks();
	}

	/**
	 * Add bulk actions to the registry.
	 *
	 * @param array $actions Bulk actions configuration.
	 *
	 * @return void
	 * @throws Exception If an action key is invalid.
	 */
	public function add_actions( array $actions ): void {
		$default_action = [
			'label'      => '',
			'callback'   => null,
			'capability' => 'manage_options',
		];

		foreach ( $actions as $key => $action ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid action key. It must be a non-empty string.' );
			}

			self::$actions[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $action, $default_action );
		}
	}

	/**
	 * Get all registered actions for a specific object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array<string, mixed> Registered actions.
	 */
	public static function get_actions( string $object_type, string $object_subtype ): array {
		return self::$actions[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Load the necessary hooks for bulk actions.
	 *
	 * @return void
	 */
	abstract public function load_hooks(): void;

	/**
	 * Register bulk actions in the dropdown.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 *
	 * @return array Modified bulk actions.
	 */
	public function register_bulk_actions( array $bulk_actions ): array {
		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		foreach ( $actions as $key => $action ) {
			if ( ! empty( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
				continue;
			}

			// Escaped here, because core does not. WP_List_Table::bulk_actions()
			// escapes the option's value and echoes the label raw
			// (class-wp-list-table.php:47), so a label built from anything a
			// user typed lands in the page as markup. The columns half has
			// always escaped its labels; this one did not.
			$bulk_actions[ $key ] = esc_html( (string) $action['label'] );
		}

		return $bulk_actions;
	}

	/**
	 * Handle bulk action execution.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Current action.
	 * @param array  $object_ids  Selected object IDs.
	 *
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_action( string $redirect_to, string $doaction, array $object_ids ): string {
		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		// Check if this is our action
		if ( ! isset( $actions[ $doaction ] ) ) {
			return $redirect_to;
		}

		$action = $actions[ $doaction ];

		// Check capability
		if ( ! empty( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
			return $redirect_to;
		}

		// Check for callback
		if ( empty( $action['callback'] ) || ! is_callable( $action['callback'] ) ) {
			return $redirect_to;
		}

		$object_ids = array_map( 'intval', $object_ids );

		try {
			// Execute callback
			$result = call_user_func( $action['callback'], $object_ids );

			// Handle response
			if ( is_array( $result ) ) {
				if ( isset( $result['message'] ) ) {
					$redirect_to = add_query_arg( 'bulk_action_message', urlencode( $result['message'] ), $redirect_to );
				}
				if ( isset( $result['success'] ) && ! $result['success'] ) {
					$redirect_to = add_query_arg( 'bulk_action_error', '1', $redirect_to );
				}
			}

			// Add count
			$redirect_to = add_query_arg( 'bulk_action_count', count( $object_ids ), $redirect_to );
			$redirect_to = add_query_arg( 'bulk_action_done', $doaction, $redirect_to );

		} catch ( Exception $e ) {
			$redirect_to = add_query_arg( [
				'bulk_action_error'   => '1',
				'bulk_action_message' => urlencode( $e->getMessage() ),
			], $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Display admin notice after bulk action.
	 *
	 * @return void
	 */
	public function display_admin_notice(): void {
		if ( empty( $_GET['bulk_action_done'] ) ) {
			return;
		}

		$action_key = sanitize_key( $_GET['bulk_action_done'] );
		$count      = isset( $_GET['bulk_action_count'] ) ? absint( $_GET['bulk_action_count'] ) : 0;
		$is_error   = isset( $_GET['bulk_action_error'] );
		// Sanitized as well as escaped at output. urldecode alone lets a
		// message carry newlines and control characters into a notice; they
		// cannot break out of it, but they are not a message either.
		$message = isset( $_GET['bulk_action_message'] )
			? sanitize_text_field( wp_unslash( $_GET['bulk_action_message'] ) )
			: '';

		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		if ( ! isset( $actions[ $action_key ] ) ) {
			return;
		}

		// Default message if none provided
		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: how many items the bulk action processed */
				_n( '%d item processed.', '%d items processed.', $count, 'arraypress' ),
				$count
			);
		}

		$class = $is_error ? 'error' : 'success';

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
