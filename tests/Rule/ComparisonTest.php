<?php
declare(strict_types=1);

namespace Meraki\Schema\Rule;

use Meraki\Schema\Facade;
use Meraki\Schema\PropertyScope;
use Meraki\Schema\Rule\Condition\Comparison;
use Meraki\Schema\Rule\Condition\Equals;
use Meraki\Schema\Rule\Condition\NotEquals;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A rule's expectation is read the same way the submitted value was.
 *
 * ### What this exists to stop happening again
 *
 * A scope pointing at a field's value resolves to the *parsed* value, so a number field answers
 * with a `BigDecimal` and a date field with a `LocalDate`. The expectation was compared against
 * that with `===`, unparsed — so `when($age)->equals(18)` compared `BigDecimal` to `int` and was
 * false for every input that has ever existed.
 *
 * Nothing raised. `equals` rules silently never fired and `notEquals` rules silently fired every
 * time, on the twelve of nineteen field types that parse to an object. A field a rule was supposed
 * to require stayed optional, and a form validated.
 *
 * The suite did not catch it because every rule test used a text or boolean field, where the
 * parsed value happens to be the scalar that was submitted. So these cases are deliberately the
 * ones that are *not* strings.
 */
#[Group('rule')]
#[CoversClass(Comparison::class)]
#[CoversClass(Equals::class)]
#[CoversClass(NotEquals::class)]
#[CoversClass(Facade::class)]
final class ComparisonTest extends TestCase
{
	/**
	 * Each row: how to build the subject field, what the author writes, what someone submits.
	 *
	 * The expectation and the submission are written differently on purpose where the field
	 * allows it — an author writing `18` and a form posting `'18'` is the ordinary case, and the
	 * whole point is that the field decides they are the same number.
	 *
	 * @return array<string, array{string, list<mixed>, mixed, mixed}>
	 */
	public static function fieldsWhoseValuesAreNotStrings(): array
	{
		return [
			'Number, written as int and submitted as string' => ['createNumberField', [], 18, '18'],
			'Number, written and submitted as string' => ['createNumberField', [], '18', '18'],
			'Date' => ['createDateField', [], '2030-01-01', '2030-01-01'],
			'Time' => ['createTimeField', [], '09:30', '09:30'],
			'Duration' => ['createDurationField', [], 'PT1H', 'PT1H'],
			'EmailAddress' => ['createEmailAddressField', [], 'a@example.test', 'a@example.test'],
		];
	}

	#[Test]
	#[DataProvider('fieldsWhoseValuesAreNotStrings')]
	public function equals_fires_when_the_field_reads_both_sides_as_the_same_value(
		string $build,
		array $args,
		mixed $expected,
		mixed $submitted,
	): void {
		$schema = self::schemaComparing($build, $args, $expected, equals: true);

		$this->assertTrue(
			$schema->validate((object) ['subject' => $submitted])->forField('target')->wasAlteredByRule(),
		);
	}

	/**
	 * The dangerous half. An always-false `equals` is a rule that does nothing; an always-true
	 * `notEquals` is a rule that does its thing on every request, including the ones it was
	 * written to exclude.
	 */
	#[Test]
	#[DataProvider('fieldsWhoseValuesAreNotStrings')]
	public function not_equals_stays_quiet_when_the_value_is_the_expected_one(
		string $build,
		array $args,
		mixed $expected,
		mixed $submitted,
	): void {
		$schema = self::schemaComparing($build, $args, $expected, equals: false);

		$this->assertFalse(
			$schema->validate((object) ['subject' => $submitted])->forField('target')->wasAlteredByRule(),
		);
	}

	#[Test]
	#[DataProvider('fieldsWhoseValuesAreNotStrings')]
	public function not_equals_fires_when_the_value_is_something_else(
		string $build,
		array $args,
		mixed $expected,
		mixed $submitted,
	): void {
		$schema = self::schemaComparing($build, $args, $expected, equals: false);

		$this->assertTrue(
			$schema->validate((object) ['subject' => null])->forField('target')->wasAlteredByRule(),
		);
	}

