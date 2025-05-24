<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Enum;
use Meraki\Schema\Field\Atomic;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('field')]
#[CoversClass(Enum::class)]
final class EnumTest extends FieldTestCase
{
	public function createField(): Enum
	{
		return new Enum(new Name('test'), ['AUD', 'USD', 'EUR']);
	}

	#[Test]
	public function it_has_the_correct_type(): void
	{
		$field = $this->createField();

		$this->assertSame('enum', $field->type->value);
	}

	#[Test]
	public function it_has_the_correct_name(): void
	{
		$field = $this->createField();

		$this->assertSame('test', $field->name->value);
	}

	#[Test]
	public function it_is_an_atomic_field(): void
	{
		$field = $this->createField();

		$this->assertInstanceOf(Atomic::class, $field);
	}

	#[Test]
	public function it_only_allows_values_in_the_set(): void
	{
		$field = $this->createField()->input('USD');

		$result = $field->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	public function it_allows_new_values_to_be_added(): void
	{
		$field = $this->createField()->allow('GBP');

		$result = $field->input('GBP')->validate();

		$this->assertConstraintValidationResultPassed('type', $result);
	}

	#[Test]
	public function it_does_not_allow_invalid_values(): void
	{
		$field = $this->createField()->input('GBP');

		$result = $field->validate();

		$this->assertConstraintValidationResultFailed('type', $result);
	}

	#[Test]
	public function it_has_no_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->value->unwrap());
	}

	#[Test]
	public function it_has_no_default_value_by_default(): void
	{
		$field = $this->createField();

		$this->assertNull($field->defaultValue->unwrap());
	}
}
