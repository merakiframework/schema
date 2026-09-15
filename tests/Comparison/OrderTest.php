<?php
declare(strict_types=1);

namespace Meraki\Schema\Comparison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Where one value sits relative to another.
 *
 * Replaces the `-1 | 0 | 1` a caller had to remember the sign convention of. The predicates are
 * the point: a comparison matcher is `compareTo($x)->isAtLeast()` rather than `>= 0`, so the
 * matchers can be written once against {@see Comparable} instead of once per field type.
 */
#[Group('comparison')]
#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
	/**
	 * A conventional comparison integer is only promised to be negative, zero or positive —
	 * `strcmp()` and several of Brick's comparisons are free to return anything — so reading one
	 * has to normalise rather than assume it is already `-1`, `0` or `1`.
	 *
	 * @return iterable<string, array{int, Order}>
	 */
	public static function comparisonIntegers(): iterable
	{
		yield 'the conventional -1' => [-1, Order::Less];
		yield 'the conventional 0' => [0, Order::Equal];
		yield 'the conventional 1' => [1, Order::Greater];
		yield 'any negative' => [-42, Order::Less];
		yield 'any positive' => [42, Order::Greater];
		yield 'PHP_INT_MIN' => [PHP_INT_MIN, Order::Less];
		yield 'PHP_INT_MAX' => [PHP_INT_MAX, Order::Greater];
	}

	#[Test]
	#[DataProvider('comparisonIntegers')]
	public function it_reads_any_conventional_comparison_integer(int $comparison, Order $expected): void
	{
		$this->assertSame($expected, Order::of($comparison));
	}

	#[Test]
	public function each_case_answers_only_its_own_question(): void
	{
		$this->assertTrue(Order::Less->isLess());
		$this->assertFalse(Order::Less->isEqual());
		$this->assertFalse(Order::Less->isGreater());

		$this->assertTrue(Order::Equal->isEqual());
		$this->assertFalse(Order::Equal->isLess());
		$this->assertFalse(Order::Equal->isGreater());

		$this->assertTrue(Order::Greater->isGreater());
		$this->assertFalse(Order::Greater->isEqual());
		$this->assertFalse(Order::Greater->isLess());
	}

	/**
	 * The inclusive pair, which is what `isAtLeast` and `isAtMost` matchers ask. Equal satisfies
	 * both, which is the whole reason they are not just `isGreater()` and `isLess()`.
	 */
	#[Test]
	public function the_inclusive_predicates_admit_equality(): void
	{
		$this->assertTrue(Order::Greater->isAtLeast());
		$this->assertTrue(Order::Equal->isAtLeast());
		$this->assertFalse(Order::Less->isAtLeast());

		$this->assertTrue(Order::Less->isAtMost());
		$this->assertTrue(Order::Equal->isAtMost());
		$this->assertFalse(Order::Greater->isAtMost());
	}

	#[Test]
	public function flipping_reads_the_comparison_from_the_other_side(): void
	{
		$this->assertSame(Order::Greater, Order::Less->flipped());
		$this->assertSame(Order::Less, Order::Greater->flipped());
		$this->assertSame(Order::Equal, Order::Equal->flipped());
	}

	#[Test]
	public function flipping_twice_changes_nothing(): void
	{
		foreach (Order::cases() as $order) {
			$this->assertSame($order, $order->flipped()->flipped());
		}
	}

	/**
	 * Backed by the conventional integers, so a value can hand a third party's comparison straight
	 * to {@see Order::of()} and a caller can hand `->value` back to one that wants an integer.
	 */
	#[Test]
	public function it_is_backed_by_the_conventional_integers(): void
	{
		$this->assertSame(-1, Order::Less->value);
		$this->assertSame(0, Order::Equal->value);
		$this->assertSame(1, Order::Greater->value);
	}
}
