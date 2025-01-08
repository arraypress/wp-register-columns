<?php
/**
 * A robust class designed to simplify the registration of custom columns in WordPress.
 *
 * This class streamlines the process of setting up custom columns for different metadata types
 * (e.g., 'user', 'post'), including their labels, capabilities, and supports. It provides a
 * structured and extendable approach to declaring new columns, ensuring consistency and reducing
 * boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom columns with minimal code.
 * - Customizable column labels for enhanced admin UI integration.
 * - Supports sortable columns with text or numeric sorting.
 * - Inline editing support for specific columns.
 * - Enforces best practices by setting public visibility flags appropriately.
 * - Handles AJAX requests for inline editing and metadata updates.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Columns;

use ArrayPress\WP\Register\Columns\Utils\Helpers;
use Exception;
use WP_Query;
use WP_User_Query;

/**
 * RegisterColumns class for custom columns.
 */
class Columns {

	/**
	 * @var array $columns Array of custom column configurations grouped by object type.
	 */
	protected static array $columns = [];

	/**
	 * @var string $object_type Object type (e.g., 'user', 'post').
	 */
	protected string $object_type;

	/**
	 * @var string $object_subtype Object subtype (e.g., 'page', 'post').
	 */
	protected string $object_subtype;

	/**
	 * @var string|null $custom_filter Custom filter to use in hooks.
	 */
	protected ?string $custom_filter;

	/**
	 * @var array $keys_to_remove Array of column keys to remove from being registered.
	 */
	protected array $keys_to_remove = [];

	/**
	 * Array of types and their corresponding to sanitize callbacks.
	 *
	 * @var array $sanitize_callbacks
	 */
	protected static array $sanitize_callbacks = [
		'number'   => 'floatval',
		'email'    => 'sanitize_email',
		'url'      => 'esc_url_raw',
		'textarea' => 'sanitize_textarea_field',
		'text'     => 'sanitize_text_field',
	];

	/**
	 * RegisterColumns constructor.
	 *
	 * @param array  $columns        Custom columns configuration.
	 * @param string $object_type    Metadata type (e.g., 'user', 'post').
	 * @param string $object_subtype Metadata subtype (e.g., 'page').
	 * @param array  $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @throws Exception If a column key is invalid.
	 */
	public function __construct( array $columns, string $object_type, string $object_subtype, array $keys_to_remove = [] ) {
		$this->set_object_type( $object_type );
		$this->set_object_subtype( $object_subtype );
		$this->set_keys_to_remove( $keys_to_remove );
		$this->add_columns( $columns );
	}

	/**
	 * Set metadata type for the current context.
	 *
	 * @param string $object_type Metadata type.
	 *
	 * @return void
	 */
	protected function set_object_type( string $object_type ): void {
		$this->object_type = $object_type;
	}

	/**
	 * Set metadata type for the current context.
	 *
	 * @param string $object_subtype Metadata type.
	 *
	 * @return void
	 */
	protected function set_object_subtype( string $object_subtype ): void {
		$this->object_subtype = $object_subtype;
	}

	/**
	 * Set the custom filter.
	 *
	 * @param string|null $custom_filter The custom filter to use in hooks.
	 *
	 * @return void
	 */
	public function set_custom_filter( ?string $custom_filter ): void {
		$this->custom_filter = $custom_filter;
	}

	/**
	 * Set the array of column keys to remove from being registered.
	 *
	 * @param array $keys Array of column keys to remove.
	 *
	 * @return void
	 */
	public function set_keys_to_remove( array $keys ): void {
		$this->keys_to_remove = $keys;
	}

