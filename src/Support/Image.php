<?php
/**
 * Turning a stored value into a thumbnail.
 *
 * @package     ArrayPress\WP\RegisterColumns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Support;

/**
 * Class Image
 *
 * The one place that decides what a thumbnail in a list table looks like, so
 * a featured image on downloads, an avatar on users and a stored logo on a
 * term all line up with each other and with core's own avatar columns.
 *
 * @package ArrayPress\WP\RegisterColumns
 */
final class Image {

	/**
	 * The size core uses for the avatar on the users table.
	 *
	 * Matching it is the whole point: an image column beside a core one that
	 * is four pixels different reads as a mistake.
	 */
	public const DEFAULT_SIZE = 32;

	/**
	 * Render whatever a column stored.
	 *
	 * Accepts what people actually store: an attachment id, an attachment id
	 * that arrived from meta as a numeric string, or a URL.
	 *
	 * @param mixed            $value The stored value.
	 * @param int|string|array $size  Square pixels, a registered size, or [ w, h ].
	 *
	 * @return string Empty when there is nothing to show.
	 */
	public static function render( $value, $size = self::DEFAULT_SIZE ): string {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( is_numeric( $value ) ) {
			return self::from_attachment( (int) $value, $size );
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return self::from_url( $value, $size );
		}

		return '';
	}

	/**
	 * Render a media library attachment.
	 *
	 * @param int              $attachment_id Attachment id.
	 * @param int|string|array $size          Square pixels, a registered size, or [ w, h ].
	 *
	 * @return string Empty when the attachment is gone.
	 */
	public static function from_attachment( int $attachment_id, $size = self::DEFAULT_SIZE ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$html = wp_get_attachment_image( $attachment_id, self::size( $size ), false, [ 'loading' => 'lazy' ] );

		// An id whose attachment has been deleted returns '', which is the
		// right answer -- the caller shows its placeholder rather than a
		// broken image.
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Render a bare URL.
	 *
	 * Nothing here knows the image's real dimensions, so both are pinned and
	 * the file is fitted to them. Without that a 2000px logo stored as a URL
	 * makes one row of the table taller than the screen.
	 *
	 * @param string           $url  Image URL.
	 * @param int|string|array $size Square pixels, a registered size, or [ w, h ].
	 *
	 * @return string Empty when the URL is not one.
	 */
	public static function from_url( string $url, $size = self::DEFAULT_SIZE ): string {
		$url = esc_url( $url );

		if ( '' === $url ) {
			return '';
		}

		[ $width, $height ] = self::pixels( $size );

		return sprintf(
			'<img src="%s" width="%d" height="%d" alt="" loading="lazy" decoding="async" style="width:%dpx;height:%dpx;object-fit:cover">',
			$url,
			$width,
			$height,
			$width,
			$height
		);
	}

	/**
	 * Normalise a configured size into something wp_get_attachment_image()
	 * understands.
	 *
	 * @param int|string|array $size Square pixels, a registered size, or [ w, h ].
	 *
	 * @return string|array
	 */
	public static function size( $size ) {
		if ( is_array( $size ) ) {
			return [ (int) ( $size[0] ?? self::DEFAULT_SIZE ), (int) ( $size[1] ?? $size[0] ?? self::DEFAULT_SIZE ) ];
		}

		if ( is_numeric( $size ) ) {
			return [ (int) $size, (int) $size ];
		}

		// A registered size name -- 'thumbnail', 'medium', whatever a theme
		// added. Passed through untouched so add_image_size() keeps working.
		return (string) $size;
	}

	/**
	 * A configured size as concrete pixels.
	 *
	 * A registered size name is resolved through the same option core uses,
	 * so 'thumbnail' is whatever the site set it to rather than a guess.
	 *
	 * @param int|string|array $size Square pixels, a registered size, or [ w, h ].
	 *
	 * @return array{0: int, 1: int}
	 */
	public static function pixels( $size ): array {
		$normalised = self::size( $size );

		if ( is_array( $normalised ) ) {
			return [ max( 1, $normalised[0] ), max( 1, $normalised[1] ) ];
		}

		$width  = (int) get_option( $normalised . '_size_w' );
		$height = (int) get_option( $normalised . '_size_h' );

		if ( $width > 0 && $height > 0 ) {
			return [ $width, $height ];
		}

		return [ self::DEFAULT_SIZE, self::DEFAULT_SIZE ];
	}
}
