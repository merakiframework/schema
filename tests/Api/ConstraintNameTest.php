<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The names a field reports a failure under. These are the strings `meraki/schema-html`
 * dispatches on, so they are the most expensive thing in the review to get wrong.
 *
 * `type` does not appear: it is removed, and missing / malformed / constraint-failed become
 * structurally distinct on the resolved field instead.
 */
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
			'Date' => ['from', 'until', 'interval'],
			'Time' => ['from', 'until', 'interval', 'precision'],
			'DateTime' => ['from', 'until', 'interval', 'precision'],

			'Boolean' => ['accepted'],
			'EmailAddress' => ['minLength', 'maxLength', 'allowedDomains', 'disallowedDomains'],
			'Uri' => ['minLength', 'maxLength', 'allowedSchemes'],
			'Uuid' => ['allowedVersions'],

			// No `unambiguous`: a number is submitted with its country, so there is no ambiguity
			// left for a constraint to report. The pairing settles it before any check runs.
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
			'Money' => ['allowedCurrencies', 'minAmount', 'maxAmount', 'scale'],
			'Address' => ['allowedCountries', 'postalCodeFormat', 'administrativeArea', 'line1Visitable', 'specific'],
			'CreditCard' => [
				'numberFormat', 'numberChecksum',
				'expiryFormat', 'expiryInFuture', 'expiryWithinReach',
				'namePresent', 'securityCodeFormat',
			],

			// A collection bounds the list and refuses repeats; each item is checked against the
			// template and reports under the template field's own names.
			'Collection' => ['minCount', 'maxCount', 'unique'],

			// Membership is a constraint, not the shape. It is the one field where membership is
			// the whole point, and reporting it as "unreadable" left a renderer with no name to
			// match on and no list to interpolate.
			'Enum' => ['allowedCases'],
		];

		foreach ($names as $class => $expected) {
			yield $class => [$class, $expected];
		}
	}

	#[Test]
	public function a_constraint_reports_the_part_it_belongs_to(): void
	{
		// A structured type reports flat names and says which part failed, so a consumer
		// never splits a string to find out. `cost.amount.min` becomes `minAmount` + a part.
		$money = new Field\Money(new FieldName('cost'), ['AUD' => 2]);
		$money->minAmountOf('AUD', '10.00');

		$failed = $money->validate((object)['currency' => 'AUD', 'amount' => '5.00'])->forConstraint('minAmount');

		$this->assertSame('minAmount', $failed->name);
		$this->assertSame('amount', $failed->part);
	}

	#[Test]
	public function a_constraint_about_the_whole_field_has_no_part(): void
	{
		// Something the dotted scheme could not express at all.
		$text = new Field\Text(new FieldName('bio'));
		$text->minLengthOf(10);

		$this->assertNull($text->validate('short')->forConstraint('minLength')->part);
	}

	#[Test]
	public function a_constraint_reports_the_bound_that_applied(): void
	{
		// So a message never reads $field->{$constraint->name}: that is a dynamic property
		// access, which static analysis cannot type, and which renders "Array" for a bound
		// held as a map.
		$text = new Field\Text(new FieldName('bio'));
		$text->minLengthOf(10);

		$this->assertSame(10, $text->validate('short')->forConstraint('minLength')->bound);
	}

	#[Test]
	public function a_per_currency_bound_reports_the_one_that_applied(): void
	{
		// Money's minimum is a map keyed by currency. The result carries the value for the
		// currency actually submitted, already resolved.
		$money = new Field\Money(new FieldName('cost'), ['AUD' => 2, 'USD' => 2]);
		$money->minAmountOf('AUD', '10.00')->minAmountOf('USD', '7.00');

		$failed = $money->validate((object)['currency' => 'USD', 'amount' => '5.00'])->forConstraint('minAmount');

		$this->assertSame('7.00', (string) $failed->bound);
	}

	#[Test]
	public function a_constraint_with_nothing_to_interpolate_reports_a_null_bound(): void
	{
		$address = new Field\Address(new FieldName('billing'), ['AU']);

		$failed = $address->validate([
			'line1' => 'PO Box 42',
			'locality' => 'Rockhampton',
			'postal_code' => '4700',
			'country_code' => 'AU',
		])->forConstraint('line1Visitable');

		$this->assertNull($failed->bound);
		$this->assertSame('line1', $failed->part);
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
