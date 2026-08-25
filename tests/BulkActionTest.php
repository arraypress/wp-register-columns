<?php
/**
 * Bulk action tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Abstracts\BulkActions;
use PHPUnit\Framework\TestCase;

/**
 * Custom bulk actions on core's list tables.
 *
 * Two hooks, both named for the screen: `bulk_actions-{$screen->id}` offers
 * the action in the dropdown, and `handle_bulk_actions-{$screen->id}` runs it
 * and hands back a redirect. Getting the screen id wrong means the action
 * never appears, and getting the second one wrong means it appears and does
 * nothing — which is worse, because somebody will use it.
 */
final class BulkActionTest extends TestCase {

	/**
	 * Forget the last test's registrations.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		$store = new \ReflectionProperty( BulkActions::class, 'actions' );
		$store->setValue( null, [] );
	}

	/**
	 * Register one action on a post type.
	 *
	 * @param array<string, mixed> $actions Actions to register.
	 *
	 * @return \ArrayPress\RegisterColumns\BulkActions\Post
	 */
	private function actions( array $actions = [] ) {
		return new \ArrayPress\RegisterColumns\BulkActions\Post(
			[] === $actions
				? [ 'apcd_archive' => [ 'label' => 'Archive', 'callback' => static fn( array $ids ): int => count( $ids ) ] ]
				: $actions,
			'download'
		);
	}

	/**
	 * Both hooks are attached, named for the right screen.
	 *
	 * @dataProvider tableProvider
	 *
	 * @param string $class   The bulk action class.
	 * @param string $subtype Its subtype.
	 * @param string $screen  The screen id core builds the hooks from.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'tableProvider' )]
	public function test_the_hooks_are_named_for_the_screen( string $class, string $subtype, string $screen ): void {
		$full = 'ArrayPress\\RegisterColumns\\BulkActions\\' . $class;

		new $full( [ 'x' => [ 'label' => 'X' ] ], $subtype );

		$this->assertArrayHasKey( 'bulk_actions-' . $screen, $GLOBALS['rc_hooks'] );
		$this->assertArrayHasKey( 'handle_bulk_actions-' . $screen, $GLOBALS['rc_hooks'] );
	}

	/**
	 * Every table, and the screen its hooks hang off.
	 *
	 * bulk_actions-{$screen->id}         class-wp-list-table.php:598
	 * handle_bulk_actions-{$screen->id}  edit.php:222, edit-comments.php:120, edit-tags.php:207
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function tableProvider(): array {
		return [
			'a post type' => [ 'Post', 'download', 'edit-download' ],
			'a taxonomy'  => [ 'Taxonomy', 'category', 'edit-category' ],
			'users'       => [ 'User', 'user', 'users' ],
			'comments'    => [ 'Comment', 'comment', 'edit-comments' ],
			'media'       => [ 'Media', 'attachment', 'upload' ],
		];
	}

	/**
	 * An action is offered in the dropdown, alongside core's.
	 */
	public function test_an_action_is_offered(): void {
		$offered = $this->actions()->register_bulk_actions( [ 'trash' => 'Move to Bin' ] );

		$this->assertSame( [ 'trash', 'apcd_archive' ], array_keys( $offered ) );
		$this->assertSame( 'Archive', $offered['apcd_archive'] );
	}

	/**
	 * A label is escaped.
	 */
	public function test_a_label_is_escaped(): void {
		$offered = $this->actions( [ 'x' => [ 'label' => '<script>alert(1)</script>' ] ] )->register_bulk_actions( [] );

		$this->assertStringNotContainsString( '<script', $offered['x'] );
	}

	/**
	 * Somebody else's action passes straight through.
	 *
	 * The handler fires for every bulk action on the screen, including
	 * core's. Returning anything but the redirect it was given would break
	 * Move to Bin.
	 */
	public function test_another_action_is_left_alone(): void {
		$this->assertSame(
			'https://example.test/edit.php',
			$this->actions()->handle_bulk_action( 'https://example.test/edit.php', 'trash', [ 1, 2 ] )
		);
	}

	/**
	 * An action with no callback does nothing rather than fataling.
	 */
	public function test_an_action_without_a_callback_is_survivable(): void {
		$actions = $this->actions( [ 'x' => [ 'label' => 'X' ] ] );

		$this->assertIsString( $actions->handle_bulk_action( 'https://example.test/edit.php', 'x', [ 1 ] ) );
	}
}
