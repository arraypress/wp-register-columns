<?php
/**
 * Row action tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\RowActions\Post;
use ArrayPress\RegisterColumns\Utils\Runtime;
use PHPUnit\Framework\TestCase;
use RA_Sent;

/**
 * A row action is a link in a list table that does something to one row, and
 * the something runs over admin-ajax.
 *
 * Which means three things have to line up: the action name the link posts to,
 * the hook the handler is registered on, and the nonce. Two of them used to be
 * built from strings that Strauss does not rewrite, so two plugins bundling
 * this library collided -- and collided in the loud way, because both handlers
 * run and the first one to find no matching action calls wp_send_json_error(),
 * which exits.
 */
final class RowActionsTest extends TestCase {

	/**
	 * Empty the stubbed WordPress and the static registry.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		/*
		 * This suite has always run as though admin_init had fired: the
		 * stub it came with answered did_action() with 1 for everything.
		 * The host library's stub reads a real store, so the precondition
		 * is stated here instead of being a side effect of a weaker stub
		 * -- RowActions hooks its ajax handler immediately when admin_init
		 * has already run, and defers it when it has not.
		 */
		$GLOBALS['rc_did']['admin_init'] = 1;

		$reflection = new \ReflectionClass( \ArrayPress\RegisterColumns\Abstracts\RowActions::class );
		$actions    = $reflection->getProperty( 'actions' );
		$actions->setValue( null, array() );

		$enqueued = $reflection->getProperty( 'assets_enqueued' );
		$enqueued->setValue( null, false );

