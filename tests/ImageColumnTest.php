<?php
/**
 * Image column tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Support\Image;
use ArrayPress\RegisterColumns\Tables\Comment;
use ArrayPress\RegisterColumns\Tables\Media;
use ArrayPress\RegisterColumns\Tables\Post;
use ArrayPress\RegisterColumns\Tables\Taxonomy;
use ArrayPress\RegisterColumns\Tables\User;
use PHPUnit\Framework\TestCase;

/**
 * Showing a picture in a list table.
 *
 * Core does this in one place -- the avatar in the users table -- and does it
 * by hand inside the row renderer. The point of the column here is that a
 * download can show its featured image the same way without the consumer
 * writing markup, sizing, or the escaping around a stored URL.
 *
 * Two failures are worth naming because neither looks like a failure. An
 * attachment id whose file has been deleted renders as a broken image icon
 * unless something checks; and an image column with no width set stretches
 * like any other column, which puts a 32px thumbnail in the middle of a third
 * of the screen.
 */
final class ImageColumnTest extends TestCase {

	protected function setUp(): void {
		rc_reset_globals();

		$store = new \ReflectionProperty( \ArrayPress\RegisterColumns\Abstracts\Columns::class, 'columns' );
		$store->setValue( null, [] );

		$GLOBALS['rc_attachments'] = [ 11 => 'https://example.test/logo.png' ];
		$GLOBALS['rc_thumbnails']  = [ 5 => 11 ];
		$GLOBALS['rc_avatars']     = [ 3 => 'https://example.test/avatar.png' ];
	}

	/**
	 * Render one cell of an image column on the downloads table.
	 *
	 * @param array<string, mixed> $config    Extra column configuration.
	 * @param int                  $object_id The post.
	 *
	 * @return string
	 */
	private function cell( array $config = [], int $object_id = 5 ): string {
		$columns = new Post(
			[ 'thumb' => array_merge( [ 'label' => 'Image', 'image' => true ], $config ) ],
			'download'
		);

		return $columns->render_column_content( '', 'thumb', $object_id );
	}

	// -- The natural image of each table ----------------------------------

	public function test_a_post_shows_its_featured_image(): void {
		$this->assertStringContainsString( 'https://example.test/logo.png', $this->cell() );
	}

	public function test_a_post_with_no_featured_image_shows_the_placeholder(): void {
		$html = $this->cell( [], 6 );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
	}

	public function test_a_media_item_shows_itself(): void {
		$columns = new Media(
			[ 'thumb' => [ 'label' => 'Image', 'image' => true ] ],
			'attachment'
		);

		$this->assertStringContainsString(
			'https://example.test/logo.png',
			$columns->render_column_content( '', 'thumb', 11 )
		);
	}

	/**
	 * A user shows the avatar, which is what this was modelled on.
	 *
	 * get_avatar() rather than an attachment lookup: an avatar is derived
	 * from the user's email and has no media library id at all, so treating
	 * it as one would render nothing on every site.
	 */
	public function test_a_user_shows_their_avatar(): void {
		$columns = new User( [ 'face' => [ 'label' => 'Avatar', 'image' => true ] ], 'user' );

		$this->assertStringContainsString(
			'https://example.test/avatar.png',
			$columns->render_column_content( '', 'face', 3 )
		);
	}

	public function test_a_comment_shows_the_authors_avatar(): void {
		$GLOBALS['rc_avatars'][9] = 'https://example.test/commenter.png';

		$columns = new Comment( [ 'face' => [ 'label' => 'Avatar', 'image' => true ] ], 'comment' );

		$this->assertStringContainsString(
			'https://example.test/commenter.png',
			$columns->render_column_content( '', 'face', 9 )
		);
	}

	/**
	 * A term has no image of its own, so it has to be told where one is.
	 *
	 * Inventing one would mean guessing a meta key, and guessing wrong is a
	 * column that is permanently empty for reasons nobody can see.
	 */
	public function test_a_term_has_no_natural_image(): void {
		$columns = new Taxonomy( [ 'logo' => [ 'label' => 'Logo', 'image' => true ] ], 'category' );

		$this->assertStringNotContainsString( '<img', $columns->render_column_content( '', 'logo', 4 ) );
	}

	// -- Reading a stored value -------------------------------------------

	public function test_an_attachment_id_in_meta_is_rendered(): void {
		$GLOBALS['rc_meta']['post'][5]['_logo'] = 11;

		$this->assertStringContainsString(
			'https://example.test/logo.png',
			$this->cell( [ 'meta_key' => '_logo' ] )
		);
	}

	/**
	 * Meta comes back from the database as a string, always.
	 */
	public function test_a_numeric_string_is_still_an_attachment_id(): void {
		$GLOBALS['rc_meta']['post'][5]['_logo'] = '11';

		$this->assertStringContainsString(
			'https://example.test/logo.png',
			$this->cell( [ 'meta_key' => '_logo' ] )
		);
	}

	public function test_a_url_in_meta_is_rendered(): void {
		$GLOBALS['rc_meta']['post'][5]['_logo'] = 'https://cdn.test/banner.jpg';

		$this->assertStringContainsString( 'https://cdn.test/banner.jpg', $this->cell( [ 'meta_key' => '_logo' ] ) );
	}

	public function test_an_empty_meta_value_shows_the_placeholder(): void {
		$html = $this->cell( [ 'meta_key' => '_logo' ] );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
	}

