<?php
/**
 * Screen tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Support\Screen;
use PHPUnit\Framework\TestCase;

/**
 * Which admin screen is which.
 *
 * The one fact all three halves of this library need, and it used to be
 * written three times — once in the columns package, once in bulk actions,
 * once in list filters. The three did not agree.
 *
 * None of these ids is guessable. A post list is `edit-{post_type}` but the
 * media list is `upload`; users is `users` but comments is `edit-comments`.
 * Get one wrong and the hook never fires, which looks exactly like a screen
 * where nobody registered anything.
 *
 * The ids below were read out of real WP_Screen objects, not remembered.
 */
final class ScreenTest extends TestCase {

	/**
	 * Reset the stubbed screen.
	 */
	protected function setUp(): void {
		rc_reset_globals();
	}

	/**
	 * The screen id for each kind of list table.
	 *
	 * @dataProvider idProvider
	 *
	 * @param string $object_type    The type.
	 * @param string $object_subtype Its subtype.
	 * @param string $expected       The screen id core produces.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'idProvider' )]
	public function test_the_screen_id_is_the_one_core_uses( string $object_type, string $object_subtype, string $expected ): void {
		$this->assertSame( $expected, Screen::id( $object_type, $object_subtype ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function idProvider(): array {
		return [
			'a post type'  => [ 'post', 'download', 'edit-download' ],
			'posts'        => [ 'post', 'post', 'edit-post' ],
			'a taxonomy'   => [ 'term', 'category', 'edit-category' ],
			'users'        => [ 'user', 'user', 'users' ],
			'comments'     => [ 'comment', 'comment', 'edit-comments' ],
			'media'        => [ 'media', 'attachment', 'upload' ],
		];
	}

	/**
	 * An object type this library does not know gets no id.
	 *
	 * Rather than a plausible-looking `edit-` one, which would attach a hook
	 * that fires on somebody else's screen.
	 */
	public function test_an_unknown_type_has_no_screen(): void {
		$this->assertSame( '', Screen::id( 'widget', 'sidebar' ) );
		$this->assertFalse( Screen::is( 'widget', 'sidebar' ) );
	}

	/**
	 * A post list is recognised by post type and base, not by id.
	 *
	 * `edit-category` is a term list and `edit-post` is a post list, and both
	 * match `edit-{something}` — so the id alone cannot tell them apart.
	 */
	public function test_a_post_list_is_recognised(): void {
		$GLOBALS['rc_screen'] = (object) [ 'id' => 'edit-download', 'base' => 'edit', 'post_type' => 'download', 'taxonomy' => '' ];

		$this->assertTrue( Screen::is( 'post', 'download' ) );
		$this->assertFalse( Screen::is( 'post', 'page' ), 'Another post type was mistaken for this one.' );
		$this->assertFalse( Screen::is( 'term', 'download' ) );
	}

	/**
	 * A term list is not the single-term edit screen.
	 *
	 * Both carry the taxonomy; only one has a list table. Matching on the
	 * taxonomy alone put column widths into a form with no columns.
	 */
	public function test_a_term_list_is_not_the_term_editor(): void {
		$GLOBALS['rc_screen'] = (object) [ 'id' => 'edit-category', 'base' => 'edit-tags', 'post_type' => 'post', 'taxonomy' => 'category' ];

		$this->assertTrue( Screen::is( 'term', 'category' ) );

		// The single-term edit screen.
		$GLOBALS['rc_screen'] = (object) [ 'id' => 'term', 'base' => 'term', 'post_type' => 'post', 'taxonomy' => 'category' ];

		$this->assertFalse( Screen::is( 'term', 'category' ) );
	}

	/**
	 * The screens identified by id alone.
	 *
	 * @dataProvider simpleProvider
	 *
	 * @param string $object_type The type.
	 * @param string $screen_id   The screen it lives on.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'simpleProvider' )]
	public function test_a_screen_identified_by_id( string $object_type, string $screen_id ): void {
		$GLOBALS['rc_screen'] = (object) [ 'id' => $screen_id, 'base' => $screen_id, 'post_type' => '', 'taxonomy' => '' ];

		$this->assertTrue( Screen::is( $object_type ) );

		$GLOBALS['rc_screen'] = (object) [ 'id' => 'dashboard', 'base' => 'dashboard', 'post_type' => '', 'taxonomy' => '' ];

		$this->assertFalse( Screen::is( $object_type ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function simpleProvider(): array {
		return [
			'users'    => [ 'user', 'users' ],
			'comments' => [ 'comment', 'edit-comments' ],
			'media'    => [ 'media', 'upload' ],
		];
	}

	/**
	 * No screen at all is not this screen.
	 *
	 * get_current_screen() returns null on the front end and early in an
	 * admin request, and every caller here runs on hooks that can fire then.
	 */
	public function test_no_screen_is_not_a_match(): void {
		$GLOBALS['rc_screen'] = null;

		foreach ( Screen::types() as $type ) {
			$this->assertFalse( Screen::is( $type, 'anything' ), sprintf( '%s matched with no screen.', $type ) );
		}
	}
}
