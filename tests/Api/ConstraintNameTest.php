<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The names a field reports a failure under. These are the strings `meraki/schema-html`
 * dispatches on, so they are the most expensive thing in the review to get wrong.
 *
 * `type` does not appear: it is removed, and missing / malformed / constraint-failed become
 * structurally distinct on the resolved field instead.
 */
#[CoversNothing]
#[Group('api-2.0')]
final class ConstraintNameTest extends TestCase
{
	#[Test]
	#[DataProvider('constraintNames')]
	public function a_field_reports_its_constraints_under_the_agreed_names(string $class, array $expected): void
	{
		$fqcn = 'Meraki\\Schema\\Field\\' . $class;

		if (!class_exists($fqcn)) {
			$this->fail("Field {$class} does not exist.");
		}

		$field = self::build($fqcn);

		// `$constraints` is public and the set knows the name each one reports under, so this
		// asks the field directly. It used to reflect on a `getConstraints()` method that
		// returned a name-keyed array; both are gone.
		$this->assertSame($expected, $field->constraints->names);
	}

	/** @return iterable<string, array{string, list<string>}> */
	public static function constraintNames(): iterable
	{
		$names = [
			'Text' => ['minLength', 'maxLength', 'pattern'],
			'Name' => ['minLength', 'maxLength'],

			// `scale` and `maxPrecision` are different questions and neither implies the other:
			// scale counts digits after the point and is exact, precision counts significant
			// digits wherever they fall and is a ceiling.
			'Number' => ['minValue', 'maxValue', 'step', 'scale', 'maxPrecision'],
			'Duration' => ['minValue', 'maxValue', 'step'],

			// `precision` is what a field accepts, not what it stores — a time field told to take
			// minutes refuses a value carrying seconds rather than truncating it.
			// Four bounds, four names. `from`/`after` are the inclusive and exclusive lower
			// bounds and `until`/`through` the exclusive and inclusive upper ones, each
			// reporting under its own name so a verdict says which the author declared — and
			// so a language pack can word "before 17:00" differently from "at or before
			// 17:00". An earlier inclusive `to()` was removed for sharing `until`'s name; the
			// objection was the shared name, not the choice.
			'Date' => ['from', 'after', 'until', 'through', 'interval'],
			'Time' => ['from', 'after', 'until', 'through', 'interval', 'precision'],
			'DateTime' => ['from', 'after', 'until', 'through', 'interval', 'precision'],

			'Boolean' => ['accepted'],
			'EmailAddress' => ['minLength', 'maxLength', 'allowedDomains', 'disallowedDomains'],
			'Uri' => ['minLength', 'maxLength', 'allowedSchemes'],
			'Uuid' => ['allowedVersions'],

			// No `unambiguous`: a number is submitted with its country, so there is no ambiguity
			// left for a constraint to report. The pairing settles it before any check runs —
			// and whether the pair is a number at all is assembly, below.
			'PhoneNumber' => ['allowedCountries', 'numberType'],

			// No `maxBytes`, and no composition *maximums*. What a hashing algorithm can swallow
			// is the hashing layer's business — see Field\Password — and a maximum number of
			// digits or symbols is a rule with no security argument behind it.
			'Password' => [
				'minLength', 'maxLength', 'minStrength',
				'minUppercaseChars', 'minLowercaseChars', 'minDigits', 'minSymbols',
			],

			'File' => ['minSize', 'maxSize', 'allowedTypes', 'disallowedTypes'],

			// Structured types: flat, and no longer prefixed with the field's own name. Each part
			// a constraint concerns is carried on the result as `part` rather than spelled into
			// the name, which is what let the dotted names go.
			//
			// Whether the halves make money at all is assembly, so `currencyRequired` and its
			// three siblings are not constraints — see the test below for every code.
			'Money' => ['knownCurrency', 'allowedCurrencies', 'minAmount', 'maxAmount', 'scale'],
			// What this field accepts: which countries, how much of the address its precision
			// floor demands, and whether it must be somewhere a person can go. Whether the parts
			// make an address in their own country is assembly, below.
			'Address' => [
				'allowedCountries',
				'streetVisitable',
				'streetRequired', 'localityRequired', 'subdivisionRequired', 'postalCodeRequired',
			],
			// The two that ask what day it is. Everything else about a card is assembly — see
			// below — and there is no `nameRequired`: the name is optional, like the security
			// code.
			'CreditCard' => ['expiryInFuture', 'expiryWithinReach'],

			// A collection bounds the list and refuses repeats; each item is checked against the
			// template and reports under the template field's own names.
			'Collection' => ['minCount', 'maxCount', 'unique'],

			// The list is the type, so membership is shape rather than a constraint. A renderer
			// reads $cases to draw the options anyway, so a bound carrying them adds nothing.
			'Enum' => [],
		];

		foreach ($names as $class => $expected) {
			yield $class => [$class, $expected];
		}
	}

