<?php
/**
 * List filter tests.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Abstracts\ListFilters;
use ArrayPress\RegisterColumns\Filters\Post;
use ArrayPress\RegisterColumns\Filters\User;
use PHPUnit\Framework\TestCase;

/**
 * Dropdown filters above core's list tables.
 *
 * Two halves that have to agree: a control rendered into the tablenav, and a
 * query modified to match what it was set to. A filter that renders and does
 * not filter is worse than no filter — it reports a result that is not the
 * one it claims.
 */
final class ListFilterTest extends TestCase {

	/**
	 * Forget the last test's registrations.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		foreach ( [ 'filters', 'instances' ] as $property ) {
			( new \ReflectionProperty( ListFilters::class, $property ) )->setValue( null, [] );
		}

		$_GET     = [];
		$_REQUEST = [];
	}

	/**
	 * Register one filter on a post type.
	 *
	 * @param array<string, mixed> $filters Filters to register.
	 *
	 * @return Post
	 */
	private function filters( array $filters = [] ): Post {
		return new Post(
			[] === $filters
				? [
					'apcd_state' => [
						'label'   => 'All states',
						'options' => [ 'live' => 'Live', 'draft' => 'Draft' ],
					],
				]
				: $filters,
			'download'
		);
	}

	/**
	 * Render the filters and hand back the markup.
	 *
	 * @param array<string, mixed> $filters Filters to register.
	 *
	 * @return string
	 */
	private function render( array $filters = [] ): string {
		$post = $this->filters( $filters );

		ob_start();

		try {
			$post->render_filters();
		} finally {
			return (string) ob_get_clean();
		}
	}

	/**
	 * Both hooks are attached: one to draw it, one to act on it.
	 */
	public function test_a_post_filter_hooks_both_halves(): void {
		$this->filters();

		$this->assertArrayHasKey( 'restrict_manage_posts', $GLOBALS['rc_hooks'] );
		$this->assertArrayHasKey( 'parse_query', $GLOBALS['rc_hooks'] );
	}

	/**
	 * And the user table's own pair, which are different hooks entirely.
	 *
	 * restrict_manage_users / users_list_table_query_args, not the post ones
	 * — a users list is not a WP_Query.
	 */
	public function test_a_user_filter_hooks_both_halves(): void {
		new User( [ 'x' => [ 'label' => 'All', 'options' => [ 'a' => 'A' ] ] ], 'user' );

		$this->assertArrayHasKey( 'restrict_manage_users', $GLOBALS['rc_hooks'] );
		$this->assertArrayHasKey( 'users_list_table_query_args', $GLOBALS['rc_hooks'] );
	}

	/**
	 * A filter renders a select with its options.
	 */
	public function test_a_filter_renders_its_options(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'name="apcd_state"', $html );
		$this->assertStringContainsString( '>Live</option>', $html );
		$this->assertStringContainsString( '>Draft</option>', $html );
	}

	/**
	 * The label is the empty option — "All states", not a heading.
	 *
	 * A filter bar with a label above every control is twice as tall as the
	 * controls in it, and core's own filters put the unfiltered case first
	 * in the list instead.
	 */
	public function test_the_label_is_the_unfiltered_option(): void {
		$this->assertStringContainsString( '<option value="">All states</option>', $this->render() );
	}

	/**
	 * The current value is selected.
	 */
	public function test_the_current_value_is_selected(): void {
		// $_REQUEST, because that is what the renderer reads — a filter
		// submitted by core's own form arrives there whether the tablenav
		// posted or linked.
		$_REQUEST['apcd_state'] = 'draft';

		$this->assertMatchesRegularExpression( '/value="draft"[^>]*selected/', $this->render() );
	}

	/**
	 * Labels and values out of a database are escaped.
	 *
	 * Options usually come from a query — terms, authors, statuses — so
	 * neither the value nor the label is something this library chose.
	 */
	public function test_options_are_escaped(): void {
		$html = $this->render(
			[
				'x' => [
					'label'   => '<script>alert(1)</script>',
					'options' => [ '"><script>alert(2)</script>' => '<script>alert(3)</script>' ],
				],
			]
		);

		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * Registering twice adds to the filters rather than replacing them.
	 *
	 * A guard against double registration used to return early from the whole
	 * constructor, so the second registration was discarded entirely — one
	 * plugin adding a filter to `post` and any other plugin adding a second
	 * meant one of them silently did not exist. Only the hooks need guarding.
	 */
	public function test_a_second_registration_adds_to_the_first(): void {
		$this->filters( [ 'one' => [ 'label' => 'One', 'options' => [ 'a' => 'A' ] ] ] );
		$this->filters( [ 'two' => [ 'label' => 'Two', 'options' => [ 'b' => 'B' ] ] ] );

		$this->assertSame(
			[ 'one', 'two' ],
			array_keys( ListFilters::get_filters( 'post', 'download' ) )
		);
	}

	/**
	 * And the hooks are still only attached once.
	 *
	 * Attaching twice renders every dropdown twice, which is what the guard
	 * was there for in the first place.
	 */
	public function test_the_hooks_attach_only_once(): void {
		$this->filters( [ 'one' => [ 'label' => 'One', 'options' => [ 'a' => 'A' ] ] ] );
		$this->filters( [ 'two' => [ 'label' => 'Two', 'options' => [ 'b' => 'B' ] ] ] );

		$this->assertCount( 1, $GLOBALS['rc_hooks']['restrict_manage_posts'] );
	}

	/**
	 * A count is shown when the filter asks for one.
	 */
	public function test_a_count_can_be_shown(): void {
		$html = $this->render(
			[
				'x' => [
					'label'      => 'All',
					'show_count' => true,
					'options'    => [ 'live' => [ 'label' => 'Live', 'count' => 12 ] ],
				],
			]
		);

		$this->assertStringContainsString( 'Live (12)', $html );
	}

	/**
	 * And left out when it does not.
	 */
	public function test_a_count_is_not_shown_unless_asked_for(): void {
		$html = $this->render(
			[
				'x' => [
					'label'   => 'All',
					'options' => [ 'live' => [ 'label' => 'Live', 'count' => 12 ] ],
				],
			]
		);

		$this->assertStringNotContainsString( '(12)', $html );
	}
}