	/**
	 * A definition property is not run through `parse()`.
	 *
	 * `#/fields/nick/minLength` is an `int` because `Text` declares it one — not because anything
	 * parsed it — and putting `3` through `Text::parse()` to compare against it would yield `null`
	 * and break a comparison that works.
	 */
	#[Test]
	public function a_definition_property_is_compared_as_it_stands(): void
	{
		$schema = new Facade('properties');
		$schema->add($schema->createTextField('nick')->minLengthOf(3), $schema->createTextField('target'));
		$schema->addRule(
			$schema->when(PropertyScope::of('nick', 'minLength'))->equals(3)->thenRequire('target'),
		);

		$this->assertTrue(
			$schema->validate((object) ['nick' => 'abc'])->forField('target')->wasAlteredByRule(),
		);
	}

	/**
	 * `null` means "nothing was given", and must not be read as the field's default.
	 *
	 * `resolvedValueFor(null)` answers with the authored default, so parsing the expectation here
	 * would quietly turn "when this was left empty" into "when this equals its default" — a rule
	 * that fires on precisely the requests it was written to ignore.
	 */
	#[Test]
	public function null_is_never_read_as_the_fields_default(): void
	{
		$schema = new Facade('absence');
		$schema->add(
			$schema->createNumberField('qty')->defaultsTo(5),
			$schema->createTextField('target'),
		);
		$schema->addRule($schema->when('qty')->equals(null)->thenRequire('target'));

		// Nothing submitted, so the default stands in — and the rule must still not match,
		// because null is the absence and not the 5 that replaced it.
		$this->assertFalse(
			$schema->validate((object) [])->forField('target')->wasAlteredByRule(),
		);
	}

	#[Test]
	public function a_comparison_the_field_could_never_satisfy_is_refused_where_it_is_written(): void
	{
		$schema = new Facade('dead');
		$schema->add($schema->createNumberField('age'), $schema->createTextField('licence'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("compares \"#/fields/age/value\" against 'eighteen'");

		$schema->addRule($schema->when('age')->equals('eighteen')->thenRequire('licence'));
	}

	#[Test]
	public function a_dead_comparison_is_found_inside_a_composed_condition(): void
	{
		$schema = new Facade('dead-group');
		$schema->add(
			$schema->createTextField('plan'),
			$schema->createNumberField('age'),
			$schema->createTextField('licence'),
		);

		$this->expectException(InvalidArgumentException::class);

		$schema->addRule(
			$schema->allOf(
				$schema->when('plan')->equals('pro'),
				$schema->when('age')->equals('eighteen'),
			)->thenRequire('licence'),
		);
	}

	/**
	 * Only the *shape* is checked, never the constraints. A rule reacting to input that is going
	 * to fail is a perfectly ordinary thing to write, and refusing it would be this guard
	 * overreaching.
	 */
	#[Test]
	public function an_expectation_that_will_fail_its_constraints_is_still_allowed(): void
	{
		$schema = new Facade('short');
		$schema->add($schema->createTextField('nick')->minLengthOf(3), $schema->createTextField('target'));

		$schema->addRule($schema->when('nick')->equals('xy')->thenRequire('target'));

		$this->assertTrue(
			$schema->validate((object) ['nick' => 'xy'])->forField('target')->wasAlteredByRule(),
		);
	}

	/**
	 * @param list<mixed> $args
	 */
	private static function schemaComparing(string $build, array $args, mixed $expected, bool $equals): Facade
	{
		$schema = new Facade('comparison');
		$schema->add($schema->{$build}('subject', ...$args), $schema->createTextField('target'));

		$matcher = $schema->when('subject');

		$schema->addRule(
			($equals ? $matcher->equals($expected) : $matcher->notEquals($expected))->thenRequire('target'),
		);

		return $schema;
	}
}
