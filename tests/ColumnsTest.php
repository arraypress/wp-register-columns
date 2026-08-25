<?php
/**
 * Column registration tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Tables\Post;
use PHPUnit\Framework\TestCase;

/**
 * Adding a column to a WordPress list table.
 *
 * Almost all of this library is arranging for other people's code to run at
 * the right moment: a filter to declare the column, a filter to declare it
 * sortable, an action to fill in each cell, and a query hook to sort by it.
 * None of that reports a mistake — a wrong hook name, a wrong position, a
 * permission check that refuses everybody, all of it is a screen that looks
 * exactly like a screen where nobody registered anything.
 *
 * So what is pinned here is the arrangement, and the two places where the
 * library hands something of the consumer's to WordPress: a width that ends
 * up inside a stylesheet, and a value that ends up inside a table cell.
 */
final class ColumnsTest extends TestCase {

	/**
	 * Forget the last test's registrations.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		// The column store is static, so it outlives an instance.
		$store = new \ReflectionProperty( \ArrayPress\RegisterColumns\Abstracts\Columns::class, 'columns' );
		$store->setValue( null, [] );
	}

	/**
	 * Register some post columns.
	 *
	 * @param array<string, mixed> $columns Column configuration.
	 * @param array<int, string>   $remove  Columns to remove.
	 *
	 * @return Post
	 */
	private function columns( array $columns, array $remove = [] ): Post {
		return new Post( $columns, 'download', $remove );
	}

	/**
	 * One column, with whatever configuration a test needs.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 *
	 * @return Post
	 */
	private function one( array $config = [] ): Post {
		return $this->columns( [ 'sales' => array_merge( [ 'label' => 'Sales' ], $config ) ] );
	}

	/**
	 * Registering hooks the four things a column needs.
	 *
	 * The filter that declares it, the one that makes it sortable, the action
	 * that fills the cell and the query hook that does the sorting. Missing
	 * any one of them is a column that half works, and the half that works is
	 * usually the half you look at first.
	 */
	public function test_registering_attaches_every_hook_a_column_needs(): void {
		$this->one();

		foreach (
			[
				'manage_download_posts_columns',
				'manage_edit-download_sortable_columns',
				'manage_download_posts_custom_column',
				'pre_get_posts',
			] as $hook
		) {
			$this->assertArrayHasKey( $hook, $GLOBALS['rc_hooks'], sprintf( '%s is not hooked.', $hook ) );
		}
	}

	/**
	 * A column is added to the table's own.
	 */
	public function test_a_column_is_added(): void {
		$columns = $this->one()->register_columns( [ 'title' => 'Title', 'date' => 'Date' ] );

		$this->assertSame( [ 'title', 'date', 'sales' ], array_keys( $columns ) );
		$this->assertSame( 'Sales', $columns['sales'] );
	}

	/**
	 * And can be put somewhere in particular.
	 *
	 * `before:` and `after:` name an existing column. Without them everything
	 * lands on the end, which for a list table means after the date — the
	 * furthest possible point from anything anyone is reading.
	 */
	public function test_a_column_can_be_positioned(): void {
		$before = $this->one( [ 'position' => 'before:date' ] )
			->register_columns( [ 'title' => 'Title', 'date' => 'Date' ] );

		$this->assertSame( [ 'title', 'sales', 'date' ], array_keys( $before ) );
	}

	/**
	 * A position naming a column that is not there does not lose the column.
	 *
	 * Someone else removed it, or renamed it, or it only exists on another
	 * post type. Dropping the column on the floor would make that somebody
	 * else's bug and this library's silence.
	 */
	public function test_a_position_naming_nothing_still_registers(): void {
		$columns = $this->one( [ 'position' => 'after:nonexistent' ] )
			->register_columns( [ 'title' => 'Title' ] );

		$this->assertArrayHasKey( 'sales', $columns );
	}

	/**
	 * Columns can be taken away as well as added.
	 */
	public function test_columns_can_be_removed(): void {
		$columns = $this->columns( [ 'sales' => [ 'label' => 'Sales' ] ], [ 'date', 'comments' ] )
			->register_columns( [ 'title' => 'Title', 'date' => 'Date' ] );

		$this->assertSame( [ 'title', 'sales' ], array_keys( $columns ) );
	}

