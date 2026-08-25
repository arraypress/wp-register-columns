<?php
/**
 * Column content tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Abstracts\Columns;
use ArrayPress\RegisterColumns\Tables\Post;
use ArrayPress\RegisterColumns\Tables\Taxonomy;
use ArrayPress\RegisterColumns\Tables\User;
use PHPUnit\Framework\TestCase;

/**
 * What ends up in a cell.
 *
 * This was five identical copies of the same twenty-four lines, one per table
 * class, differing only in which get_*_meta they called. Five copies is four
 * chances for a fix to land in some of them — and the escaping and the
 * empty-value handling both live here, which are the two things you would
 * least like to be inconsistent between a post column and a term column.
 */
final class RenderTest extends TestCase {

	/**
	 * Forget the last test's registrations.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		$store = new \ReflectionProperty( Columns::class, 'columns' );
		$store->setValue( null, [] );
	}

	/**
	 * Render one post column and hand back the cell.
	 *
	 * @param array<string, mixed> $config Column configuration.
	 * @param int                  $id     The post id.
	 *
	 * @return string
	 */
	private function cell( array $config, int $id = 7 ): string {
		$columns = new Post( [ 'sales' => array_merge( [ 'label' => 'Sales' ], $config ) ], 'download' );

		return $columns->render_column_content( '', 'sales', $id );
	}

	/**
	 * A column with a meta key shows the stored value.
	 */
	public function test_a_meta_column_shows_its_value(): void {
		$GLOBALS['rc_meta']['post'][7]['_sales'] = '42';

		$this->assertSame( '42', $this->cell( [ 'meta_key' => '_sales' ] ) );
	}

	/**
	 * A stored value is escaped.
	 *
	 * Post meta is whatever anybody with access to it has ever written, and
	 * this goes straight into a table cell.
	 */
	public function test_a_stored_value_is_escaped(): void {
		$GLOBALS['rc_meta']['post'][7]['_sales'] = '<script>alert(1)</script>';

		$this->assertStringNotContainsString( '<script', $this->cell( [ 'meta_key' => '_sales' ] ) );
	}

	/**
	 * Nothing stored shows a placeholder, not a blank cell.
	 *
	 * A blank cell in a list table reads as a column that failed to load.
	 */
	public function test_an_empty_value_shows_a_placeholder(): void {
		$cell = $this->cell( [ 'meta_key' => '_sales' ] );

		$this->assertStringContainsString( '&#8212;', $cell );

		// And says so out loud: an em dash announces as "em dash", or as
		// nothing whatever, depending on the screen reader.
		$this->assertStringContainsString( 'screen-reader-text', $cell );
	}

	/**
	 * A stored nought is a value, not an absence.
	 *
	 * A column of counts showing a dash where it should show 0 is a column
	 * that looks broken rather than one that looks empty. empty() cannot tell
	 * the two apart, which is why it is not used.
	 */
	public function test_a_stored_zero_is_shown(): void {
		$GLOBALS['rc_meta']['post'][7]['_sales'] = '0';

		$this->assertSame( '0', $this->cell( [ 'meta_key' => '_sales' ] ) );
	}

	/**
	 * A display callback with a meta key is given the value first.
	 *
	 * So a column about a stored number does not have to fetch it, which is
	 * the whole reason the pair of arguments exists.
	 */
	public function test_a_callback_with_a_meta_key_is_given_the_value(): void {
		$GLOBALS['rc_meta']['post'][7]['_sales'] = '42';

		$seen = [];

		$this->cell(
			[
				'meta_key'         => '_sales',
				'display_callback' => static function ( $value, $id ) use ( &$seen ): string {
					$seen = [ $value, $id ];

					return 'ok';
				},
			]
		);

		$this->assertSame( [ '42', 7 ], $seen );
	}

	/**
	 * And without one, only the object id.
	 */
	public function test_a_callback_without_a_meta_key_is_given_the_id(): void {
		$seen = null;

		$this->cell(
			[
				'display_callback' => static function ( $id ) use ( &$seen ): string {
					$seen = $id;

					return 'ok';
				},
			]
		);

		$this->assertSame( 7, $seen );
	}

	/**
	 * A callback's markup is left alone.
	 *
	 * A column that renders a badge or a link is the reason to write one, and
	 * escaping its return value would make every such column print its own
	 * source. What it returns is the consumer's to escape.
	 */
	public function test_a_callback_may_return_markup(): void {
		$cell = $this->cell( [ 'display_callback' => static fn(): string => '<strong>Yes</strong>' ] );

		$this->assertSame( '<strong>Yes</strong>', $cell );
	}

	/**
	 * A column nobody registered is left as it was.
	 *
	 * Every one of these hooks fires for every column on the screen,
	 * including core's own — returning anything but the value untouched would
	 * blank the title column.
	 */
	public function test_an_unknown_column_is_untouched(): void {
		$columns = new Post( [ 'sales' => [ 'label' => 'Sales' ] ], 'download' );

		$this->assertSame( 'Original', $columns->render_column_content( 'Original', 'title', 7 ) );
	}

	/**
	 * Every table type reads its own kind of meta.
	 *
	 * The one thing that differed between the five copies, and so the one
	 * thing worth checking for each of them: a user column reading post meta
	 * would return nothing at all and look like a column with no data.
	 */
	public function test_each_table_reads_its_own_meta(): void {
		$GLOBALS['rc_meta']['user'][3]['_score'] = 'user value';
		$GLOBALS['rc_meta']['term'][4]['_score'] = 'term value';

		$users = new User( [ 'score' => [ 'label' => 'Score', 'meta_key' => '_score' ] ], 'user' );
		$terms = new Taxonomy( [ 'score' => [ 'label' => 'Score', 'meta_key' => '_score' ] ], 'category' );

		$this->assertSame( 'user value', $users->render_column_content( '', 'score', 3 ) );
		$this->assertSame( 'term value', $terms->render_column_content( '', 'score', 4 ) );
	}
}
