<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Field;
use Meraki\Schema\FieldName;
use Meraki\Schema\Field\PhoneNumber\Value;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * `allow()` used to do two jobs at once: constrain which countries were acceptable, and
 * supply the region a national-format number was parsed against. Those are separated, and
 * the second becomes a stated rule rather than a side effect.
 */
#[Group('api-2.0')]
#[CoversClass(Field\PhoneNumber::class)]
#[CoversClass(Field\PhoneNumber\Type::class)]
final class PhoneNumberTest extends TestCase
{
	private function field(string ...$countries): Field\PhoneNumber
	{
		return new Field\PhoneNumber(new FieldName('phone'), $countries);
	}

	#[Test]
	public function a_number_is_submitted_with_its_country(): void
	{
		// The same pairing Money makes between an amount and its currency, and for the same
		// reason: a number means nothing until you know where it is from.
		$resolved = $this->field('AU')->validate((object)['number' => '0411 222 333', 'country' => 'AU']);

		$this->assertFalse($resolved->anyFailed());

		// The field's own value object, not libphonenumber's class. Every field parses to one it
		// defines, so nothing downstream compares two numbers by a layout somebody else controls.
		$this->assertInstanceOf(Value::class, $resolved->value);
		$this->assertInstanceOf(LibPhoneNumber::class, $resolved->value->number);
	}

	#[Test]
	public function a_number_without_its_country_never_described_a_number(): void
	{
		// A shape failure, not a constraint one. This replaced an `unambiguous` constraint whose
		// only job was to report the missing half — asking for it outright removes the need.
		$resolved = $this->field('AU', 'NZ')->validate('0411 222 333');

		$this->assertTrue($resolved->shape->failed());
		$this->assertNull($resolved->forConstraint('unambiguous'));
	}

	#[Test]
	public function an_e164_number_states_its_country_too(): void
	{
		// Not redundant: +1 covers twenty-five regions, so E.164 alone cannot say whether a
		// number is American or Canadian. libphonenumber answers `null` when asked.
		$resolved = $this->field('AU')->validate((object)['number' => '+61411222333', 'country' => 'AU']);

		$this->assertFalse($resolved->anyFailed());
	}

	#[Test]
	public function the_declared_country_must_match_the_number(): void
	{
		$resolved = $this->field()->validate((object)['number' => '+61411222333', 'country' => 'US']);

		$this->assertTrue($resolved->shape->failed());
	}

	#[Test]
	public function a_number_from_a_country_that_is_not_allowed_fails(): void
	{
		$this->assertTrue(
			$this->field('AU')->validate((object)['number' => '+12125551234', 'country' => 'US'])
				->forConstraint('allowedCountries')->failed(),
		);
	}

	#[Test]
	public function the_value_is_the_parsed_number(): void
	{
		// libphonenumber's own type, the way a Date hands back a brick/date-time LocalDate. The
		// library does not pick an output format on the application's behalf; E.164 is one call
		// away for anyone who wants it.
		$value = $this->field('AU')->validate((object)['number' => '0411 222 333', 'country' => 'AU'])->value;

		$this->assertInstanceOf(Value::class, $value);

		// libphonenumber's parsed number is still right there, unchanged.
		$this->assertInstanceOf(LibPhoneNumber::class, $value->number);

		// And E.164 is now the value's own business, because it is what the value compares on:
		// `0411 222 333` and `+61411222333` are one number, and only the canonical form says so.
		$this->assertSame('+61411222333', $value->toE164());
		$this->assertSame(
			'+61411222333',
			PhoneNumberUtil::getInstance()->format($value->number, PhoneNumberFormat::E164),
		);
	}
}