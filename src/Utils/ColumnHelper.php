<?php
/**
 * A utility class providing common HTML generation operations for column data.
 *
 * Features:
 * - Generate a color circle div.
 * - Generate an image thumbnail.
 * - Generate a badge with specific colors.
 * - Get the file size of an attachment.
 * - Generate a progress bar.
 * - Generate a link.
 * - Generate an icon with a label.
 * - Format date values.
 * - Format numeric values.
 * - Format boolean values as Yes/No.
 * - Highlight text.
 * - Generate a tooltip.
 * - Generate a button.
 * - Provide Yes/No options.
 * - Format date values with color based on past or active status.
 *
 * @package         arraypress/register-custom-columns
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         0.1.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterCustomColumns\Utils;

use function class_exists;
use function esc_attr;
use function sprintf;
use function wp_get_attachment_image;
use function esc_html;
use function date_i18n;
use function get_option;
use function strtotime;
use function filter_var;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_NULL_ON_FAILURE;

/**
 * Check if the class `Generate` is defined, and if not, define it.
 */
if ( ! class_exists( 'ColumnHelper' ) ) :

	class ColumnHelper {

		/**
		 * Generate a color circle div with the actual color in it.
		 *
		 * @param string $color The hex color code.
		 *
		 * @return string The HTML for the color circle div.
		 */
		public static function color_circle( string $color ): string {
			$sanitized_color = esc_attr( $color );

			return sprintf(
				'<div style="display: inline-block; width: 20px; height: 20px; border-radius: 50%%; background-color: %s; border: 1px solid #ccc;"></div>',
				$sanitized_color
			);
		}

		/**
		 * Generate an image thumbnail based on an attachment ID.
		 *
		 * @param int   $attachment_id The attachment ID.
		 * @param mixed $size          The image size. Default is 'thumbnail'. Can be a string or an array of width and height.
		 * @param array $atts          Optional. Additional attributes to pass to the wp_get_attachment_image function.
		 *
		 * @return string The HTML for the image thumbnail.
		 */
		public static function image_thumbnail( int $attachment_id, $size = 'thumbnail', array $atts = [] ): string {
			if ( ! $attachment_id ) {
				return '&mdash;';
			}

			$image_html = wp_get_attachment_image( $attachment_id, $size, false, $atts );

			if ( ! $image_html ) {
				return '&mdash;';
			}

			return sprintf( '<div class="thumbnail">%s</div>', $image_html );
		}

		/**
		 * Generate a badge with specific colors.
		 *
		 * @param string $text       The text to display inside the badge.
		 * @param string $bg_color   The background color of the badge.
		 * @param string $text_color The text color of the badge.
		 *
		 * @return string The HTML for the badge.
		 */
		public static function badge( string $text, string $bg_color, string $text_color ): string {
			$sanitized_bg_color   = esc_attr( $bg_color );
			$sanitized_text_color = esc_attr( $text_color );
			$sanitized_text       = esc_html( $text );

			return sprintf(
				'<span class="badge" style="background-color: %s; color: %s; padding: 2px 8px; border-radius: 4px;">%s</span>',
				$sanitized_bg_color,
				$sanitized_text_color,
				$sanitized_text
			);
		}

		/**
		 * Get the file size of an attachment.
		 *
		 * @param int $attachment_id The attachment ID.
		 *
		 * @return string The formatted file size or 'N/A'.
		 */
		public static function attachment_file_size( int $attachment_id ): string {
			$file_path = get_attached_file( $attachment_id );
			$default   = '&mdash;';

			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );

				return $file_size ? sprintf( '<span>%s</span>', esc_html( size_format( $file_size ) ) ) : esc_html( $default );
			}

			return esc_html( $default );
		}

		/**
		 * Generate a progress bar.
		 *
		 * @param int    $current    The current value.
		 * @param int    $total      The total value.
		 * @param string $bg_color   The background color of the progress bar.
		 * @param string $text_color The text color of the progress bar.
		 *
		 * @return string The HTML for the progress bar.
		 */
		public static function progress_bar( int $current, int $total, string $bg_color = '#4caf50', string $text_color = '#ffffff' ): string {
			$percentage           = ( $total > 0 ) ? ( $current / $total ) * 100 : 0;
			$sanitized_bg_color   = esc_attr( $bg_color );
			$sanitized_text_color = esc_attr( $text_color );

			return sprintf(
				'<div style="background-color: #e0e0e0; border-radius: 4px; padding: 2px;">
					<div style="width: %1$d%%; background-color: %2$s; color: %3$s; text-align: center; padding: 4px; border-radius: 4px;">%1$d%%</div>
				</div>',
				$percentage,
				$sanitized_bg_color,
				$sanitized_text_color
			);
		}

		/**
		 * Generate a link.
		 *
		 * @param string $url    The URL.
		 * @param string $text   The link text.
		 * @param string $target The target attribute for the link (e.g., '_blank').
		 *
		 * @return string The HTML for the link.
		 */
		public static function link( string $url, string $text, string $target = '_self' ): string {
			$sanitized_url    = esc_url( $url );
			$sanitized_text   = esc_html( $text );
			$sanitized_target = esc_attr( $target );

			return sprintf(
				'<a href="%s" target="%s">%s</a>',
				$sanitized_url,
				$sanitized_target,
				$sanitized_text
			);
		}

		/**
		 * Generate an icon with a label.
		 *
		 * @param string $icon_class The class for the icon (e.g., 'dashicons dashicons-admin-users').
		 * @param string $label      The label text.
		 *
		 * @return string The HTML for the icon with a label.
		 */
		public static function icon_label( string $icon_class, string $label ): string {
			$sanitized_icon_class = esc_attr( $icon_class );
			$sanitized_label      = esc_html( $label );

			return sprintf(
				'<span class="%s" style="margin-right: 5px;"></span>%s',
				$sanitized_icon_class,
				$sanitized_label
			);
		}

		/**
		 * Get the file type (e.g., audio, video, etc.) of an attachment.
		 *
		 * @param int $attachment_id The attachment ID.
		 *
		 * @return string The file type or 'unknown'.
		 */
		public static function attachment_file_type( int $attachment_id ): string {
			$file_path = get_attached_file( $attachment_id );

			if ( $file_path && file_exists( $file_path ) ) {
				$file_type = wp_check_filetype( $file_path );

				return esc_html( $file_type['type'] ) ?? 'unknown';
			}

			return 'unknown';
		}

		/**
		 * Get the file extension of an attachment if the file is found.
		 *
		 * @param int $attachment_id The attachment ID.
		 *
		 * @return string|null The file extension or null if not found.
		 */
		public static function attachment_file_extension( int $attachment_id ): ?string {
			$file_path = get_attached_file( $attachment_id );

			if ( $file_path && file_exists( $file_path ) ) {
				return esc_html( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			}

			return '&mdash;';
		}

		/**
		 * Format numeric values.
		 *
		 * @param mixed $value The value to be formatted.
		 *
		 * @return string The formatted numeric value or '–'.
		 */
		public static function format_numeric( $value ): string {
			if ( is_numeric( $value ) ) {
				return number_format_i18n( (float) $value );
			}

			return '&mdash;';
		}

		/**
		 * Format date values.
		 *
		 * @param string $value   The date value to be formatted.
		 * @param string $default The default value to display if the date is not available.
		 *
		 * @return string The formatted date or the default value.
		 */
		public static function format_date( string $value, string $default = '&mdash;' ): string {
			if ( ! empty( $value ) ) {
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $value ) ) );
			}

			return esc_html( $default );
		}

		/**
		 * Format boolean values as Yes/No.
		 *
		 * @param mixed  $value   The value to be formatted.
		 * @param string $default The default value to display if the value is not available.
		 *
		 * @return string The formatted boolean value or the default value.
		 */
		public static function format_boolean( $value, string $default = '&mdash;' ): string {
			$boolean = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( is_null( $boolean ) ) {
				return esc_html( $default );
			}

			return esc_html( $boolean ? __( 'Yes', 'text-domain' ) : __( 'No', 'text-domain' ) );
		}

		/**
		 * Get Yes/No options array.
		 *
		 * @return array The array of Yes/No options.
		 */
		public static function get_yes_no_options(): array {
			return [
				'no'  => __( 'No', 'text-domain' ),
				'yes' => __( 'Yes', 'text-domain' ),
			];
		}

		/**
		 * Highlight text with a background color.
		 *
		 * @param string $text  The text to highlight.
		 * @param string $color The background color for the highlight.
		 *
		 * @return string The HTML for the highlighted text.
		 */
		public static function highlight_text( string $text, string $color = '#ffff00' ): string {
			$sanitized_color = esc_attr( $color );
			$sanitized_text  = esc_html( $text );

			return sprintf(
				'<span style="background-color: %s;">%s</span>',
				$sanitized_color,
				$sanitized_text
			);
		}

		/**
		 * Generate a tooltip.
		 *
		 * @param string $text    The text to display in the tooltip.
		 * @param string $tooltip The tooltip text.
		 *
		 * @return string The HTML for the tooltip.
		 */
		public static function tooltip( string $text, string $tooltip ): string {
			$sanitized_text    = esc_html( $text );
			$sanitized_tooltip = esc_attr( $tooltip );

			return sprintf(
				'<span title="%s">%s</span>',
				$sanitized_tooltip,
				$sanitized_text
			);
		}

		/**
		 * Generate a button.
		 *
		 * @param string $text The button text.
		 * @param string $url  The URL the button links to.
		 * @param array  $atts Additional attributes for the button.
		 *
		 * @return string The HTML for the button.
		 */
		public static function button( string $text, string $url, array $atts = [] ): string {
			$sanitized_text = esc_html( $text );
			$sanitized_url  = esc_url( $url );
			$attributes     = '';

			foreach ( $atts as $key => $value ) {
				$attributes .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}

			return sprintf(
				'<a href="%s" class="button"%s>%s</a>',
				$sanitized_url,
				$attributes,
				$sanitized_text
			);
		}

		/**
		 * Format date values with color based on past or active status.
		 *
		 * @param string $value        The date value to be formatted.
		 * @param string $past_color   The hex color for past dates.
		 * @param string $active_color The hex color for active dates.
		 * @param string $default      The default value to display if the date is not available.
		 *
		 * @return string The formatted date with color or the default value.
		 */
		public static function format_date_with_color( string $value, string $past_color = '#ff0000', string $active_color = '#a3b745', string $default = '&mdash;' ): string {
			if ( ! empty( $value ) ) {
				$timestamp = strtotime( $value );
				$color     = $timestamp < time() ? $past_color : $active_color;

				return sprintf(
					'<span style="color: %s;">%s</span>',
					esc_attr( $color ),
					esc_html( date_i18n( get_option( 'date_format' ), $timestamp ) )
				);
			}

			return $default === '&mdash;' ? '&mdash;' : esc_html( $default );
		}

	}
endif;