	/**
	 * Add new columns to the existing configuration.
	 *
	 * @param array $columns Custom columns configuration.
	 *
	 * @return void
	 * @throws Exception If a column key is invalid.
	 */
	public function add_columns( array $columns ): void {
		$default_column = [
			'label'                => '',
			'meta_key'             => '',
			'object_type'          => null,
			'object_subtype'       => null,
			'position'             => '',
			'sortable'             => false,
			'numeric'              => false,
			'inline_edit'          => false,
			'inline_attributes'    => [ 'type' => 'text' ],
			'display_callback'     => null,
			'permission_callback'  => null,
			'update_meta_callback' => null,
			'delete_meta_callback' => null,
			'sanitize_callback'    => null,
			'width'                => null, // New width property
		];

		foreach ( $columns as $key => $column ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid column key provided. It must be a non-empty string.' );
			}

			self::$columns[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $column, $default_column );
		}

		if ( $this->has_inline_editing_columns() ) {
			$this->load_ajax_hooks();
		}

		$this->add_column_filters();
	}

	/**
	 * Add filters to manage columns and content.
	 */
	protected function add_column_filters(): void {
		add_action( 'admin_head', [ $this, 'add_custom_column_styles' ] );
	}

	/**
	 * Get columns array for the given metadata type and subtype.
	 *
	 * @param string $object_type    Metadata type.
	 * @param string $object_subtype Metadata subtype.
	 *
	 * @return array
	 */
	public static function get_columns( string $object_type, string $object_subtype ): array {
		return self::$columns[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Get the configuration for a specific column by name.
	 *
	 * @param string $column_name    The name of the column.
	 * @param string $object_type    Metadata type (e.g., 'user', 'post').
	 * @param string $object_subtype Metadata subtype.
	 *
	 * @return array|null The column configuration if exists, null otherwise.
	 */
	public function get_column_by_name( string $column_name, string $object_type, string $object_subtype ): ?array {
		$columns = self::get_columns( $object_type, $object_subtype );

		return $columns[ $column_name ] ?? null;
	}

	/**
	 * Register custom columns with their labels.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array Updated array of columns with custom columns.
	 */
	public function register_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		// Remove specified keys from existing columns
		$columns = $this->remove_keys_from_columns( $columns );

		foreach ( $custom_columns as $key => $column ) {
			if ( ! $this->check_column_permission( $column ) ) {
				continue;
			}

			$position         = $column['position'];
			$reference_column = str_replace( [ 'before:', 'after:' ], '', $position );
			$label            = esc_html( $column['label'] );

			if ( str_starts_with( $position, 'after:' ) ) {
				$columns = Helpers::insert_after( $columns, $reference_column, [ $key => $label ] );
			} elseif ( str_starts_with( $position, 'before:' ) ) {
				$columns = Helpers::insert_before( $columns, $reference_column, [ $key => $label ] );
			} else {
				$columns[ $key ] = $label;
			}
		}

		return $columns;
	}

	/**
	 * Remove specified keys from the columns array.
	 *
	 * This method verifies if the keys exist before attempting to remove them.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array The columns array with specified keys removed.
	 */
	protected function remove_keys_from_columns( array $columns ): array {
		foreach ( $this->keys_to_remove as $key ) {
			if ( array_key_exists( $key, $columns ) ) {
				unset( $columns[ $key ] );
			}
		}

		return $columns;
	}

	/**
	 * Register custom columns as sortable.
	 *
	 * @param array $columns Array of existing sortable columns.
	 *
	 * @return array Updated array of sortable columns with custom columns.
	 */
	public function register_sortable_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		foreach ( $custom_columns as $key => $column ) {
			if ( $column['sortable'] ) {
				$columns[ $key ] = [ $key, $column['numeric'] ?? false ];
			}
		}

		return $columns;
	}

	/**
	 * Add custom column styles.
	 */
	public function add_custom_column_styles(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		echo '<style>';
		foreach ( self::get_columns( $this->object_type, $this->object_subtype ) as $key => $column ) {
			if ( ! empty( $column['width'] ) ) {
				$width = esc_attr( $column['width'] );
				echo ".column-$key { width: {$width}; }";
			}
		}
		echo '</style>';
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the correct screen, false otherwise.
	 */
	protected function is_screen(): bool {
		$screen = get_current_screen();

		return (
			( $screen->base === 'edit' && $screen->post_type === $this->object_subtype ) ||
			( $screen->id === $this->object_subtype ) ||
			( str_replace( 'edit-', '', $screen->id ) === $this->object_subtype )
		);
	}

	/**
	 * Render the custom column content.
	 *
	 * This wrapper function is used to render the custom column content for posts.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $id          The ID of the current item.
	 *
	 * @return void
	 */
	public function render_column_content_wrapper( string $column_name, int $id ): void {
		echo $this->render_column_content( '', $column_name, $id );
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param int    $id          The ID of the current item.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, int $id ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type, $this->object_subtype );

		if ( ! $column ) {
			return $value;
		}

		$object_type    = $this->get_column_object_type( $column );
		$object_subtype = $this->get_column_object_subtype( $column );

		if ( empty( $value ) ) {
			$value = get_metadata( $object_type, $id, $column['meta_key'], true );
		}

		$raw_value = ! empty( $value ) ? $value : '';
		$content   = $this->render_custom_column_content( $id, $column, $object_type );

		if ( $column['inline_edit'] ) {
			$nonce      = wp_create_nonce( 'inline_edit_' . $id . '_' . $column_name . '_' . $object_type . '_' . $object_subtype );
			$attributes = $this->build_inline_attributes( $column );
			$content    = sprintf(
				'<span class="inline-editable" data-column-name="%1$s" data-object-id="%2$d" data-raw-value="%3$s" data-object-type="%4$s" data-object-subtype="%5$s" data-nonce="%6$s" data-inline-attributes="%7$s">%8$s</span>',
				esc_attr( $column_name ),
				esc_attr( $id ),
				esc_attr( $raw_value ),
				esc_attr( $object_type ),
				esc_attr( $object_subtype ),
				esc_attr( $nonce ),
				esc_attr( $attributes ),
				wp_kses_post( $content )
			);
		}

		return $content;
	}

	/**
	 * Get the object type for a specific column.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return string The object type.
	 */
	protected function get_column_object_type( array $column ): string {
		return $column['object_type'] ?? $this->object_type;
	}

	/**
	 * Get the object type for a specific column.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return string The object sub type.
	 */
	protected function get_column_object_subtype( array $column ): string {
		return $column['object_subtype'] ?? $this->object_subtype;
	}

	/**
	 * Build the inline attributes string for the input element.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return string The JSON-encoded attributes string.
	 */
	protected function build_inline_attributes( array $column ): string {
		$attributes         = $column['inline_attributes'] ?? [];
		$attributes['type'] = $attributes['type'] ?? 'text'; // Ensure type is always set

		return htmlspecialchars( json_encode( $attributes, JSON_HEX_APOS | JSON_HEX_QUOT ) );
	}

	/**
	 * Render the content for a custom column.
	 *
	 * @param int    $id          The ID of the current item.
	 * @param array  $column      The configuration array of the current column.
	 * @param string $object_type Object type (e.g., 'user', 'post').
	 *
	 * @return string The rendered content.
	 */
	private function render_custom_column_content( int $id, array $column, string $object_type ): string {
		$meta_key = $column['meta_key'] ?? '';
		$value    = get_metadata( $object_type, $id, $meta_key, true );

		if ( is_callable( $column['display_callback'] ) ) {
			return (string) call_user_func( $column['display_callback'], $value, $id, $column );
		}

		if ( empty( $value ) ) {
			$value = '–';
		}

		return esc_html( $value );
	}

	/**
	 * Check if any column has inline editing enabled.
	 *
	 * @return bool
	 */
	private function has_inline_editing_columns(): bool {
		foreach ( self::get_columns( $this->object_type, $this->object_subtype ) as $column ) {
			if ( $column['inline_edit'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort the items based on custom columns.
	 *
	 * @param WP_Query|WP_User_Query $query The query instance.
	 *
	 * @return void
	 */
	public function sort_items( $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		// Ensure $orderby is a valid string before proceeding
		if ( ! is_string( $orderby ) ) {
			return;
		}

		$column = $this->get_column_by_name( $orderby, $this->object_type, $this->object_subtype );

		// Ensure the column exists and is sortable
		if ( ! $column || ! $column['sortable'] ) {
			return;
		}

		$meta_key     = $column['meta_key'] ?? '';
		$sort_numeric = $column['numeric'] ?? false;

		$query->set( 'meta_key', $meta_key );
		$query->set( 'orderby', $sort_numeric ? 'meta_value_num' : 'meta_value' );
	}

	/**
	 * Enqueue inline editing script if there are inline editable columns.
	 *
	 * @return void
	 */
	public function enqueue_inline_editing_script(): void {
		if ( ! $this->has_inline_editing_columns() ) {
			return;
		}
		?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $(document).on('click', '.inline-editable', function (e) {
                    if ($(e.target).is('input') || $(e.target).is('select')) {
                        return;
                    }

                    var $this = $(this);

                    if (!$this.data('original-content')) {
                        $this.data('original-content', $this.html());
                    }

                    var originalValue = $this.data('raw-value');
                    var columnName = $this.data('column-name');
                    var objectId = $this.data('object-id');
                    var objectType = $this.data('object-type');
                    var objectSubType = $this.data('object-subtype');
                    var nonce = $this.data('nonce');
                    var inlineAttributes = JSON.parse($this.attr('data-inline-attributes') || '{}');
                    var $input;

                    if (inlineAttributes.type === 'select') {
                        $input = $('<select>').css({
                            'min-width': '80px',
                            'width': '100%'
                        });
                        $.each(inlineAttributes.options, function (key, value) {
                            var $option = $('<option>').val(key).text(value);
                            if (key === originalValue) {
                                $option.prop('selected', true);
                            }
                            $input.append($option);
                        });
                    } else {
                        $input = $('<input>').val(originalValue).css({
                            'min-width': '80px',
                            'width': '100%'
                        });
                    }

                    $.each(inlineAttributes, function (key, value) {
                        if (key !== 'options') {
                            $input.attr(key, value);
                        }
                    });

                    $this.html($input);
                    $input.focus();

                    var revertToOriginal = function () {
                        $input.off('blur keyup keydown');
                        $this.html($this.data('original-content'));
                    };

                    var saveValue = function () {
                        var newValue = $input.val();
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'update_inline_metadata',
                                object_id: objectId,
                                object_type: objectType,
                                object_subtype: objectSubType,
                                column_name: columnName,
                                meta_value: newValue,
                                nonce: nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    $this.replaceWith(response.data.formatted_value);
                                } else {
                                    console.error('Error response: ', response);
                                    revertToOriginal();
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('AJAX error: ', textStatus, errorThrown);
                                revertToOriginal();
                            }
                        });
                    };

                    $input.on('blur', function () {
                        saveValue();
                        $input.off('keyup');
                    });

                    $input.on('keydown', function (e) {
                        if (e.keyCode === 27) { // Escape key
                            e.preventDefault();
                            revertToOriginal();
                        } else if (e.keyCode === 13) { // Enter key
                            e.preventDefault();
                            saveValue();
                            $input.off('blur');
                        }
                    });
                });
            });
        </script>
		<?php
	}

	/**
	 * Handle AJAX request to update metadata.
	 *
	 * @return void
	 */
	public function handle_ajax_update_metadata(): void {
		$object_id      = intval( $_POST['object_id'] );
		$object_type    = sanitize_text_field( $_POST['object_type'] );
		$object_subtype = sanitize_text_field( $_POST['object_subtype'] );
		$column_name    = sanitize_text_field( $_POST['column_name'] );
		$meta_value     = sanitize_text_field( $_POST['meta_value'] );
		$nonce          = sanitize_text_field( $_POST['nonce'] );

		if ( ! wp_verify_nonce( $nonce, 'inline_edit_' . $object_id . '_' . $column_name . '_' . $object_type . '_' . $object_subtype ) ) {
			wp_send_json_error( [ 'message' => 'Nonce verification failed.' ] );
		}

		$column = $this->get_column_by_name( $column_name, $object_type, $object_subtype );
		if ( ! $column ) {
			wp_send_json_error( [ 'message' => 'Invalid column name.' ] );
		}

		if ( ! $this->check_column_permission( $column ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$meta_key = $column['meta_key'];

		if ( empty( $meta_value ) ) {
			$this->delete_metadata( $column, $object_type, $object_id, $meta_key );
		} else {
			$meta_value = $this->sanitize_meta_value( $column, $object_type, $meta_value );
			$this->update_metadata( $column, $object_type, $object_id, $meta_key, $meta_value );
		}

		$this->set_object_type( $object_type );
		$this->set_object_subtype( $object_subtype );

		wp_send_json_success( [ 'formatted_value' => $this->render_column_content( '', $column_name, $object_id ) ] );
	}

	/**
	 * Delete metadata for the specified object type.
	 *
	 * @param array  $column      The name of the column.
	 * @param string $object_type The type of object (user, post, etc.).
	 * @param int    $object_id   The ID of the object.
	 * @param string $meta_key    The metadata key.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function delete_metadata( array $column, string $object_type, int $object_id, string $meta_key ): bool {
		if ( isset( $column['delete_meta_callback'] ) && is_callable( $column['delete_meta_callback'] ) ) {
			return call_user_func( $column['delete_meta_callback'], $object_type, $object_id, $meta_key );
		}

		return delete_metadata( $object_type, $object_id, $meta_key );
	}

	/**
	 * Sanitize the meta value based on the column configuration.
	 *
	 * @param array  $column      The name of the column.
	 * @param string $object_type The type of object (user, post, etc.).
	 * @param mixed  $meta_value  The meta value to sanitize.
	 *
	 * @return mixed The sanitized meta value.
	 */
	protected function sanitize_meta_value( array $column, string $object_type, $meta_value ) {
		if ( isset( $column['sanitize_callback'] ) && is_callable( $column['sanitize_callback'] ) ) {
			return call_user_func( $column['sanitize_callback'], $meta_value );
		}

		$inline_attributes = $column['inline_attributes'] ?? [];
		$type              = $inline_attributes['type'] ?? 'text';

		$callback = self::$sanitize_callbacks[ $type ] ?? 'sanitize_text_field';

		return call_user_func( $callback, $meta_value );
	}

	/**
	 * Update metadata for the specified object type.
	 *
	 * @param array  $column      The name of the column.
	 * @param string $object_type The type of object (user, post, etc.).
	 * @param int    $object_id   The ID of the object.
	 * @param string $meta_key    The metadata key.
	 * @param mixed  $meta_value  The metadata value.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function update_metadata( array $column, string $object_type, int $object_id, string $meta_key, $meta_value ): bool {
		if ( isset( $column['update_meta_callback'] ) && is_callable( $column['update_meta_callback'] ) ) {
			return call_user_func( $column['update_meta_callback'], $object_type, $object_id, $meta_key, $meta_value );
		}

		return (bool) update_metadata( $object_type, $object_id, $meta_key, $meta_value );
	}

	/**
	 * Load AJAX hooks for inline editing.
	 *
	 * @return void
	 */
	protected function load_ajax_hooks(): void {
		add_action( 'admin_footer', [ $this, 'enqueue_inline_editing_script' ] );
		add_action( 'wp_ajax_update_inline_metadata', [ $this, 'handle_ajax_update_metadata' ] );
	}

	/**
	 * Check column permission.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return bool True if permission is granted, false otherwise.
	 */
	protected function check_column_permission( array $column ): bool {
		if ( isset( $column['permission_callback'] ) && is_callable( $column['permission_callback'] ) ) {
			return call_user_func( $column['permission_callback'] );
		}

		// Default to checking 'manage_options' capability
		return current_user_can( 'manage_options' );
	}

}
