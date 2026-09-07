<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Password;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use Meraki\Schema\ValidationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use InvalidArgumentException;

#[Group('field')]
#[CoversClass(Password::class)]
final class PasswordTest extends FieldTestCase
{
	public function createField(): Password
	{
		return new Password(new Name('password'));
	}
	#[Test]
	#[DataProvider('validAnyOfGroups')]
	public function it_accepts_a_valid_anyof_group(string $value, ValidationStatus $expectedStatus, string $constraintName): void
	{
		$field = $this->createField()
			->minNumberOfDigits(1)
			->minNumberOfSymbols(1)
			->satisfyAnyOf('digits', 'symbols');

		$result = $field->validate($value);

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultHasStatusOf($expectedStatus, $constraintName, $result);
	}

	public static function validAnyOfGroups(): array
	{
		return [
			['hello$', ValidationStatus::Passed, 'symbols'],
			['hello$', ValidationStatus::Skipped, 'digits'],
			['hello$', ValidationStatus::Passed, 'anyOf'],

			['hello7', ValidationStatus::Skipped, 'symbols'],
			['hello7', ValidationStatus::Passed, 'digits'],
			['hello7', ValidationStatus::Passed, 'anyOf'],

			['hel1o$', ValidationStatus::Passed, 'symbols'],
			['hel1o$', ValidationStatus::Passed, 'digits'],
			['hel1o$', ValidationStatus::Passed, 'anyOf'],

			['hello', ValidationStatus::Skipped, 'symbols'],
			['hello', ValidationStatus::Skipped, 'digits'],
			['hello', ValidationStatus::Failed, 'anyOf'],
		];
	}

	#[Test]
	public function it_rejects_anyof_group_with_invalid_key(): void
	{
		$field = $this->createField()
			->minLengthOf(8)
			->minNumberOfSymbols(1);

		$this->expectException(InvalidArgumentException::class);

		$field->satisfyAnyOf('letters', 'symbols');
	}

	#[Test]
	public function it_rejects_anyof_group_with_only_one_item(): void
	{
		$field = $this->createField()
			->minLengthOf(8)
			->minNumberOfSymbols(1);

		$this->expectException(InvalidArgumentException::class);

		$field->satisfyAnyOf('digits');
	}

	#[Test]
	public function value_passes_against_strong_password_policy(): void
	{
		$policy = Password::strong(new Name('password'));

		$result = $policy->validate('Str0ng@Passw0rd!');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_fails_against_strong_password_policy(): void
	{
		$policy = Password::strong(new Name('password'));

		$result = $policy->validate('weakpass');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('length', $result);
		$this->assertConstraintValidationResultFailed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultFailed('digits', $result);
		$this->assertConstraintValidationResultFailed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_passes_against_moderate_password_policy(): void
	{
		$policy = Password::moderate(new Name('password'));

		$result = $policy->validate('Mod3rate!Pass');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_fails_against_moderate_password_policy(): void
	{
		$policy = Password::moderate(new Name('password'));

		$result = $policy->validate('noDigitsOrSymbols');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultFailed('digits', $result);
		$this->assertConstraintValidationResultFailed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_passes_against_weak_password_policy(): void
	{
		$policy = Password::weak(new Name('password'));

		$result = $policy->validate('anystring');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_fails_against_weak_password_policy(): void
	{
		$policy = Password::weak(new Name('password'));

		$result = $policy->validate('short');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}

	#[Test]
	public function value_passes_against_common_password_policy(): void
	{
		$policy = Password::common(new Name('password'));

		$result = $policy->validate('Hello World');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultSkipped('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultPassed('anyOf', $result);
	}

	#[Test]
	public function value_fails_against_common_password_policy(): void
	{
		$policy = Password::common(new Name('password'));

		$result = $policy->validate('He1lo');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultFailed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultSkipped('symbols', $result);
		$this->assertConstraintValidationResultPassed('anyOf', $result);
	}

	#[Test]
	public function value_passes_for_no_policy(): void
	{
		$policy = Password::none(new Name('password'));

		$result = $policy->validate('anystring');

		$this->assertConstraintValidationResultPassed('type', $result);
		$this->assertConstraintValidationResultPassed('length', $result);
		$this->assertConstraintValidationResultPassed('uppercase', $result);
		$this->assertConstraintValidationResultPassed('lowercase', $result);
		$this->assertConstraintValidationResultPassed('digits', $result);
		$this->assertConstraintValidationResultPassed('symbols', $result);
		$this->assertConstraintValidationResultSkipped('anyOf', $result);
	}


	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}
}
