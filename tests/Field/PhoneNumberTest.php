<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\PhoneNumber;
use Meraki\Schema\Field\PhoneNumber\Type;
use Meraki\Schema\FieldName;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\Field\PhoneNumber\Value;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('field')]
#[CoversClass(PhoneNumber::class)]
#[CoversClass(Type::class)]
final class PhoneNumberTest extends FieldTestCase
{
	public function createField(): PhoneNumber
	{
		return new PhoneNumber(new FieldName('phone'));
	}

	/** @return array{number: string, country: string} */
	private static function pair(string $number = '0411 222 333', string $country = 'AU'): array
	{
		return ['number' => $number, 'country' => $country];
	}

	/**
	 * The field hands back its own value object, and E.164 is what that compares on — so the
	 * canonical form is a method on the value rather than something a caller assembles.
	 */
	private static function e164(Value $number): string
	{
		return $number->toE164();
	}

	// ── a number is submitted with its country ────────────────────────────────────────────

	#[Test]
	public function a_number_is_read_together_with_its_country(): void
	{
		$resolved = (new PhoneNumber(new FieldName('phone'), ['AU']))->validate((object) self::pair());

		$this->assertFalse($resolved->anyFailed());
		$this->assertSame('+61411222333', self::e164($resolved->value));
	}

	#[Test]
	public function an_e164_number_still_states_its_country(): void
	{
		// Looks redundant and is not: `+1` covers twenty-five regions, so E.164 alone cannot say
		// whether a number is American or Canadian. One rule beats two.
		$resolved = (new PhoneNumber(new FieldName('phone'), ['AU']))->validate((object) self::pair('+61411222333'));

		$this->assertFalse($resolved->anyFailed());
	}

	#[Test]
	#[DataProvider('incompletePairs')]
	public function half_a_pair_never_described_a_number(mixed $given): void
	{
		// A shape failure rather than a constraint one: there is nothing to report against,
		// because the input did not describe a phone number at all. This replaced an
		// `unambiguous` constraint whose only job was to ask for the missing half.
		$resolved = (new PhoneNumber(new FieldName('phone'), ['AU']))->validate((object) $given);

		$this->assertShapeFailed($resolved);
		$this->assertConstraintValidationResultSkipped('allowedCountries', $resolved);
	}

	/** @return array<string, array{mixed}> */
	public static function incompletePairs(): array
	{
		return [
			'a bare national string' => ['0411 222 333'],
			'a bare E.164 string' => ['+61411222333'],
			'a number with no country' => [['number' => '0411 222 333']],
			'a country with no number' => [['country' => 'AU']],
			'an empty country' => [['number' => '0411 222 333', 'country' => '']],
			'an empty number' => [['number' => '', 'country' => 'AU']],
			'neither' => [[]],
			'not an array at all' => [12345],
		];
	}

	#[Test]
	#[DataProvider('pairsThatMayNotAgree')]
	public function the_two_halves_must_agree(string $number, string $country, bool $agrees): void
	{
		// libphonenumber ignores the region it is handed once a number is E.164, so this is
		// checked rather than assumed — otherwise `+61…` declared `US` would pass with the
		// number and its own country metadata contradicting each other.
		$resolved = $this->createField()->validate((object) self::pair($number, $country));

		$this->assertSame($agrees, $resolved->shape->passed(), "{$number} as {$country}");
	}

	/** @return array<string, array{string, string, bool}> */
	public static function pairsThatMayNotAgree(): array
	{
		return [
			'an Australian number said to be Australian' => ['+61411222333', 'AU', true],
			'an Australian number said to be American' => ['+61411222333', 'US', false],
			// +1 is shared, so the area code is what separates these two.
			'a New York number said to be American' => ['+12125551234', 'US', true],
			'a New York number said to be Canadian' => ['+12125551234', 'CA', false],
			'a Vancouver number said to be Canadian' => ['+16045551234', 'CA', true],
			'a national number against the wrong country' => ['0411 222 333', 'NZ', false],
		];
	}