	/**
	 * The one that would otherwise ship a broken image icon.
	 *
	 * An id survives the attachment being deleted -- the meta row is still
	 * there. wp_get_attachment_image() answers '' for it, and the cell has to
	 * treat that as empty rather than printing it.
	 */
	public function test_an_attachment_that_was_deleted_shows_the_placeholder(): void {
		$GLOBALS['rc_meta']['post'][5]['_logo'] = 4242;

		$html = $this->cell( [ 'meta_key' => '_logo' ] );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
	}

	// -- Sizing -----------------------------------------------------------

	public function test_the_default_size_matches_the_avatar_core_uses(): void {
		$this->assertSame( 32, Image::DEFAULT_SIZE );
		$this->assertStringContainsString( 'width="32"', $this->cell() );
	}

	public function test_a_square_size_is_taken_as_pixels(): void {
		$this->assertStringContainsString( 'width="64"', $this->cell( [ 'image_size' => 64 ] ) );
	}

	public function test_a_width_and_height_pair_is_passed_through(): void {
		$html = $this->cell( [ 'image_size' => [ 80, 40 ] ] );

		$this->assertStringContainsString( 'width="80"', $html );
		$this->assertStringContainsString( 'height="40"', $html );
	}

	/**
	 * A registered size name has to reach core untouched.
	 *
	 * Converting it to pixels here would break add_image_size(): the site
	 * would get a stretched full-size file instead of the cropped one it
	 * generated.
	 */
	public function test_a_registered_size_name_is_not_converted(): void {
		$this->assertSame( 'thumbnail', Image::size( 'thumbnail' ) );
	}

	public function test_a_registered_size_resolves_to_the_sites_own_pixels(): void {
		$GLOBALS['rc_options'] = [ 'thumbnail_size_w' => 150, 'thumbnail_size_h' => 150 ];

		$this->assertSame( [ 150, 150 ], Image::pixels( 'thumbnail' ) );
	}

	public function test_an_unknown_size_name_falls_back_rather_than_collapsing(): void {
		$this->assertSame( [ 32, 32 ], Image::pixels( 'nonexistent' ) );
	}

	/**
	 * A URL cannot be measured, so both dimensions are pinned.
	 *
	 * Without this a 2000px file stored as a URL makes one row taller than
	 * the screen.
	 */
	public function test_a_url_is_given_both_dimensions(): void {
		$html = Image::from_url( 'https://cdn.test/huge.jpg', 48 );

		$this->assertStringContainsString( 'width="48"', $html );
		$this->assertStringContainsString( 'height="48"', $html );
		$this->assertStringContainsString( 'object-fit:cover', $html );
	}

	// -- The column's own width -------------------------------------------

	/**
	 * An image column sizes itself.
	 *
	 * Left alone it stretches like every other column, which is how a 32px
	 * thumbnail ends up centred in a third of the table.
	 */
	public function test_an_image_column_gets_a_width_without_being_asked(): void {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'download', 'base' => 'edit' ];

		$columns = new Post( [ 'thumb' => [ 'label' => 'Image', 'image' => true ] ], 'download' );

		ob_start();
		$columns->add_custom_column_styles();
		$style = (string) ob_get_clean();

		$this->assertStringContainsString( '.column-thumb{width:52px}', $style );
	}

	public function test_an_explicit_width_still_wins(): void {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'download', 'base' => 'edit' ];

		$columns = new Post(
			[ 'thumb' => [ 'label' => 'Image', 'image' => true, 'width' => '120px' ] ],
			'download'
		);

		ob_start();
		$columns->add_custom_column_styles();
		$style = (string) ob_get_clean();

		$this->assertStringContainsString( '.column-thumb{width:120px}', $style );
	}

	/**
	 * Core floats images inside list-table cells so an avatar sits beside a
	 * username. A column that is only an image has nothing beside it, so that
	 * rule has to be undone or the thumbnail hangs off the left edge.
	 */
	public function test_the_float_core_puts_on_list_table_images_is_undone(): void {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'download', 'base' => 'edit' ];

		$columns = new Post( [ 'thumb' => [ 'label' => 'Image', 'image' => true ] ], 'download' );

		ob_start();
		$columns->add_custom_column_styles();
		$style = (string) ob_get_clean();

		$this->assertStringContainsString( '.column-thumb img{', $style );
		$this->assertStringContainsString( 'float:none', $style );
	}

	public function test_a_plain_column_gets_no_image_rules(): void {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'download', 'base' => 'edit' ];

		$columns = new Post( [ 'sales' => [ 'label' => 'Sales', 'width' => '90px' ] ], 'download' );

		ob_start();
		$columns->add_custom_column_styles();
		$style = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'img{', $style );
	}

	// -- Interaction with what was already there --------------------------

	/**
	 * A display_callback still wins.
	 *
	 * It is the escape hatch for anything this does not cover, and an image
	 * column that ignored it would be a worse version of the thing it
	 * replaced.
	 */
	public function test_a_display_callback_takes_precedence(): void {
		$html = $this->cell(
			[
				'display_callback' => static fn ( $id ): string => 'mine:' . $id,
			]
		);

		$this->assertSame( 'mine:5', $html );
	}

	public function test_image_false_leaves_the_column_alone(): void {
		$GLOBALS['rc_meta']['post'][5]['_logo'] = '11';

		$columns = new Post(
			[ 'thumb' => [ 'label' => 'Image', 'image' => false, 'meta_key' => '_logo' ] ],
			'download'
		);

		$this->assertSame( '11', $columns->render_column_content( '', 'thumb', 5 ) );
	}
}