	/**
	 * The codes a field reports before any constraint runs — whether a record's parts make a value
	 * at all — under the agreed names.
	 *
	 * Every code a field declares is either one of these or a constraint's, and a language pack
	 * words both under the same kind of key: the step a code belongs to is a fact about the result,
	 * never part of its name, so a check can move between them without a pack noticing.
	 *
	 * @param list<string> $expected
	 */
	#[Test]
	#[DataProvider('assemblyCodes')]
	public function a_field_reports_whether_its_parts_make_a_value_under_the_agreed_codes(string $class, array $expected): void
	{
		$field = self::build($class);
		$constraints = $field->constraints->names;

		$this->assertSame($expected, array_values(array_filter(
			array_column($field->checks, 'value'),
			static fn(string $code): bool => !in_array($code, $constraints, true),
		)));
	}

	/** @return iterable<string, array{class-string<Field>, list<string>}> */
	public static function assemblyCodes(): iterable
	{
		$codes = [
			// Whether the halves make money at all. No configuration changes any of these, which
			// is what makes them assembly rather than constraints — see docs/DESIGN.md.
			'Money' => ['currencyRequired', 'amountRequired', 'currencyFormat', 'amountFormat'],
			// A number is only a number in a country, so whether it is valid *there* is part of
			// whether it is a number at all.
			'PhoneNumber' => ['numberRequired', 'countryRequired', 'numberFormat', 'knownCountry', 'numberInCountry'],
			// A card number that fails Luhn is not a card number on any field there will ever
			// be, so the checksum is assembly too.
			'CreditCard' => [
				'numberRequired', 'expiryRequired',
				'numberFormat', 'numberChecksum', 'expiryFormat', 'nameFormat', 'securityCodeFormat',
			],
			// A country's published format is reference data, the same for every field there will
			// ever be. Only the country is essential; the street and the rest are demanded, by
			// the precision floor, so their `*Required` codes are constraints.
			'Address' => [
				'countryRequired', 'knownCountry',
				'streetFormat', 'streetLineLimit',
				'dependentLocalityFormat', 'dependentLocalityUsed',
				'localityFormat', 'localityUsed',
				'knownSubdivision', 'subdivisionUsed',
				'postalCodeFormat', 'postalCodeUsed',
			],
			// An upload described without its size is not described on any field.
			'File' => ['nameRequired', 'typeRequired', 'sizeRequired', 'nameFormat', 'typeFormat', 'sizeFormat'],
		];

		foreach (SealedFieldTest::fields() as $short => [$class]) {
			yield $short => [$class, $codes[$short] ?? []];
		}
	}

	/**
	 * A constraint is named by a case of the field's own enum, and the field lists every case.
	 *
	 * So a pack, a port or a test can know every failure a field may report without validating
	 * anything — and a constraint can never be reported under a code its field does not declare.
	 */
	#[Test]
	#[DataProvider('everyField')]
	public function every_constraint_is_named_by_a_code_its_field_declares(string $class): void
	{
		$field = self::build($class);
		$undeclared = [];

		foreach ($field->constraints as $constraint) {
			if (!in_array($constraint->code, $field->checks, true)) {
				$undeclared[] = $constraint->name;
			}
		}

		$this->assertSame([], $undeclared, "{$class} reports codes it does not declare.");
	}

	/**
	 * The wire name is the case's value, and it is a string: a language pack's key, a serialised
	 * schema's word. An int-backed enum would satisfy the interface and break both.
	 */
	#[Test]
	#[DataProvider('everyField')]
	public function every_code_is_named_by_a_string(string $class): void
	{
		$notStrings = array_values(array_filter(
			self::build($class)->checks,
			static fn(Field\Check $check): bool => !is_string($check->value),
		));

		$this->assertSame([], $notStrings, "{$class} has codes a language pack could not key a message by.");
	}

	/**
	 * A code's part is one the field's value has — so a failure can always be put beside an
	 * input the form actually drew.
	 */
	#[Test]
	#[DataProvider('everyField')]
	public function every_code_is_about_a_part_the_field_has_or_about_the_whole_value(string $class): void
	{
		$field = self::build($class);
		$strays = [];

		foreach ($field->checks as $check) {
			$part = $check->part();

			if ($part !== null && !in_array($part, $field->parts, true)) {
				$strays[] = sprintf('%s::%s', $check::class, $check->name);
			}
		}

		$this->assertSame([], $strays, "{$class} has codes about parts it does not have.");
	}