		$_POST = array();
	}

	/**
	 * And again.
	 */
	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Register one AJAX action on the product post type.
	 *
	 * @param callable|null $callback What the action does.
	 *
	 * @return Post
	 */
	private function actions( ?callable $callback = null ): Post {
		return new Post(
			array(
				'resend' => array(
					'label'      => 'Resend',
					'ajax'       => true,
					'capability' => 'edit_posts',
					'callback'   => $callback ?? static fn( $id, $options ) => array( 'message' => 'done' ),
				),
			),
			'product'
		);
	}

	/**
	 * The handler is hooked on this build's own action name.
	 */
	public function test_the_handler_is_hooked_on_a_derived_action(): void {
		$this->actions();

		$expected = 'wp_ajax_' . Runtime::ajax_action( 'post_product' );

		$this->assertArrayHasKey( $expected, $GLOBALS['rc_hooks'] );
		// The action name is an underscore form, since it is a hook rather
		// than a handle.
		$this->assertStringContainsString( str_replace( '-', '_', Runtime::prefix() ), $expected );
	}

	/**
	 * The link carries the action name rather than the browser rebuilding it.
	 *
	 * Every prefixed copy loads the same script file under its own handle, so
	 * a shared JavaScript object is owned by whichever localised last and
	 * every other copy's links would dispatch to the wrong plugin.
	 */
	public function test_the_link_carries_its_own_action(): void {
		$instance = $this->actions();

		$rendered = $instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 );

		$link = implode( ' ', $rendered );

		$this->assertStringContainsString( 'data-ajax-action="' . Runtime::ajax_action( 'post_product' ) . '"', $link );
		$this->assertStringContainsString( 'data-action-key="resend"', $link );
		$this->assertStringContainsString( 'data-object-id="7"', $link );
	}

	/**
	 * The nonce the link carries is the one the handler checks.
	 *
	 * They are built in two different methods, which is exactly how they come
	 * apart -- and when they do, every action fails with "invalid security
	 * token" and nothing says why.
	 */
	public function test_the_link_nonce_is_the_one_the_handler_checks(): void {
		$instance = $this->actions();

		$instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 );

		$created = $GLOBALS['ra_nonces'];

		$this->assertNotEmpty( $created );

		$_POST = array(
			'action_key' => 'resend',
			'object_id'  => 7,
			'_wpnonce'   => 'nonce:' . $created[0],
			'options'    => '{}',
		);

		$this->expectException( RA_Sent::class );

		try {
			$instance->handle_ajax();
		} finally {
			$sent = end( $GLOBALS['ra_json'] );

			$this->assertTrue( $sent['success'], 'The nonce the link carries was refused by the handler.' );
		}
	}

	/**
	 * A wrong nonce is refused.
	 */
	public function test_a_wrong_nonce_is_refused(): void {
		$instance = $this->actions();

		$_POST = array(
			'action_key' => 'resend',
			'object_id'  => 7,
			'_wpnonce'   => 'nonce:something-else',
			'options'    => '{}',
		);

		try {
			$instance->handle_ajax();
			$this->fail( 'The handler did not respond.' );
		} catch ( RA_Sent $sent ) {
			$response = end( $GLOBALS['ra_json'] );

			$this->assertFalse( $response['success'] );
			$this->assertSame( 403, $response['status'] );
		}
	}

	/**
	 * Without the capability, nothing runs.
	 */
	public function test_the_capability_is_required(): void {
		$ran      = false;
		$instance = $this->actions(
			function () use ( &$ran ) {
				$ran = true;

				return array();
			}
		);

		$instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 );

		$GLOBALS['rc_caps'] = [];

		$_POST = array(
			'action_key' => 'resend',
			'object_id'  => 7,
			'_wpnonce'   => 'nonce:' . $GLOBALS['ra_nonces'][0],
			'options'    => '{}',
		);

		try {
			$instance->handle_ajax();
		} catch ( RA_Sent $sent ) {
			$response = end( $GLOBALS['ra_json'] );

			$this->assertFalse( $response['success'] );
			$this->assertSame( 403, $response['status'] );
		}

		$this->assertFalse( $ran, 'The callback ran without the capability.' );
	}

	/**
	 * A malformed options body reaches the callback as an array.
	 *
	 * json_decode() returns null on a parse failure and false for the literal
	 * `false`, and `?? []` catches only the first -- so a body of `false` used
	 * to arrive at the callback as a boolean where an array was expected.
	 *
	 * @param string $body What was posted.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'malformedProvider' )]
	public function test_a_malformed_options_body_is_still_an_array( string $body ): void {
		$seen = 'not called';

		$instance = $this->actions(
			function ( $id, $options ) use ( &$seen ) {
				$seen = $options;

				return array();
			}
		);

		$instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 );

		$_POST = array(
			'action_key' => 'resend',
			'object_id'  => 7,
			'_wpnonce'   => 'nonce:' . $GLOBALS['ra_nonces'][0],
			'options'    => $body,
		);

		try {
			$instance->handle_ajax();
		} catch ( RA_Sent $sent ) {
			// Expected: the handler responds and exits.
			$this->addToAssertionCount( 1 );
		}

		$this->assertIsArray( $seen, sprintf( 'A body of %s reached the callback as a %s.', $body, gettype( $seen ) ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function malformedProvider(): array {
		return array(
			'the literal false' => array( 'false' ),
			'the literal null'  => array( 'null' ),
			'a bare number'     => array( '42' ),
			'a bare string'     => array( '"hello"' ),
			'nonsense'          => array( 'not json at all' ),
			'empty'             => array( '' ),
		);
	}

	/**
	 * A slashed options body still decodes.
	 *
	 * WordPress slashes the request superglobals on load, so a quoted value
	 * arrives carrying backslashes that were never sent -- and json_decode()
	 * refuses the result outright.
	 */
	public function test_a_slashed_options_body_still_decodes(): void {
		$seen = null;

		$instance = $this->actions(
			function ( $id, $options ) use ( &$seen ) {
				$seen = $options;

				return array();
			}
		);

		$instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 );

		$_POST = array(
			'action_key' => 'resend',
			'object_id'  => 7,
			'_wpnonce'   => 'nonce:' . $GLOBALS['ra_nonces'][0],
			// What core hands back for {"reason":"expired"}.
			'options'    => '{\"reason\":\"expired\"}',
		);

		try {
			$instance->handle_ajax();
		} catch ( RA_Sent $sent ) {
			$this->addToAssertionCount( 1 );
		}

		$this->assertSame( array( 'reason' => 'expired' ), $seen );
	}

	/**
	 * Two builds share no action name, handle or nonce.
	 */
	public function test_two_builds_share_nothing(): void {
		$one = Runtime::prefix_for( 'PluginOne\\ArrayPress\\RegisterColumns\\Utils' );
		$two = Runtime::prefix_for( 'PluginTwo\\ArrayPress\\RegisterColumns\\Utils' );

		$this->assertNotSame( $one, $two );
		$this->assertSame( 'row-actions', Runtime::prefix_for( 'ArrayPress\\RegisterColumns\\Utils' ) );
	}

	/**
	 * No runtime key is written as a literal.
	 *
	 * In an unprefixed build the derived name equals the old hardcoded one,
	 * so a value-based test cannot tell them apart.
	 */
	public function test_no_runtime_key_is_hardcoded(): void {
		$files = glob( dirname( __DIR__ ) . '/src/{,*/}*.php', GLOB_BRACE );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			if ( 'Runtime.php' === basename( $file ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			$this->assertStringNotContainsString(
				'wp_ajax_row_action_',
				$source,
				sprintf( '%s hardcodes the admin-ajax action name.', basename( $file ) )
			);

			$this->assertStringNotContainsString(
				"'row-actions-ajax'",
				$source,
				sprintf( '%s hardcodes the asset handle.', basename( $file ) )
			);
		}
	}
}