	/**
	 * A label is escaped.
	 *
	 * It goes into the table header as markup, and it is a string the
	 * consumer wrote — usually a translation, occasionally something built
	 * from a setting.
	 */
	public function test_a_label_is_escaped(): void {
		$columns = $this->one( [ 'label' => '<script>alert(1)</script>' ] )->register_columns( [] );

		$this->assertStringNotContainsString( '<script', $columns['sales'] );
	}

	/**
	 * A column nobody may see is not offered.
	 */
	public function test_a_column_can_be_refused(): void {
		$columns = $this->one( [ 'permission_callback' => static fn(): bool => false ] )
			->register_columns( [ 'title' => 'Title' ] );

		$this->assertArrayNotHasKey( 'sales', $columns );
	}

	/**
	 * Without a callback of its own, a column needs manage_options.
	 */
	public function test_the_default_permission_is_manage_options(): void {
		$GLOBALS['rc_caps'] = [ 'edit_posts' ];

		$this->assertArrayNotHasKey( 'sales', $this->one()->register_columns( [] ) );

		$GLOBALS['rc_caps'] = [ 'manage_options' ];

		$this->assertArrayHasKey( 'sales', $this->one()->register_columns( [] ) );
	}

	/**
	 * A sortable column is declared to core in core's own shape.
	 *
	 * `[ $orderby, $desc_first ]`, where the second element decides whether
	 * the *first* click sorts descending. `numeric` was being passed there —
	 * so a column declared numeric sorted descending to begin with, which is
	 * not what the word means and not what anyone asked for. `numeric` has a
	 * job already: it picks meta_value_num over meta_value in the query.
	 */
	public function test_a_sortable_column_uses_cores_contract(): void {
		$sortable = $this->one( [ 'sortable' => true, 'numeric' => true ] )->register_sortable_columns( [] );

		$this->assertSame( [ 'sales', false ], $sortable['sales'] );

		$descending = $this->one( [ 'sortable' => true, 'desc_first' => true ] )->register_sortable_columns( [] );

		$this->assertSame( [ 'sales', true ], $descending['sales'] );
	}

	/**
	 * A column that is not sortable is not offered as one.
	 */
	public function test_an_unsortable_column_is_not_declared(): void {
		$this->assertSame( [], $this->one()->register_sortable_columns( [] ) );
	}

	/**
	 * A width reaches the page as CSS, and only if it is a width.
	 *
	 * esc_attr() is for an HTML attribute and does nothing useful in a
	 * stylesheet: braces and semicolons pass straight through it, so
	 * `10px;} body{display:none` closed the rule and wrote another one.
	 */
	public function test_a_width_that_is_not_a_width_is_refused(): void {
		$this->assertSame( '120px', $this->style( [ 'width' => '120px' ] ) ? '120px' : '' );

		$css = $this->style( [ 'width' => '10px;} body{display:none' ] );

		$this->assertStringNotContainsString( 'display:none', $css );
		$this->assertSame( '', $css, 'A width that is not a length should produce no rule at all.' );
	}

	/**
	 * Widths in the units a column might reasonably use.
	 *
	 * @dataProvider widthProvider
	 *
	 * @param string $width    A configured width.
	 * @param bool   $accepted Whether it should reach the page.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'widthProvider' )]
	public function test_a_width_is_accepted_or_refused( string $width, bool $accepted ): void {
		$css = $this->style( [ 'width' => $width ] );

		$this->assertSame( $accepted, '' !== $css, sprintf( '%s was handled wrongly.', $width ) );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function widthProvider(): array {
		return [
			'pixels'      => [ '120px', true ],
			'percent'     => [ '10%', true ],
			'em'          => [ '6em', true ],
			'rem'         => [ '6.5rem', true ],
			'no unit'     => [ '120', false ],
			'auto'        => [ 'auto', true ],
			'a word'      => [ 'inherit', false ],
			'a function'  => [ 'calc(10% - 2px)', false ],
			'an escape'   => [ '10px;}body{display:none', false ],
			'nothing'     => [ '', false ],
		];
	}

	/**
	 * Whatever the style block would contain for one column.
	 *
	 * @param array<string, mixed> $config Column configuration.
	 *
	 * @return string
	 */
	private function style( array $config ): string {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'download', 'base' => 'edit' ];

