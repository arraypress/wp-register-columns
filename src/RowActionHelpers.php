<?php
/**
 * Row Actions Helper Functions
 *
 * Common utility functions for row actions.
 *
 * @package ArrayPress\RegisterColumns\
 */

if ( ! function_exists( 'get_toggle_label' ) ) :
	/**
	 * Get toggle label based on meta value
	 *
	 * @param string $object_type    Object type (post, comment, user, term)
	 * @param int    $object_id      Object ID
	 * @param string $meta_key       Meta key to check
	 * @param string $active_label   Label when active (e.g., 'Unfeatured')
	 * @param string $inactive_label Label when inactive (e.g., 'Mark Featured')
	 *
	 * @return string The appropriate label
	 */
	function get_toggle_label( string $object_type, int $object_id, string $meta_key, string $active_label, string $inactive_label ): string {
		$is_active = false;

		switch ( $object_type ) {
			case 'post':
			case 'attachment':
				$is_active = get_post_meta( $object_id, $meta_key, true );
				break;
			case 'comment':
				$is_active = get_comment_meta( $object_id, $meta_key, true );
				break;
			case 'user':
				$is_active = get_user_meta( $object_id, $meta_key, true );
				break;
			case 'term':
				$is_active = get_term_meta( $object_id, $meta_key, true );
				break;
		}

		return $is_active ? $active_label : $inactive_label;
	}
endif;

if ( ! function_exists( 'toggle_meta' ) ) :
	/**
	 * Toggle meta value
	 *
	 * @param string $object_type Object type (post, comment, user, term)
	 * @param int    $object_id   Object ID
	 * @param string $meta_key    Meta key to toggle
	 *
	 * @return bool New status (true if now active, false if now inactive)
	 */
	function toggle_meta( string $object_type, int $object_id, string $meta_key ): bool {
		$is_active  = false;
		$new_status = true;

		switch ( $object_type ) {
			case 'post':
			case 'attachment':
				$is_active  = get_post_meta( $object_id, $meta_key, true );
				$new_status = ! $is_active;
				update_post_meta( $object_id, $meta_key, $new_status );
				break;
			case 'comment':
				$is_active  = get_comment_meta( $object_id, $meta_key, true );
				$new_status = ! $is_active;
				update_comment_meta( $object_id, $meta_key, $new_status );
				break;
			case 'user':
				$is_active  = get_user_meta( $object_id, $meta_key, true );
				$new_status = ! $is_active;
				update_user_meta( $object_id, $meta_key, $new_status );
				break;
			case 'term':
				$is_active  = get_term_meta( $object_id, $meta_key, true );
				$new_status = ! $is_active;
				update_term_meta( $object_id, $meta_key, $new_status );
				break;
		}

		return $new_status;
	}
endif;

if ( ! function_exists( 'get_ajax_url' ) ) :
	/**
	 * Get AJAX URL with nonce
	 *
	 * @param string $action Action name
	 * @param int    $id     Object ID
	 * @param array  $args   Additional query args
	 *
	 * @return string AJAX URL with nonce
	 */
	function get_ajax_url( string $action, int $id, array $args = [] ): string {
		$default_args = [
			'action' => $action,
			'id'     => $id,
			'nonce'  => wp_create_nonce( "{$action}_{$id}" ),
		];

		$args = array_merge( $default_args, $args );

		return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
	}
endif;