	#[Test]
	public function a_number_that_is_not_a_number_fails_the_shape(): void
	{
		$this->assertShapeFailed($this->createField()->validate((object) self::pair('not a number')));
	}

	#[Test]
	public function the_ambiguity_machinery_is_gone(): void
	{
		// Asking for the country outright removed the need for a constraint whose only job was to
		// report that it was missing, and for the rule that guessed when exactly one country was
		// allowed.
		$this->assertNotContains('unambiguous', $this->createField()->constraints->names);
	}

	// ── which countries are acceptable ────────────────────────────────────────────────────

	#[Test]
	public function any_country_is_accepted_by_default(): void
	{
		$field = $this->createField();

		$this->assertSame([], $field->allowedCountries);
		$this->assertConstraintValidationResultSkipped('allowedCountries', $field->validate((object) self::pair()));
	}

	#[Test]
	public function a_country_outside_the_allow_list_is_reported(): void
	{
		// Separate question from "which country is this": the pair says where the number is from,
		// and this says whether the field accepts numbers from there. So the shape passes and the
		// constraint fails, which is what lets a form say the useful thing.
		$resolved = (new PhoneNumber(new FieldName('phone'), ['AU']))
			->validate((object) self::pair('+6421222333', 'NZ'));

		$this->assertShapePassed($resolved);
		$this->assertConstraintValidationResultFailed('allowedCountries', $resolved);
	}

	#[Test]
	public function countries_accumulate_and_are_stored_upper_cased(): void
	{
		$field = (new PhoneNumber(new FieldName('phone'), ['au']))->allowCountries('nz', 'US');

		$this->assertSame(['AU', 'NZ', 'US'], $field->allowedCountries);
	}

	#[Test]
	public function the_allow_list_can_be_cleared_again(): void
	{
		$field = (new PhoneNumber(new FieldName('phone'), ['AU']))->clearAllowedCountries();

		$this->assertSame([], $field->allowedCountries);
	}

	#[Test]
	public function a_region_libphonenumber_does_not_know_is_refused_where_it_is_written(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new PhoneNumber(new FieldName('phone'), ['ZZ']);
	}

	// ── what kind of number ───────────────────────────────────────────────────────────────

	#[Test]
	public function any_type_is_accepted_by_default(): void
	{
		$field = $this->createField();

		$this->assertSame(Type::Any, $field->numberType);
		$this->assertConstraintValidationResultSkipped('numberType', $field->validate((object) self::pair()));
	}

	#[Test]
	public function a_mobile_is_reported_when_a_fixed_line_was_asked_for(): void
	{
		$field = $this->createField()->ofType(Type::FixedLine);

		$this->assertConstraintValidationResultFailed('numberType', $field->validate((object) self::pair('0411 222 333')));
		$this->assertConstraintValidationResultPassed('numberType', $field->validate((object) self::pair('(02) 9374 4000')));
	}

	#[Test]
	public function either_accepts_a_mobile_or_a_fixed_line(): void
	{
		$field = $this->createField()->ofType(Type::Either);

		$this->assertConstraintValidationResultPassed('numberType', $field->validate((object) self::pair('0411 222 333')));
		$this->assertConstraintValidationResultPassed('numberType', $field->validate((object) self::pair('(02) 9374 4000')));
	}

	#[Test]
	public function configuring_it_leaves_the_original_alone(): void
	{
		$field = new PhoneNumber(new FieldName('phone'), ['AU']);
		$restricted = $field->ofType(Type::Mobile)->allowCountries('NZ');

		$this->assertNotSame($field, $restricted);
		$this->assertSame(Type::Any, $field->numberType);
		$this->assertSame(['AU'], $field->allowedCountries);
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$this->assertNull($this->createField()->defaultValue);
	}
}
