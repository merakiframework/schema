<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The three point-in-time fields answer `from` and `until` the same way as each other.
 *
 * They did not. `Time::until()` was inclusive while `Date` and `DateTime` were exclusive, and
 * all three reported under the constraint name `until` — so one sentence in a language pack was
 * right for two fields and wrong for the third. Nothing compared them, because each field's
 * tests lived in its own file and asserted its own behaviour.
 *
 * That is what this file is for. Every case runs against all three fields from one provider, so
 * a bound that means one thing on `Time` and another on `Date` fails here rather than being
 * discovered by whoever writes the wording.
 *
 * {@see \Meraki\Schema\Field\Duration} is deliberately absent: it is a *length* of time rather
 * than a point in one, so it takes `minValue`/`maxValue` like {@see \Meraki\Schema\Field\Number}
 * and both of those are inclusive.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class TemporalBoundsTest extends TestCase
{
	/**
	 * A value equal to `from` is in range.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function from_includes_the_value_it_names(callable $make, string $lower, string $upper): void
	{
		$field = $make()->from($lower);

		$this->assertSame(
			ValidationStatus::Passed,
			$field->validate($lower)->forConstraint('from')->status,
		);
	}

	/**
	 * A value equal to `until` is *out* of range. This is the one that disagreed.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function until_excludes_the_value_it_names(callable $make, string $lower, string $upper): void
	{
		$field = $make()->until($upper);

		$this->assertSame(
			ValidationStatus::Failed,
			$field->validate($upper)->forConstraint('until')->status,
		);
	}

	/**
	 * A value equal to `after` is out of range — it is the exclusive lower bound.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function after_excludes_the_value_it_names(callable $make, string $lower, string $upper): void
	{
		$field = $make()->after($lower);

		$this->assertSame(
			ValidationStatus::Failed,
			$field->validate($lower)->forConstraint('after')->status,
		);
	}

	/**
	 * A value equal to `through` is in range — it is the inclusive upper bound.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function through_includes_the_value_it_names(callable $make, string $lower, string $upper): void
	{
		$field = $make()->through($upper);

		$this->assertSame(
			ValidationStatus::Passed,
			$field->validate($upper)->forConstraint('through')->status,
		);
	}

	/**
	 * The two lower bounds are one bound said two ways, so setting either clears the other.
	 * Likewise the two upper. Nothing may end up carrying both.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function the_paired_bounds_are_mutually_exclusive(callable $make, string $lower, string $upper): void
	{
		$lowerLast = $make()->from($lower)->after($lower);
		$upperLast = $make()->until($upper)->through($upper);

		$this->assertNull($lowerLast->from, 'after() should have cleared from()');
		$this->assertNotNull($lowerLast->after);

		$this->assertNull($upperLast->until, 'through() should have cleared until()');
		$this->assertNotNull($upperLast->through);

		// And the other way round, because a wither must not depend on call order.
		$this->assertNull($make()->after($lower)->from($lower)->after);
		$this->assertNull($make()->through($upper)->until($upper)->through);
	}

	/**
	 * An unset bound is skipped rather than passed, because nothing was asked.
	 *
	 * A sentinel stood here before — `LocalTime::max()`, `LocalDate::max()` — which answered
	 * every value with "yes" and reported a verdict on a question the author never put. It was
	 * also unsound on `Time`, where `LocalTime::max()` is `23:59:59.999999999`: a reachable
	 * value that an exclusive bound would have refused while claiming to be unbounded.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function an_unset_bound_is_skipped(callable $make, string $lower, string $upper): void
	{
		$result = $make()->validate($lower);

		$this->assertSame(ValidationStatus::Skipped, $result->forConstraint('from')->status);
		$this->assertSame(ValidationStatus::Skipped, $result->forConstraint('until')->status);
	}

	/**
	 * An interval counts from `from`, so without one there is nothing to count and the
	 * constraint is skipped rather than measured against an arbitrary origin.
	 */
	#[Test]
	#[DataProvider('pointInTimeFields')]
	public function an_interval_without_a_lower_bound_is_skipped(callable $make, string $lower, string $upper): void
	{
		$this->assertSame(
			ValidationStatus::Skipped,
			$make()->validate($lower)->forConstraint('interval')->status,
		);
	}

	/**
	 * @return iterable<string, array{callable(): Field, string, string}>
	 */
	public static function pointInTimeFields(): iterable
	{
		yield 'Date' => [
			static fn(): Field\Date => new Field\Date(new FieldName('d')),
			'2026-01-01',
			'2026-12-31',
		];

		yield 'Time' => [
			static fn(): Field\Time => new Field\Time(new FieldName('t')),
			'09:00',
			'17:00',
		];

		yield 'DateTime' => [
			static fn(): Field\DateTime => new Field\DateTime(new FieldName('dt')),
			'2026-01-01T09:00',
			'2026-12-31T17:00',
		];
	}
}
