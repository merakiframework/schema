<?php
declare(strict_types=1);

namespace Meraki\Schema\Field;

use Meraki\Schema\Field\Text;
use Meraki\Schema\Property\Name;
use Meraki\Schema\FieldTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('field')]
#[CoversClass(Text::class)]
final class TextTest extends FieldTestCase
{
	public function createField(): Text
	{
		return new Text(new Name('text'));
	}

	#[Test]
	public function min_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->minLengthOf(4)
			->input('hello');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('min', $result);
	}

	#[Test]
	public function min_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->minLengthOf(4)
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('min', $result);
	}

	#[Test]
	public function max_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4)
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('max', $result);
	}

	#[Test]
	public function max_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->maxLengthOf(4)
			->input('hello');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('max', $result);
	}

	#[Test]
	public function pattern_constraint_passes_when_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i')
			->input('abc');

		$result = $type->validate();

		$this->assertConstraintValidationResultPassed('pattern', $result);
	}

	#[Test]
	public function pattern_constraint_fails_when_not_met(): void
	{
		$type = $this->createField()
			->matches('/^[a-z]+$/i')
			->input('abc123');

		$result = $type->validate();

		$this->assertConstraintValidationResultFailed('pattern', $result);
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