		ob_start();

		try {
			$this->one( $config )->add_custom_column_styles();
		} finally {
			return (string) ob_get_clean();
		}
	}

	/**
	 * Nothing to style is nothing printed.
	 */
	public function test_no_widths_prints_no_style_block(): void {
		$this->assertSame( '', $this->style( [] ) );
	}

	/**
	 * Styles are only printed on the screen they are for.
	 */
	public function test_styles_are_confined_to_their_own_screen(): void {
		$GLOBALS['rc_screen'] = (object) [ 'post_type' => 'post', 'base' => 'edit' ];

		ob_start();

		try {
			$this->one( [ 'width' => '120px' ] )->add_custom_column_styles();
		} finally {
			$css = (string) ob_get_clean();
		}

		$this->assertSame( '', $css );
	}
	/**
	 * Every hook name is one WordPress actually fires.
	 *
	 * These are the whole library. A misspelling is not an error, not a
	 * warning and not a failing test — it is a screen that looks exactly
	 * like a screen where nobody registered anything, which is the hardest
	 * kind of bug to see. Checked against core's source rather than from
	 * memory: the names are built at runtime from a screen id, so they do
	 * not appear literally anywhere and cannot be grepped for.
	 *
	 * @dataProvider hookProvider
	 *
	 * @param string $table    A table class.
	 * @param string $subtype  Its subtype.
	 * @param array  $expected The hooks it must attach to.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'hookProvider' )]
	public function test_every_table_attaches_to_the_hooks_core_fires( string $table, string $subtype, array $expected ): void {
		$class = 'ArrayPress\\RegisterColumns\\Tables\\' . $table;

		new $class( [ 'x' => [ 'label' => 'X' ] ], $subtype );

		foreach ( $expected as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['rc_hooks'], sprintf( '%s does not attach to %s.', $table, $hook ) );
		}
	}

	/**
	 * The hook each table needs, and where core fires it.
	 *
	 * post       manage_{$post_type}_posts_columns          class-wp-posts-list-table.php:755
	 *            manage_{$screen->id}_sortable_columns      class-wp-list-table.php:1353
	 *            manage_{$post_type}_posts_custom_column    class-wp-posts-list-table.php:1508
	 * user       manage_{$screen->id}_columns               screen.php:37
	 *            manage_users_custom_column                 class-wp-users-list-table.php:632
	 * taxonomy   manage_{$taxonomy}_custom_column           class-wp-terms-list-table.php:667
	 * comment    manage_comments_custom_column              class-wp-comments-list-table.php:1170
	 * media      manage_media_columns                       class-wp-media-list-table.php:423
	 *            manage_media_custom_column                 class-wp-media-list-table.php:743
	 *
	 * @return array<string, array{0: string, 1: string, 2: string[]}>
	 */
	public static function hookProvider(): array {
		return [
			'post'     => [
				'Post',
				'download',
				[
					'manage_download_posts_columns',
					'manage_edit-download_sortable_columns',
					'manage_download_posts_custom_column',
					'pre_get_posts',
				],
			],
			'user'     => [
				'User',
				'user',
				[ 'manage_users_columns', 'manage_users_sortable_columns', 'manage_users_custom_column', 'pre_get_users' ],
			],
			'taxonomy' => [
				'Taxonomy',
				'category',
				[
					'manage_edit-category_columns',
					'manage_edit-category_sortable_columns',
					'manage_category_custom_column',
					'terms_clauses',
				],
			],
			'comment'  => [
				'Comment',
				'comment',
				[
					'manage_edit-comments_columns',
					'manage_edit-comments_sortable_columns',
					'manage_comments_custom_column',
					'pre_get_comments',
				],
			],
			'media'    => [
				'Media',
				'attachment',
				[ 'manage_media_columns', 'manage_upload_sortable_columns', 'manage_media_custom_column', 'pre_get_posts' ],
			],
		];
	}

}
