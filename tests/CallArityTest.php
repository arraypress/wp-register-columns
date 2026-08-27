<?php
/**
 * Calls into the abstract have to match the abstract.
 *
 * @package ArrayPress\RegisterColumns
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterColumns\Tests;

use ArrayPress\RegisterColumns\Abstracts\Columns;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Every `$this->method(...)` in a table class has to be a call the abstract
 * will accept.
 *
 * PHP does not check this until the line runs, and these lines run inside a
 * column renderer and a query filter -- so a wrong argument count is not an
 * error anywhere until somebody loads the list table, and then it is a fatal
 * that takes the whole screen with it.
 *
 * That is not hypothetical. get_column_by_name() required the object type and
 * subtype even though the instance already knew both, and ten call sites in
 * edd-registrars passed only the type. Every EDD table -- orders, customers,
 * discounts, downloads, licences, subscriptions, commissions -- fatalled the
 * moment one of its cells drew, and it was reported from a live site.
 */
final class CallArityTest extends TestCase {

	/**
	 * The concrete table classes shipped here.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function tableProvider(): array {
		$tables = [ 'Post', 'User', 'Taxonomy', 'Comment', 'Media' ];
		$cases  = [];

		foreach ( $tables as $table ) {
			$cases[ $table ] = [ 'ArrayPress\\RegisterColumns\\Tables\\' . $table ];
		}

		return $cases;
	}

	/**
	 * Calls a file makes on itself, as name => argument count.
	 *
	 * Read from source rather than reflected: reflection can say what a
	 * method accepts but not how the body calls it.
	 *
	 * @param string $source File contents.
	 *
	 * @return array<int, array{0: string, 1: int, 2: int}> name, args, line.
	 */
	private function calls_in( string $source ): array {
		$calls = [];
		$lines = explode( "\n", $source );

		foreach ( $lines as $number => $line ) {
			if ( ! preg_match_all( '/\$this->([a-zA-Z0-9_]+)\(/', $line, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			foreach ( $matches[1] as $index => $match ) {
				$open = $matches[0][ $index ][1] + strlen( $matches[0][ $index ][0] ) - 1;
				$args = $this->count_arguments( $line, $open );

				// A call broken across lines cannot be counted this way, and
				// guessing would produce false failures. Skipped rather than
				// half-read.
				if ( null === $args ) {
					continue;
				}

				$calls[] = [ $match[0], $args, $number + 1 ];
			}
		}

		return $calls;
	}

	/**
	 * Arguments between one balanced pair of brackets.
	 *
	 * @param string $line The line.
	 * @param int    $open Offset of the opening bracket.
	 *
	 * @return int|null Null when the call does not close on this line.
	 */
	private function count_arguments( string $line, int $open ): ?int {
		$depth  = 0;
		$args   = 0;
		$length = strlen( $line );

		for ( $index = $open; $index < $length; $index++ ) {
			$character = $line[ $index ];

			if ( '(' === $character || '[' === $character ) {
				$depth++;

				if ( 1 === $depth ) {
					$args = 1;
				}

				continue;
			}

			if ( ')' === $character || ']' === $character ) {
				$depth--;

				if ( 0 === $depth ) {
					// `foo()` is nought arguments, not one.
					return '' === trim( substr( $line, $open + 1, $index - $open - 1 ) ) ? 0 : $args;
				}

				continue;
			}

			if ( ',' === $character && 1 === $depth ) {
				$args++;
			}
		}

		return null;
	}

	/**
	 * No table calls an inherited method with too few or too many arguments.
	 *
	 * @dataProvider tableProvider
	 *
	 * @param string $class Table class.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'tableProvider' )]
	public function test_calls_match_the_methods_they_name( string $class ): void {
		$reflection = new ReflectionClass( $class );
		$source     = (string) file_get_contents( (string) $reflection->getFileName() );

		$offences = [];

		foreach ( $this->calls_in( $source ) as [ $name, $args, $line ] ) {
			if ( ! $reflection->hasMethod( $name ) ) {
				$offences[] = sprintf( '%s:%d calls %s(), which does not exist', $reflection->getShortName(), $line, $name );

				continue;
			}

			$method = $reflection->getMethod( $name );

			if ( $args < $method->getNumberOfRequiredParameters() ) {
				$offences[] = sprintf(
					'%s:%d calls %s() with %d argument(s); it requires %d',
					$reflection->getShortName(),
					$line,
					$name,
					$args,
					$method->getNumberOfRequiredParameters()
				);

				continue;
			}

			if ( ! $method->isVariadic() && $args > $method->getNumberOfParameters() ) {
				$offences[] = sprintf(
					'%s:%d calls %s() with %d argument(s); it accepts %d',
					$reflection->getShortName(),
					$line,
					$name,
					$args,
					$method->getNumberOfParameters()
				);
			}
		}

		$this->assertSame(
			[],
			$offences,
			"A call does not match the method it names. This is a fatal the moment the table renders:\n  "
			. implode( "\n  ", $offences )
		);
	}

	/**
	 * The instance knows its own types, so nobody should have to pass them.
	 *
	 * Pinning the shape rather than only the arity: making these required
	 * again is what broke edd-registrars, and it would break it silently --
	 * this package's own tests all passed while every consumer fatalled.
	 */
	public function test_get_column_by_name_can_be_called_with_just_a_name(): void {
		$method = new ReflectionMethod( Columns::class, 'get_column_by_name' );

		$this->assertSame(
			1,
			$method->getNumberOfRequiredParameters(),
			'A consumer holding a table instance should not have to restate what that instance is.'
		);
	}

	/**
	 * And answers about its own table when it is.
	 */
	public function test_it_answers_for_its_own_table_by_default(): void {
		rc_reset_globals();

		$store = new \ReflectionProperty( Columns::class, 'columns' );
		$store->setValue( null, [] );

		$columns = new \ArrayPress\RegisterColumns\Tables\Post(
			[ 'sales' => [ 'label' => 'Sales', 'meta_key' => '_sales' ] ],
			'download'
		);

		$this->assertSame( '_sales', $columns->get_column_by_name( 'sales' )['meta_key'] );
		$this->assertNull( $columns->get_column_by_name( 'nothing' ) );
	}
}
