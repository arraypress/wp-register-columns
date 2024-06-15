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
				return '';
			}

			$image_html = wp_get_attachment_image( $attachment_id, $size, false, $atts );

			if ( ! $image_html ) {
				return '';
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
			$default   = __( 'N/A', 'text-domain' );

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

				return $file_type['type'] ?? 'unknown';
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
				return pathinfo( $file_path, PATHINFO_EXTENSION );
			}

			return '–';
		}

		/**
		 * Format numeric values.
		 *
		 * @param mixed $value The value to be formatted.
		 *
		 * @return string The formatted numeric value or '–'.
		 */
		public static function numeric( $value ): string {
			if ( is_numeric( $value ) ) {
				return number_format_i18n( (float) $value );
			}

			return '–';
		}


	}
endif;
