<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\PartScope;
use Meraki\Schema\Rule\Condition\Comparison;
use Meraki\Schema\ValueScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * A rule can compare two fields, and can compare one part of each.
 *
 * Two capabilities that arrived together because neither is much use alone. Comparing whole values
 * needs the expectation to be a scope rather than a literal; comparing *parts* needs a scope that
 * can name one. Between them they cover the questions a checkout form actually asks — "is the
 * shipping address the billing address", and the weaker but more useful "are they at least in the
 * same country".
 */
#[Group('rule')]
#[CoversClass(Comparison::class)]
#[CoversClass(PartScope::class)]
final class PartComparisonTest extends TestCase
{
	private const AU = [
		'street' => ['1 Denham St'],
		'locality' => 'Rockhampton',
		'subdivision' => 'QLD',
		'postal_code' => '4700',
		'country' => 'AU',
	];

	private const NZ = [
		'street' => ['1 Queen St'],
		'locality' => 'Auckland',
		'subdivision' => 'AUK',
		'postal_code' => '1010',
		'country' => 'NZ',
	];

	private function schema(): Facade
	{
		$schema = new Facade('checkout');
		$schema->add(
			$schema->createAddressField('billing', ['AU', 'NZ']),
			$schema->createAddressField('shipping', ['AU', 'NZ']),
			$schema->createTextField('note'),
		);

		return $schema;
	}

	private function fired(Facade $schema, array $billing, array $shipping): bool
	{
		return $schema
			->validate((object) ['billing' => (object) $billing, 'shipping' => (object) $shipping])
			->forField('note')
			->wasAlteredByRule();
	}

	#[Test]
	public function two_whole_values_can_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping'))->equals(ValueScope::of('billing'))->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
		$this->assertFalse($this->fired($schema, self::AU, ['street' => ['2 Denham St']] + self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::NZ));
	}

	#[Test]
	public function one_part_of_each_can_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'country'))
				->equals(ValueScope::of('billing', 'country'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));

		// The weaker question, and the point of having it: a different street in the same country
		// is a different address and the same country.
		$this->assertTrue($this->fired($schema, self::AU, ['street' => ['2 Denham St']] + self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::NZ));
	}

	/**
	 * Nothing requires the two sides to be the same part, or even the same kind of field. The
	 * comparison is between two values, and two values that are not alike answer false.
	 */
	#[Test]
	public function two_different_parts_may_be_compared(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'postal_code'))
				->equals(ValueScope::of('billing', 'locality'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertFalse($this->fired($schema, self::AU, self::AU));
		$this->assertTrue($this->fired($schema, ['locality' => '4700'] + self::AU, self::AU));
	}

	/**
	 * A part nobody submitted is absent, not an error — so two absent parts are equal, which is
	 * the honest answer to "are these the same" when neither exists.
	 */
	#[Test]
	public function an_absent_part_resolves_to_nothing(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'dependent_locality'))
				->equals(ValueScope::of('billing', 'dependent_locality'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
	}

	/**
	 * A part whose emptiness is a list rather than a string.
	 *
	 * `street` is the first part held as a list, and emptiness was decided by a match on `null`,
	 * `Countable` and `Stringable|string` — a plain PHP array is none of those, so it fell to
	 * the default and an absent street reported as *not* empty. A latent hole for any
	 * array-valued part, which `street` is simply the first to stand in.
	 */
	#[Test]
	public function an_absent_street_is_empty(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'street'))
				->isEmpty()
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$withoutStreet = array_diff_key(self::AU, ['street' => null]);

		$this->assertTrue($this->fired($schema, $withoutStreet, self::AU));
		$this->assertFalse($this->fired($schema, self::AU, self::AU));
	}

	#[Test]
	public function a_part_may_be_compared_against_a_literal(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'country'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertTrue($this->fired($schema, self::AU, self::AU));
		$this->assertFalse($this->fired($schema, self::NZ, self::NZ));
	}

	#[Test]
	public function a_mistyped_part_is_refused_where_the_rule_is_written(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('has no part "ctry"');

		$schema->addRule($schema->when(ValueScope::of('billing', 'ctry'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()));
	}

	#[Test]
	public function a_part_of_a_field_that_has_none_is_refused(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('holds one value rather than named parts');

		$schema->addRule($schema->when(ValueScope::of('note', 'country'))->equals('AU')->then($schema->fields->getByName('note')->makeRequired()));
	}

	/**
	 * The expectation is checked too, not just the subject.
	 */
	#[Test]
	public function a_bad_part_on_the_expectation_is_refused(): void
	{
		$schema = $this->schema();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('has no part "ctry"');

		$schema->addRule(
			$schema->when(ValueScope::of('billing', 'country'))
				->equals(ValueScope::of('shipping', 'ctry'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);
	}

	#[Test]
	public function notequals_is_the_same_comparison_negated(): void
	{
		$schema = $this->schema();
		$schema->addRule(
			$schema->when(ValueScope::of('shipping', 'country'))
				->notEquals(ValueScope::of('billing', 'country'))
				->then($schema->fields->getByName('note')->makeRequired()),
		);

		$this->assertFalse($this->fired($schema, self::AU, self::AU));
		$this->assertTrue($this->fired($schema, self::AU, self::NZ));
	}
}