	/** @return iterable<string, array{class-string<Field>}> */
	public static function everyField(): iterable
	{
		foreach (SealedFieldTest::fields() as $short => [$class]) {
			yield $short => [$class];
		}
	}

	#[Test]
	public function a_constraint_can_be_looked_up_by_its_code_or_by_its_wire_name(): void
	{
		$text = (new Field\Text(new FieldName('bio')))->minLengthOf(10);
		$result = $text->validate('short');

		$this->assertSame($result->forConstraint(Field\Text\Check::MinLength), $result->forConstraint('minLength'));
		$this->assertSame(Field\Text\Check::MinLength, $result->forConstraint('minLength')->code);
		$this->assertSame(10, $text->constraints->named(Field\Text\Check::MinLength)->bound);
	}

	#[Test]
	public function a_constraint_reports_the_part_it_belongs_to(): void
	{
		// A structured type reports flat names and says which part failed, so a consumer
		// never splits a string to find out. `cost.amount.min` becomes `minAmount` + a part.
		// Assigned: a field is immutable, and a wither's copy that is thrown away bounds nothing —
		// which left this asserting the name and part of a skipped verdict.
		$money = (new Field\Money(new FieldName('cost'), ['AUD' => 2]))->minAmountOf('AUD', '10.00');

		$failed = $money->validate((object)['currency' => 'AUD', 'amount' => '5.00'])->forConstraint('minAmount');

		$this->assertTrue($failed->failed());
		$this->assertSame('minAmount', $failed->name);
		$this->assertSame(Field\Money\Part::Amount, $failed->part);
	}

	#[Test]
	public function a_constraint_about_the_whole_field_has_no_part(): void
	{
		// Something the dotted scheme could not express at all.
		$text = (new Field\Text(new FieldName('bio')))->minLengthOf(10);

		$this->assertNull($text->validate('short')->forConstraint('minLength')->part);
	}

	#[Test]
	public function a_constraint_reports_the_bound_that_applied(): void
	{
		// So a message never reads $field->{$constraint->name}: that is a dynamic property
		// access, which static analysis cannot type, and which renders "Array" for a bound
		// held as a map.
		$text = (new Field\Text(new FieldName('bio')))->minLengthOf(10);

		$this->assertSame(10, $text->validate('short')->forConstraint('minLength')->bound);
	}

	#[Test]
	public function a_per_currency_bound_reports_the_one_that_applied(): void
	{
		// Money's minimum is a map keyed by currency. The result carries the value for the
		// currency actually submitted, already resolved.
		$money = (new Field\Money(new FieldName('cost'), ['AUD' => 2, 'USD' => 2]))
			->minAmountOf('AUD', '10.00')
			->minAmountOf('USD', '7.00');

		$failed = $money->validate((object)['currency' => 'USD', 'amount' => '5.00'])->forConstraint('minAmount');

		$this->assertSame('7.00', (string) $failed->bound);
	}

	#[Test]
	public function a_constraint_with_nothing_to_interpolate_reports_a_null_bound(): void
	{
		// An object and `country`, not an array and `country_code`. Both mistakes were in here and
		// both made the address unreadable, so every constraint came back *skipped* — and a skipped
		// one reports the declared bound, which for this constraint is null. The assertions passed
		// without the constraint ever having run.
		$address = (new Field\Address(new FieldName('billing'), ['AU']))->mustBeVisitable();

		$failed = $address->validate((object) [
			'street' => ['PO Box 42'],
			'locality' => 'Rockhampton',
			'subdivision' => 'QLD',
			'postal_code' => '4700',
			'country' => 'AU',
		])->forConstraint('streetVisitable');

		$this->assertTrue($failed->failed());
		$this->assertNull($failed->bound);
		$this->assertSame(Field\Address\Part::Street, $failed->part);
	}

	private static function build(string $fqcn): Field
	{
		$name = new FieldName('f');

		return match ($fqcn) {
			Field\Enum::class => new Field\Enum($name, ['a', 'b']),
			Field\Money::class => new Field\Money($name, ['AUD' => 2]),
			Field\Address::class => new Field\Address($name, ['AU']),
			Field\PhoneNumber::class => new Field\PhoneNumber($name, ['AU']),
			// A collection's template is variadic and must not be empty: one field is enough
			// to build a valid one, and the names asserted here are the collection's own.
			Field\Collection::class => new Field\Collection($name, new Field\Text(new FieldName('item'))),
			default => new $fqcn($name),
		};
	}
}
