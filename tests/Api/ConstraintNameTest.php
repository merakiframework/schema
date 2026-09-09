<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\Property;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

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
		$method = new ReflectionMethod($field, 'getConstraints');
		$method->setAccessible(true);

		$this->assertSame($expected, array_keys($method->invoke($field)));
	}

	/** @return iterable<string, array{string, list<string>}> */
	public static function constraintNames(): iterable
	{
		$names = [
			'Text' => ['minLength', 'maxLength', 'pattern'],
			'Name' => ['minLength', 'maxLength'],
			'Number' => ['minValue', 'maxValue', 'step', 'scale'],
			'Duration' => ['minValue', 'maxValue', 'step'],

			'Date' => ['from', 'until', 'interval'],
			'Time' => ['from', 'until', 'interval'],
			'DateTime' => ['from', 'until', 'interval'],

			'EmailAddress' => ['minLength', 'maxLength', 'allowedDomains', 'disallowedDomains'],
			'Uri' => ['minLength', 'maxLength', 'allowedSchemes'],
			'Uuid' => ['allowedVersions'],
			'PhoneNumber' => ['allowedCountries', 'numberType', 'unambiguous'],

			'Password' => [
				'minLength', 'maxLength', 'maxBytes', 'minStrength',
				'minUppercaseChars', 'maxUppercaseChars',
				'minLowercaseChars', 'maxLowercaseChars',
				'minDigits', 'maxDigits',
				'minSymbols', 'maxSymbols',
			],

			'File' => ['minSize', 'maxSize', 'allowedTypes', 'disallowedTypes'],

			// Structured types: flat, and no longer prefixed with the field's own name.
			'Money' => ['allowedCurrencies', 'minAmount', 'maxAmount', 'scale'],
			'Address' => ['allowedCountries', 'postalCodeFormat', 'line1Visitable', 'specific'],
			'CreditCard' => ['numberChecksum', 'expiryInFuture'],

			// The list is the type, so membership is shape rather than a constraint.
			'Enum' => [],
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
		$money = new Field\Money(new Property\Name('cost'), ['AUD' => 2]);
		$money->minAmountOf('AUD', '10.00');

		$failed = $money->validate(['currency' => 'AUD', 'amount' => '5.00'])->get('minAmount');

		$this->assertSame('minAmount', $failed->name);
		$this->assertSame('amount', $failed->part);
	}

	#[Test]
	public function a_constraint_about_the_whole_field_has_no_part(): void
	{
		// Something the dotted scheme could not express at all.
		$text = new Field\Text(new Property\Name('bio'));
		$text->minLengthOf(10);

		$this->assertNull($text->validate('short')->get('minLength')->part);
	}

	#[Test]
	public function a_constraint_reports_the_bound_that_applied(): void
	{
		// So a message never reads $field->{$constraint->name}: that is a dynamic property
		// access, which static analysis cannot type, and which renders "Array" for a bound
		// held as a map.
		$text = new Field\Text(new Property\Name('bio'));
		$text->minLengthOf(10);

		$this->assertSame(10, $text->validate('short')->get('minLength')->bound);
	}

	#[Test]
	public function a_per_currency_bound_reports_the_one_that_applied(): void
	{
		// Money's minimum is a map keyed by currency. The result carries the value for the
		// currency actually submitted, already resolved.
		$money = new Field\Money(new Property\Name('cost'), ['AUD' => 2, 'USD' => 2]);
		$money->minAmountOf('AUD', '10.00')->minAmountOf('USD', '7.00');

		$failed = $money->validate(['currency' => 'USD', 'amount' => '5.00'])->get('minAmount');

		$this->assertSame('7.00', (string) $failed->bound);
	}

	#[Test]
	public function a_constraint_with_nothing_to_interpolate_reports_a_null_bound(): void
	{
		$address = new Field\Address(new Property\Name('billing'), ['AU']);

		$failed = $address->validate([
			'line1' => 'PO Box 42',
			'locality' => 'Rockhampton',
			'postal_code' => '4700',
			'country_code' => 'AU',
		])->get('line1Visitable');

		$this->assertNull($failed->bound);
		$this->assertSame('line1', $failed->part);
	}

	private static function build(string $fqcn): Field
	{
		$name = new Property\Name('f');

		return match ($fqcn) {
			Field\Enum::class => new Field\Enum($name, ['a', 'b']),
			Field\Money::class => new Field\Money($name, ['AUD' => 2]),
			Field\Address::class => new Field\Address($name, ['AU']),
			Field\PhoneNumber::class => new Field\PhoneNumber($name, ['AU']),
			default => new $fqcn($name),
		};
	}
}
